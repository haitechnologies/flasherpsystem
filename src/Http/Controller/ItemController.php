<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\ItemService;
use App\Exception\ValidationException;

class ItemController extends BaseController
{
    private ItemService $service;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        ItemService $service,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->service = $service;
    }

    private function getTaxTreatments(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, tax_treatment FROM `" . \App\Core\DB::TAX_TREATMENTS . "` WHERE is_active = 1 AND organization_id = " . (int)$this->orgId . " ORDER BY tax_treatment ASC"
            );
        } catch (\Throwable $e) {
            $this->logError("ItemController::getTaxTreatments error: " . $e->getMessage());
            return [];
        }
    }

    private function getVendors(): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT id, display_name FROM `" . \App\Core\DB::VENDORS . "` WHERE is_active = 1 AND organization_id = " . (int)$this->orgId . " ORDER BY display_name ASC"
            );
        } catch (\Throwable $e) {
            $this->logError("ItemController::getVendors error: " . $e->getMessage());
            return [];
        }
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('items', 'Item');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            flash_error('Invalid security token.');
            return Response::redirect('items.php');
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_items' && $id > 0 && $this->canEdit()
            => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_items' && $this->canCreate()
            => $this->handleCreate($request),
            default => $this->showForm($id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        try {
            $this->service->update($id, [
                'item_type' => $request->post('item_type', 'services'),
                'item_name' => $request->post('item_name', ''),
                'unit_price' => $request->post('unit_price', '0'),
                'selling_price' => $request->post('selling_price', ''),
                'tax_treatment_id' => (int)$request->post('tax_treatment_id', 0),
                'is_excise' => $request->has('is_excise') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'sale_account' => (int)$request->post('sale_account', 0),
                'sale_description' => $request->post('sale_description', ''),
                'purchase_account' => (int)$request->post('purchase_account', 0),
                'purchase_description' => $request->post('purchase_description', ''),
                'cost_price' => $request->post('cost_price', ''),
                'preferred_vendor_id' => (int)$request->post('preferred_vendor_id', 0),
            ], $this->userId);
            flash_success('Item updated successfully.');
            return Response::redirect('listing_items.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("items.php?id=$id&action=edit_items");
        } catch (\Throwable $e) {
            $this->logError("ItemController::handleUpdate error: " . $e->getMessage());
            flash_error('Item could not be updated.');
            return Response::redirect("items.php?id=$id&action=edit_items");
        }
    }

    private function handleCreate(Request $request): Response
    {
        try {
            $this->service->create([
                'item_type' => $request->post('item_type', 'services'),
                'item_name' => $request->post('item_name', ''),
                'unit_price' => $request->post('unit_price', '0'),
                'selling_price' => $request->post('selling_price', ''),
                'tax_treatment_id' => (int)$request->post('tax_treatment_id', 0),
                'is_excise' => $request->has('is_excise') ? 1 : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'sale_account' => (int)$request->post('sale_account', 0),
                'sale_description' => $request->post('sale_description', ''),
                'purchase_account' => (int)$request->post('purchase_account', 0),
                'purchase_description' => $request->post('purchase_description', ''),
                'cost_price' => $request->post('cost_price', ''),
                'preferred_vendor_id' => (int)$request->post('preferred_vendor_id', 0),
            ], $this->userId);
            flash_success('Item saved successfully.');
            return Response::redirect('listing_items.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("items.php");
        } catch (\Throwable $e) {
            $this->logError("ItemController::handleCreate error: " . $e->getMessage());
            flash_error('Item could not be saved.');
            return Response::redirect("items.php");
        }
    }

    private function showForm(int $id): Response
    {
        $itemType = 'services';
        $itemName = '';
        $unitPrice = '0';
        $sellingPrice = '';
        $taxTreatmentId = 0;
        $isExcise = false;
        $publish = 1;
        $saleAccount = 0;
        $saleDescription = '';
        $purchaseAccount = 0;
        $purchaseDescription = '';
        $costPrice = '';
        $preferredVendorId = 0;

        if ($id > 0) {
            $item = $this->service->getById($id);
            if ($item === null) {
                flash_error('Record not found.');
                return Response::redirect('listing_items.php');
            }
            $itemType = $item->itemType;
            $itemName = $item->itemName;
            $unitPrice = $item->unitPrice;
            $sellingPrice = $item->sellingPrice;
            $taxTreatmentId = $item->taxTreatmentId;
            $isExcise = $item->isExcise;
            $publish = $item->isActive ? 1 : 0;
            $saleAccount = $item->saleAccount;
            $saleDescription = $item->saleDescription;
            $purchaseAccount = $item->purchaseAccount;
            $purchaseDescription = $item->purchaseDescription;
            $costPrice = $item->costPrice;
            $preferredVendorId = $item->preferredVendorId;
        }

        $taxTreatments = $this->getTaxTreatments();
        $vendors = $this->getVendors();

        return Response::html($this->view->render('items/form.php', [
            'id' => $id,
            'itemType' => $itemType,
            'itemName' => $itemName,
            'unitPrice' => $unitPrice,
            'sellingPrice' => $sellingPrice,
            'taxTreatmentId' => $taxTreatmentId,
            'isExcise' => $isExcise,
            'publish' => $publish,
            'moduleCaption' => $this->moduleCaption,
            'module' => 'items',
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
            'saleAccount' => $saleAccount,
            'saleDescription' => $saleDescription,
            'purchaseAccount' => $purchaseAccount,
            'purchaseDescription' => $purchaseDescription,
            'costPrice' => $costPrice,
            'preferredVendorId' => $preferredVendorId,
            'taxTreatments' => $taxTreatments,
            'vendors' => $vendors,
        ]));
    }
}
