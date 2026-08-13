<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Invoice;
use App\Model\InvoiceItem;

/**
 * Invoice Repository
 *
 * Handles PDO-based data access for erp_invoices and erp_invoice_items
 * tables with strict tenant isolation.
 */
class InvoiceRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find invoice by ID and organization
     */
    public function find(int $id, int $orgId): ?Invoice
    {
        $sql = "SELECT * FROM `{DB::INVOICES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToInvoice($row);
    }

    /**
     * Find invoice items by invoice ID and organization
     */
    public function findItemsByInvoice(int $invoiceId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::INVOICE_ITEMS}` WHERE invoice_id = :invoice_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['invoice_id' => $invoiceId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToInvoiceItem($row);
        }
        return $items;
    }

    /**
     * Get the last invoice number for a given monthly prefix and organization
     */
    public function getLastInvoiceNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT invoice_no FROM `{DB::INVOICES}` 
                WHERE invoice_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY invoice_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['invoice_no'] : null;
    }

    /**
     * Find all invoices in an organization
     */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::INVOICES}` WHERE organization_id = :org_id ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $invoices = [];
        foreach ($rows as $row) {
            $invoices[] = $this->mapRowToInvoice($row);
        }
        return $invoices;
    }

    /**
     * Save Invoice (Insert or Update)
     */
    public function save(Invoice $invoice): Invoice
    {
        if ($invoice->id === null) {
            return $this->insert($invoice);
        }
        return $this->update($invoice);
    }

    private function insert(Invoice $invoice): Invoice
    {
        $sql = "INSERT INTO `{DB::INVOICES}` (
                    organization_id, invoice_no, customer_id, invoice_status, invoice_date, expiry_date,
                    reference_no, warehouse_id, expected_shipment_date, payment_term, shipment_type,
                    sales_person, job_reference_no, master_awb_no, hwb_hbol, lead_id, shipper, consignee, origin,
                    origin_country, destination, destination_country, no_of_packs, gross_weight, chargeable_weight, volume,
                    cbm, terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total, balance_due,
                    publish, is_active, created_at, updated_at, updated_by, created_by, recurring, pdf
                ) VALUES (
                    :organization_id, :invoice_no, :customer_id, :invoice_status, :invoice_date, :expiry_date,
                    :reference_no, :warehouse_id, :expected_shipment_date, :payment_term, :shipment_type,
                    :sales_person, :job_reference_no, :master_awb_no, :hwb_hbol, :lead_id, :shipper, :consignee, :origin,
                    :origin_country, :destination, :destination_country, :no_of_packs, :gross_weight, :chargeable_weight, :volume,
                    :cbm, :terms_and_conditions, :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :customer_notes, :grand_tax, :grand_total, :balance_due,
                    :publish, :is_active, NOW(), NOW(), :updated_by, :created_by, :recurring, :pdf
                )";

        $params = $invoice->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $invoice->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted invoice.");
        }

        return $inserted;
    }

    private function update(Invoice $invoice): Invoice
    {
        $sql = "UPDATE `{DB::INVOICES}` SET
                    invoice_no = :invoice_no,
                    customer_id = :customer_id,
                    invoice_status = :invoice_status,
                    invoice_date = :invoice_date,
                    expiry_date = :expiry_date,
                    reference_no = :reference_no,
                    warehouse_id = :warehouse_id,
                    expected_shipment_date = :expected_shipment_date,
                    payment_term = :payment_term,
                    shipment_type = :shipment_type,
                    sales_person = :sales_person,
                    job_reference_no = :job_reference_no,
                    master_awb_no = :master_awb_no,
                    hwb_hbol = :hwb_hbol,
                    lead_id = :lead_id,
                    shipper = :shipper,
                    consignee = :consignee,
                    origin = :origin,
                    origin_country = :origin_country,
                    destination = :destination,
                    destination_country = :destination_country,
                    no_of_packs = :no_of_packs,
                    gross_weight = :gross_weight,
                    chargeable_weight = :chargeable_weight,
                    volume = :volume,
                    cbm = :cbm,
                    terms_and_conditions = :terms_and_conditions,
                    grand_subtotal = :grand_subtotal,
                    grand_discount_type = :grand_discount_type,
                    grand_discount_type_value = :grand_discount_type_value,
                    grand_discount_amount = :grand_discount_amount,
                    grand_after_discount = :grand_after_discount,
                    customer_notes = :customer_notes,
                    grand_tax = :grand_tax,
                    grand_total = :grand_total,
                    balance_due = :balance_due,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    recurring = :recurring,
                    pdf = :pdf
                WHERE id = :id AND organization_id = :organization_id";

        $params = $invoice->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$invoice->id, $invoice->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated invoice.");
        }

        return $updated;
    }

    /**
     * Save InvoiceItem (Insert or Update)
     */
    public function saveItem(InvoiceItem $item): InvoiceItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(InvoiceItem $item): InvoiceItem
    {
        $sql = "INSERT INTO `{DB::INVOICE_ITEMS}` (
                    organization_id, invoice_id, service, description, qty, rate, sub_total,
                    tax, tax_amount, total, discount_type, discount_type_value, discount_amount,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :invoice_id, :service, :description, :qty, :rate, :sub_total,
                    :tax, :tax_amount, :total, :discount_type, :discount_type_value, :discount_amount,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted invoice item.");
        }

        return $inserted;
    }

    private function updateItem(InvoiceItem $item): InvoiceItem
    {
        $sql = "UPDATE `{DB::INVOICE_ITEMS}` SET
                    invoice_id = :invoice_id,
                    service = :service,
                    description = :description,
                    qty = :qty,
                    rate = :rate,
                    sub_total = :sub_total,
                    tax = :tax,
                    tax_amount = :tax_amount,
                    total = :total,
                    discount_type = :discount_type,
                    discount_type_value = :discount_type_value,
                    discount_amount = :discount_amount,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $item->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id, $item->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated invoice item.");
        }

        return $updated;
    }

    /**
     * Find a single InvoiceItem by ID and organization
     */
    public function findItem(int $id, int $orgId): ?InvoiceItem
    {
        $sql = "SELECT * FROM `{DB::INVOICE_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToInvoiceItem($row);
    }

    /**
     * Delete an invoice and its associated items under transaction (handled at repository or service level)
     */
    public function delete(int $id, int $orgId): bool
    {
        // Service should wrap this in database transaction, but we also ensure items are deleted.
        $this->deleteItemsByInvoice($id, $orgId);
        $sql = "DELETE FROM `{DB::INVOICES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all items for a specific invoice
     */
    public function deleteItemsByInvoice(int $invoiceId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::INVOICE_ITEMS}` WHERE invoice_id = :invoice_id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['invoice_id' => $invoiceId, 'org_id' => $orgId]);
        return true;
    }

    /**
     * Delete specific invoice items by IDs
     */
    public function deleteItemsByIds(array $ids, int $invoiceId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['invoice_id' => $invoiceId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::INVOICE_ITEMS}` 
                WHERE id IN ($inClause) AND invoice_id = :invoice_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    /**
     * Update invoice status
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $sql = "UPDATE `{DB::INVOICES}` SET invoice_status = :status, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['status' => $status, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update invoice PDF path
     */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        $sql = "UPDATE `{DB::INVOICES}` SET pdf = :pdf, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['pdf' => $pdfFilename, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Find invoice by ID only (useful for public PDF links with token verification)
     */
    public function findByIdOnly(int $id): ?Invoice
    {
        $sql = "SELECT * FROM `{DB::INVOICES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToInvoice($row);
    }

    /**
     * Find invoice items by invoice ID only (useful for public PDF links)
     */
    public function findItemsByInvoiceIdOnly(int $invoiceId): array
    {
        $sql = "SELECT * FROM `{DB::INVOICE_ITEMS}` WHERE invoice_id = :invoice_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['invoice_id' => $invoiceId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToInvoiceItem($row);
        }
        return $items;
    }

    /**
     * Map database row to Invoice DTO
     */
    private function mapRowToInvoice(array $row): Invoice
    {
        return new Invoice(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            invoiceNo: (string)$row['invoice_no'],
            customerId: (int)$row['customer_id'],
            invoiceStatus: (string)$row['invoice_status'],
            invoiceDate: (string)$row['invoice_date'],
            expiryDate: (string)$row['expiry_date'],
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            expectedShipmentDate: $row['expected_shipment_date'] !== null && $row['expected_shipment_date'] !== '1970-01-01' ? (string)$row['expected_shipment_date'] : null,
            paymentTerm: (int)($row['payment_term'] ?? 0),
            shipmentType: $row['shipment_type'] !== null ? (string)$row['shipment_type'] : null,
            salesPerson: (int)($row['sales_person'] ?? 0),
            jobReferenceNo: $row['job_reference_no'] !== null ? (string)$row['job_reference_no'] : null,
            masterAwbNo: $row['master_awb_no'] !== null ? (string)$row['master_awb_no'] : null,
            hwbHbol: ($row['hwb_hbol'] ?? null) !== null ? (string)$row['hwb_hbol'] : null,
            leadId: (int)($row['lead_id'] ?? 0),
            shipper: (int)($row['shipper'] ?? 0),
            consignee: (int)($row['consignee'] ?? 0),
            origin: (int)($row['origin'] ?? 0),
            originCountry: (int)($row['origin_country'] ?? 0),
            destination: (int)($row['destination'] ?? 0),
            destinationCountry: (int)($row['destination_country'] ?? 0),
            noOfPacks: (int)($row['no_of_packs'] ?? 0),
            grossWeight: (float)($row['gross_weight'] ?? 0.0),
            chargeableWeight: (float)($row['chargeable_weight'] ?? 0.0),
            volume: (float)($row['volume'] ?? 0.0),
            cbm: (float)($row['cbm'] ?? 0.0),
            termsAndConditions: $row['terms_and_conditions'] !== null ? (string)$row['terms_and_conditions'] : null,
            grandSubtotal: (float)($row['grand_subtotal'] ?? 0.0),
            grandDiscountType: (string)($row['grand_discount_type'] ?? '0.00'),
            grandDiscountTypeValue: (float)($row['grand_discount_type_value'] ?? 0.0),
            grandDiscountAmount: (float)($row['grand_discount_amount'] ?? 0.0),
            grandAfterDiscount: (float)($row['grand_after_discount'] ?? 0.0),
            customerNotes: $row['customer_notes'] !== null ? (string)$row['customer_notes'] : null,
            grandTax: (float)($row['grand_tax'] ?? 0.0),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            balanceDue: $row['balance_due'] !== null ? (float)$row['balance_due'] : null,
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? $row['publish'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
            recurring: (int)($row['recurring'] ?? 0),
            pdf: $row['pdf'] !== null ? (string)$row['pdf'] : null
        );
    }

    /**
     * Map database row to InvoiceItem DTO
     */
    private function mapRowToInvoiceItem(array $row): InvoiceItem
    {
        return new InvoiceItem(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            invoiceId: (int)$row['invoice_id'],
            service: (int)$row['service'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            qty: (float)($row['qty'] ?? 1.0),
            rate: (float)($row['rate'] ?? 0.0),
            subTotal: (float)($row['sub_total'] ?? 0.0),
            tax: (float)($row['tax'] ?? 0.0),
            taxAmount: (float)($row['tax_amount'] ?? 0.0),
            total: (float)($row['total'] ?? 0.0),
            discountType: $row['discount_type'] !== null ? (string)$row['discount_type'] : null,
            discountTypeValue: (float)($row['discount_type_value'] ?? 0.0),
            discountAmount: (float)($row['discount_amount'] ?? 0.0),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }
}
