<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\DebitNote;
use App\Model\DebitNoteItem;

class DebitNoteRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?DebitNote
    {
        $sql = "SELECT * FROM `{DB::DEBIT_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToDebitNote($row);
    }

    public function findItems(int $parentId): array
    {
        $sql = "SELECT * FROM `{DB::DEBIT_NOTE_ITEMS}` WHERE debit_note_id = :debit_note_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['debit_note_id' => $parentId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToDebitNoteItem($row);
        }
        return $items;
    }

    public function findItem(int $id): ?DebitNoteItem
    {
        $sql = "SELECT * FROM `{DB::DEBIT_NOTE_ITEMS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToDebitNoteItem($row);
    }

    public function getLastNoteNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT debit_note_no FROM `{DB::DEBIT_NOTES}` 
                WHERE debit_note_no LIKE :prefix 
                ORDER BY debit_note_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%']);
        return $row !== null ? (string)$row['debit_note_no'] : null;
    }

    public function save(DebitNote $debitNote): DebitNote
    {
        if ($debitNote->id === null) {
            return $this->insert($debitNote);
        }
        return $this->update($debitNote);
    }

    private function insert(DebitNote $debitNote): DebitNote
    {
        $sql = "INSERT INTO `{DB::DEBIT_NOTES}` (
                    organization_id, debit_note_no, debit_note_date, debit_note_status,
                    reference_no, vendor_id, purchase_id, warehouse_id, purchase_person,
                    vendor_notes, terms_and_conditions,
                    grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, grand_tax, grand_total,
                    publish, is_active, created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :debit_note_no, :debit_note_date, :debit_note_status,
                    :reference_no, :vendor_id, :purchase_id, :warehouse_id, :purchase_person,
                    :vendor_notes, :terms_and_conditions,
                    :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :grand_tax, :grand_total,
                    :publish, :is_active, NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $debitNote->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $debitNote->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted debit note.");
        }

        return $inserted;
    }

    private function update(DebitNote $debitNote): DebitNote
    {
        $sql = "UPDATE `{DB::DEBIT_NOTES}` SET
                    debit_note_no = :debit_note_no,
                    debit_note_date = :debit_note_date,
                    debit_note_status = :debit_note_status,
                    reference_no = :reference_no,
                    vendor_id = :vendor_id,
                    purchase_id = :purchase_id,
                    warehouse_id = :warehouse_id,
                    purchase_person = :purchase_person,
                    vendor_notes = :vendor_notes,
                    terms_and_conditions = :terms_and_conditions,
                    grand_subtotal = :grand_subtotal,
                    grand_discount_type = :grand_discount_type,
                    grand_discount_type_value = :grand_discount_type_value,
                    grand_discount_amount = :grand_discount_amount,
                    grand_after_discount = :grand_after_discount,
                    grand_tax = :grand_tax,
                    grand_total = :grand_total,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $debitNote->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$debitNote->id, $debitNote->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated debit note.");
        }

        return $updated;
    }

    public function saveItem(DebitNoteItem $item): DebitNoteItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(DebitNoteItem $item): DebitNoteItem
    {
        $sql = "INSERT INTO `{DB::DEBIT_NOTE_ITEMS}` (
                    debit_note_id, service, description, qty, rate,
                    sub_total, tax, tax_amount, total,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :debit_note_id, :service, :description, :qty, :rate,
                    :sub_total, :tax, :tax_amount, :total,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['organization_id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted debit note item.");
        }

        return $inserted;
    }

    private function updateItem(DebitNoteItem $item): DebitNoteItem
    {
        $sql = "UPDATE `{DB::DEBIT_NOTE_ITEMS}` SET
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
        unset($params['debit_note_id'], $params['organization_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated debit note item.");
        }

        return $updated;
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByParent($id);
        $sql = "DELETE FROM `{DB::DEBIT_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByParent(int $parentId): bool
    {
        $sql = "DELETE FROM `{DB::DEBIT_NOTE_ITEMS}` WHERE debit_note_id = :debit_note_id";
        $this->db->execute($sql, ['debit_note_id' => $parentId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $parentId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['debit_note_id' => $parentId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::DEBIT_NOTE_ITEMS}` 
                WHERE id IN ($inClause) AND debit_note_id = :debit_note_id";
        $this->db->execute($sql, $params);
    }

    private function mapRowToDebitNote(array $row): DebitNote
    {
        return new DebitNote(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            debitNoteNo: (string)$row['debit_note_no'],
            debitNoteDate: (string)$row['debit_note_date'],
            debitNoteStatus: (string)$row['debit_note_status'],
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            vendorId: (int)($row['vendor_id'] ?? 0),
            purchaseId: (int)($row['purchase_id'] ?? 0),
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            purchasePerson: (int)($row['purchase_person'] ?? 0),
            vendorNotes: $row['vendor_notes'] !== null ? (string)$row['vendor_notes'] : null,
            termsAndConditions: $row['terms_and_conditions'] !== null ? (string)$row['terms_and_conditions'] : null,
            grandSubtotal: (float)($row['grand_subtotal'] ?? 0.0),
            grandDiscountType: (string)($row['grand_discount_type'] ?? '0.00'),
            grandDiscountTypeValue: (float)($row['grand_discount_type_value'] ?? 0.0),
            grandDiscountAmount: (float)($row['grand_discount_amount'] ?? 0.0),
            grandAfterDiscount: (float)($row['grand_after_discount'] ?? 0.0),
            grandTax: (float)($row['grand_tax'] ?? 0.0),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? $row['publish'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }

    private function mapRowToDebitNoteItem(array $row): DebitNoteItem
    {
        return new DebitNoteItem(
            id: (int)$row['id'],
            organizationId: 0,
            debitNoteId: (int)$row['debit_note_id'],
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
