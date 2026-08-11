<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\DB;
use App\Core\Database;
use App\Http\Request;
use App\Http\Response;
use App\Service\PurchaseOrderService;
use App\Security\Roles;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class PurchaseOrderController extends BaseController
{
    private PurchaseOrderService $purchaseOrderService;

    public function __construct(
        Database $db,
        int $userId,
        int $roleId,
        int $orgId,
        PurchaseOrderService $purchaseOrderService,
    ) {
        parent::__construct($db, $userId, $roleId, $orgId);
        $this->purchaseOrderService = $purchaseOrderService;
    }

    public function __invoke(Request $request): Response
    {
        $this->requiresModule('purchase_orders', 'Purchase Order');

        if (!$this->canView()) {
            return new Response('Forbidden', 403);
        }

        if ($request->isPost() && !$this->validateCsrf($request)) {
            return new Response('Invalid security token.', 403);
        }

        $id = $request->getInt('id');
        $action = $request->getString('action');

        return match (true) {
            $request->isPost() && $action === 'update_purchase_orders' && $id > 0 && $this->canEdit()
                => $this->handleUpdate($request, $id),
            $request->isPost() && $action === 'add_purchase_orders' && $this->canCreate()
                => $this->handleCreate($request),
            default => $this->showForm($request, $id),
        };
    }

    private function handleUpdate(Request $request, int $id): Response
    {
        $orderData = $this->buildOrderData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->purchaseOrderService->updatePurchaseOrder($id, $orderData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Purchase Order has been updated successfully.');
            return Response::redirect('listing_purchase_orders.php');
        } catch (ValidationException $e) {
            return $this->renderFormWithData($orderData, $itemsData, current($e->getErrors()), $id);
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::handleUpdate error: " . $e->getMessage());
            return $this->renderFormWithData($orderData, $itemsData, $e->getMessage(), $id);
        }
    }

    private function handleCreate(Request $request): Response
    {
        $orderData = $this->buildOrderData($request);
        $itemsData = $this->buildItemsData($request);

        try {
            $this->purchaseOrderService->createPurchaseOrder($orderData, $itemsData, $this->orgId, $this->userId);
            flash_success('The Purchase Order has been saved successfully.');
            return Response::redirect('listing_purchase_orders.php');
        } catch (ValidationException $e) {
            return $this->renderFormWithData($orderData, $itemsData, current($e->getErrors()), 0);
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::handleCreate error: " . $e->getMessage());
            return $this->renderFormWithData($orderData, $itemsData, $e->getMessage(), 0);
        }
    }

    private function buildOrderData(Request $request): array
    {
        return [
            'vendor_id' => $request->getString('vendor_id'),
            'purchase_order_status' => $request->getString('purchase_order_status', 'draft'),
            'purchase_order_date' => $request->getString('purchase_order_date'),
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
        $module = 'purchase_orders';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;
        $error_message = $request->getString('error_message');
        $action = $request->getString('action');

        // Default values
        $vendor_id = '0';
        $purchase_order_status = 'draft';
        $purchase_order_date = date('d-m-Y');
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
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];
        $total_rows = 1;

        if ($id > 0) {
            $created_by = 0;
            try {
                $sql = "SELECT created_by FROM `" . DB::PURCHASE_ORDERS . "` WHERE id = :id";
                $row = $this->db->fetchOne($sql, ['id' => $id]);
                $created_by = $row ? (int)$row['created_by'] : 0;
            } catch (\Throwable $e) {
                $this->logError("PurchaseOrderController::showForm error: " . $e->getMessage());
                $created_by = 0;
            }

            $canEdit = Roles::hasFullAccess($session_role_id) || $session_user_id === $created_by;

            if ($canEdit) {
                try {
                    $order = $this->purchaseOrderService->getPurchaseOrder($id, $this->orgId);
                    $vendor_id = (string)$order->vendorId;
                    $purchase_order_no = (string)($order->purchaseOrderNo ?? '');
                    $purchase_order_status = $order->purchaseOrderStatus;
                    $purchase_order_date = DateHelper::toDisplayDate($order->purchaseOrderDate) ?: $order->purchaseOrderDate;
                    $reference_no = (string)$order->referenceNo;
                    $subject = (string)$order->subject;
                    $warehouse_id = (string)$order->warehouseId;
                    $vendor_notes = (string)$order->vendorNotes;
                    $terms_and_conditions = (string)$order->termsAndConditions;
                    $grand_subtotal = (string)$order->grandSubtotal;
                    $grand_discount_type = (string)$order->grandDiscountType;
                    $grand_discount_type_value = (string)$order->grandDiscountTypeValue;
                    $grand_discount_amount = (string)$order->grandDiscountAmount;
                    $grand_after_discount = (string)$order->grandAfterDiscount;
                    $grand_tax = (string)$order->grandTax;
                    $grand_total = (string)$order->grandTotal;

                    $items = $this->purchaseOrderService->getPurchaseOrderItems($id);
                    $total_rows = count($items);

                    foreach ($items as $item) {
                        $item_id_arr[] = $item->id;
                        $service_arr[] = $item->service;
                        $description_arr[] = $item->description;
                        $qty_arr[] = $item->qty;
                        $rate_arr[] = $item->rate;
                        $sub_total_arr[] = $item->subTotal;
                        $tax_arr[] = $item->tax;
                        $tax_amount_arr[] = $item->taxAmount;
                        $total_arr[] = $item->total;
                    }
                } catch (\Throwable $e) {
                    $this->logError("PurchaseOrderController::showForm error: " . $e->getMessage());
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
            $this->logError("PurchaseOrderController::showForm error: " . $e->getMessage());
            $vendorOptions = [];
        }

        $warehouseOptions = [];
        try {
            $warehouseOptions = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::showForm error: " . $e->getMessage());
            $warehouseOptions = [];
        }

        $itemsList = [];
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name, cost_price FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::showForm error: " . $e->getMessage());
            $itemsList = [];
        }

        return Response::html($this->view->render('purchase_orders/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'vendor_id' => $vendor_id,
            'purchase_order_no' => $purchase_order_no ?? '',
            'purchase_order_status' => $purchase_order_status,
            'purchase_order_date' => $purchase_order_date,
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

    private function renderFormWithData(array $orderData, array $itemsData, string $errorMessage, int $id): Response
    {
        $error_message = $errorMessage;
        $module = 'purchase_orders';
        $moduleCaption = $this->moduleCaption;
        $moduleId = $this->moduleId;
        $session_user_id = $this->userId;
        $session_role_id = $this->roleId;

        $vendor_id = $orderData['vendor_id'] ?? '0';
        $purchase_order_no = $orderData['purchase_order_no'] ?? '';
        $purchase_order_status = $orderData['purchase_order_status'] ?? 'draft';
        $purchase_order_date = DateHelper::toDisplayDate($orderData['purchase_order_date'] ?? '') ?: ($orderData['purchase_order_date'] ?? '');
        $reference_no = $orderData['reference_no'] ?? '';
        $subject = $orderData['subject'] ?? '';
        $warehouse_id = $orderData['warehouse_id'] ?? '0';
        $vendor_notes = $orderData['vendor_notes'] ?? '';
        $terms_and_conditions = $orderData['terms_and_conditions'] ?? '';
        $grand_subtotal = $orderData['grand_subtotal'] ?? '0.00';
        $grand_discount_type = $orderData['grand_discount_type'] ?? '';
        $grand_discount_type_value = $orderData['grand_discount_type_value'] ?? '';
        $grand_discount_amount = $orderData['grand_discount_amount'] ?? '';
        $grand_after_discount = $orderData['grand_after_discount'] ?? '';
        $grand_tax = $orderData['grand_tax'] ?? '0.00';
        $grand_total = $orderData['grand_total'] ?? '0.00';

        $item_id_arr = [];
        $service_arr = [];
        $description_arr = [];
        $qty_arr = [];
        $rate_arr = [];
        $sub_total_arr = [];
        $tax_arr = [];
        $tax_amount_arr = [];
        $total_arr = [];

        foreach ($itemsData as $item) {
            $item_id_arr[] = $item['id'] ?? null;
            $service_arr[] = $item['service'] ?? '';
            $description_arr[] = $item['description'] ?? '';
            $qty_arr[] = $item['qty'] ?? '1';
            $rate_arr[] = $item['rate'] ?? '0';
            $sub_total_arr[] = $item['sub_total'] ?? '0';
            $tax_arr[] = $item['tax'] ?? '0';
            $tax_amount_arr[] = $item['tax_amount'] ?? '0';
            $total_arr[] = $item['total'] ?? '0';
        }

        $total_rows = count($itemsData) > 0 ? count($itemsData) : 1;

        $vendorOptions = [];
        try {
            $vendorOptions = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` WHERE is_active=1 ORDER BY id DESC");
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::renderFormWithData error: " . $e->getMessage());
        }

        $warehouseOptions = [];
        try {
            $warehouseOptions = $this->db->fetchAll("SELECT id, warehouse_name FROM `" . DB::WAREHOUSES . "` WHERE is_active=1");
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::renderFormWithData error: " . $e->getMessage());
        }

        $itemsList = [];
        try {
            $itemsList = $this->db->fetchAll("SELECT id, item_name, cost_price FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
        } catch (\Throwable $e) {
            $this->logError("PurchaseOrderController::renderFormWithData error: " . $e->getMessage());
        }

        return Response::html($this->view->render('purchase_orders/form.php', [
            'id' => $id,
            'module' => $module,
            'moduleCaption' => $moduleCaption,
            'moduleId' => $moduleId,
            'session_user_id' => $session_user_id,
            'session_role_id' => $session_role_id,
            'error_message' => $error_message,
            'vendor_id' => $vendor_id,
            'purchase_order_no' => $purchase_order_no,
            'purchase_order_status' => $purchase_order_status,
            'purchase_order_date' => $purchase_order_date,
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
