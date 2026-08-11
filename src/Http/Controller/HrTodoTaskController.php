<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\HrTodoTaskService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class HrTodoTaskController extends BaseController
{
    private HrTodoTaskService $hrTodoTaskService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        HrTodoTaskService $hrTodoTaskService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->hrTodoTaskService = $hrTodoTaskService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('hr_todo_tasks', 'HR To-Do Tasks');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('hr_todo_tasks.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_hr_todo_tasks' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_hr_todo_tasks' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id, $request),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'task_type' => $request->getString('task_type'),
            'description' => $request->getString('description'),
            'due_date' => $request->getString('due_date') ?: null,
            'status' => $request->getString('status'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->hrTodoTaskService->update($id, $data, $this->userId, $this->orgId);
            flash_success('The HR To-Do Task has been updated successfully.');
            return Response::redirect('hr_todo_tasks.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("hr_todo_tasks.php?id=$id&action=edit_hr_todo_tasks");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("hr_todo_tasks.php?id=$id&action=edit_hr_todo_tasks");
        } catch (\Throwable $e) {
            $this->logError("HrTodoTaskController::handleUpdate error: " . $e->getMessage());
            flash_error('The HR To-Do Task could not be updated.');
            return Response::redirect("hr_todo_tasks.php?id=$id&action=edit_hr_todo_tasks");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'employee_id' => $request->getInt('employee_id'),
            'task_type' => $request->getString('task_type'),
            'description' => $request->getString('description'),
            'due_date' => $request->getString('due_date') ?: null,
            'status' => $request->getString('status', 'pending'),
            'notes' => $request->getString('notes'),
        ];

        try {
            $this->hrTodoTaskService->create($data, $this->userId, $this->orgId);
            flash_success('The HR To-Do Task has been saved successfully.');
            return Response::redirect('hr_todo_tasks.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect('hr_todo_tasks.php');
        } catch (\Throwable $e) {
            $this->logError("HrTodoTaskController::handleCreate error: " . $e->getMessage());
            flash_error('The HR To-Do Task could not be saved.');
            return Response::redirect('hr_todo_tasks.php');
        }
    }

    private function showForm(int $id, Request $request): Response
    {
        $employeeId = 0;
        $taskType = 'general';
        $description = '';
        $dueDate = '';
        $status = 'pending';
        $notes = '';
        $errorMessage = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'hr_todo_tasks';
        $moduleId = $this->moduleId;
        $sessionUserId = $this->userId;

        if ($id > 0) {
            try {
                $task = $this->hrTodoTaskService->getById($id, $this->orgId);
                $employeeId = $task->employeeId;
                $taskType = $task->taskType;
                $description = $task->description;
                $dueDate = $task->dueDate ?? '';
                $status = $task->status;
                $notes = $task->notes;
            } catch (NotFoundException $e) {
                $errorMessage = $e->getMessage();
            }
        }

        $employees = $this->db->fetchAll(
            "SELECT id, full_name FROM " . \App\Core\DB::USERS . "
             WHERE organization_id = :org_id AND is_active = 1
             ORDER BY full_name ASC",
            ['org_id' => $this->orgId]
        );

        return Response::html($this->view->render('hr_todo_tasks/form.php', [
            'id' => $id,
            'employeeId' => $employeeId,
            'taskType' => $taskType,
            'description' => $description,
            'dueDate' => $dueDate,
            'status' => $status,
            'notes' => $notes,
            'errorMessage' => $errorMessage,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'sessionUserId' => $sessionUserId,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'employees' => $employees,
        ]));
    }
}
