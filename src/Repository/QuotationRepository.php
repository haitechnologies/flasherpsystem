<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Quotation;
use App\Model\QuotationItem;

/**
 * Quotation Repository
 *
 * Handles PDO-based data access for erp_quotations and erp_quotation_items
 * tables with strict tenant isolation.
 */
class QuotationRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find quotation by ID and organization
     */
    public function find(int $id, int $orgId): ?Quotation
    {
        $sql = "SELECT * FROM `{DB::QUOTATIONS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToQuotation($row);
    }

    /**
     * Find quotation items by quotation ID and organization
     */
    public function findItemsByQuotation(int $quotationId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::QUOTATION_ITEMS}` WHERE quotation_id = :quotation_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['quotation_id' => $quotationId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToQuotationItem($row);
        }
        return $items;
    }

    /**
     * Get the last quotation number for a given monthly prefix and organization
     */
    public function getLastQuotationNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT quotation_no FROM `{DB::QUOTATIONS}` 
                WHERE quotation_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY quotation_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['quotation_no'] : null;
    }

    /**
     * Find all quotations in an organization
     */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::QUOTATIONS}` WHERE organization_id = :org_id ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $quotations = [];
        foreach ($rows as $row) {
            $quotations[] = $this->mapRowToQuotation($row);
        }
        return $quotations;
    }

    /**
     * Save Quotation (Insert or Update)
     */
    public function save(Quotation $quotation): Quotation
    {
        if ($quotation->id === null) {
            return $this->insert($quotation);
        }
        return $this->update($quotation);
    }

    private function insert(Quotation $quotation): Quotation
    {
        $sql = "INSERT INTO `{DB::QUOTATIONS}` (
                    organization_id, quotation_no, customer_id, quotation_status, quotation_date, expiry_date,
                    lead_id, warehouse_id, expected_shipment_date, payment_term, shipment_type,
                    sales_person, job_reference_no, mawb_bol, hwb_hbol, shipper_id, consignee_id, origin_port,
                    origin_country, destination_port, destination_country, no_of_packs, gross_weight, chargeable_weight, volume,
                    cbm,
                    terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total,
                    publish, is_active, created_at, updated_at, updated_by, created_by, pdf
                ) VALUES (
                    :organization_id, :quotation_no, :customer_id, :quotation_status, :quotation_date, :expiry_date,
                    :lead_id, :warehouse_id, :expected_shipment_date, :payment_term, :shipment_type,
                    :sales_person, :job_reference_no, :mawb_bol, :hwb_hbol, :shipper_id, :consignee_id, :origin_port,
                    :origin_country, :destination_port, :destination_country, :no_of_packs, :gross_weight, :chargeable_weight, :volume,
                    :cbm,
                    :terms_and_conditions, :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :customer_notes, :grand_tax, :grand_total,
                    :publish, :is_active, NOW(), NOW(), :updated_by, :created_by, :pdf
                )";

        $params = $quotation->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $quotation->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted quotation.");
        }

        return $inserted;
    }

    private function update(Quotation $quotation): Quotation
    {
        $sql = "UPDATE `{DB::QUOTATIONS}` SET
                    quotation_no = :quotation_no,
                    customer_id = :customer_id,
                    quotation_status = :quotation_status,
                    quotation_date = :quotation_date,
                    expiry_date = :expiry_date,
                    lead_id = :lead_id,
                    warehouse_id = :warehouse_id,
                    expected_shipment_date = :expected_shipment_date,
                    payment_term = :payment_term,
                    shipment_type = :shipment_type,
                    sales_person = :sales_person,
                    job_reference_no = :job_reference_no,
                    mawb_bol = :mawb_bol,
                    hwb_hbol = :hwb_hbol,
                    shipper_id = :shipper_id,
                    consignee_id = :consignee_id,
                    origin_port = :origin_port,
                    origin_country = :origin_country,
                    destination_port = :destination_port,
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
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    pdf = :pdf
                WHERE id = :id AND organization_id = :organization_id";

        $params = $quotation->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$quotation->id, $quotation->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated quotation.");
        }

        return $updated;
    }

    /**
     * Save QuotationItem (Insert or Update)
     */
    public function saveItem(QuotationItem $item): QuotationItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(QuotationItem $item): QuotationItem
    {
        $sql = "INSERT INTO `{DB::QUOTATION_ITEMS}` (
                    organization_id, quotation_id, service, description, qty, rate, sub_total,
                    tax, tax_amount, total, discount_type, discount_type_value, discount_amount,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :quotation_id, :service, :description, :qty, :rate, :sub_total,
                    :tax, :tax_amount, :total, :discount_type, :discount_type_value, :discount_amount,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted quotation item.");
        }

        return $inserted;
    }

    private function updateItem(QuotationItem $item): QuotationItem
    {
        $sql = "UPDATE `{DB::QUOTATION_ITEMS}` SET
                    quotation_id = :quotation_id,
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
            throw new \RuntimeException("Failed to retrieve updated quotation item.");
        }

        return $updated;
    }

    /**
     * Find a single QuotationItem by ID and organization
     */
    public function findItem(int $id, int $orgId): ?QuotationItem
    {
        $sql = "SELECT * FROM `{DB::QUOTATION_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToQuotationItem($row);
    }

    /**
     * Delete a quotation and its associated items
     */
    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByQuotation($id, $orgId);
        $this->db->execute("DELETE FROM `{DB::DIMENSION_ITEMS}` WHERE module_type = 'quotations' AND record_id = :id", ['id' => $id]);
        $sql = "DELETE FROM `{DB::QUOTATIONS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Delete all items for a specific quotation
     */
    public function deleteItemsByQuotation(int $quotationId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::QUOTATION_ITEMS}` WHERE quotation_id = :quotation_id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['quotation_id' => $quotationId, 'org_id' => $orgId]);
        return true;
    }

    /**
     * Delete specific quotation items by IDs
     */
    public function deleteItemsByIds(array $ids, int $quotationId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['quotation_id' => $quotationId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::QUOTATION_ITEMS}` 
                WHERE id IN ($inClause) AND quotation_id = :quotation_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    /**
     * Update quotation status
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $sql = "UPDATE `{DB::QUOTATIONS}` SET quotation_status = :status, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['status' => $status, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Update quotation PDF path
     */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        $sql = "UPDATE `{DB::QUOTATIONS}` SET pdf = :pdf, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['pdf' => $pdfFilename, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Map database row to Quotation DTO
     */
    private function mapRowToQuotation(array $row): Quotation
    {
        return new Quotation(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            quotationNo: (string)$row['quotation_no'],
            customerId: (int)($row['customer_id'] ?? 0),
            quotationStatus: (string)($row['quotation_status'] ?? 'draft'),
            quotationDate: (string)$row['quotation_date'],
            expiryDate: (string)$row['expiry_date'],
            leadId: (int)($row['lead_id'] ?? 0),
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            expectedShipmentDate: $row['expected_shipment_date'] !== null && $row['expected_shipment_date'] !== '1970-01-01' ? (string)$row['expected_shipment_date'] : null,
            paymentTerm: (int)($row['payment_term'] ?? 0),
            shipmentType: $row['shipment_type'] !== null ? (string)$row['shipment_type'] : null,
            salesPerson: (int)($row['sales_person'] ?? 0),
            jobReferenceNo: $row['job_reference_no'] !== null ? (string)$row['job_reference_no'] : null,
            masterAwbNo: $row['mawb_bol'] !== null ? (string)$row['mawb_bol'] : null,
            hwbHbol: $row['hwb_hbol'] !== null ? (string)$row['hwb_hbol'] : null,
            shipper: (int)($row['shipper_id'] ?? 0),
            consignee: (int)($row['consignee_id'] ?? 0),
            origin: (int)($row['origin_port'] ?? 0),
            originCountry: (int)($row['origin_country'] ?? 0),
            destination: (int)($row['destination_port'] ?? 0),
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
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? $row['publish'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
            pdf: $row['pdf'] !== null ? (string)$row['pdf'] : null
        );
    }

    /**
     * Map database row to QuotationItem DTO
     */
    private function mapRowToQuotationItem(array $row): QuotationItem
    {
        return new QuotationItem(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            quotationId: (int)$row['quotation_id'],
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
