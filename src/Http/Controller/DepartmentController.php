<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Container;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\DepartmentService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class DepartmentController extends BaseController
{
    private DepartmentService $deptService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        DepartmentService $deptService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->deptService = $deptService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('departments', 'Department');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('departments.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_departments' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_departments' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $department = $request->getString('department');
        $email = $request->getString('email');

        try {
            $this->deptService->update($id, $department, true, $email);
            flash_success('The Department has been updated successfully.');
            return Response::redirect('listing_departments.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("departments.php?id=$id&action=edit_departments");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("departments.php?id=$id&action=edit_departments");
        } catch (\Throwable $e) {
            $this->logError("DepartmentController update error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("departments.php?id=$id&action=edit_departments");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $department = $request->getString('department');
        $email = $request->getString('email');

        try {
            $newDept = $this->deptService->create($department, $this->orgId, $this->userId, $email);
            flash_success('The Department has been saved successfully.');
            return Response::redirect('listing_departments.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("departments.php");
        } catch (\Throwable $e) {
            $this->logError("DepartmentController create error: " . $e->getMessage());
            flash_error('The Department could not be saved.');
            return Response::redirect("departments.php");
        }
    }

    private function showForm(int $id): Response
    {
        $department = '';
        $email = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'departments';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        if ($id > 0) {
            try {
                $dept = $this->deptService->getById($id);
                $department = $dept->department;
                $email = $dept->email ?? '';
                $publish = $dept->publish ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('departments/form.php', [
            'id' => $id,
            'department' => $department,
            'email' => $email,
            'publish' => $publish,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
