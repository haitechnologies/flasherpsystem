<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\DesignationService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class DesignationController extends BaseController
{
    private DesignationService $designationService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        DesignationService $designationService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->designationService = $designationService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('designations', 'Designation');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_designations.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_designations' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_designations' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $designation = $request->getString('designation');

        try {
            $this->designationService->update($id, $designation, true);
            flash_success('The Designation has been updated successfully.');
            return Response::redirect('listing_designations.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("designations.php?id=$id&action=edit_designations");
        } catch (NotFoundException $e) {
            flash_error($e->getMessage());
            return Response::redirect("designations.php?id=$id&action=edit_designations");
        } catch (\Throwable $e) {
            $this->logError("DesignationController update error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("designations.php?id=$id&action=edit_designations");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $designation = $request->getString('designation');

        try {
            $newDesg = $this->designationService->create($designation, $this->orgId, $this->userId);
            flash_success('The Designation has been saved successfully.');
            return Response::redirect('listing_designations.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("designations.php");
        } catch (\Throwable $e) {
            $this->logError("DesignationController create error: " . $e->getMessage());
            flash_error('The Designation could not be saved.');
            return Response::redirect("designations.php");
        }
    }

    private function showForm(int $id): Response
    {
        $designation = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'designations';
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;

        if ($id > 0) {
            try {
                $desg = $this->designationService->getById($id);
                $designation = $desg->designation;
                $publish = $desg->publish ? 1 : 0;
            } catch (NotFoundException $e) {
                $error_message = $e->getMessage();
            }
        }

        return Response::html($this->view->render('designations/form.php', [
            'id' => $id,
            'designation' => $designation,
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
