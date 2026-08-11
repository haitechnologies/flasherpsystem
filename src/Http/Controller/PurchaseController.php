<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PurchaseService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class PurchaseController extends BaseController
{
    private PurchaseService $purchaseService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PurchaseService $purchaseService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->purchaseService = $purchaseService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('purchases', 'Purchase');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_purchases' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_purchases' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $purchaseData = $this->buildPurchaseData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->purchaseService->updatePurchase($id, $purchaseData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Purchase has been updated successfully.');
            return Response::redirect('listing_purchases.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("purchases.php?id=$id&action=edit_purchases");
        } catch (\Throwable $e) {
            $this->logError("PurchaseController::handleUpdate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("purchases.php?id=$id&action=edit_purchases");
        }
    }

    private function handleCreate(Request $request): Response
    {
        $purchaseData = $this->buildPurchaseData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->purchaseService->createPurchase($purchaseData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Purchase has been saved successfully.');
            return Response::redirect('listing_purchases.php');
        } catch (ValidationException $e) {
            $error = current($e->getErrors());
            flash_error($error);
            return Response::redirect("purchases.php");
        } catch (\Throwable $e) {
            $this->logError("PurchaseController::handleCreate error: " . $e->getMessage());
            flash_error($e->getMessage());
            return Response::redirect("purchases.php");
        }
    }

    private function buildPurchaseData(Request $request): array
    {
        return [
            'vendor_id' => $request->getString('vendor_id'),
            'purchase_status' => $request->getString('purchase_status', 'draft'),
            'purchase_date' => $request->getString('purchase_date'),
            'reference_no' => $request->getString('reference_no'),
            'subject' => $request->getString('subject'),
            'warehouse_id' => $request->getString('warehouse_id'),
            'vendor_notes' => $request->getString('vendor_notes'),
            'terms_and_conditions' => $request->getString('terms_and_conditions'),
            'grand_subtotal' => $request->getString('grand_subtotal', '0.00'),
            'grand_discount_type' => $request->getString('grand_discount_type'),
            'grand_discount_type_value' => $request->getString('grand_discount_type_value', '0.00'),
            'grand_discount_amount' => $request->getString('grand_discount_amount', '0.00'),
            'grand_after_discount' => $request->getString('grand_after_discount', '0.00'),
            'grand_tax' => $request->getString('grand_tax', '0.00'),
            'grand_total' => $request->getString('grand_total', '0.00'),
        ];
    }

    private function buildItemsData(Request $request): array
    {
        $totalRows = (int)$request->getString('total_rows', '1');
        $items = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $service = $request->getArrayItem('service', $i);
            $itemId = $request->getArrayItem('item_id', $i);

            if (empty($service) || (int)$service <= 0) {
                continue;
            }

            $items[] = [
                'id' => !empty($itemId) ? (int)$itemId : null,
                'service' => (int)$service,
                'description' => $request->getArrayItem('description', $i),
                'qty' => $request->getArrayItem('qty', $i, '1'),
                'rate' => $request->getArrayItem('rate', $i, '0'),
                'discount_type' => $request->getArrayItem('discount_type', $i),
                'discount_type_value' => $request->getArrayItem('discount_type_value', $i, '0'),
                'discount_amount' => $request->getArrayItem('discount_amount', $i, '0'),
                'sub_total' => $request->getArrayItem('sub_total', $i, '0'),
                'tax' => $request->getArrayItem('tax', $i, '0'),
                'tax_amount' => $request->getArrayItem('tax_amount', $i, '0'),
                'total' => $request->getArrayItem('total', $i, '0'),
            ];
        }

        return $items;
    }

    private function showForm(Request $request, int $id): Response
    {
        $module = 'purchases';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        $action = $request->getString('action');

        // Default values
        $vendor_id = '0';
        $purchase_status = 'draft';
        $purchase_date = date('d-m-Y');
        $reference_no = '';
        $subject = '';
        $warehouse_id = '0';
        $vendor_notes = '';
        $terms_and_conditions = '';
        $grand_subtotal = '0.00';
        $grand_discount_type = '';
        $grand_discount_type_value = '';
        $grand_discount_amount = '';
        $grand_after_discount = '';
        $grand_tax = '0.00';
        $grand_total = '0.00';

        $item_id_arr = [];
        $service_arr = [];
        $description_arr = [];
        $qty_arr = [];
        $rate_arr = [];
        $discount_type_arr = [];
        $discount_type_value_arr = [];
        $discount_amount_arr = [];
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::PURCHASES . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $this->logError("PurchaseController::showForm error: " . $e->getMessage());
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $purchase = $this->purchaseService->getPurchase($id, $this->orgId);
                    $vendor_id = (string)$purchase->vendorId;
                    $purchase_no = (string)$purchase->purchaseNo;
                    $purchase_status = $purchase->purchaseStatus;
                    $purchase_date = DateHelper::toDisplayDate($purchase->purchaseDate) ?: $purchase->purchaseDate;
                    $reference_no = (string)$purchase->referenceNo;
                    $subject = (string)$purchase->subject;
                    $warehouse_id = (string)$purchase->warehouseId;
                    $vendor_notes = (string)$purchase->vendorNotes;
                    $terms_and_conditions = (string)$purchase->termsAndConditions;
                    $grand_subtotal = (string)$purchase->grandSubtotal;
                    $grand_discount_type = (string)$purchase->grandDiscountType;
                    $grand_discount_type_value = (string)$purchase->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$purchase->grandDiscountAmount;
                    $grand_after_discount = (string)$purchase->grandAfterDiscount;
                    $grand_tax = (string)$purchase->grandTax;
                    $grand_total = (string)$purchase->grandTotal;

                    $items = $this->purchaseService->getPurchaseItems($id);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $service_arr[] = $item->service;
                        $description_arr[] = $item->description;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $discount_type_arr[] = $item->discountType;
                        $discount_type_value_arr[] = $item->discountTypeValue;
                        $discount_amount_arr[] = $item->discountAmount;
                        $sub_total_arr[] = $item->subTotal;
                        $tax_arr[] = $item->tax;
                        $tax_amount_arr[] = $item->taxAmount;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    $this->logError("PurchaseController::showForm error: " . $e->getMessage());
                    $error_message = $e->getMessage();
                }
            }
        }

        if ($total_rows == 0) {
            $total_rows = 1;
        }

        // Fetch dropdown data
        $vendorOptions = [];
        try {
            $vendorOptions = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` WHERE is_active=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
            $this->logError("PurchaseController::showForm error: " . $e->getMessage());
            $vendorOptions = [];
        }

        $warehouseOptions = [];
        try {
            $warehouseOptions = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $this->logError("PurchaseController::showForm error: " . $e->getMessage());
            $warehouseOptions = [];
        }

        $itemsList = [];
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $this->logError("PurchaseController::showForm error: " . $e->getMessage());
            $itemsList = [];
        }

        return Response::html($this->view->render('purchases/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'vendor_id' => $vendor_id,
            'purchase_no' => $purchase_no ?? '',
            'purchase_status' => $purchase_status,
            'purchase_date' => $purchase_date,
            'reference_no' => $reference_no,
            'subject' => $subject,
            'warehouse_id' => $warehouse_id,
            'vendor_notes' => $vendor_notes,
            'terms_and_conditions' => $terms_and_conditions,
            'grand_subtotal' => $grand_subtotal,
            'grand_discount_type' => $grand_discount_type,
            'grand_discount_type_value' => $grand_discount_type_value,
            'grand_discount_amount' => $grand_discount_amount,
            'grand_after_discount' => $grand_after_discount,
            'grand_tax' => $grand_tax,
            'grand_total' => $grand_total,
            'total_rows' => $total_rows,
            'item_id_arr' => $item_id_arr,
            'service_arr' => $service_arr,
            'description_arr' => $description_arr,
            'qty_arr' => $qty_arr,
            'rate_arr' => $rate_arr,
            'discount_type_arr' => $discount_type_arr,
            'discount_type_value_arr' => $discount_type_value_arr,
            'discount_amount_arr' => $discount_amount_arr,
            'sub_total_arr' => $sub_total_arr,
            'tax_arr' => $tax_arr,
            'tax_amount_arr' => $tax_amount_arr,
            'total_arr' => $total_arr,
            'vendorOptions' => $vendorOptions,
            'warehouseOptions' => $warehouseOptions,
            'itemsList' => $itemsList,
            'canCreate' => $this->canCreate(),
            'canEdit' => $this->canEdit(),
        ]));
    }
}
