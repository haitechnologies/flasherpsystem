<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Model\Purchase;
use App\Model\PurchaseOrder;
use App\Model\PurchaseOrderItem;
use App\Repository\PurchaseOrderRepository;
use App\Repository\VendorRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;
use App\Helper\PdfHelper;

class PurchaseOrderService
{
    private PurchaseOrderRepository $purchaseOrderRepo;
    private VendorRepository $vendorRepo;
    private PurchaseService $purchaseService;
    private Database $db;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepo, VendorRepository $vendorRepo, PurchaseService $purchaseService, Database $db)
    {
        $this->purchaseOrderRepo = $purchaseOrderRepo;
        $this->vendorRepo = $vendorRepo;
        $this->purchaseService = $purchaseService;
        $this->db = $db;
    }

    public function getPurchaseOrder(int $id, int $orgId): PurchaseOrder
    {
        $order = $this->purchaseOrderRepo->find($id, $orgId);
        if ($order === null) {
            throw new NotFoundException("Purchase Order with ID {$id} not found.");
        }
        return $order;
    }

    public function getPurchaseOrderItems(int $purchaseOrderId): array
    {
        return $this->purchaseOrderRepo->findItemsByPurchaseOrder($purchaseOrderId);
    }

    public function createPurchaseOrder(array $data, array $itemsData, int $orgId, int $userId): PurchaseOrder
    {
        $this->validatePurchaseOrderData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $purchaseOrderDate = $this->parseDate((string)($data['purchase_order_date'] ?? ''));

            $order = new PurchaseOrder(
                id: null,
                organizationId: $orgId,
                purchaseOrderDate: $purchaseOrderDate,
                vendorId: (int)($data['vendor_id'] ?? 0),
                purchaseOrderNo: $this->purchaseOrderRepo->generatePurchaseOrderNo($orgId),
                purchaseOrderStatus: !empty($data['purchase_order_status']) ? trim((string)$data['purchase_order_status']) : 'draft',
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                subject: !empty($data['subject']) ? trim((string)$data['subject']) : null,
                warehouseId: (int)($data['warehouse_id'] ?? 0),
                vendorNotes: !empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: (float)($data['grand_subtotal'] ?? 0.0),
                grandDiscountType: !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00',
                grandDiscountTypeValue: (float)($data['grand_discount_type_value'] ?? 0.0),
                grandDiscountAmount: (float)($data['grand_discount_amount'] ?? 0.0),
                grandAfterDiscount: (float)($data['grand_after_discount'] ?? 0.0),
                grandTax: (float)($data['grand_tax'] ?? 0.0),
                grandTotal: (float)($data['grand_total'] ?? 0.0),
                createdBy: $userId,
            );

            $savedOrder = $this->purchaseOrderRepo->save($order);
            $orderId = $savedOrder->id;

            if ($orderId === null) {
                throw new \RuntimeException("Failed to insert purchase order header.");
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service']) || (int)$itemData['service'] <= 0) {
                    continue;
                }
                $item = new PurchaseOrderItem(
                    id: null,
                    organizationId: $orgId,
                    purchaseOrderId: $orderId,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->purchaseOrderRepo->saveItem($item);
            }

            $this->db->commit();

            PdfGeneratorHelper::ensure('purchase_orders', (int)$orderId);

            return $savedOrder;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePurchaseOrder(int $id, array $data, array $itemsData, int $orgId, int $userId): PurchaseOrder
    {
        $order = $this->getPurchaseOrder($id, $orgId);
        $this->validatePurchaseOrderData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $purchaseOrderDate = isset($data['purchase_order_date']) ? $this->parseDate((string)$data['purchase_order_date']) : $order->purchaseOrderDate;

            $updatedOrder = new PurchaseOrder(
                id: $order->id,
                organizationId: $order->organizationId,
                purchaseOrderDate: $purchaseOrderDate,
                vendorId: isset($data['vendor_id']) ? (int)$data['vendor_id'] : $order->vendorId,
                purchaseOrderNo: $order->purchaseOrderNo,
                purchaseOrderStatus: isset($data['purchase_order_status']) ? (!empty($data['purchase_order_status']) ? trim((string)$data['purchase_order_status']) : 'draft') : $order->purchaseOrderStatus,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $order->referenceNo,
                subject: isset($data['subject']) ? (!empty($data['subject']) ? trim((string)$data['subject']) : null) : $order->subject,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $order->warehouseId,
                vendorNotes: isset($data['vendor_notes']) ? (!empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null) : $order->vendorNotes,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $order->termsAndConditions,
                grandSubtotal: isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $order->grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? (!empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00') : $order->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $order->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $order->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $order->grandAfterDiscount,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $order->grandTax,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $order->grandTotal,
                createdAt: $order->createdAt,
                createdBy: $order->createdBy,
                updatedBy: $userId,
            );

            $savedOrder = $this->purchaseOrderRepo->save($updatedOrder);

            $existingItems = $this->purchaseOrderRepo->findItemsByPurchaseOrder($id);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                $itemService = isset($itemData['service']) ? (int)$itemData['service'] : 0;

                $itemId = !empty($itemData['id']) ? (int)$itemData['id'] : null;

                if ($itemId === null && $itemService <= 0) {
                    continue;
                }

                if ($itemId !== null) {
                    $incomingIds[] = $itemId;
                }

                $item = new PurchaseOrderItem(
                    id: $itemId,
                    organizationId: $orgId,
                    purchaseOrderId: $id,
                    service: $itemService,
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->purchaseOrderRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->purchaseOrderRepo->deleteItemsByIds($deletedIds, $id);
            }

            $this->db->commit();

            PdfGeneratorHelper::ensure('purchase_orders', (int)$id);

            return $savedOrder;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deletePurchaseOrder(int $id, int $orgId): bool
    {
        $this->getPurchaseOrder($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->purchaseOrderRepo->delete($id, $orgId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($result) {
            $this->removePurchaseOrderPdf($id);
        }

        return $result;
    }

    public function convertToPurchase(int $poId, int $orgId, int $userId): Purchase
    {
        $order = $this->getPurchaseOrder($poId, $orgId);

        if ($order->purchaseOrderStatus === 'purchased' || $this->hasPurchaseId($poId)) {
            throw new \RuntimeException('This Purchase Order has already been converted to a Purchase.');
        }

        $itemsData = $this->fetchItemsWithDiscounts($poId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $data = [
            'vendor_id' => (string)$order->vendorId,
            'purchase_date' => date('Y-m-d'),
            'reference_no' => $order->referenceNo,
            'subject' => $order->subject,
            'warehouse_id' => (string)$order->warehouseId,
            'vendor_notes' => $order->vendorNotes,
            'terms_and_conditions' => $order->termsAndConditions,
            'grand_subtotal' => (string)$order->grandSubtotal,
            'grand_discount_type' => $order->grandDiscountType,
            'grand_discount_type_value' => (string)$order->grandDiscountTypeValue,
            'grand_discount_amount' => (string)$order->grandDiscountAmount,
            'grand_after_discount' => (string)$order->grandAfterDiscount,
            'grand_tax' => (string)$order->grandTax,
            'grand_total' => (string)$order->grandTotal,
        ];

        $purchase = $this->purchaseService->createPurchase($data, $itemsData, $orgId, $userId);

        $this->db->execute(
            "UPDATE `" . DB::PURCHASE_ORDERS . "` SET purchase_id = :purchase_id, purchase_order_status = 'purchased' WHERE id = :po_id AND organization_id = :org_id",
            ['purchase_id' => (int)$purchase->id, 'po_id' => $poId, 'org_id' => $orgId]
        );

        return $purchase;
    }

    public function clonePurchaseOrder(int $poId, int $orgId, int $userId): PurchaseOrder
    {
        $order = $this->getPurchaseOrder($poId, $orgId);

        $this->db->beginTransaction();
        try {
            $clone = new PurchaseOrder(
                id: null,
                organizationId: $orgId,
                purchaseOrderDate: date('Y-m-d'),
                vendorId: $order->vendorId,
                purchaseOrderNo: $this->purchaseOrderRepo->generatePurchaseOrderNo($orgId),
                purchaseOrderStatus: 'draft',
                referenceNo: $order->referenceNo,
                subject: $order->subject,
                warehouseId: $order->warehouseId,
                vendorNotes: $order->vendorNotes,
                termsAndConditions: $order->termsAndConditions,
                grandSubtotal: $order->grandSubtotal,
                grandDiscountType: $order->grandDiscountType,
                grandDiscountTypeValue: $order->grandDiscountTypeValue,
                grandDiscountAmount: $order->grandDiscountAmount,
                grandAfterDiscount: $order->grandAfterDiscount,
                grandTax: $order->grandTax,
                grandTotal: $order->grandTotal,
                createdBy: $userId,
            );

            $savedClone = $this->purchaseOrderRepo->save($clone);
            $cloneId = $savedClone->id;

            if ($cloneId === null) {
                throw new \RuntimeException("Failed to insert cloned purchase order header.");
            }

            $rows = $this->db->fetchAll(
                "SELECT * FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id = :po_id ORDER BY id ASC",
                ['po_id' => $poId]
            );

            foreach ($rows as $row) {
                $item = new PurchaseOrderItem(
                    id: null,
                    organizationId: $orgId,
                    purchaseOrderId: $cloneId,
                    service: (int)$row['service'],
                    description: $row['description'] !== null ? (string)$row['description'] : null,
                    qty: (float)($row['qty'] ?? 1.0),
                    rate: (float)($row['rate'] ?? 0.0),
                    subTotal: (float)($row['sub_total'] ?? 0.0),
                    tax: (float)($row['tax'] ?? 0.0),
                    taxAmount: (float)($row['tax_amount'] ?? 0.0),
                    total: (float)($row['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->purchaseOrderRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedClone;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $allowed = ['draft', 'sent', 'accepted', 'declined', 'expired', 'invoiced', 'purchased'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['purchase_order_status' => "Invalid purchase order status."]);
        }

        $this->getPurchaseOrder($id, $orgId);

        $stmt = $this->db->execute(
            "UPDATE `" . DB::PURCHASE_ORDERS . "` SET purchase_order_status = :status WHERE id = :id AND organization_id = :org_id",
            ['status' => $status, 'id' => $id, 'org_id' => $orgId]
        );

        return $stmt->rowCount() > 0;
    }

    private function fetchItemsWithDiscounts(int $purchaseOrderId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT * FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id = :po_id ORDER BY id ASC",
            ['po_id' => $purchaseOrderId]
        );

        $items = [];
        foreach ($rows as $row) {
            if (empty($row['service']) || (int)$row['service'] <= 0) {
                continue;
            }
            $items[] = [
                'service' => (int)$row['service'],
                'description' => $row['description'],
                'qty' => (float)($row['qty'] ?? 1.0),
                'rate' => (float)($row['rate'] ?? 0.0),
                'discount_type' => $row['discount_type'] ?? null,
                'discount_type_value' => (float)($row['discount_type_value'] ?? 0.0),
                'discount_amount' => (float)($row['discount_amount'] ?? 0.0),
                'sub_total' => (float)($row['sub_total'] ?? 0.0),
                'tax' => (float)($row['tax'] ?? 0.0),
                'tax_amount' => (float)($row['tax_amount'] ?? 0.0),
                'total' => (float)($row['total'] ?? 0.0),
            ];
        }

        return $items;
    }

    private function hasPurchaseId(int $purchaseOrderId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT purchase_id FROM `" . DB::PURCHASE_ORDERS . "` WHERE id = :id",
            ['id' => $purchaseOrderId]
        );
        return $row !== null && !empty($row['purchase_id']);
    }

    private function removePurchaseOrderPdf(int $id): void
    {
        try {
            $path = PdfHelper::storageDirFor('purchase_orders') . '/' . PdfHelper::filenameWithExt($id);
            if (is_file($path)) {
                unlink($path);
            }
        } catch (\Throwable $e) {
            // Ignore PDF removal failure; record deletion already succeeded.
        }
    }

    private function validatePurchaseOrderData(array $data, int $orgId): void
    {
        if (empty($data['vendor_id']) || (int)$data['vendor_id'] <= 0) {
            throw new ValidationException(['vendor_id' => "Please select Vendor."]);
        }
        // Verify vendor exists
        $vendor = $this->vendorRepo->find((int)$data['vendor_id'], $orgId);
        if ($vendor === null) {
            throw new ValidationException(['vendor_id' => "Selected vendor does not exist."]);
        }
        if (empty($data['purchase_order_date'])) {
            throw new ValidationException(['purchase_order_date' => "Please select Purchase Order Date."]);
        }
        if (empty($data['warehouse_id']) || $data['warehouse_id'] === 'Please select') {
            throw new ValidationException(['warehouse_id' => "Please select Warehouse."]);
        }
    }

    private function parseDate(string $date): string
    {
        if (empty($date)) {
            return date('Y-m-d');
        }
        if (strpos($date, '-') !== false) {
            $parts = explode('-', $date);
            if (count($parts) === 3 && (int)$parts[0] > 31) {
                return $date;
            }
        }
        return DateHelper::toDbDate($date) ?: $date;
    }
}
