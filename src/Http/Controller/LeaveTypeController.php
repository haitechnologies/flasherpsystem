<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\LeaveTypeService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class LeaveTypeController extends BaseController
{
    private LeaveTypeService $leaveTypeService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        LeaveTypeService $leaveTypeService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->leaveTypeService = $leaveTypeService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('leave_types', 'Leave Type');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_leave_types.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_leave_types' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_leave_types' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $leaveType = $request->getString('leave_type');
        $paid = $request->get('paid') ? true : false;

        try {
            $this->leaveTypeService->update($id, $leaveType, $paid, $this->orgId);
            flash_success('The Leave Type has been updated successfully.');
            return Response::redirect('listing_leave_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("leave_types.php?id=$id&action=edit_leave_types");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("leave_types.php?id=$id&action=edit_leave_types");
        } catch (\Throwable $e) {
            $this->logError("LeaveTypeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Leave Type could not be updated.');
            return Response::redirect("leave_types.php?id=$id&action=edit_leave_types");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $leaveType = $request->getString('leave_type');
        $allowed = ['Annual Leave', 'Sick Leave', 'Urgent Leave'];
        if (!in_array($leaveType, $allowed, true)) {
            flash_error('Invalid leave type selected.');
            return Response::redirect("leave_types.php");
        }
        $paid = (bool)$request->get('paid', '0');

        try {
            $this->leaveTypeService->create($leaveType, $paid, $this->orgId);
            flash_success('The Leave Type has been saved successfully.');
            return Response::redirect('listing_leave_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("leave_types.php");
        } catch (\Throwable $e) {
            $this->logError("LeaveTypeController::handleCreate error: " . $e->getMessage());
            flash_error('The Leave Type could not be saved.');
            return Response::redirect("leave_types.php");
        }
    }

    private function showForm(int $id): Response
    {
        $leaveType = '';
        $paid = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'leave_types';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        $publish = 1;

        if ($id > 0) {
            try {
                $type = $this->leaveTypeService->getById($id, $this->orgId);
                $leaveType = $type->leaveType;
                $paid = $type->paid ? 1 : 0;
                $publish = 1;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('leave_types/form.php', [
            'id' => $id,
            'leaveType' => $leaveType,
            'paid' => $paid,
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
