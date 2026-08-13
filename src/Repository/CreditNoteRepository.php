<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\CreditNote;
use App\Model\CreditNoteItem;

class CreditNoteRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?CreditNote
    {
        $sql = "SELECT id, organization_id, credit_note_no, credit_note_date, credit_note_status,
                       reference_no, customer_id, invoice_id, warehouse_id, subject, payment_term,
                       expiry_date, expected_shipment_date, shipment_type, sales_person,
                       job_reference_no, master_awb_no, shipper, consignee, origin, destination,
                       no_of_packs, gross_weight, chargeable_weight, volume, customer_notes,
                       terms_and_conditions, grand_subtotal, grand_discount_type,
                       grand_discount_type_value, grand_discount_amount, grand_after_discount,
                       grand_tax, grand_total, qrcode, pdf, publish, is_active,
                       created_at, updated_at, updated_by, created_by
                FROM `{DB::CREDIT_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToCreditNote($row);
    }

    public function findItems(int $parentId, int $orgId): array
    {
        $sql = "SELECT id, organization_id, credit_note_id, service, description, qty, rate,
                       discount_type, discount_type_value, discount_amount, tax, tax_amount,
                       sub_total, total, created_at, updated_at, updated_by, created_by
                FROM `{DB::CREDIT_NOTE_ITEMS}` WHERE credit_note_id = :credit_note_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['credit_note_id' => $parentId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToCreditNoteItem($row);
        }
        return $items;
    }

    public function findItem(int $id, int $orgId): ?CreditNoteItem
    {
        $sql = "SELECT id, organization_id, credit_note_id, service, description, qty, rate,
                       discount_type, discount_type_value, discount_amount, tax, tax_amount,
                       sub_total, total, created_at, updated_at, updated_by, created_by
                FROM `{DB::CREDIT_NOTE_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToCreditNoteItem($row);
    }

    public function getLastNoteNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT credit_note_no FROM `{DB::CREDIT_NOTES}` 
                WHERE credit_note_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY credit_note_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['credit_note_no'] : null;
    }

    public function save(CreditNote $creditNote): CreditNote
    {
        if ($creditNote->id === null) {
            return $this->insert($creditNote);
        }
        return $this->update($creditNote);
    }

    private function insert(CreditNote $creditNote): CreditNote
    {
        $sql = "INSERT INTO `{DB::CREDIT_NOTES}` (
                    organization_id, credit_note_no, credit_note_date, credit_note_status,
                    reference_no, customer_id, invoice_id, warehouse_id, subject, payment_term,
                    expiry_date, expected_shipment_date, shipment_type, sales_person,
                    job_reference_no, master_awb_no, shipper, consignee, origin, destination,
                    no_of_packs, gross_weight, chargeable_weight, volume, customer_notes,
                    terms_and_conditions, grand_subtotal, grand_discount_type,
                    grand_discount_type_value, grand_discount_amount, grand_after_discount,
                    grand_tax, grand_total, qrcode, pdf, publish, is_active,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :credit_note_no, :credit_note_date, :credit_note_status,
                    :reference_no, :customer_id, :invoice_id, :warehouse_id, :subject, :payment_term,
                    :expiry_date, :expected_shipment_date, :shipment_type, :sales_person,
                    :job_reference_no, :master_awb_no, :shipper, :consignee, :origin, :destination,
                    :no_of_packs, :gross_weight, :chargeable_weight, :volume, :customer_notes,
                    :terms_and_conditions, :grand_subtotal, :grand_discount_type,
                    :grand_discount_type_value, :grand_discount_amount, :grand_after_discount,
                    :grand_tax, :grand_total, :qrcode, :pdf, :publish, :is_active,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $creditNote->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $creditNote->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted credit note.");
        }

        return $inserted;
    }

    private function update(CreditNote $creditNote): CreditNote
    {
        $sql = "UPDATE `{DB::CREDIT_NOTES}` SET
                    credit_note_no = :credit_note_no,
                    credit_note_date = :credit_note_date,
                    credit_note_status = :credit_note_status,
                    reference_no = :reference_no,
                    customer_id = :customer_id,
                    invoice_id = :invoice_id,
                    warehouse_id = :warehouse_id,
                    subject = :subject,
                    payment_term = :payment_term,
                    expiry_date = :expiry_date,
                    expected_shipment_date = :expected_shipment_date,
                    shipment_type = :shipment_type,
                    sales_person = :sales_person,
                    job_reference_no = :job_reference_no,
                    master_awb_no = :master_awb_no,
                    shipper = :shipper,
                    consignee = :consignee,
                    origin = :origin,
                    destination = :destination,
                    no_of_packs = :no_of_packs,
                    gross_weight = :gross_weight,
                    chargeable_weight = :chargeable_weight,
                    volume = :volume,
                    customer_notes = :customer_notes,
                    terms_and_conditions = :terms_and_conditions,
                    grand_subtotal = :grand_subtotal,
                    grand_discount_type = :grand_discount_type,
                    grand_discount_type_value = :grand_discount_type_value,
                    grand_discount_amount = :grand_discount_amount,
                    grand_after_discount = :grand_after_discount,
                    grand_tax = :grand_tax,
                    grand_total = :grand_total,
                    qrcode = :qrcode,
                    pdf = :pdf,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $creditNote->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$creditNote->id, $creditNote->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated credit note.");
        }

        return $updated;
    }

    public function saveItem(CreditNoteItem $item): CreditNoteItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(CreditNoteItem $item): CreditNoteItem
    {
        $sql = "INSERT INTO `{DB::CREDIT_NOTE_ITEMS}` (
                    organization_id, credit_note_id, service, description, qty, rate,
                    discount_type, discount_type_value, discount_amount,
                    sub_total, tax, tax_amount, total,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :credit_note_id, :service, :description, :qty, :rate,
                    :discount_type, :discount_type_value, :discount_amount,
                    :sub_total, :tax, :tax_amount, :total,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted credit note item.");
        }

        return $inserted;
    }

    private function updateItem(CreditNoteItem $item): CreditNoteItem
    {
        $sql = "UPDATE `{DB::CREDIT_NOTE_ITEMS}` SET
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
                WHERE id = :id AND organization_id = :organization_id";

        $params = $item->toArray();
        unset($params['credit_note_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id, $item->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated credit note item.");
        }

        return $updated;
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByParent($id, $orgId);
        $sql = "DELETE FROM `{DB::CREDIT_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByParent(int $parentId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::CREDIT_NOTE_ITEMS}` WHERE credit_note_id = :credit_note_id AND organization_id = :org_id";
        $this->db->execute($sql, ['credit_note_id' => $parentId, 'org_id' => $orgId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $parentId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['credit_note_id' => $parentId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::CREDIT_NOTE_ITEMS}` 
                WHERE id IN ($inClause) AND credit_note_id = :credit_note_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    private function mapRowToCreditNote(array $row): CreditNote
    {
        return new CreditNote(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            creditNoteNo: (string)$row['credit_note_no'],
            creditNoteDate: (string)$row['credit_note_date'],
            creditNoteStatus: (string)$row['credit_note_status'],
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            customerId: (int)($row['customer_id'] ?? 0),
            invoiceId: (int)($row['invoice_id'] ?? 0),
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            subject: $row['subject'] !== null ? (string)$row['subject'] : null,
            paymentTerm: (int)($row['payment_term'] ?? 0),
            expiryDate: $row['expiry_date'] !== null ? (string)$row['expiry_date'] : null,
            expectedShipmentDate: $row['expected_shipment_date'] !== null && $row['expected_shipment_date'] !== '1970-01-01' ? (string)$row['expected_shipment_date'] : null,
            shipmentType: $row['shipment_type'] !== null ? (string)$row['shipment_type'] : null,
            salesPerson: (int)($row['sales_person'] ?? 0),
            jobReferenceNo: $row['job_reference_no'] !== null ? (string)$row['job_reference_no'] : null,
            masterAwbNo: $row['master_awb_no'] !== null ? (string)$row['master_awb_no'] : null,
            shipper: (int)($row['shipper'] ?? 0),
            consignee: (int)($row['consignee'] ?? 0),
            origin: (int)($row['origin'] ?? 0),
            destination: (int)($row['destination'] ?? 0),
            noOfPacks: (int)($row['no_of_packs'] ?? 0),
            grossWeight: (float)($row['gross_weight'] ?? 0.0),
            chargeableWeight: (float)($row['chargeable_weight'] ?? 0.0),
            volume: (float)($row['volume'] ?? 0.0),
            qrcode: $row['qrcode'] !== null ? (string)$row['qrcode'] : null,
            pdf: $row['pdf'] !== null ? (string)$row['pdf'] : null,
            customerNotes: $row['customer_notes'] !== null ? (string)$row['customer_notes'] : null,
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

    private function mapRowToCreditNoteItem(array $row): CreditNoteItem
    {
        return new CreditNoteItem(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            creditNoteId: (int)$row['credit_note_id'],
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
