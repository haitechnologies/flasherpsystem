<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Quotation;
use App\Model\QuotationItem;
use App\Repository\QuotationRepository;
use App\Repository\CustomerRepository;
use App\Core\Database;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * Quotation Service
 *
 * Implements business logic and validations for quotations and line items.
 */
class QuotationService
{
    private QuotationRepository $quotationRepo;
    private CustomerRepository $customerRepo;
    private Database $db;

    public function __construct(QuotationRepository $quotationRepo, CustomerRepository $customerRepo, Database $db)
    {
        $this->quotationRepo = $quotationRepo;
        $this->customerRepo = $customerRepo;
        $this->db = $db;
    }

    /**
     * Get Quotation by ID and organization
     *
     * @throws NotFoundException
     */
    public function getQuotation(int $id, int $orgId): Quotation
    {
        $quotation = $this->quotationRepo->find($id, $orgId);
        if ($quotation === null) {
            throw new NotFoundException("Quotation with ID {$id} not found.");
        }
        return $quotation;
    }

    /**
     * Get items of a quotation
     */
    public function getQuotationItems(int $quotationId, int $orgId): array
    {
        return $this->quotationRepo->findItemsByQuotation($quotationId, $orgId);
    }

    /**
     * Create a new quotation
     *
     * @throws ValidationException
     */
    public function createQuotation(array $data, array $itemsData, int $orgId, int $userId): Quotation
    {
        $this->validateQuotationData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            // Auto generate Quotation number
            $prefix = 'FL-QT' . date('ym');
            $lastQtNo = $this->quotationRepo->getLastQuotationNoForMonth($prefix, $orgId);
            if ($lastQtNo !== null) {
                $lastSerial = (int) substr($lastQtNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $quotationNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            // Date parsing
            $quotationDate = (string)($data['quotation_date'] ?? date('Y-m-d'));
            if (strpos($quotationDate, '-') === false) {
                $quotationDate = \App\Helper\DateHelper::toDbDate($quotationDate) ?: $quotationDate;
            }
            $expiryDate = (string)($data['expiry_date'] ?? '');
            if (!empty($expiryDate)) {
                if (strpos($expiryDate, '-') === false) {
                    $expiryDate = \App\Helper\DateHelper::toDbDate($expiryDate) ?: $expiryDate;
                }
            } else {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = (string)($data['expected_shipment_date'] ?? '');
            if (!empty($expectedShipmentDate)) {
                if (strpos($expectedShipmentDate, '-') === false) {
                    $expectedShipmentDate = \App\Helper\DateHelper::toDbDate($expectedShipmentDate) ?: $expectedShipmentDate;
                }
            } else {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = 0.0;
            $grandTax = 0.0;
            foreach ($itemsData as $i => $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $qty = (float)($itemData['qty'] ?? 1);
                $rate = (float)($itemData['rate'] ?? 0);
                $taxPct = (float)($itemData['tax'] ?? 0);
                $subTotal = $qty * $rate;
                $taxAmount = $subTotal * $taxPct / 100.0;
                $itemsData[$i]['sub_total'] = $subTotal;
                $itemsData[$i]['tax_amount'] = $taxAmount;
                $itemsData[$i]['total'] = $subTotal + $taxAmount;
                $grandSubtotal += $subTotal;
                $grandTax += $taxAmount;
            }
            $discountType = (string)($data['grand_discount_type'] ?? '0.00');
            $discountTypeValue = (float)($data['grand_discount_type_value'] ?? 0);
            $grandDiscountAmount = 0.0;
            if (($discountType === 'percentage' || $discountType === 'percent') && $discountTypeValue > 0) {
                $grandDiscountAmount = $grandSubtotal * $discountTypeValue / 100.0;
            } elseif ($discountType === 'fixed' && $discountTypeValue > 0) {
                $grandDiscountAmount = $discountTypeValue;
            }
            $grandAfterDiscount = $grandSubtotal - $grandDiscountAmount;
            $data['grand_subtotal'] = round($grandSubtotal, 2);
            $data['grand_discount_amount'] = round($grandDiscountAmount, 2);
            $data['grand_after_discount'] = round(max(0, $grandAfterDiscount), 2);
            $data['grand_tax'] = round($grandTax, 2);
            $data['grand_total'] = round(max(0, $grandAfterDiscount) + $grandTax, 2);

            $grandSubtotal = (float)($data['grand_subtotal'] ?? 0.0);
            $grandTotal = (float)($data['grand_total'] ?? 0.0);

            $quotation = new Quotation(
                id: null,
                organizationId: $orgId,
                quotationNo: $quotationNo,
                customerId: (int)$data['customer_id'],
                quotationStatus: !empty($data['quotation_status']) ? trim((string)$data['quotation_status']) : 'draft',
                quotationDate: $quotationDate,
                expiryDate: $expiryDate,
                leadId: !empty($data['lead_id']) ? (int)$data['lead_id'] : 0,
                warehouseId: !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: !empty($data['payment_term']) ? (int)$data['payment_term'] : 0,
                shipmentType: !empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null,
                salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : 0,
                jobReferenceNo: !empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null,
                masterAwbNo: !empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null,
                hwbHbol: !empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null,
                shipper: !empty($data['shipper']) ? (int)$data['shipper'] : 0,
                consignee: !empty($data['consignee']) ? (int)$data['consignee'] : 0,
                origin: !empty($data['origin']) ? (int)$data['origin'] : 0,
                originCountry: !empty($data['origin_country']) ? (int)$data['origin_country'] : 0,
                destination: !empty($data['destination']) ? (int)$data['destination'] : 0,
                destinationCountry: !empty($data['destination_country']) ? (int)$data['destination_country'] : 0,
                noOfPacks: !empty($data['no_of_packs']) ? (int)$data['no_of_packs'] : 0,
                grossWeight: !empty($data['gross_weight']) ? (float)$data['gross_weight'] : 0.0,
                chargeableWeight: !empty($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : 0.0,
                volume: !empty($data['volume']) ? (float)$data['volume'] : 0.0,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00',
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

            $savedQuotation = $this->quotationRepo->save($quotation);
            $quotationId = $savedQuotation->id;

            if ($quotationId === null) {
                throw new \RuntimeException("Failed to insert quotation header.");
            }

            // Save line items
            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $item = new QuotationItem(
                    id: null,
                    organizationId: $orgId,
                    quotationId: $quotationId,
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
                $this->quotationRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedQuotation;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing quotation
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateQuotation(int $id, array $data, array $itemsData, int $orgId, int $userId): Quotation
    {
            $quotation = $this->getQuotation($id, $orgId);
            $this->validateQuotationData($data, $orgId);

            if (empty($itemsData)) {
                throw new ValidationException(['items' => "No items added. Please add at least one item."]);
            }

            $this->db->beginTransaction();
        try {
            // Date parsing
            $quotationDate = isset($data['quotation_date']) ? (string)$data['quotation_date'] : $quotation->quotationDate;
            if (strpos($quotationDate, '-') === false) {
                $quotationDate = \App\Helper\DateHelper::toDbDate($quotationDate) ?: $quotationDate;
            }
            $expiryDate = isset($data['expiry_date']) ? (string)$data['expiry_date'] : $quotation->expiryDate;
            if (!empty($expiryDate)) {
                if (strpos($expiryDate, '-') === false) {
                    $expiryDate = \App\Helper\DateHelper::toDbDate($expiryDate) ?: $expiryDate;
                }
            } else {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = isset($data['expected_shipment_date']) ? (string)$data['expected_shipment_date'] : $quotation->expectedShipmentDate;
            if (!empty($expectedShipmentDate)) {
                if (strpos($expectedShipmentDate, '-') === false) {
                    $expectedShipmentDate = \App\Helper\DateHelper::toDbDate($expectedShipmentDate) ?: $expectedShipmentDate;
                }
            } else {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = 0.0;
            $grandTax = 0.0;
            foreach ($itemsData as $i => $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $qty = (float)($itemData['qty'] ?? 1);
                $rate = (float)($itemData['rate'] ?? 0);
                $taxPct = (float)($itemData['tax'] ?? 0);
                $subTotal = $qty * $rate;
                $taxAmount = $subTotal * $taxPct / 100.0;
                $itemsData[$i]['sub_total'] = $subTotal;
                $itemsData[$i]['tax_amount'] = $taxAmount;
                $itemsData[$i]['total'] = $subTotal + $taxAmount;
                $grandSubtotal += $subTotal;
                $grandTax += $taxAmount;
            }
            $discountType = (string)($data['grand_discount_type'] ?? '0.00');
            $discountTypeValue = (float)($data['grand_discount_type_value'] ?? 0);
            $grandDiscountAmount = 0.0;
            if (($discountType === 'percentage' || $discountType === 'percent') && $discountTypeValue > 0) {
                $grandDiscountAmount = $grandSubtotal * $discountTypeValue / 100.0;
            } elseif ($discountType === 'fixed' && $discountTypeValue > 0) {
                $grandDiscountAmount = $discountTypeValue;
            }
            $grandAfterDiscount = $grandSubtotal - $grandDiscountAmount;
            $data['grand_subtotal'] = round($grandSubtotal, 2);
            $data['grand_discount_amount'] = round($grandDiscountAmount, 2);
            $data['grand_after_discount'] = round(max(0, $grandAfterDiscount), 2);
            $data['grand_tax'] = round($grandTax, 2);
            $data['grand_total'] = round(max(0, $grandAfterDiscount) + $grandTax, 2);

            $grandSubtotal = isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $quotation->grandSubtotal;
            $grandTotal = isset($data['grand_total']) ? (float)$data['grand_total'] : $quotation->grandTotal;

            $updatedQuotation = new Quotation(
                id: $quotation->id,
                organizationId: $quotation->organizationId,
                quotationNo: isset($data['quotation_no']) ? trim((string)$data['quotation_no']) : $quotation->quotationNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $quotation->customerId,
                quotationStatus: isset($data['quotation_status']) ? trim((string)$data['quotation_status']) : $quotation->quotationStatus,
                quotationDate: $quotationDate,
                expiryDate: $expiryDate,
                leadId: isset($data['lead_id']) ? (int)$data['lead_id'] : $quotation->leadId,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $quotation->warehouseId,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: isset($data['payment_term']) ? (int)$data['payment_term'] : $quotation->paymentTerm,
                shipmentType: isset($data['shipment_type']) ? (!empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null) : $quotation->shipmentType,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $quotation->salesPerson,
                jobReferenceNo: isset($data['job_reference_no']) ? (!empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null) : $quotation->jobReferenceNo,
                masterAwbNo: isset($data['master_awb_no']) ? (!empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null) : $quotation->masterAwbNo,
                hwbHbol: isset($data['hwb_hbol']) ? (!empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null) : $quotation->hwbHbol,
                shipper: isset($data['shipper']) ? (int)$data['shipper'] : $quotation->shipper,
                consignee: isset($data['consignee']) ? (int)$data['consignee'] : $quotation->consignee,
                origin: isset($data['origin']) ? (int)$data['origin'] : $quotation->origin,
                originCountry: isset($data['origin_country']) ? (int)$data['origin_country'] : $quotation->originCountry,
                destination: isset($data['destination']) ? (int)$data['destination'] : $quotation->destination,
                destinationCountry: isset($data['destination_country']) ? (int)$data['destination_country'] : $quotation->destinationCountry,
                noOfPacks: isset($data['no_of_packs']) ? (int)$data['no_of_packs'] : $quotation->noOfPacks,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $quotation->grossWeight,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $quotation->chargeableWeight,
                volume: isset($data['volume']) ? (float)$data['volume'] : $quotation->volume,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $quotation->termsAndConditions,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : $quotation->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $quotation->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $quotation->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $quotation->grandAfterDiscount,
                customerNotes: isset($data['customer_notes']) ? (!empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null) : $quotation->customerNotes,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $quotation->grandTax,
                grandTotal: $grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : $quotation->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $quotation->isActive,
                createdAt: $quotation->createdAt,
                createdBy: $quotation->createdBy,
                updatedBy: $userId,
                pdf: isset($data['pdf']) ? trim((string)$data['pdf']) : $quotation->pdf
            );

            $savedQuotation = $this->quotationRepo->save($updatedQuotation);

            // Fetch existing items to manage changes (updates, inserts, deletions)
            $existingItems = $this->quotationRepo->findItemsByQuotation($id, $orgId);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $itemId = !empty($itemData['id']) ? (int)$itemData['id'] : null;
                if ($itemId !== null) {
                    if (!in_array($itemId, $existingIds, true)) {
                        throw new ValidationException(['items' => "Invalid item reference."]);
                    }
                    $incomingIds[] = $itemId;
                }

                $item = new QuotationItem(
                    id: $itemId,
                    organizationId: $orgId,
                    quotationId: $id,
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
                $this->quotationRepo->saveItem($item);
            }

            // Identify and delete removed items
            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->quotationRepo->deleteItemsByIds($deletedIds, $id, $orgId);
            }

            $this->db->commit();

            return $savedQuotation;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * List all quotations in an organization
     */
    public function list(int $orgId): array
    {
        return $this->quotationRepo->findAll($orgId);
    }

    /**
     * Delete a quotation and its items
     */
    public function deleteQuotation(int $id, int $orgId): bool
    {
        $quotation = $this->getQuotation($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->quotationRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clone a quotation
     */
    public function cloneQuotation(int $id, int $orgId, int $userId): Quotation
    {
        $quotation = $this->getQuotation($id, $orgId);
        $items = $this->getQuotationItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            // Auto generate new Quotation number
            $prefix = 'FL-QT' . date('ym');
            $lastQtNo = $this->quotationRepo->getLastQuotationNoForMonth($prefix, $orgId);
            if ($lastQtNo !== null) {
                $lastSerial = (int) substr($lastQtNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $quotationNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $cloned = new Quotation(
                id: null,
                organizationId: $orgId,
                quotationNo: $quotationNo,
                customerId: $quotation->customerId,
                quotationStatus: 'draft',
                quotationDate: date('Y-m-d'),
                expiryDate: $quotation->expiryDate,
                leadId: $quotation->leadId,
                warehouseId: $quotation->warehouseId,
                expectedShipmentDate: $quotation->expectedShipmentDate,
                paymentTerm: $quotation->paymentTerm,
                shipmentType: $quotation->shipmentType,
                salesPerson: $quotation->salesPerson,
                jobReferenceNo: $quotation->jobReferenceNo,
                masterAwbNo: $quotation->masterAwbNo,
                hwbHbol: $quotation->hwbHbol,
                shipper: $quotation->shipper,
                consignee: $quotation->consignee,
                origin: $quotation->origin,
                originCountry: $quotation->originCountry,
                destination: $quotation->destination,
                destinationCountry: $quotation->destinationCountry,
                noOfPacks: $quotation->noOfPacks,
                grossWeight: $quotation->grossWeight,
                chargeableWeight: $quotation->chargeableWeight,
                volume: $quotation->volume,
                termsAndConditions: $quotation->termsAndConditions,
                grandSubtotal: $quotation->grandSubtotal,
                grandDiscountType: $quotation->grandDiscountType,
                grandDiscountTypeValue: $quotation->grandDiscountTypeValue,
                grandDiscountAmount: $quotation->grandDiscountAmount,
                grandAfterDiscount: $quotation->grandAfterDiscount,
                customerNotes: $quotation->customerNotes,
                grandTax: $quotation->grandTax,
                grandTotal: $quotation->grandTotal,
                publish: $quotation->publish,
                isActive: $quotation->isActive,
                createdBy: $userId
            );

            $savedCloned = $this->quotationRepo->save($cloned);
            $newQuotationId = $savedCloned->id;

            if ($newQuotationId === null) {
                throw new \RuntimeException("Failed to clone quotation header.");
            }

            foreach ($items as $item) {
                $clonedItem = new QuotationItem(
                    id: null,
                    organizationId: $orgId,
                    quotationId: $newQuotationId,
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
                $this->quotationRepo->saveItem($clonedItem);
            }

            $this->db->commit();

            return $savedCloned;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update status of a quotation
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $allowedStatuses = ['draft', 'sent', 'accepted', 'rejected', 'expired', 'confirmed', 'declined', 'pending', 'approved', 'invoiced', 'not_confirmed', 'on_hold', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException(['status' => "Invalid status: {$status}"]);
        }
        return $this->quotationRepo->updateStatus($id, $status, $orgId);
    }

    /**
     * Update quotation PDF path
     */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        return $this->quotationRepo->updatePdf($id, $pdfFilename, $orgId);
    }

    /**
     * Validate Quotation fields
     *
     * @throws ValidationException
     */
    private function validateQuotationData(array $data, int $orgId): void
    {
        if ((empty($data['customer_id']) || $data['customer_id'] === 'Please select' || $data['customer_id'] === '0')
            && (empty($data['lead_id']) || $data['lead_id'] === '0')) {
            throw new ValidationException(['customer_id' => "Please select Customer or Lead."]);
        }
        if (empty($data['warehouse_id']) || $data['warehouse_id'] === '0' || $data['warehouse_id'] === 'Please select') {
            throw new ValidationException(['warehouse_id' => "Please select Warehouse."]);
        }
        if (empty($data['quotation_date'])) {
            throw new ValidationException(['quotation_date' => "Please select Quotation Date."]);
        }

        // Verify customer exists if provided
        if (!empty($data['customer_id']) && $data['customer_id'] !== 'Please select' && $data['customer_id'] !== '0') {
            $customerId = (int)$data['customer_id'];
            $customer = $this->customerRepo->find($customerId, $orgId);
            if ($customer === null) {
                throw new ValidationException(['customer_id' => "Selected customer does not exist in your organization."]);
            }
        }
    }
}
