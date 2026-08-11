<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\SetupGroupService;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupGroupController extends BaseController
{
    private SetupGroupService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        SetupGroupService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('setup_groups', 'Group Name');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('listing_setup_groups.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_setup_groups' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_setup_groups' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $data = [
            'group_name' => $request->getString('group_name'),
            'description' => $request->getString('description'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->update($id, $data, $this->userId);
            flash_success('The Group Name has been updated successfully.');
            return Response::redirect('listing_setup_groups.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_groups.php?id=$id&action=edit_setup_groups");
        } catch (\Throwable $e) {
            $this->logError("SetupGroupController::handleUpdate error: " . $e->getMessage());
            flash_error('The Group Name could not be updated.');
            return Response::redirect("setup_groups.php?id=$id&action=edit_setup_groups");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $data = [
            'group_name' => $request->getString('group_name'),
            'description' => $request->getString('description'),
            'publish' => $request->get('publish') ? 1 : 0,
        ];

        try {
            $this->service->create($data, $this->userId);
            flash_success('The Group Name has been saved successfully.');
            return Response::redirect('listing_setup_groups.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("setup_groups.php");
        } catch (\Throwable $e) {
            $this->logError("SetupGroupController::handleCreate error: " . $e->getMessage());
            flash_error('The Group Name could not be saved.');
            return Response::redirect("setup_groups.php");
        }
    }

    private function showForm(int $id): Response
    {
        $groupName = '';
        $description = '';
        $publish = 1;
        $error_message = '';
        $moduleCaption = $this->moduleCaption;
        $module = 'setup_groups';
        $session_user_id = $this->userId;

        if ($id > 0) {
            $model = $this->service->getById($id);
            if ($model !== null) {
                $groupName = $model->groupName;
                $description = $model->description;
                $publish = $model->isActive ? 1 : 0;
            }
        }

        return Response::html($this->view->render('setup_groups/form.php', [
            'id' => $id,
            'groupName' => $groupName,
            'description' => $description,
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
