<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Model\RecurringInvoice;
use App\Model\InvoiceItem;
use App\Repository\RecurringInvoiceRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class RecurringInvoiceService
{
    private RecurringInvoiceRepository $invoiceRepo;
    private Database $db;

    public function __construct(RecurringInvoiceRepository $invoiceRepo, Database $db)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->db = $db;
    }

    public function getInvoice(int $id, int $orgId): RecurringInvoice
    {
        $invoice = $this->invoiceRepo->find($id, $orgId);
        if ($invoice === null) {
            throw new NotFoundException("Recurring Invoice with ID {$id} not found.");
        }
        return $invoice;
    }

    public function getInvoiceItems(int $invoiceId, int $orgId): array
    {
        return $this->invoiceRepo->findItemsByInvoice($invoiceId, $orgId);
    }

    public function createInvoice(array $data, array $itemsData, int $orgId, int $userId): RecurringInvoice
    {
        $this->validateInvoiceData($data);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $invoiceDate = $this->parseDate((string)($data['invoice_date'] ?? ''));
            $invoiceNo = $this->generateInvoiceNo($orgId);
            $startDate = $this->parseDate((string)($data['start_date'] ?? ''));
            $endDate = !empty($data['end_date']) ? $this->parseDate((string)$data['end_date']) : null;
            $expiryDate = !empty($data['expiry_date']) ? $this->parseDate((string)$data['expiry_date']) : '1970-01-01';
            $expectedShipmentDate = !empty($data['expected_shipment_date']) ? $this->parseDate((string)$data['expected_shipment_date']) : null;

            $grandSubtotal = 0.0;
            $grandTax = 0.0;
            foreach ($itemsData as $itemData) {
                if ((int)($itemData['service'] ?? 0) <= 0) {
                    continue;
                }
                $qty = (float)($itemData['qty'] ?? 1.0);
                $rate = (float)($itemData['rate'] ?? 0.0);
                $sub = (float)($itemData['sub_total'] ?? $qty * $rate);
                $sub = $sub > 0 ? $sub : $qty * $rate;
                $tax = (float)($itemData['tax'] ?? 0.0);
                $taxAmt = (float)($itemData['tax_amount'] ?? ($sub * $tax / 100));
                $taxAmt = $taxAmt > 0 ? $taxAmt : $sub * $tax / 100;
                $grandSubtotal += $sub;
                $grandTax += $taxAmt;
            }

            $discountType = (string)($data['grand_discount_type'] ?? '');
            $discountValue = (float)($data['grand_discount_type_value'] ?? 0.0);
            $discountAmount = 0.0;
            if ($discountType === 'percent' && $discountValue > 0) {
                $discountAmount = $grandSubtotal * $discountValue / 100;
            } elseif ($discountType === 'fixed' && $discountValue > 0) {
                $discountAmount = $discountValue;
            }
            $grandAfterDiscount = $grandSubtotal - $discountAmount;
            $grandTotal = $grandAfterDiscount + $grandTax;

            $invoice = new RecurringInvoice(
                id: null,
                organizationId: $orgId,
                invoiceNo: $invoiceNo,
                customerId: (int)($data['customer_id'] ?? 0),
                invoiceStatus: (string)($data['invoice_status'] ?? 'draft'),
                invoiceDate: $invoiceDate,
                profileName: trim((string)($data['profile_name'] ?? '')),
                frequency: (string)($data['frequency'] ?? 'monthly'),
                startDate: $startDate,
                endDate: $endDate,
                expiryDate: $expiryDate,
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                warehouseId: (int)($data['warehouse_id'] ?? 0),
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: (int)($data['payment_term'] ?? 0),
                shipmentType: !empty($data['shipment_type']) ? (string)$data['shipment_type'] : null,
                salesPerson: (int)($data['sales_person'] ?? 0),
                jobReferenceNo: !empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null,
                masterAwbNo: !empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null,
                shipper: (int)($data['shipper'] ?? 0),
                consignee: (int)($data['consignee'] ?? 0),
                origin: (int)($data['origin'] ?? 0),
                destination: (int)($data['destination'] ?? 0),
                noOfPacks: (int)($data['no_of_packs'] ?? 0),
                grossWeight: (float)($data['gross_weight'] ?? 0.0),
                chargeableWeight: (float)($data['chargeable_weight'] ?? 0.0),
                volume: (float)($data['volume'] ?? 0.0),
                customerNotes: !empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: (string)($data['grand_discount_type'] ?? ''),
                grandDiscountTypeValue: (float)($data['grand_discount_type_value'] ?? 0.0),
                grandDiscountAmount: $discountAmount,
                grandAfterDiscount: $grandAfterDiscount,
                grandTax: $grandTax,
                grandTotal: $grandTotal,
                isActive: !empty($data['publish']),
                createdBy: $userId,
            );

            $savedInvoice = $this->invoiceRepo->save($invoice);
            $invoiceIdVal = $savedInvoice->id;

            if ($invoiceIdVal === null) {
                throw new \RuntimeException("Failed to insert recurring invoice header.");
            }

            foreach ($itemsData as $itemData) {
                $service = (int)($itemData['service'] ?? 0);
                if ($service <= 0) {
                    continue;
                }

                $item = new InvoiceItem(
                    id: null,
                    organizationId: $orgId,
                    invoiceId: $invoiceIdVal,
                    service: $service,
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->invoiceRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedInvoice;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateInvoice(int $id, array $data, array $itemsData, int $orgId, int $userId): RecurringInvoice
    {
        $existing = $this->getInvoice($id, $orgId);
        $this->validateInvoiceData($data);

        $this->db->beginTransaction();
        try {
            $invoiceDate = isset($data['invoice_date']) ? $this->parseDate((string)$data['invoice_date']) : $existing->invoiceDate;
            $startDate = isset($data['start_date']) ? $this->parseDate((string)$data['start_date']) : $existing->startDate;
            $endDate = array_key_exists('end_date', $data)
                ? (!empty($data['end_date']) ? $this->parseDate((string)$data['end_date']) : null)
                : $existing->endDate;
            $expiryDate = isset($data['expiry_date']) ? (!empty($data['expiry_date']) ? $this->parseDate((string)$data['expiry_date']) : '1970-01-01') : $existing->expiryDate;
            $expectedShipmentDate = array_key_exists('expected_shipment_date', $data)
                ? (!empty($data['expected_shipment_date']) ? $this->parseDate((string)$data['expected_shipment_date']) : null)
                : $existing->expectedShipmentDate;

            $updated = new RecurringInvoice(
                id: $existing->id,
                organizationId: $existing->organizationId,
                invoiceNo: $existing->invoiceNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $existing->customerId,
                invoiceStatus: (string)($data['invoice_status'] ?? $existing->invoiceStatus),
                invoiceDate: $invoiceDate,
                profileName: isset($data['profile_name']) ? trim((string)$data['profile_name']) : $existing->profileName,
                frequency: (string)($data['frequency'] ?? $existing->frequency),
                startDate: $startDate,
                endDate: $endDate,
                expiryDate: $expiryDate,
                referenceNo: isset($data['reference_no']) ? trim((string)$data['reference_no']) : $existing->referenceNo,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $existing->warehouseId,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: isset($data['payment_term']) ? (int)$data['payment_term'] : $existing->paymentTerm,
                shipmentType: isset($data['shipment_type']) ? (string)$data['shipment_type'] : $existing->shipmentType,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $existing->salesPerson,
                jobReferenceNo: isset($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : $existing->jobReferenceNo,
                masterAwbNo: isset($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : $existing->masterAwbNo,
                shipper: isset($data['shipper']) ? (int)$data['shipper'] : $existing->shipper,
                consignee: isset($data['consignee']) ? (int)$data['consignee'] : $existing->consignee,
                origin: isset($data['origin']) ? (int)$data['origin'] : $existing->origin,
                destination: isset($data['destination']) ? (int)$data['destination'] : $existing->destination,
                noOfPacks: isset($data['no_of_packs']) ? (int)$data['no_of_packs'] : $existing->noOfPacks,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $existing->grossWeight,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $existing->chargeableWeight,
                volume: isset($data['volume']) ? (float)$data['volume'] : $existing->volume,
                customerNotes: isset($data['customer_notes']) ? trim((string)$data['customer_notes']) : $existing->customerNotes,
                termsAndConditions: isset($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : $existing->termsAndConditions,
                grandSubtotal: isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $existing->grandSubtotal,
                grandDiscountType: (string)($data['grand_discount_type'] ?? $existing->grandDiscountType),
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $existing->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $existing->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $existing->grandAfterDiscount,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $existing->grandTax,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $existing->grandTotal,
                isActive: isset($data['publish']) ? !empty($data['publish']) : $existing->isActive,
                createdAt: $existing->createdAt,
                createdBy: $existing->createdBy,
                updatedBy: $userId,
            );

            $savedInvoice = $this->invoiceRepo->save($updated);

            $existingItems = $this->invoiceRepo->findItemsByInvoice($id, $orgId);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                $service = (int)($itemData['service'] ?? 0);
                $itemId = !empty($itemData['item_id']) ? (int)$itemData['item_id'] : null;

                if ($itemId !== null && $service <= 0) {
                    continue;
                }
                if ($itemId === null && $service <= 0) {
                    continue;
                }

                if ($itemId !== null) {
                    $incomingIds[] = $itemId;
                }

                $item = new InvoiceItem(
                    id: $itemId,
                    organizationId: $orgId,
                    invoiceId: $id,
                    service: $service,
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->invoiceRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->invoiceRepo->deleteItemsByIds($deletedIds, $id, $orgId);
            }

            $this->db->commit();

            return $savedInvoice;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteInvoice(int $id, int $orgId): bool
    {
        $this->getInvoice($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->invoiceRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateInvoiceData(array $data): void
    {
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select') {
            throw new ValidationException(['customer_id' => "Please select Customer."]);
        }
        if (empty($data['invoice_date'])) {
            throw new ValidationException(['invoice_date' => "Please select Invoice Date."]);
        }
        if (empty($data['profile_name'])) {
            throw new ValidationException(['profile_name' => "Please enter Profile Name."]);
        }
    }

    private function generateInvoiceNo(int $orgId): string
    {
        $prefix = 'FL-IN' . date('ym');
        $lastNo = $this->invoiceRepo->getLastInvoiceNoForMonth($prefix, $orgId);
        if ($lastNo === null) {
            return $prefix . '-0001';
        }
        $suffix = (int)substr($lastNo, -4);
        return $prefix . '-' . str_pad((string)($suffix + 1), 4, '0', STR_PAD_LEFT);
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
