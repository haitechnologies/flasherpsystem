<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Purchase;
use App\Model\PurchaseItem;

class PurchaseRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?Purchase
    {
        $sql = "SELECT * FROM `{DB::PURCHASES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPurchase($row);
    }

    public function generatePurchaseNo(int $orgId): string
    {
        $prefix = 'FL-PR' . date('ym');
        $sql = "SELECT purchase_no FROM `{DB::PURCHASES}` WHERE purchase_no LIKE :prefix ORDER BY purchase_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '%']);
        $serial = 1;
        if ($row !== null && !empty($row['purchase_no'])) {
            $serial = (int)substr((string)$row['purchase_no'], -4) + 1;
        }
        return $prefix . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);
    }

    public function findItemsByPurchase(int $purchaseId): array
    {
        $sql = "SELECT * FROM `{DB::PURCHASE_ITEMS}` WHERE purchase_id = :purchase_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['purchase_id' => $purchaseId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToPurchaseItem($row);
        }
        return $items;
    }

    public function save(Purchase $purchase): Purchase
    {
        if ($purchase->id === null) {
            return $this->insert($purchase);
        }
        return $this->update($purchase);
    }

    private function insert(Purchase $purchase): Purchase
    {
        $sql = "INSERT INTO `{DB::PURCHASES}` (
                    organization_id, purchase_date, vendor_id, purchase_no, purchase_status,
                    reference_no, subject, warehouse_id, vendor_notes, terms_and_conditions,
                    grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, grand_tax, grand_total,
                    is_active, created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :purchase_date, :vendor_id, :purchase_no, :purchase_status,
                    :reference_no, :subject, :warehouse_id, :vendor_notes, :terms_and_conditions,
                    :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :grand_tax, :grand_total,
                    :is_active, NOW(), NOW(), :created_by
                )";

        $params = $purchase->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $purchase->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted purchase.");
        }

        return $inserted;
    }

    private function update(Purchase $purchase): Purchase
    {
        $sql = "UPDATE `{DB::PURCHASES}` SET
                    purchase_date = :purchase_date,
                    vendor_id = :vendor_id,
                    purchase_status = :purchase_status,
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

        $params = $purchase->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by'], $params['purchase_no']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$purchase->id, $purchase->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated purchase.");
        }

        return $updated;
    }

    public function saveItem(PurchaseItem $item): PurchaseItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(PurchaseItem $item): PurchaseItem
    {
        $sql = "INSERT INTO `{DB::PURCHASE_ITEMS}` (
                    purchase_id, service, description, qty, rate,
                    discount_type, discount_type_value, discount_amount,
                    sub_total, tax, tax_amount, total,
                    created_at, updated_at, created_by
                ) VALUES (
                    :purchase_id, :service, :description, :qty, :rate,
                    :discount_type, :discount_type_value, :discount_amount,
                    :sub_total, :tax, :tax_amount, :total,
                    NOW(), NOW(), :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['organization_id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted purchase item.");
        }

        return $inserted;
    }

    private function updateItem(PurchaseItem $item): PurchaseItem
    {
        $sql = "UPDATE `{DB::PURCHASE_ITEMS}` SET
                    service = :service,
                    description = :description,
                    qty = :qty,
                    rate = :rate,
                    discount_type = :discount_type,
                    discount_type_value = :discount_type_value,
                    discount_amount = :discount_amount,
                    sub_total = :sub_total,
                    tax = :tax,
                    tax_amount = :tax_amount,
                    total = :total,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id";

        $params = $item->toArray();
        unset($params['purchase_id'], $params['organization_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated purchase item.");
        }

        return $updated;
    }

    public function findItem(int $id): ?PurchaseItem
    {
        $sql = "SELECT * FROM `{DB::PURCHASE_ITEMS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToPurchaseItem($row);
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByPurchase($id);
        $sql = "DELETE FROM `{DB::PURCHASES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByPurchase(int $purchaseId): bool
    {
        $sql = "DELETE FROM `{DB::PURCHASE_ITEMS}` WHERE purchase_id = :purchase_id";
        $this->db->execute($sql, ['purchase_id' => $purchaseId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $purchaseId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['purchase_id' => $purchaseId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::PURCHASE_ITEMS}` 
                WHERE id IN ($inClause) AND purchase_id = :purchase_id";
        $this->db->execute($sql, $params);
    }

    private function mapRowToPurchase(array $row): Purchase
    {
        return new Purchase(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            purchaseDate: (string)$row['purchase_date'],
            vendorId: (int)($row['vendor_id'] ?? 0),
            purchaseNo: $row['purchase_no'] !== null ? (string)$row['purchase_no'] : null,
            purchaseStatus: (string)($row['purchase_status'] ?? 'draft'),
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

    private function mapRowToPurchaseItem(array $row): PurchaseItem
    {
        return new PurchaseItem(
            id: (int)$row['id'],
            organizationId: 0,
            purchaseId: (int)$row['purchase_id'],
            service: (int)$row['service'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            qty: (float)($row['qty'] ?? 1.0),
            rate: (float)($row['rate'] ?? 0.0),
            discountType: $row['discount_type'] !== null ? (string)$row['discount_type'] : null,
            discountTypeValue: (float)($row['discount_type_value'] ?? 0.0),
            discountAmount: (float)($row['discount_amount'] ?? 0.0),
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
