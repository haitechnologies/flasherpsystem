<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PurchaseTypeService;
use App\Exception\ValidationException;

class PurchaseTypeController extends BaseController
{
    private PurchaseTypeService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PurchaseTypeService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('purchase_types', 'Purchase Type');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('purchase_types.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_purchase_types' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_purchase_types' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'purchase_type' => $request->post('purchase_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Purchase Type has been updated successfully.');
            return Response::redirect('listing_purchase_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("purchase_types.php?id=$id&action=edit_purchase_types");
        } catch (\Throwable $e) {
            $this->logError("PurchaseTypeController::handleUpdate error: " . $e->getMessage());
            flash_error('The Purchase Type could not be updated.');
            return Response::redirect("purchase_types.php?id=$id&action=edit_purchase_types");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'purchase_type' => $request->post('purchase_type', ''),
                'description' => $request->post('description', ''),
                'is_active' => $request->has('is_active') ? 1 : 0,
            ], $this->userId);
            flash_success('The Purchase Type has been saved successfully.');
            return Response::redirect('listing_purchase_types.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("purchase_types.php");
        } catch (\Throwable $e) {
            $this->logError("PurchaseTypeController::handleCreate error: " . $e->getMessage());
            flash_error('The Purchase Type could not be saved.');
            return Response::redirect("purchase_types.php");
        }
    }

    private function showForm(int $id): Response
    {
        $purchaseType = '';
        $description = '';
        $publish = 1;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_purchase_types.php');
            }
            $purchaseType = $item->purchaseType;
            $description = $item->description;
            $publish = $item->isActive ? 1 : 0;
        }

        return Response::html($this->view->render('purchase_types/form.php', [
            'id' => $id,
            'purchaseType' => $purchaseType,
            'description' => $description,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'purchase_types',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
