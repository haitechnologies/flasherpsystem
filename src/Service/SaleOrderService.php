<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\SaleOrder;
use App\Model\SaleOrderItem;
use App\Repository\SaleOrderRepository;
use App\Repository\CustomerRepository;
use App\Core\Database;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * SaleOrder Service
 *
 * Implements business logic and validations for sale orders and line items.
 */
class SaleOrderService
{
    public const ALLOWED_STATUSES = ['draft', 'sent', 'approved', 'shipped', 'delivered', 'paid', 'partially_paid', 'overdue', 'cancelled', 'confirmed', 'invoiced'];

    private SaleOrderRepository $saleOrderRepo;
    private CustomerRepository $customerRepo;
    private Database $db;

    public function __construct(SaleOrderRepository $saleOrderRepo, CustomerRepository $customerRepo, Database $db)
    {
        $this->saleOrderRepo = $saleOrderRepo;
        $this->customerRepo = $customerRepo;
        $this->db = $db;
    }

    /**
     * Get SaleOrder by ID and organization
     *
     * @throws NotFoundException
     */
    public function getSaleOrder(int $id, int $orgId): SaleOrder
    {
        $saleOrder = $this->saleOrderRepo->find($id, $orgId);
        if ($saleOrder === null) {
            throw new NotFoundException("Sale Order with ID {$id} not found.");
        }
        return $saleOrder;
    }

    /**
     * Get items of a sale order
     */
    public function getSaleOrderItems(int $saleOrderId, int $orgId): array
    {
        return $this->saleOrderRepo->findItemsBySaleOrder($saleOrderId, $orgId);
    }

    /**
     * Create a new sale order
     *
     * @throws ValidationException
     */
    public function createSaleOrder(array $data, array $itemsData, int $orgId, int $userId): SaleOrder
    {
        $this->validateSaleOrderData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            // Auto generate SaleOrder number
            $prefix = 'FL-SO' . date('ym');
            $lastSoNo = $this->saleOrderRepo->getLastSaleOrderNoForMonth($prefix, $orgId);
            if ($lastSoNo !== null) {
                $lastSerial = (int) substr($lastSoNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $saleOrderNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            // Date parsing
            $saleOrderDate = $this->normalizeDate((string)($data['sale_order_date'] ?? date('Y-m-d')));
            $expiryDate = $this->normalizeDate((string)($data['expiry_date'] ?? ''));
            if ($expiryDate === '') {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = $this->normalizeDate((string)($data['expected_shipment_date'] ?? ''));
            if ($expectedShipmentDate === '') {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = (float)($data['grand_subtotal'] ?? 0.0);
            $grandTotal = (float)($data['grand_total'] ?? 0.0);

            $saleOrder = new SaleOrder(
                id: null,
                organizationId: $orgId,
                saleOrderNo: $saleOrderNo,
                customerId: (int)$data['customer_id'],
                saleOrderStatus: !empty($data['sale_order_status']) ? trim((string)$data['sale_order_status']) : 'draft',
                saleOrderDate: $saleOrderDate,
                expiryDate: $expiryDate,
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                warehouseId: !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: !empty($data['payment_term']) ? (int)$data['payment_term'] : 0,
                shipmentType: !empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null,
                salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : 0,
                jobReferenceNo: !empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null,
                masterAwbNo: !empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null,
                mawbBol: !empty($data['mawb_bol']) ? trim((string)$data['mawb_bol']) : null,
                hwbHbol: !empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null,
                shipperId: !empty($data['shipper_id']) ? (int)$data['shipper_id'] : 0,
                consigneeId: !empty($data['consignee_id']) ? (int)$data['consignee_id'] : 0,
                originPort: !empty($data['origin_port']) ? (int)$data['origin_port'] : 0,
                originCountry: !empty($data['origin_country']) ? (int)$data['origin_country'] : 0,
                destinationPort: !empty($data['destination_port']) ? (int)$data['destination_port'] : 0,
                destinationCountry: !empty($data['destination_country']) ? (int)$data['destination_country'] : 0,
                noOfPacks: !empty($data['no_of_packs']) ? (int)$data['no_of_packs'] : 0,
                grossWeight: !empty($data['gross_weight']) ? (float)$data['gross_weight'] : 0.0,
                chargeableWeight: !empty($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : 0.0,
                volume: !empty($data['volume']) ? (float)$data['volume'] : 0.0,
                cbm: !empty($data['cbm']) ? (float)$data['cbm'] : 0.0,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '',
                grandDiscountTypeValue: !empty($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : 0.0,
                grandDiscountAmount: !empty($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : 0.0,
                grandAfterDiscount: !empty($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : 0.0,
                customerNotes: !empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null,
                grandTax: !empty($data['grand_tax']) ? (float)$data['grand_tax'] : 0.0,
                grandTotal: $grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : true,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : true,
                createdBy: $userId,
                pdf: !empty($data['pdf']) ? trim((string)$data['pdf']) : null
            );

            $savedSaleOrder = $this->saleOrderRepo->save($saleOrder);
            $saleOrderId = $savedSaleOrder->id;

            if ($saleOrderId === null) {
                throw new \RuntimeException("Failed to insert sale order header.");
            }

            // Save line items
            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $item = new SaleOrderItem(
                    id: null,
                    organizationId: $orgId,
                    saleOrderId: $saleOrderId,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : 0.0,
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : 0.0,
                    total: isset($itemData['total']) ? (float)$itemData['total'] : 0.0,
                    discountType: !empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null,
                    discountTypeValue: !empty($itemData['discount_type_value']) ? (float)$itemData['discount_type_value'] : 0.0,
                    discountAmount: !empty($itemData['discount_amount']) ? (float)$itemData['discount_amount'] : 0.0,
                    createdBy: $userId
                );
                $this->saleOrderRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedSaleOrder;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing sale order
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateSaleOrder(int $id, array $data, array $itemsData, int $orgId, int $userId): SaleOrder
    {
        $saleOrder = $this->getSaleOrder($id, $orgId);
        $this->validateSaleOrderData($data, $orgId);

        $this->db->beginTransaction();
        try {
            // Date parsing
            $saleOrderDate = isset($data['sale_order_date']) ? $this->normalizeDate((string)$data['sale_order_date']) : $saleOrder->saleOrderDate;
            $expiryDate = isset($data['expiry_date']) ? $this->normalizeDate((string)$data['expiry_date']) : $saleOrder->expiryDate;
            if ($expiryDate === '') {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = isset($data['expected_shipment_date']) ? $this->normalizeDate((string)$data['expected_shipment_date']) : $saleOrder->expectedShipmentDate;
            if ($expectedShipmentDate === '') {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $saleOrder->grandSubtotal;
            $grandTotal = isset($data['grand_total']) ? (float)$data['grand_total'] : $saleOrder->grandTotal;

            $updatedSaleOrder = new SaleOrder(
                id: $saleOrder->id,
                organizationId: $saleOrder->organizationId,
                saleOrderNo: isset($data['sale_order_no']) ? trim((string)$data['sale_order_no']) : $saleOrder->saleOrderNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $saleOrder->customerId,
                saleOrderStatus: isset($data['sale_order_status']) ? trim((string)$data['sale_order_status']) : $saleOrder->saleOrderStatus,
                saleOrderDate: $saleOrderDate,
                expiryDate: $expiryDate,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $saleOrder->referenceNo,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $saleOrder->warehouseId,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: isset($data['payment_term']) ? (int)$data['payment_term'] : $saleOrder->paymentTerm,
                shipmentType: isset($data['shipment_type']) ? (!empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null) : $saleOrder->shipmentType,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $saleOrder->salesPerson,
                jobReferenceNo: isset($data['job_reference_no']) ? (!empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null) : $saleOrder->jobReferenceNo,
                masterAwbNo: isset($data['master_awb_no']) ? (!empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null) : $saleOrder->masterAwbNo,
                mawbBol: isset($data['mawb_bol']) ? (!empty($data['mawb_bol']) ? trim((string)$data['mawb_bol']) : null) : $saleOrder->mawbBol,
                hwbHbol: isset($data['hwb_hbol']) ? (!empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null) : $saleOrder->hwbHbol,
                shipperId: isset($data['shipper_id']) ? (int)$data['shipper_id'] : $saleOrder->shipperId,
                consigneeId: isset($data['consignee_id']) ? (int)$data['consignee_id'] : $saleOrder->consigneeId,
                originPort: isset($data['origin_port']) ? (int)$data['origin_port'] : $saleOrder->originPort,
                originCountry: isset($data['origin_country']) ? (int)$data['origin_country'] : $saleOrder->originCountry,
                destinationPort: isset($data['destination_port']) ? (int)$data['destination_port'] : $saleOrder->destinationPort,
                destinationCountry: isset($data['destination_country']) ? (int)$data['destination_country'] : $saleOrder->destinationCountry,
                noOfPacks: isset($data['no_of_packs']) ? (int)$data['no_of_packs'] : $saleOrder->noOfPacks,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $saleOrder->grossWeight,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $saleOrder->chargeableWeight,
                volume: isset($data['volume']) ? (float)$data['volume'] : $saleOrder->volume,
                cbm: isset($data['cbm']) ? (float)$data['cbm'] : $saleOrder->cbm,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $saleOrder->termsAndConditions,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : $saleOrder->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $saleOrder->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $saleOrder->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $saleOrder->grandAfterDiscount,
                customerNotes: isset($data['customer_notes']) ? (!empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null) : $saleOrder->customerNotes,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $saleOrder->grandTax,
                grandTotal: $grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : $saleOrder->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $saleOrder->isActive,
                createdAt: $saleOrder->createdAt,
                createdBy: $saleOrder->createdBy,
                updatedBy: $userId,
                pdf: isset($data['pdf']) ? trim((string)$data['pdf']) : $saleOrder->pdf
            );

            $savedSaleOrder = $this->saleOrderRepo->save($updatedSaleOrder);

            // Fetch existing items to manage changes (updates, inserts, deletions)
            $existingItems = $this->saleOrderRepo->findItemsBySaleOrder($id, $orgId);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $itemId = !empty($itemData['id']) ? (int)$itemData['id'] : null;
                if ($itemId !== null) {
                    $incomingIds[] = $itemId;
                }

                $item = new SaleOrderItem(
                    id: $itemId,
                    organizationId: $orgId,
                    saleOrderId: $id,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : 0.0,
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : 0.0,
                    total: isset($itemData['total']) ? (float)$itemData['total'] : 0.0,
                    discountType: !empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null,
                    discountTypeValue: !empty($itemData['discount_type_value']) ? (float)$itemData['discount_type_value'] : 0.0,
                    discountAmount: !empty($itemData['discount_amount']) ? (float)$itemData['discount_amount'] : 0.0,
                    createdBy: $userId
                );
                $this->saleOrderRepo->saveItem($item);
            }

            // Identify and delete removed items
            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->saleOrderRepo->deleteItemsByIds($deletedIds, $id, $orgId);
            }

            $this->db->commit();

            return $savedSaleOrder;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * List all sale orders in an organization
     */
    public function list(int $orgId): array
    {
        return $this->saleOrderRepo->findAll($orgId);
    }

    /**
     * Delete a sale order and its items
     */
    public function deleteSaleOrder(int $id, int $orgId): bool
    {
        $saleOrder = $this->getSaleOrder($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->saleOrderRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clone a sale order
     */
    public function cloneSaleOrder(int $id, int $orgId, int $userId): SaleOrder
    {
        $saleOrder = $this->getSaleOrder($id, $orgId);
        $items = $this->getSaleOrderItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            // Auto generate new SaleOrder number
            $prefix = 'FL-SO' . date('ym');
            $lastSoNo = $this->saleOrderRepo->getLastSaleOrderNoForMonth($prefix, $orgId);
            if ($lastSoNo !== null) {
                $lastSerial = (int) substr($lastSoNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $saleOrderNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $cloned = new SaleOrder(
                id: null,
                organizationId: $orgId,
                saleOrderNo: $saleOrderNo,
                customerId: $saleOrder->customerId,
                saleOrderStatus: 'draft',
                saleOrderDate: date('Y-m-d'),
                expiryDate: date('Y-m-d'),
                referenceNo: $saleOrder->referenceNo,
                warehouseId: $saleOrder->warehouseId,
                expectedShipmentDate: $saleOrder->expectedShipmentDate,
                paymentTerm: $saleOrder->paymentTerm,
                shipmentType: $saleOrder->shipmentType,
                salesPerson: $saleOrder->salesPerson,
                jobReferenceNo: $saleOrder->jobReferenceNo,
                masterAwbNo: $saleOrder->masterAwbNo,
                shipperId: $saleOrder->shipperId,
                consigneeId: $saleOrder->consigneeId,
                originPort: $saleOrder->originPort,
                originCountry: $saleOrder->originCountry,
                destinationPort: $saleOrder->destinationPort,
                destinationCountry: $saleOrder->destinationCountry,
                noOfPacks: $saleOrder->noOfPacks,
                grossWeight: $saleOrder->grossWeight,
                chargeableWeight: $saleOrder->chargeableWeight,
                volume: $saleOrder->volume,
                cbm: $saleOrder->cbm,
                termsAndConditions: $saleOrder->termsAndConditions,
                grandSubtotal: $saleOrder->grandSubtotal,
                grandDiscountType: $saleOrder->grandDiscountType,
                grandDiscountTypeValue: $saleOrder->grandDiscountTypeValue,
                grandDiscountAmount: $saleOrder->grandDiscountAmount,
                grandAfterDiscount: $saleOrder->grandAfterDiscount,
                customerNotes: $saleOrder->customerNotes,
                grandTax: $saleOrder->grandTax,
                grandTotal: $saleOrder->grandTotal,
                publish: $saleOrder->publish,
                isActive: $saleOrder->isActive,
                createdBy: $userId
            );

            $savedCloned = $this->saleOrderRepo->save($cloned);
            $newSaleOrderId = $savedCloned->id;

            if ($newSaleOrderId === null) {
                throw new \RuntimeException("Failed to clone sale order header.");
            }

            foreach ($items as $item) {
                $clonedItem = new SaleOrderItem(
                    id: null,
                    organizationId: $orgId,
                    saleOrderId: $newSaleOrderId,
                    service: $item->service,
                    description: $item->description,
                    qty: $item->qty,
                    rate: $item->rate,
                    subTotal: $item->subTotal,
                    tax: $item->tax,
                    taxAmount: $item->taxAmount,
                    total: $item->total,
                    discountType: $item->discountType,
                    discountTypeValue: $item->discountTypeValue,
                    discountAmount: $item->discountAmount,
                    createdBy: $userId
                );
                $this->saleOrderRepo->saveItem($clonedItem);
            }

            $this->db->commit();

            return $savedCloned;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Convert a Quotation to a Sale Order, copying items and dimensions.
     *
     * @return SaleOrder The newly created sale order
     * @throws NotFoundException|ValidationException|\Throwable
     */
    public function convertFromQuotation(int $quotationId, int $orgId, int $userId): SaleOrder
    {
        $this->db->beginTransaction();
        try {
            $quotation = $this->db->fetchOne(
                "SELECT * FROM `" . \App\Core\DB::QUOTATIONS . "` WHERE id = :id AND organization_id = :org_id",
                ['id' => $quotationId, 'org_id' => $orgId]
            );
            if ($quotation === null) {
                throw new NotFoundException("Quotation with ID {$quotationId} not found.");
            }

            $quotationItems = $this->db->fetchAll(
                "SELECT * FROM `" . \App\Core\DB::QUOTATION_ITEMS . "` WHERE quotation_id = :qid AND organization_id = :org_id ORDER BY id ASC",
                ['qid' => $quotationId, 'org_id' => $orgId]
            );

            $prefix = 'FL-SO' . date('ym');
            $lastNo = $this->saleOrderRepo->getLastSaleOrderNoForMonth($prefix, $orgId);
            $serial = $lastNo !== null ? ((int)substr($lastNo, -4)) + 1 : 1;
            $saleOrderNo = $prefix . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);

            $soData = [
                'sale_order_no' => $saleOrderNo,
                'customer_id' => $quotation['customer_id'] ?? 0,
                'warehouse_id' => $quotation['warehouse_id'] ?? 0,
                'subject' => $quotation['subject'] ?? '',
                'reference_no' => $quotation['reference_no'] ?? '',
                'sale_order_date' => date('Y-m-d'),
                'expiry_date' => $quotation['expiry_date'] ?? '1970-01-01',
                'expected_shipment_date' => $quotation['expected_shipment_date'] ?? '1970-01-01',
                'payment_term' => $quotation['payment_term'] ?? 0,
                'shipment_type' => $quotation['shipment_type'] ?? '',
                'sales_person' => $quotation['sales_person'] ?? 0,
                'job_reference_no' => $quotation['job_reference_no'] ?? '',
                'master_awb_no' => $quotation['master_awb_no'] ?? '',
                'mawb_bol' => $quotation['mawb_bol'] ?? '',
                'hwb_hbol' => $quotation['hwb_hbol'] ?? '',
                'shipper_id' => $quotation['shipper_id'] ?? 0,
                'consignee_id' => $quotation['consignee_id'] ?? 0,
                'origin_port' => $quotation['origin_port'] ?? 0,
                'origin_country' => $quotation['origin_country'] ?? 0,
                'destination_port' => $quotation['destination_port'] ?? 0,
                'destination_country' => $quotation['destination_country'] ?? 0,
                'no_of_packs' => $quotation['no_of_packs'] ?? 0,
                'gross_weight' => $quotation['gross_weight'] ?? 0,
                'chargeable_weight' => $quotation['chargeable_weight'] ?? 0,
                'volume' => $quotation['volume'] ?? 0,
                'cbm' => $quotation['cbm'] ?? 0,
                'grand_subtotal' => $quotation['grand_subtotal'] ?? '0.00',
                'grand_discount_type' => $quotation['grand_discount_type'] ?? '',
                'grand_discount_type_value' => $quotation['grand_discount_type_value'] ?? '0.00',
                'grand_discount_amount' => $quotation['grand_discount_amount'] ?? '0.00',
                'grand_after_discount' => $quotation['grand_after_discount'] ?? '0.00',
                'grand_tax' => $quotation['grand_tax'] ?? '0.00',
                'grand_total' => $quotation['grand_total'] ?? '0.00',
                'customer_notes' => $quotation['customer_notes'] ?? '',
                'terms_and_conditions' => $quotation['terms_and_conditions'] ?? '',
                'sale_order_status' => 'draft',
                'quotation_id' => $quotationId,
            ];

            $saleOrder = new SaleOrder(
                id: null,
                organizationId: $orgId,
                saleOrderNo: $saleOrderNo,
                customerId: (int)$soData['customer_id'],
                saleOrderStatus: 'draft',
                saleOrderDate: $soData['sale_order_date'],
                expiryDate: $soData['expiry_date'],
                referenceNo: (string)$soData['reference_no'],
                warehouseId: (int)$soData['warehouse_id'],
                expectedShipmentDate: $soData['expected_shipment_date'],
                paymentTerm: (int)$soData['payment_term'],
                shipmentType: (string)$soData['shipment_type'],
                salesPerson: (int)$soData['sales_person'],
                jobReferenceNo: (string)$soData['job_reference_no'],
                masterAwbNo: (string)$soData['master_awb_no'],
                mawbBol: (string)$soData['mawb_bol'],
                hwbHbol: (string)$soData['hwb_hbol'],
                shipperId: (int)$soData['shipper_id'],
                consigneeId: (int)$soData['consignee_id'],
                originPort: (int)$soData['origin_port'],
                originCountry: (int)$soData['origin_country'],
                destinationPort: (int)$soData['destination_port'],
                destinationCountry: (int)$soData['destination_country'],
                noOfPacks: (int)$soData['no_of_packs'],
                grossWeight: (float)$soData['gross_weight'],
                chargeableWeight: (float)$soData['chargeable_weight'],
                volume: (float)$soData['volume'],
                cbm: (float)$soData['cbm'],
                grandSubtotal: (float)$soData['grand_subtotal'],
                grandDiscountType: (string)$soData['grand_discount_type'],
                grandDiscountTypeValue: (float)$soData['grand_discount_type_value'],
                grandDiscountAmount: (float)$soData['grand_discount_amount'],
                grandAfterDiscount: (float)$soData['grand_after_discount'],
                grandTax: (float)$soData['grand_tax'],
                grandTotal: (float)$soData['grand_total'],
                customerNotes: (string)$soData['customer_notes'],
                termsAndConditions: (string)$soData['terms_and_conditions'],
                publish: true,
                isActive: true,
                createdAt: date('Y-m-d H:i:s'),
                updatedAt: date('Y-m-d H:i:s'),
                updatedBy: $userId,
                createdBy: $userId,
                pdf: null,
                quotationId: $quotationId,
                invoiceId: null,
            );

            $savedSO = $this->saleOrderRepo->save($saleOrder);

            foreach ($quotationItems as $item) {
                $soItem = new SaleOrderItem(
                    id: null,
                    organizationId: $orgId,
                    saleOrderId: $savedSO->id,
                    service: (int)($item['service'] ?? 0),
                    description: $item['description'] ?? '',
                    qty: (float)($item['qty'] ?? 1),
                    rate: (float)($item['rate'] ?? 0),
                    subTotal: (float)($item['sub_total'] ?? 0),
                    tax: (float)($item['tax'] ?? 0),
                    taxAmount: (float)($item['tax_amount'] ?? 0),
                    total: (float)($item['total'] ?? 0),
                    discountType: (string)($item['discount_type'] ?? ''),
                    discountTypeValue: (float)($item['discount_type_value'] ?? 0),
                    discountAmount: (float)($item['discount_amount'] ?? 0),
                    createdAt: date('Y-m-d H:i:s'),
                    updatedAt: date('Y-m-d H:i:s'),
                    updatedBy: $userId,
                    createdBy: $userId,
                );
                $this->saleOrderRepo->saveItem($soItem);
            }

            $dimItems = $this->db->fetchAll(
                "SELECT * FROM `" . \App\Core\DB::DIMENSION_ITEMS . "` WHERE module_type = 'quotations' AND record_id = :qid AND organization_id = :org_id ORDER BY id ASC",
                ['qid' => $quotationId, 'org_id' => $orgId]
            );
            foreach ($dimItems as $dim) {
                $this->db->execute(
                    "INSERT INTO `" . \App\Core\DB::DIMENSION_ITEMS . "` (organization_id, quotation_id, module_type, record_id, pcs, unit, length, width, height, formula, cbm, volume, created_by, updated_by, created_at, updated_at) 
                     VALUES (:org_id, :quotation_id, 'sale_orders', :record_id, :pcs, :unit, :length, :width, :height, :formula, :cbm, :volume, :created_by, :updated_by, NOW(), NOW())",
                    [
                        'org_id' => $orgId,
                        'quotation_id' => null,
                        'record_id' => $savedSO->id,
                        'pcs' => (int)($dim['pcs'] ?? 0),
                        'unit' => $dim['unit'] ?? 'cm',
                        'length' => (float)($dim['length'] ?? 0),
                        'width' => (float)($dim['width'] ?? 0),
                        'height' => (float)($dim['height'] ?? 0),
                        'formula' => (int)($dim['formula'] ?? 6000),
                        'cbm' => (float)($dim['cbm'] ?? 0),
                        'volume' => (float)($dim['volume'] ?? 0),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }

            $this->db->execute(
                "UPDATE `" . \App\Core\DB::QUOTATIONS . "` SET sale_order_id = :so_id, quotation_status = 'converted_to_so' WHERE id = :qid AND organization_id = :org_id",
                ['so_id' => $savedSO->id, 'qid' => $quotationId, 'org_id' => $orgId]
            );

            $this->db->commit();
            return $this->getSaleOrder($savedSO->id, $orgId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Convert a Sale Order to an Invoice, copying items and dimensions.
     *
     * @return array{invoice_id: int, invoice_no: string}
     * @throws NotFoundException|ValidationException|\Throwable
     */
    public function convertToInvoice(int $id, int $orgId, int $userId): array
    {
        $saleOrder = $this->getSaleOrder($id, $orgId);
        if ($saleOrder->saleOrderStatus === 'invoiced') {
            throw new ValidationException(['status' => 'This Sale Order has already been converted to an invoice.']);
        }

        $this->db->beginTransaction();
        try {
            $prefix = 'FL-IN' . date('ym');
            $lastNo = $this->saleOrderRepo->getLastInvoiceNoForMonth($prefix, $orgId);
            $serial = $lastNo !== null ? ((int)substr($lastNo, -4)) + 1 : 1;
            $invoiceNo = $prefix . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);

            $this->db->execute(
                "INSERT INTO `" . \App\Core\DB::INVOICES . "` (organization_id, customer_id, warehouse_id, subject, reference_no, sale_order_id, invoice_no, invoice_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, invoice_status, is_active, created_at, updated_at, created_by)
                 VALUES (:org_id, :customer_id, :warehouse_id, :subject, :reference_no, :sale_order_id, :invoice_no, NOW(), NOW(), :grand_subtotal, :grand_discount_type, :grand_discount_type_value, :grand_discount_amount, :grand_after_discount, :grand_tax, :grand_total, :customer_notes, :terms_and_conditions, 'draft', 1, NOW(), NOW(), :created_by)",
                [
                    'org_id' => $orgId,
                    'customer_id' => $saleOrder->customerId,
                    'warehouse_id' => $saleOrder->warehouseId,
                    'subject' => $saleOrder->jobReferenceNo,
                    'reference_no' => $saleOrder->referenceNo,
                    'sale_order_id' => $id,
                    'invoice_no' => $invoiceNo,
                    'grand_subtotal' => $saleOrder->grandSubtotal,
                    'grand_discount_type' => $saleOrder->grandDiscountType,
                    'grand_discount_type_value' => $saleOrder->grandDiscountTypeValue,
                    'grand_discount_amount' => $saleOrder->grandDiscountAmount,
                    'grand_after_discount' => $saleOrder->grandAfterDiscount,
                    'grand_tax' => $saleOrder->grandTax,
                    'grand_total' => $saleOrder->grandTotal,
                    'customer_notes' => $saleOrder->customerNotes,
                    'terms_and_conditions' => $saleOrder->termsAndConditions,
                    'created_by' => $userId,
                ]
            );
            $newInvoiceId = (int)$this->db->getConnection()->lastInsertId();

            $soItems = $this->getSaleOrderItems($id, $orgId);
            foreach ($soItems as $item) {
                $this->db->execute(
                    "INSERT INTO `" . \App\Core\DB::INVOICE_ITEMS . "` (organization_id, invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by)
                     VALUES (:org_id, :invoice_id, :service, :description, :qty, :rate, :discount_type, :discount_type_value, :discount_amount, :tax, :tax_amount, :sub_total, :total, NOW(), NOW(), :created_by)",
                    [
                        'org_id' => $orgId,
                        'invoice_id' => $newInvoiceId,
                        'service' => $item->service,
                        'description' => $item->description,
                        'qty' => $item->qty,
                        'rate' => $item->rate,
                        'discount_type' => $item->discountType,
                        'discount_type_value' => $item->discountTypeValue,
                        'discount_amount' => $item->discountAmount,
                        'tax' => $item->tax,
                        'tax_amount' => $item->taxAmount,
                        'sub_total' => $item->subTotal,
                        'total' => $item->total,
                        'created_by' => $userId,
                    ]
                );
            }

            $dimItems = $this->saleOrderRepo->findDimensionItemsBySaleOrder($id, $orgId);
            foreach ($dimItems as $dim) {
                $this->db->execute(
                    "INSERT INTO `" . \App\Core\DB::DIMENSION_ITEMS . "` (organization_id, quotation_id, module_type, record_id, pcs, unit, length, width, height, formula, cbm, volume, created_by, updated_by, created_at, updated_at)
                     VALUES (:org_id, :quotation_id, 'invoices', :record_id, :pcs, :unit, :length, :width, :height, :formula, :cbm, :volume, :created_by, :updated_by, NOW(), NOW())",
                    [
                        'org_id' => $orgId,
                        'quotation_id' => null,
                        'record_id' => $newInvoiceId,
                        'pcs' => (int)($dim['pcs'] ?? 0),
                        'unit' => $dim['unit'] ?? 'cm',
                        'length' => (float)($dim['length'] ?? 0),
                        'width' => (float)($dim['width'] ?? 0),
                        'height' => (float)($dim['height'] ?? 0),
                        'formula' => (int)($dim['formula'] ?? 6000),
                        'cbm' => (float)($dim['cbm'] ?? 0),
                        'volume' => (float)($dim['volume'] ?? 0),
                        'created_by' => $userId,
                        'updated_by' => $userId,
                    ]
                );
            }

            $this->saleOrderRepo->updateStatus($id, 'invoiced', $orgId);
            $this->db->execute(
                "UPDATE `" . \App\Core\DB::SALE_ORDERS . "` SET invoice_id = :inv_id WHERE id = :id AND organization_id = :org_id",
                ['inv_id' => $newInvoiceId, 'id' => $id, 'org_id' => $orgId]
            );

            $this->db->commit();
            return ['invoice_id' => $newInvoiceId, 'invoice_no' => $invoiceNo];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function normalizeDate(string $date): string
    {
        $date = trim($date);
        if ($date === '' || $date === '1970-01-01') {
            return $date;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        $converted = \App\Helper\DateHelper::toDbDate($date);
        return $converted !== '' ? $converted : $date;
    }

    /**
     * Update status of a sale order
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $allowedStatuses = self::ALLOWED_STATUSES;
        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException(['status' => "Invalid status: {$status}"]);
        }
        return $this->saleOrderRepo->updateStatus($id, $status, $orgId);
    }

    /**
     * Update sale order PDF path
     */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        return $this->saleOrderRepo->updatePdf($id, $pdfFilename, $orgId);
    }

    /**
     * Validate SaleOrder fields
     *
     * @throws ValidationException
     */
    private function validateSaleOrderData(array $data, int $orgId): void
    {
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select') {
            throw new ValidationException(['customer_id' => "Please select Customer."]);
        }
        if (empty($data['sale_order_date'])) {
            throw new ValidationException(['sale_order_date' => "Please select Sale Order Date."]);
        }

        if (!empty($data['sale_order_status']) && !in_array($data['sale_order_status'], self::ALLOWED_STATUSES, true)) {
            throw new ValidationException(['sale_order_status' => "Invalid sale order status."]);
        }

        // Verify customer exists in organization
        $customerId = (int)$data['customer_id'];
        $customer = $this->customerRepo->find($customerId, $orgId);
        if ($customer === null) {
            throw new ValidationException(['customer_id' => "Selected customer does not exist in your organization."]);
        }
    }
}
