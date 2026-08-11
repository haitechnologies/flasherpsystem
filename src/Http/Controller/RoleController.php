<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\RoleService;
use App\Exception\ValidationException;

class RoleController extends BaseController
{
    private RoleService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        RoleService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('role_names', 'Role');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('role_names.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_role_names' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_role_names' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'role_name' => $request->post('role_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Role has been updated successfully.');
            return Response::redirect('listing_role_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("role_names.php?id=$id&action=edit_role_names");
        } catch (\Throwable $e) {
            $this->logError("RoleController::handleUpdate error: " . $e->getMessage());
            flash_error('The Role could not be updated.');
            return Response::redirect("role_names.php?id=$id&action=edit_role_names");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'role_name' => $request->post('role_name', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Role has been saved successfully.');
            return Response::redirect('listing_role_names.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("role_names.php");
        } catch (\Throwable $e) {
            $this->logError("RoleController::handleCreate error: " . $e->getMessage());
            flash_error('The Role could not be saved.');
            return Response::redirect("role_names.php");
        }
    }

    private function showForm(int $id): Response
    {
        $roleName = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_role_names.php');
            }
            $roleName = $item->roleName;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('role_names/form.php', [
            'id' => $id,
            'roleName' => $roleName,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'role_names',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
