<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ContainerTypeService;
use App\Exception\ValidationException;

class ContainerTypeController extends BaseController
{
    private ContainerTypeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ContainerTypeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('container_types', 'Container Type');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('container_types.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_container_types' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_container_types' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'container_type' => $request->post('container_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Container Type has been updated successfully.');
            return Response::redirect('listing_container_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("container_types.php?id=$id&action=edit_container_types");
        } catch (\Throwable $e) {
            $this->logError("ContainerTypeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Container Type could not be updated.');
            return Response::redirect("container_types.php?id=$id&action=edit_container_types");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'container_type' => $request->post('container_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Container Type has been saved successfully.');
            return Response::redirect('listing_container_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("container_types.php");
        } catch (\Throwable $e) {
            $this->logError("ContainerTypeController::handleCreate error: " . $e->getMessage());
            flash_error('The Container Type could not be saved.');
            return Response::redirect("container_types.php");
        }
    }

    private function showForm(int $id): Response
    {
        $containerType = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_container_types.php');
            }
            $containerType = $item->containerType;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('container_types/form.php', [
            'id' => $id,
            'containerType' => $containerType,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'container_types',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
