<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SetupStatusService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupStatusController extends BaseController
{
    private SetupStatusService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SetupStatusService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('setup_statuses', 'Status');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_setup_statuses.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_setup_statuses' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_setup_statuses' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'status_name' => $request->getString('status_name'),
            'status_type' => $request->getString('status_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('The Status has been updated successfully.');
            return Response::redirect('listing_setup_statuses.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_statuses.php?id=$id&action=edit_setup_statuses");
        } catch (\Throwable $e) {
            $this->logError("SetupStatusController::handleUpdate error: " . $e->getMessage());
            flash_error('The Status could not be updated.');
            return Response::redirect("setup_statuses.php?id=$id&action=edit_setup_statuses");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'status_name' => $request->getString('status_name'),
            'status_type' => $request->getString('status_type'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('The Status has been saved successfully.');
            return Response::redirect('listing_setup_statuses.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_statuses.php");
        } catch (\Throwable $e) {
            $this->logError("SetupStatusController::handleCreate error: " . $e->getMessage());
            flash_error('The Status could not be saved.');
            return Response::redirect("setup_statuses.php");
        }
    }

    private function showForm(int $id): Response
    {
        $statusName = '';
        $statusType = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'setup_statuses';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id);
            if ($model !== null) {
                $statusName = $model->statusName;
                $statusType = $model->statusType;
                $publish = $model->isActive ? 1 : 0;
            }
        }

        return Response::html($this->view->render('setup_statuses/form.php', [
            'id' => $id,
            'statusName' => $statusName,
            'statusType' => $statusType,
            'publish' => $publish,
            'error_message' => $error_message,
            'moduleCaption' => $moduleCaption,
            'module' => $module,
            'session_user_id' => $session_user_id,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
