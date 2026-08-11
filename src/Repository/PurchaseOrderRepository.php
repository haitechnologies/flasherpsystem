<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\PurchaseOrder;
use App\Model\PurchaseOrderItem;

class PurchaseOrderRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?PurchaseOrder
    {
        $sql = "SELECT * FROM `{DB::PURCHASE_ORDERS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPurchaseOrder($row);
    }

    public function generatePurchaseOrderNo(int $orgId): string
    {
        $prefix = 'FL-PO' . date('ym');
        $sql = "SELECT purchase_order_no FROM `{DB::PURCHASE_ORDERS}`
                WHERE purchase_order_no LIKE :prefix
                ORDER BY purchase_order_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '%']);
        $serial = 1;
        if ($row !== null && !empty($row['purchase_order_no'])) {
            $serial = (int)substr((string)$row['purchase_order_no'], -4) + 1;
        }
        return $prefix . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);
    }

    public function findItemsByPurchaseOrder(int $purchaseOrderId): array
    {
        $sql = "SELECT * FROM `{DB::PURCHASE_ORDER_ITEMS}` WHERE purchase_order_id = :po_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['po_id' => $purchaseOrderId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToPurchaseOrderItem($row);
        }
        return $items;
    }

    public function save(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->id === null) {
            return $this->insert($order);
        }
        return $this->update($order);
    }

    private function insert(PurchaseOrder $order): PurchaseOrder
    {
        $sql = "INSERT INTO `{DB::PURCHASE_ORDERS}` (
                    organization_id, purchase_order_no, purchase_order_date, vendor_id, purchase_order_status,
                    reference_no, subject, warehouse_id, vendor_notes, terms_and_conditions,
                    grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, grand_tax, grand_total,
                    is_active, created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :purchase_order_no, :purchase_order_date, :vendor_id, :purchase_order_status,
                    :reference_no, :subject, :warehouse_id, :vendor_notes, :terms_and_conditions,
                    :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :grand_tax, :grand_total,
                    :is_active, NOW(), NOW(), :created_by
                )";

        $params = $order->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $order->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted purchase order.");
        }

        return $inserted;
    }

    private function update(PurchaseOrder $order): PurchaseOrder
    {
        $sql = "UPDATE `{DB::PURCHASE_ORDERS}` SET
                    purchase_order_date = :purchase_order_date,
                    vendor_id = :vendor_id,
                    purchase_order_status = :purchase_order_status,
                    reference_no = :reference_no,
                    subject = :subject,
                    warehouse_id = :warehouse_id,
                    vendor_notes = :vendor_notes,
                    terms_and_conditions = :terms_and_conditions,
                    grand_subtotal = :grand_subtotal,
                    grand_discount_type = :grand_discount_type,
                    grand_discount_type_value = :grand_discount_type_value,
                    grand_discount_amount = :grand_discount_amount,
                    grand_after_discount = :grand_after_discount,
                    grand_tax = :grand_tax,
                    grand_total = :grand_total,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $order->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by'], $params['purchase_order_no']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$order->id, $order->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated purchase order.");
        }

        return $updated;
    }

    public function saveItem(PurchaseOrderItem $item): PurchaseOrderItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(PurchaseOrderItem $item): PurchaseOrderItem
    {
        $sql = "INSERT INTO `{DB::PURCHASE_ORDER_ITEMS}` (
                    purchase_order_id, service, description, qty,
                    rate, sub_total, tax, tax_amount, total,
                    created_at, updated_at, created_by
                ) VALUES (
                    :purchase_order_id, :service, :description, :qty,
                    :rate, :sub_total, :tax, :tax_amount, :total,
                    NOW(), NOW(), :created_by
                )";

        $params = $item->toArray();
        unset(
            $params['id'],
            $params['organization_id'],
            $params['created_at'],
            $params['updated_at'],
            $params['updated_by']
        );

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted purchase order item.");
        }

        return $inserted;
    }

    private function updateItem(PurchaseOrderItem $item): PurchaseOrderItem
    {
        $sql = "UPDATE `{DB::PURCHASE_ORDER_ITEMS}` SET
                    service = :service,
                    description = :description,
                    qty = :qty,
                    rate = :rate,
                    sub_total = :sub_total,
                    tax = :tax,
                    tax_amount = :tax_amount,
                    total = :total,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id";

        $params = $item->toArray();
        unset(
            $params['purchase_order_id'],
            $params['organization_id'],
            $params['created_at'],
            $params['updated_at'],
            $params['created_by']
        );

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated purchase order item.");
        }

        return $updated;
    }

    public function findItem(int $id): ?PurchaseOrderItem
    {
        $sql = "SELECT * FROM `{DB::PURCHASE_ORDER_ITEMS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPurchaseOrderItem($row);
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByPurchaseOrder($id);
        $sql = "DELETE FROM `{DB::PURCHASE_ORDERS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByPurchaseOrder(int $purchaseOrderId): bool
    {
        $sql = "DELETE FROM `{DB::PURCHASE_ORDER_ITEMS}` WHERE purchase_order_id = :po_id";
        $this->db->execute($sql, ['po_id' => $purchaseOrderId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $purchaseOrderId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['po_id' => $purchaseOrderId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::PURCHASE_ORDER_ITEMS}` 
                WHERE id IN ($inClause) AND purchase_order_id = :po_id";
        $this->db->execute($sql, $params);
    }

    private function mapRowToPurchaseOrder(array $row): PurchaseOrder
    {
        return new PurchaseOrder(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            purchaseOrderDate: (string)$row['purchase_order_date'],
            vendorId: (int)($row['vendor_id'] ?? 0),
            purchaseOrderNo: $row['purchase_order_no'] !== null ? (string)$row['purchase_order_no'] : null,
            purchaseOrderStatus: (string)($row['purchase_order_status'] ?? 'draft'),
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            subject: $row['subject'] !== null ? (string)$row['subject'] : null,
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            vendorNotes: $row['vendor_notes'] !== null ? (string)$row['vendor_notes'] : null,
            termsAndConditions: $row['terms_and_conditions'] !== null ? (string)$row['terms_and_conditions'] : null,
            grandSubtotal: (float)($row['grand_subtotal'] ?? 0.0),
            grandDiscountType: (string)($row['grand_discount_type'] ?? '0.00'),
            grandDiscountTypeValue: (float)($row['grand_discount_type_value'] ?? 0.0),
            grandDiscountAmount: (float)($row['grand_discount_amount'] ?? 0.0),
            grandAfterDiscount: (float)($row['grand_after_discount'] ?? 0.0),
            grandTax: (float)($row['grand_tax'] ?? 0.0),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            isActive: (bool)($row['is_active'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }

    private function mapRowToPurchaseOrderItem(array $row): PurchaseOrderItem
    {
        return new PurchaseOrderItem(
            id: (int)$row['id'],
            organizationId: 0,
            purchaseOrderId: (int)$row['purchase_order_id'],
            service: (int)$row['service'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            qty: (float)($row['qty'] ?? 1.0),
            rate: (float)($row['rate'] ?? 0.0),
            subTotal: (float)($row['sub_total'] ?? 0.0),
            tax: (float)($row['tax'] ?? 0.0),
            taxAmount: (float)($row['tax_amount'] ?? 0.0),
            total: (float)($row['total'] ?? 0.0),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }
}
