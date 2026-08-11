<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\StorageTypeService;
use App\Exception\ValidationException;

class StorageTypeController extends BaseController
{
    private StorageTypeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        StorageTypeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('storage_types', 'Storage Type');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('storage_types.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_storage_types' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_storage_types' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'storage_type' => $request->post('storage_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Storage Type has been updated successfully.');
            return Response::redirect('listing_storage_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("storage_types.php?id=$id&action=edit_storage_types");
        } catch (\Throwable $e) {
            $this->logError("StorageTypeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Storage Type could not be updated.');
            return Response::redirect("storage_types.php?id=$id&action=edit_storage_types");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'storage_type' => $request->post('storage_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Storage Type has been saved successfully.');
            return Response::redirect('listing_storage_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("storage_types.php");
        } catch (\Throwable $e) {
            $this->logError("StorageTypeController::handleCreate error: " . $e->getMessage());
            flash_error('The Storage Type could not be saved.');
            return Response::redirect("storage_types.php");
        }
    }

    private function showForm(int $id): Response
    {
        $storageType = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_storage_types.php');
            }
            $storageType = $item->storageType;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('storage_types/form.php', [
            'id' => $id,
            'storageType' => $storageType,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'storage_types',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
