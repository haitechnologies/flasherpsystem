<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SaleOrder;
use App\Model\SaleOrderItem;

/**
 * SaleOrder Repository
 *
 * Handles PDO-based data access for erp_sale_orders, erp_sale_order_items,
 * and erp_dimension_items tables with strict tenant isolation.
 */
class SaleOrderRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /** Find sale order by ID and organization */
    public function find(int $id, int $orgId): ?SaleOrder
    {
        $sql = "SELECT * FROM `{DB::SALE_ORDERS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToSaleOrder($row);
    }

    /** Find sale order items by sale order ID and organization */
    public function findItemsBySaleOrder(int $saleOrderId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::SALE_ORDER_ITEMS}` WHERE sale_order_id = :sale_order_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['sale_order_id' => $saleOrderId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToSaleOrderItem($row);
        }
        return $items;
    }

    /** Get the last sale order number for a given monthly prefix */
    public function getLastSaleOrderNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT sale_order_no FROM `{DB::SALE_ORDERS}` 
                WHERE sale_order_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY sale_order_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['sale_order_no'] : null;
    }

    /** Find all sale orders */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::SALE_ORDERS}` WHERE organization_id = :org_id ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $saleOrders = [];
        foreach ($rows as $row) {
            $saleOrders[] = $this->mapRowToSaleOrder($row);
        }
        return $saleOrders;
    }

    /** Save SaleOrder (Insert or Update) */
    public function save(SaleOrder $saleOrder): SaleOrder
    {
        if ($saleOrder->id === null) {
            return $this->insert($saleOrder);
        }
        return $this->update($saleOrder);
    }

    private function insert(SaleOrder $saleOrder): SaleOrder
    {
        $sql = "INSERT INTO `{DB::SALE_ORDERS}` (
                    organization_id, sale_order_no, customer_id, sale_order_status, sale_order_date, expiry_date,
                    reference_no, warehouse_id, expected_shipment_date, payment_term, shipment_type,
                    sales_person, job_reference_no, master_awb_no, mawb_bol, hwb_hbol,
                    shipper_id, consignee_id, origin_port, origin_country, destination_port, destination_country,
                    no_of_packs, gross_weight, chargeable_weight, volume, cbm,
                    terms_and_conditions, grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, customer_notes, grand_tax, grand_total,
                    publish, is_active, invoice_id, quotation_id,
                    created_at, updated_at, updated_by, created_by, pdf
                ) VALUES (
                    :organization_id, :sale_order_no, :customer_id, :sale_order_status, :sale_order_date, :expiry_date,
                    :reference_no, :warehouse_id, :expected_shipment_date, :payment_term, :shipment_type,
                    :sales_person, :job_reference_no, :master_awb_no, :mawb_bol, :hwb_hbol,
                    :shipper_id, :consignee_id, :origin_port, :origin_country, :destination_port, :destination_country,
                    :no_of_packs, :gross_weight, :chargeable_weight, :volume, :cbm,
                    :terms_and_conditions, :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :customer_notes, :grand_tax, :grand_total,
                    :publish, :is_active, :invoice_id, :quotation_id,
                    NOW(), NOW(), :updated_by, :created_by, :pdf
                )";

        $params = $saleOrder->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $saleOrder->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted sale order.");
        }

        return $inserted;
    }

    private function update(SaleOrder $saleOrder): SaleOrder
    {
        $sql = "UPDATE `{DB::SALE_ORDERS}` SET
                    sale_order_no = :sale_order_no,
                    customer_id = :customer_id,
                    sale_order_status = :sale_order_status,
                    sale_order_date = :sale_order_date,
                    expiry_date = :expiry_date,
                    reference_no = :reference_no,
                    warehouse_id = :warehouse_id,
                    expected_shipment_date = :expected_shipment_date,
                    payment_term = :payment_term,
                    shipment_type = :shipment_type,
                    sales_person = :sales_person,
                    job_reference_no = :job_reference_no,
                    master_awb_no = :master_awb_no,
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
                    invoice_id = :invoice_id,
                    quotation_id = :quotation_id,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    pdf = :pdf
                WHERE id = :id AND organization_id = :organization_id";

        $params = $saleOrder->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);
        if (($params['expected_shipment_date'] ?? null) === null) {
            $params['expected_shipment_date'] = '1970-01-01';
        }

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$saleOrder->id, $saleOrder->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated sale order.");
        }

        return $updated;
    }

    /** Save SaleOrderItem (Insert or Update) */
    public function saveItem(SaleOrderItem $item): SaleOrderItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(SaleOrderItem $item): SaleOrderItem
    {
        $sql = "INSERT INTO `{DB::SALE_ORDER_ITEMS}` (
                    organization_id, sale_order_id, service, description, qty, rate, sub_total,
                    tax, tax_amount, total, discount_type, discount_type_value, discount_amount,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :sale_order_id, :service, :description, :qty, :rate, :sub_total,
                    :tax, :tax_amount, :total, :discount_type, :discount_type_value, :discount_amount,
                    NOW(), NOW(), :updated_by, :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted sale order item.");
        }

        return $inserted;
    }

    private function updateItem(SaleOrderItem $item): SaleOrderItem
    {
        $sql = "UPDATE `{DB::SALE_ORDER_ITEMS}` SET
                    sale_order_id = :sale_order_id,
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
            throw new \RuntimeException("Failed to retrieve updated sale order item.");
        }

        return $updated;
    }

    /** Find a single SaleOrderItem by ID and organization */
    public function findItem(int $id, int $orgId): ?SaleOrderItem
    {
        $sql = "SELECT * FROM `{DB::SALE_ORDER_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToSaleOrderItem($row);
    }

    /** Delete a sale order and its associated items */
    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsBySaleOrder($id, $orgId);
        $sql = "DELETE FROM `{DB::SALE_ORDERS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /** Delete all items for a specific sale order */
    public function deleteItemsBySaleOrder(int $saleOrderId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::SALE_ORDER_ITEMS}` WHERE sale_order_id = :sale_order_id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['sale_order_id' => $saleOrderId, 'org_id' => $orgId]);
        return true;
    }

    /** Delete specific sale order items by IDs */
    public function deleteItemsByIds(array $ids, int $saleOrderId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['sale_order_id' => $saleOrderId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::SALE_ORDER_ITEMS}` 
                WHERE id IN ($inClause) AND sale_order_id = :sale_order_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    /** Update sale order status */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $sql = "UPDATE `{DB::SALE_ORDERS}` SET sale_order_status = :status, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['status' => $status, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /** Update sale order PDF path */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        $sql = "UPDATE `{DB::SALE_ORDERS}` SET pdf = :pdf, updated_at = NOW() 
                WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['pdf' => $pdfFilename, 'id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    // =========================================================================
    // Dimension Items
    // =========================================================================

    /** Find dimension items for a sale order */
    public function findDimensionItemsBySaleOrder(int $saleOrderId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::DIMENSION_ITEMS}` 
                WHERE module_type = 'sale_orders' AND record_id = :record_id AND organization_id = :org_id 
                ORDER BY id ASC";
        return $this->db->fetchAll($sql, ['record_id' => $saleOrderId, 'org_id' => $orgId]);
    }

    /** Upsert a single dimension item row */
    public function saveDimensionItem(array $dimData, int $orgId, int $userId): int
    {
        if (!empty($dimData['id'])) {
            $sql = "UPDATE `{DB::DIMENSION_ITEMS}` SET
                        pcs = :pcs, unit = :unit, length = :length, width = :width,
                        height = :height, formula = :formula, cbm = :cbm, volume = :volume,
                        updated_by = :updated_by, updated_at = NOW()
                    WHERE id = :id AND organization_id = :org_id";
            $this->db->execute($sql, [
                'id' => (int)$dimData['id'],
                'org_id' => $orgId,
                'pcs' => (int)($dimData['pcs'] ?? 0),
                'unit' => ($dimData['unit'] ?? 'cm'),
                'length' => (float)($dimData['length'] ?? 0),
                'width' => (float)($dimData['width'] ?? 0),
                'height' => (float)($dimData['height'] ?? 0),
                'formula' => (int)($dimData['formula'] ?? 6000),
                'cbm' => (float)($dimData['cbm'] ?? 0),
                'volume' => (float)($dimData['volume'] ?? 0),
                'updated_by' => $userId,
            ]);
            return (int)$dimData['id'];
        }

        $sql = "INSERT INTO `{DB::DIMENSION_ITEMS}` (
                    organization_id, quotation_id, module_type, record_id,
                    pcs, unit, length, width, height, formula, cbm, volume,
                    created_by, updated_by, created_at, updated_at
                ) VALUES (
                    :org_id, :quotation_id, :module_type, :record_id,
                    :pcs, :unit, :length, :width, :height, :formula, :cbm, :volume,
                    :created_by, :updated_by, NOW(), NOW()
                )";
        $insertId = (int)$this->db->insert($sql, [
            'org_id' => $orgId,
            'quotation_id' => ($dimData['module_type'] ?? 'sale_orders') === 'quotations' ? (int)$dimData['record_id'] : null,
            'module_type' => $dimData['module_type'] ?? 'sale_orders',
            'record_id' => (int)$dimData['record_id'],
            'pcs' => (int)($dimData['pcs'] ?? 0),
            'unit' => $dimData['unit'] ?? 'cm',
            'length' => (float)($dimData['length'] ?? 0),
            'width' => (float)($dimData['width'] ?? 0),
            'height' => (float)($dimData['height'] ?? 0),
            'formula' => (int)($dimData['formula'] ?? 6000),
            'cbm' => (float)($dimData['cbm'] ?? 0),
            'volume' => (float)($dimData['volume'] ?? 0),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        return $insertId;
    }

    /** Delete a single dimension item */
    public function deleteDimensionItem(int $dimId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::DIMENSION_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $dimId, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /** Delete all dimension items for a sale order */
    public function deleteDimensionItemsByRecord(int $recordId, string $moduleType, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::DIMENSION_ITEMS}` 
                WHERE module_type = :module_type AND record_id = :record_id AND organization_id = :org_id";
        $this->db->execute($sql, ['module_type' => $moduleType, 'record_id' => $recordId, 'org_id' => $orgId]);
        return true;
    }

    /** Get last invoice no for monthly prefix */
    public function getLastInvoiceNoForMonth(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT invoice_no FROM `{DB::INVOICES}` 
                WHERE invoice_no LIKE :prefix AND organization_id = :org_id 
                ORDER BY invoice_no DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '-%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['invoice_no'] : null;
    }

    // =========================================================================
    // Mappers
    // =========================================================================

    private function mapRowToSaleOrder(array $row): SaleOrder
    {
        return new SaleOrder(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            saleOrderNo: (string)$row['sale_order_no'],
            customerId: (int)$row['customer_id'],
            saleOrderStatus: (string)$row['sale_order_status'],
            saleOrderDate: (string)$row['sale_order_date'],
            expiryDate: (string)$row['expiry_date'],
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            expectedShipmentDate: $row['expected_shipment_date'] !== null && $row['expected_shipment_date'] !== '1970-01-01' ? (string)$row['expected_shipment_date'] : null,
            paymentTerm: (int)($row['payment_term'] ?? 0),
            shipmentType: $row['shipment_type'] !== null ? (string)$row['shipment_type'] : null,
            salesPerson: (int)($row['sales_person'] ?? 0),
            jobReferenceNo: $row['job_reference_no'] !== null ? (string)$row['job_reference_no'] : null,
            masterAwbNo: $row['master_awb_no'] !== null ? (string)$row['master_awb_no'] : null,
            mawbBol: $row['mawb_bol'] !== null ? (string)$row['mawb_bol'] : null,
            hwbHbol: $row['hwb_hbol'] !== null ? (string)$row['hwb_hbol'] : null,
            shipperId: (int)($row['shipper_id'] ?? 0),
            consigneeId: (int)($row['consignee_id'] ?? 0),
            originPort: (int)($row['origin_port'] ?? 0),
            originCountry: (int)($row['origin_country'] ?? 0),
            destinationPort: (int)($row['destination_port'] ?? 0),
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
            pdf: $row['pdf'] !== null ? (string)$row['pdf'] : null,
            invoiceId: $row['invoice_id'] !== null ? (int)$row['invoice_id'] : null,
            quotationId: $row['quotation_id'] !== null ? (int)$row['quotation_id'] : null,
        );
    }

    private function mapRowToSaleOrderItem(array $row): SaleOrderItem
    {
        return new SaleOrderItem(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            saleOrderId: (int)$row['sale_order_id'],
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
