<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Invoice;
use App\Model\InvoiceItem;
use App\Repository\InvoiceRepository;
use App\Repository\CustomerRepository;
use App\Repository\JournalRepository;
use App\Core\Database;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

/**
 * Invoice Service
 *
 * Implements business logic and validations for invoices and line items.
 */
class InvoiceService
{
    private InvoiceRepository $invoiceRepo;
    private CustomerRepository $customerRepo;
    private JournalRepository $journalRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(InvoiceRepository $invoiceRepo, CustomerRepository $customerRepo, JournalRepository $journalRepo, JournalService $journalService, Database $db)
    {
        $this->invoiceRepo = $invoiceRepo;
        $this->customerRepo = $customerRepo;
        $this->journalRepo = $journalRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    /**
     * Get Invoice by ID and organization
     *
     * @throws NotFoundException
     */
    public function getInvoice(int $id, int $orgId): Invoice
    {
        $invoice = $this->invoiceRepo->find($id, $orgId);
        if ($invoice === null) {
            throw new NotFoundException("Invoice with ID {$id} not found.");
        }
        return $invoice;
    }

    /**
     * Get items of an invoice
     */
    public function getInvoiceItems(int $invoiceId, int $orgId): array
    {
        return $this->invoiceRepo->findItemsByInvoice($invoiceId, $orgId);
    }

    /**
     * Get Invoice by ID only (without organization scoping, for public token access)
     *
     * @throws NotFoundException
     */
    public function getInvoicePublic(int $id): Invoice
    {
        $invoice = $this->invoiceRepo->findByIdOnly($id);
        if ($invoice === null) {
            throw new NotFoundException("Invoice with ID {$id} not found.");
        }
        return $invoice;
    }

    /**
     * Get items of an invoice by ID only (without organization scoping, for public token access)
     */
    public function getInvoiceItemsPublic(int $invoiceId): array
    {
        return $this->invoiceRepo->findItemsByInvoiceIdOnly($invoiceId);
    }

    /**
     * Create a new sales invoice
     *
     * @throws ValidationException
     */
    public function createInvoice(array $data, array $itemsData, int $orgId, int $userId): Invoice
    {
        $this->validateInvoiceData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            // Auto generate Invoice number
            $prefix = 'FL-IN' . date('ym');
            $lastInvoiceNo = $this->invoiceRepo->getLastInvoiceNoForMonth($prefix, $orgId);
            if ($lastInvoiceNo !== null) {
                $lastSerial = (int) substr($lastInvoiceNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $invoiceNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            // Date parsing (accepts d-m-Y / d/m/Y / Y-m-d)
            $invoiceDate = $this->normalizeDate((string)($data['invoice_date'] ?? date('Y-m-d')));
            $expiryDate = (string)($data['expiry_date'] ?? '');
            if (!empty($expiryDate)) {
                $expiryDate = $this->normalizeDate($expiryDate);
            } else {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = (string)($data['expected_shipment_date'] ?? '');
            if (!empty($expectedShipmentDate)) {
                $expectedShipmentDate = $this->normalizeDate($expectedShipmentDate);
            } else {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = (float)($data['grand_subtotal'] ?? 0.0);
            $grandTotal = (float)($data['grand_total'] ?? 0.0);

            $invoice = new Invoice(
                id: null,
                organizationId: $orgId,
                invoiceNo: $invoiceNo,
                customerId: (int)$data['customer_id'],
                invoiceStatus: !empty($data['invoice_status']) ? trim((string)$data['invoice_status']) : 'draft',
                invoiceDate: $invoiceDate,
                expiryDate: $expiryDate,
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                warehouseId: !empty($data['warehouse_id']) ? (int)$data['warehouse_id'] : 0,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: !empty($data['payment_term']) ? (int)$data['payment_term'] : 0,
                shipmentType: !empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null,
                salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : 0,
                jobReferenceNo: !empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null,
                masterAwbNo: !empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null,
                hwbHbol: !empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null,
                leadId: !empty($data['lead_id']) ? (int)$data['lead_id'] : 0,
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
                balanceDue: $grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : true,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : true,
                createdBy: $userId,
                recurring: !empty($data['recurring']) ? (int)$data['recurring'] : 0,
                pdf: !empty($data['pdf']) ? trim((string)$data['pdf']) : null
            );

            $savedInvoice = $this->invoiceRepo->save($invoice);
            $invoiceId = $savedInvoice->id;

            if ($invoiceId === null) {
                throw new \RuntimeException("Failed to insert invoice header.");
            }

            // Save line items
            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $item = new InvoiceItem(
                    id: null,
                    organizationId: $orgId,
                    invoiceId: $invoiceId,
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
                $this->invoiceRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedInvoice;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing sales invoice
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateInvoice(int $id, array $data, array $itemsData, int $orgId, int $userId): Invoice
    {
        $invoice = $this->getInvoice($id, $orgId);
        $this->validateInvoiceData($data, $orgId);

        if (isset($data['invoice_status'])) {
            $allowedStatuses = ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'confirmed', 'writeoff', 'void'];
            $status = trim((string)$data['invoice_status']);
            if (!in_array($status, $allowedStatuses, true)) {
                throw new ValidationException(['invoice_status' => "Invalid status: {$status}"]);
            }
        }

        $this->db->beginTransaction();
        try {
            // Date parsing (accepts d-m-Y / d/m/Y / Y-m-d)
            $invoiceDate = isset($data['invoice_date']) ? $this->normalizeDate((string)$data['invoice_date']) : $invoice->invoiceDate;
            $expiryDate = isset($data['expiry_date']) ? (string)$data['expiry_date'] : $invoice->expiryDate;
            if (!empty($expiryDate)) {
                $expiryDate = $this->normalizeDate($expiryDate);
            } else {
                $expiryDate = '1970-01-01';
            }

            $expectedShipmentDate = isset($data['expected_shipment_date']) ? (string)$data['expected_shipment_date'] : $invoice->expectedShipmentDate;
            if (!empty($expectedShipmentDate)) {
                $expectedShipmentDate = $this->normalizeDate($expectedShipmentDate);
            } else {
                $expectedShipmentDate = '1970-01-01';
            }

            $grandSubtotal = isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $invoice->grandSubtotal;
            $grandTotal = isset($data['grand_total']) ? (float)$data['grand_total'] : $invoice->grandTotal;

            $updatedInvoice = new Invoice(
                id: $invoice->id,
                organizationId: $invoice->organizationId,
                invoiceNo: isset($data['invoice_no']) ? trim((string)$data['invoice_no']) : $invoice->invoiceNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $invoice->customerId,
                invoiceStatus: isset($data['invoice_status']) ? trim((string)$data['invoice_status']) : $invoice->invoiceStatus,
                invoiceDate: $invoiceDate,
                expiryDate: $expiryDate,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $invoice->referenceNo,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $invoice->warehouseId,
                expectedShipmentDate: $expectedShipmentDate,
                paymentTerm: isset($data['payment_term']) ? (int)$data['payment_term'] : $invoice->paymentTerm,
                shipmentType: isset($data['shipment_type']) ? (!empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null) : $invoice->shipmentType,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $invoice->salesPerson,
                jobReferenceNo: isset($data['job_reference_no']) ? (!empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null) : $invoice->jobReferenceNo,
                masterAwbNo: isset($data['master_awb_no']) ? (!empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null) : $invoice->masterAwbNo,
                hwbHbol: isset($data['hwb_hbol']) ? (!empty($data['hwb_hbol']) ? trim((string)$data['hwb_hbol']) : null) : $invoice->hwbHbol,
                leadId: isset($data['lead_id']) ? (int)$data['lead_id'] : $invoice->leadId,
                shipper: isset($data['shipper']) ? (int)$data['shipper'] : $invoice->shipper,
                consignee: isset($data['consignee']) ? (int)$data['consignee'] : $invoice->consignee,
                origin: isset($data['origin']) ? (int)$data['origin'] : $invoice->origin,
                originCountry: isset($data['origin_country']) ? (int)$data['origin_country'] : $invoice->originCountry,
                destination: isset($data['destination']) ? (int)$data['destination'] : $invoice->destination,
                destinationCountry: isset($data['destination_country']) ? (int)$data['destination_country'] : $invoice->destinationCountry,
                noOfPacks: isset($data['no_of_packs']) ? (int)$data['no_of_packs'] : $invoice->noOfPacks,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $invoice->grossWeight,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $invoice->chargeableWeight,
                volume: isset($data['volume']) ? (float)$data['volume'] : $invoice->volume,
                cbm: isset($data['cbm']) ? (float)$data['cbm'] : $invoice->cbm,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $invoice->termsAndConditions,
                grandSubtotal: $grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : $invoice->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $invoice->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $invoice->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $invoice->grandAfterDiscount,
                customerNotes: isset($data['customer_notes']) ? (!empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null) : $invoice->customerNotes,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $invoice->grandTax,
                grandTotal: $grandTotal,
                balanceDue: isset($data['balance_due']) ? (float)$data['balance_due'] : ($invoice->balanceDue ?? $grandTotal),
                publish: isset($data['publish']) ? (bool)$data['publish'] : $invoice->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $invoice->isActive,
                createdAt: $invoice->createdAt,
                createdBy: $invoice->createdBy,
                updatedBy: $userId,
                recurring: isset($data['recurring']) ? (int)$data['recurring'] : $invoice->recurring,
                pdf: isset($data['pdf']) ? trim((string)$data['pdf']) : $invoice->pdf
            );

            $savedInvoice = $this->invoiceRepo->save($updatedInvoice);

            // Fetch existing items to manage changes (updates, inserts, deletions)
            $existingItems = $this->invoiceRepo->findItemsByInvoice($id, $orgId);
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

                $item = new InvoiceItem(
                    id: $itemId,
                    organizationId: $orgId,
                    invoiceId: $id,
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
                $this->invoiceRepo->saveItem($item);
            }

            // Identify and delete removed items
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

    /**
     * List all invoices in an organization
     */
    public function list(int $orgId): array
    {
        return $this->invoiceRepo->findAll($orgId);
    }

    /**
     * Delete an invoice and its items
     */
    public function deleteInvoice(int $id, int $orgId): bool
    {
        $invoice = $this->getInvoice($id, $orgId);
        if ($invoice->invoiceStatus === 'confirmed') {
            throw new ValidationException(['invoice' => "Confirmed invoices cannot be deleted."]);
        }

        $this->db->beginTransaction();
        try {
            $this->deleteInvoiceJournal($id);
            $result = $this->invoiceRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Clone an invoice
     */
    public function cloneInvoice(int $id, int $orgId, int $userId): Invoice
    {
        $invoice = $this->getInvoice($id, $orgId);
        $items = $this->getInvoiceItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            // Auto generate new Invoice number
            $prefix = 'FL-IN' . date('ym');
            $lastInvoiceNo = $this->invoiceRepo->getLastInvoiceNoForMonth($prefix, $orgId);
            if ($lastInvoiceNo !== null) {
                $lastSerial = (int) substr($lastInvoiceNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $invoiceNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $cloned = new Invoice(
                id: null,
                organizationId: $orgId,
                invoiceNo: $invoiceNo,
                customerId: $invoice->customerId,
                invoiceStatus: 'draft',
                invoiceDate: date('Y-m-d'),
                expiryDate: date('Y-m-d'),
                referenceNo: $invoice->referenceNo,
                warehouseId: $invoice->warehouseId,
                expectedShipmentDate: $invoice->expectedShipmentDate,
                paymentTerm: $invoice->paymentTerm,
                shipmentType: $invoice->shipmentType,
                salesPerson: $invoice->salesPerson,
                jobReferenceNo: $invoice->jobReferenceNo,
                masterAwbNo: $invoice->masterAwbNo,
                hwbHbol: $invoice->hwbHbol,
                leadId: $invoice->leadId,
                shipper: $invoice->shipper,
                consignee: $invoice->consignee,
                origin: $invoice->origin,
                originCountry: $invoice->originCountry,
                destination: $invoice->destination,
                destinationCountry: $invoice->destinationCountry,
                noOfPacks: $invoice->noOfPacks,
                grossWeight: $invoice->grossWeight,
                chargeableWeight: $invoice->chargeableWeight,
                volume: $invoice->volume,
                cbm: $invoice->cbm,
                termsAndConditions: $invoice->termsAndConditions,
                grandSubtotal: $invoice->grandSubtotal,
                grandDiscountType: $invoice->grandDiscountType,
                grandDiscountTypeValue: $invoice->grandDiscountTypeValue,
                grandDiscountAmount: $invoice->grandDiscountAmount,
                grandAfterDiscount: $invoice->grandAfterDiscount,
                customerNotes: $invoice->customerNotes,
                grandTax: $invoice->grandTax,
                grandTotal: $invoice->grandTotal,
                balanceDue: $invoice->grandTotal,
                publish: $invoice->publish,
                isActive: $invoice->isActive,
                createdBy: $userId
            );

            $savedCloned = $this->invoiceRepo->save($cloned);
            $newInvoiceId = $savedCloned->id;

            if ($newInvoiceId === null) {
                throw new \RuntimeException("Failed to clone invoice header.");
            }

            foreach ($items as $item) {
                $clonedItem = new InvoiceItem(
                    id: null,
                    organizationId: $orgId,
                    invoiceId: $newInvoiceId,
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
                $this->invoiceRepo->saveItem($clonedItem);
            }

            $this->db->commit();

            return $savedCloned;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Convert quote/existing invoice to a new invoice
     */
    public function convertToInvoice(int $id, int $orgId, int $userId): Invoice
    {
        // For the scope of this modernization, convert behaves like clone but sets dates to NOW
        return $this->cloneInvoice($id, $orgId, $userId);
    }

    /**
     * Update status of an invoice
     */
    public function updateStatus(int $id, string $status, int $orgId): bool
    {
        $allowedStatuses = ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'confirmed', 'writeoff', 'void'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new ValidationException(['status' => "Invalid status: {$status}"]);
        }

        $invoice = $this->getInvoice($id, $orgId);

        $this->db->beginTransaction();
        try {
            if ($status === 'sent' && $invoice->grandTotal > 0) {
                $this->postInvoiceJournal($id, $orgId);
            } elseif ($status === 'void') {
                $this->voidInvoiceJournal($id, $orgId);
            }

            $result = $this->invoiceRepo->updateStatus($id, $status, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update invoice PDF path
     */
    public function updatePdf(int $id, string $pdfFilename, int $orgId): bool
    {
        return $this->invoiceRepo->updatePdf($id, $pdfFilename, $orgId);
    }

    /**
     * Validate Invoice fields
     *
     * @throws ValidationException
     */
    private function validateInvoiceData(array $data, int $orgId): void
    {
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select') {
            throw new ValidationException(['customer_id' => "Please select Customer."]);
        }
        if (empty($data['invoice_date'])) {
            throw new ValidationException(['invoice_date' => "Please select Invoice Date."]);
        }
        if (empty($data['reference_no']) || trim((string)$data['reference_no']) === '') {
            throw new ValidationException(['reference_no' => "Please enter Job Reference no."]);
        }

        // Verify customer exists in organization
        $customerId = (int)$data['customer_id'];
        $customer = $this->customerRepo->find($customerId, $orgId);
        if ($customer === null) {
            throw new ValidationException(['customer_id' => "Selected customer does not exist in your organization."]);
        }
    }

    /**
     * Normalize a date from display/input formats (d-m-Y, d/m/Y) to DB format (Y-m-d).
     * Already-normalized Y-m-d values pass through unchanged.
     */
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

    private function deleteInvoiceJournal(int $invoiceId): void
    {
        $journalId = $this->db->fetchOne(
            "SELECT id FROM `{DB::JOURNALS}` WHERE reference_type IN ('invoice', 'invoice_void') AND reference_id = :ref_id LIMIT 1",
            ['ref_id' => $invoiceId]
        );

        if ($journalId !== null) {
            $jid = (int)$journalId['id'];
            $this->db->execute("DELETE FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :jid", ['jid' => $jid]);
            $this->db->execute("DELETE FROM `{DB::JOURNALS}` WHERE id = :jid", ['jid' => $jid]);
        }
    }

    private function postInvoiceJournal(int $invoiceId, int $orgId): void
    {
        $existing = $this->journalRepo->findByReference('invoice', $invoiceId, $orgId);
        if (!empty($existing)) {
            return;
        }

        $invoice = $this->getInvoice($invoiceId, $orgId);
        if ($invoice->grandTotal <= 0) {
            return;
        }

        $ar = $this->db->fetchOne(
            "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1"
        );
        $revenue = $this->db->fetchOne(
            "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('4000','4100') OR account_name LIKE '%Revenue%' OR account_name LIKE '%Income%' OR account_name LIKE '%Sales%' LIMIT 1"
        );

        if ($ar !== null && $revenue !== null) {
            $items = [
                ['account' => (int)$ar['id'], 'debit' => $invoice->grandTotal, 'credit' => 0.0, 'description' => 'Invoice #' . $invoice->invoiceNo],
                ['account' => (int)$revenue['id'], 'debit' => 0.0, 'credit' => $invoice->grandTotal, 'description' => 'Invoice #' . $invoice->invoiceNo],
            ];

            $this->journalService->createJournal(
                [
                    'journal_date' => $invoice->invoiceDate,
                    'journal_status' => 'posted',
                    'reference_no' => $invoice->invoiceNo,
                    'notes' => 'Invoice #' . $invoice->invoiceNo . ' - Customer ID: ' . $invoice->customerId,
                    'reporting_method' => 'accrual',
                    'reference_type' => 'invoice',
                    'reference_id' => $invoiceId,
                    'currency' => 'AED',
                    'warehouse_id' => $invoice->warehouseId,
                    'grand_subtotal' => $invoice->grandTotal,
                    'grand_total' => $invoice->grandTotal,
                ],
                $items,
                $orgId,
                0
            );
        }
    }

    private function voidInvoiceJournal(int $invoiceId, int $orgId): void
    {
        $existing = $this->journalRepo->findByReference('invoice_void', $invoiceId, $orgId);
        if (!empty($existing)) {
            return;
        }

        $original = $this->journalRepo->findByReference('invoice', $invoiceId, $orgId);
        if (empty($original)) {
            return;
        }

        $journal = $original[0];
        $originalItems = $this->journalRepo->findItemsByJournal($journal->id, $orgId);
        $reversalItems = [];
        foreach ($originalItems as $ji) {
            $reversalItems[] = [
                'account' => $ji->account,
                'debit' => $ji->credit,
                'credit' => $ji->debit,
                'description' => 'VOID - Reversal of Invoice #' . $journal->referenceNo,
            ];
        }

        $this->journalService->createJournal(
            [
                'journal_date' => date('Y-m-d'),
                'journal_status' => 'posted',
                'reference_no' => ($journal->referenceNo ?? '') . ' (VOID)',
                'notes' => 'VOID - Reversal of Invoice #' . $journal->referenceNo,
                'reporting_method' => 'accrual',
                'reference_type' => 'invoice_void',
                'reference_id' => $invoiceId,
                'currency' => 'AED',
                'warehouse_id' => $journal->warehouseId,
                'grand_subtotal' => -$journal->grandSubtotal,
                'grand_total' => -$journal->grandTotal,
            ],
            $reversalItems,
            $orgId,
            0
        );
    }
}
