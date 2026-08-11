<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\StorageSubtypeService;
use App\Service\StorageTypeService;
use App\Exception\ValidationException;

class StorageSubtypeController extends BaseController
{
    private StorageSubtypeService $subService;
    private StorageTypeService $typeService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        StorageSubtypeService $subService,
        StorageTypeService $typeService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->subService = $subService;
        $this->typeService = $typeService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('storage_subtypes', 'Storage Subtype');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('storage_subtypes.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_storage_subtypes' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_storage_subtypes' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->subService->update($id, [
                'storage_type_id' => (int)$request->post('storage_type_id', 0),
                'storage_subtype' => $request->post('storage_subtype', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('storage_subtypes.php?id=' . $id . '&action=edit_storage_subtypes&updated=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("storage_subtypes.php?id=$id&action=edit_storage_subtypes");
        } catch (\Throwable $e) {
            $this->logError("StorageSubtypeController::handleUpdate error: " . $e->getMessage());
            flash_error('Storage Subtype could not be updated.');
            return Response::redirect("storage_subtypes.php?id=$id&action=edit_storage_subtypes");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $newId = $this->subService->create([
                'storage_type_id' => (int)$request->post('storage_type_id', 0),
                'storage_subtype' => $request->post('storage_subtype', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            return Response::redirect('storage_subtypes.php?id=' . $newId . '&action=edit_storage_subtypes&created=1');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("storage_subtypes.php");
        } catch (\Throwable $e) {
            $this->logError("StorageSubtypeController::handleCreate error: " . $e->getMessage());
            flash_error('Storage Subtype could not be saved.');
            return Response::redirect("storage_subtypes.php");
        }
    }

    private function showForm(int $id): Response
    {
        $storageTypeId = 0;
        $storageSubtype = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->subService->getById($id);
            if ($item === null) {
                flash_error('Storage Subtype not found.');
                return Response::redirect('listing_storage_subtypes.php');
            }
            $storageTypeId = $item->storageTypeId;
            $storageSubtype = $item->storageSubtype;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('storage_subtypes/form.php', [
            'id' => $id,
            'storageTypeId' => $storageTypeId,
            'storageSubtype' => $storageSubtype,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'storage_subtypes',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'allTypes' => $this->typeService->list(),
        ]));
    }
}
