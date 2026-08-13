<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Model\CreditNote;
use App\Model\CreditNoteItem;
use App\Repository\CreditNoteRepository;
use App\Repository\CustomerRepository;
use App\Repository\JournalRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;

class CreditNoteService
{
    private CreditNoteRepository $creditNoteRepo;
    private CustomerRepository $customerRepo;
    private JournalRepository $journalRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(
        CreditNoteRepository $creditNoteRepo,
        CustomerRepository $customerRepo,
        JournalRepository $journalRepo,
        JournalService $journalService,
        Database $db
    ) {
        $this->creditNoteRepo = $creditNoteRepo;
        $this->customerRepo = $customerRepo;
        $this->journalRepo = $journalRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    public function getCreditNote(int $id, int $orgId): CreditNote
    {
        $creditNote = $this->creditNoteRepo->find($id, $orgId);
        if ($creditNote === null) {
            throw new NotFoundException("Credit Note with ID {$id} not found.");
        }
        return $creditNote;
    }

    public function getCreditNoteItems(int $creditNoteId, int $orgId): array
    {
        return $this->creditNoteRepo->findItems($creditNoteId, $orgId);
    }

    public function createNote(array $data, array $itemsData, int $orgId, int $userId): CreditNote
    {
        $this->validateNoteData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $prefix = 'FL-CN' . date('ym');
            $lastNoteNo = $this->creditNoteRepo->getLastNoteNoForMonth($prefix, $orgId);
            if ($lastNoteNo !== null) {
                $lastSerial = (int) substr($lastNoteNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $creditNoteNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $creditNoteDate = $this->parseDate((string)($data['credit_note_date'] ?? ''));

            $grandSubtotal = 0.0;
            $grandTax = 0.0;
            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $itemQty = isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0;
                $itemRate = isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0;
                $itemSub = $itemQty * $itemRate;
                $itemTaxPct = isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0;
                $grandSubtotal += $itemSub;
                $grandTax += $itemSub * $itemTaxPct / 100;
            }
            $discountType = !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '';
            $discountValue = (float)($data['grand_discount_type_value'] ?? 0.0);
            $discountAmount = 0.0;
            if ($discountType === 'percent') {
                $discountAmount = $grandSubtotal * $discountValue / 100;
            } elseif ($discountType === 'fixed') {
                $discountAmount = $discountValue;
            }
            $grandAfterDiscount = $grandSubtotal - $discountAmount;
            $grandTotal = $grandAfterDiscount + $grandTax;

            $creditNote = new CreditNote(
                id: null,
                organizationId: $orgId,
                creditNoteNo: $creditNoteNo,
                creditNoteDate: $creditNoteDate,
                creditNoteStatus: !empty($data['credit_note_status']) ? trim((string)$data['credit_note_status']) : 'draft',
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                customerId: (int)($data['customer_id'] ?? 0),
                invoiceId: (int)($data['invoice_id'] ?? 0),
                warehouseId: (int)($data['warehouse_id'] ?? 0),
                subject: !empty($data['subject']) ? trim((string)$data['subject']) : null,
                paymentTerm: (int)($data['payment_term'] ?? 0),
                expiryDate: !empty($data['expiry_date']) ? $this->parseDate((string)$data['expiry_date']) : null,
                expectedShipmentDate: !empty($data['expected_shipment_date']) ? $this->parseDate((string)$data['expected_shipment_date']) : '1970-01-01',
                shipmentType: !empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null,
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
                grandDiscountType: $discountType,
                grandDiscountTypeValue: $discountValue,
                grandDiscountAmount: $discountAmount,
                grandAfterDiscount: $grandAfterDiscount,
                grandTax: $grandTax,
                grandTotal: $grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : true,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : true,
                createdBy: $userId,
            );

            $savedCreditNote = $this->creditNoteRepo->save($creditNote);
            $creditNoteId = $savedCreditNote->id;

            if ($creditNoteId === null) {
                throw new \RuntimeException("Failed to insert credit note header.");
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $item = new CreditNoteItem(
                    id: null,
                    organizationId: $orgId,
                    creditNoteId: $creditNoteId,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    discountType: !empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null,
                    discountTypeValue: isset($itemData['discount_type_value']) ? (float)$itemData['discount_type_value'] : 0.0,
                    discountAmount: isset($itemData['discount_amount']) ? (float)$itemData['discount_amount'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : ((float)($itemData['qty'] ?? 1.0)) * ((float)($itemData['rate'] ?? 0.0)),
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : (((float)($itemData['qty'] ?? 1.0)) * ((float)($itemData['rate'] ?? 0.0)) * ((float)($itemData['tax'] ?? 0.0)) / 100),
                    total: isset($itemData['total']) ? (float)$itemData['total'] : (((float)($itemData['qty'] ?? 1.0)) * ((float)($itemData['rate'] ?? 0.0)) + (((float)($itemData['qty'] ?? 1.0)) * ((float)($itemData['rate'] ?? 0.0)) * ((float)($itemData['tax'] ?? 0.0)) / 100)),
                    createdBy: $userId,
                );
                $this->creditNoteRepo->saveItem($item);
            }

            $this->db->commit();

            return $savedCreditNote;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateNote(int $id, array $data, array $itemsData, int $orgId, int $userId): CreditNote
    {
        $creditNote = $this->getCreditNote($id, $orgId);
        $this->validateNoteData($data, $orgId);

        $this->db->beginTransaction();
        try {
            $creditNoteDate = isset($data['credit_note_date']) ? $this->parseDate((string)$data['credit_note_date']) : $creditNote->creditNoteDate;

            $updatedCreditNote = new CreditNote(
                id: $creditNote->id,
                organizationId: $creditNote->organizationId,
                creditNoteNo: isset($data['credit_note_no']) ? trim((string)$data['credit_note_no']) : $creditNote->creditNoteNo,
                creditNoteDate: $creditNoteDate,
                creditNoteStatus: isset($data['credit_note_status']) ? trim((string)$data['credit_note_status']) : $creditNote->creditNoteStatus,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $creditNote->referenceNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $creditNote->customerId,
                invoiceId: isset($data['invoice_id']) ? (int)$data['invoice_id'] : $creditNote->invoiceId,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $creditNote->warehouseId,
                subject: isset($data['subject']) ? (!empty($data['subject']) ? trim((string)$data['subject']) : null) : $creditNote->subject,
                paymentTerm: isset($data['payment_term']) ? (int)$data['payment_term'] : $creditNote->paymentTerm,
                expiryDate: isset($data['expiry_date']) ? (!empty($data['expiry_date']) ? $this->parseDate((string)$data['expiry_date']) : null) : $creditNote->expiryDate,
                expectedShipmentDate: isset($data['expected_shipment_date']) ? (!empty($data['expected_shipment_date']) ? $this->parseDate((string)$data['expected_shipment_date']) : '1970-01-01') : $creditNote->expectedShipmentDate,
                shipmentType: isset($data['shipment_type']) ? (!empty($data['shipment_type']) ? trim((string)$data['shipment_type']) : null) : $creditNote->shipmentType,
                salesPerson: isset($data['sales_person']) ? (int)$data['sales_person'] : $creditNote->salesPerson,
                jobReferenceNo: isset($data['job_reference_no']) ? (!empty($data['job_reference_no']) ? trim((string)$data['job_reference_no']) : null) : $creditNote->jobReferenceNo,
                masterAwbNo: isset($data['master_awb_no']) ? (!empty($data['master_awb_no']) ? trim((string)$data['master_awb_no']) : null) : $creditNote->masterAwbNo,
                shipper: isset($data['shipper']) ? (int)$data['shipper'] : $creditNote->shipper,
                consignee: isset($data['consignee']) ? (int)$data['consignee'] : $creditNote->consignee,
                origin: isset($data['origin']) ? (int)$data['origin'] : $creditNote->origin,
                destination: isset($data['destination']) ? (int)$data['destination'] : $creditNote->destination,
                noOfPacks: isset($data['no_of_packs']) ? (int)$data['no_of_packs'] : $creditNote->noOfPacks,
                grossWeight: isset($data['gross_weight']) ? (float)$data['gross_weight'] : $creditNote->grossWeight,
                chargeableWeight: isset($data['chargeable_weight']) ? (float)$data['chargeable_weight'] : $creditNote->chargeableWeight,
                volume: isset($data['volume']) ? (float)$data['volume'] : $creditNote->volume,
                customerNotes: isset($data['customer_notes']) ? (!empty($data['customer_notes']) ? trim((string)$data['customer_notes']) : null) : $creditNote->customerNotes,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $creditNote->termsAndConditions,
                grandSubtotal: isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $creditNote->grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : $creditNote->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $creditNote->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $creditNote->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $creditNote->grandAfterDiscount,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $creditNote->grandTax,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $creditNote->grandTotal,
                publish: isset($data['publish']) ? (bool)$data['publish'] : $creditNote->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $creditNote->isActive,
                createdAt: $creditNote->createdAt,
                createdBy: $creditNote->createdBy,
                updatedBy: $userId,
            );

            $savedCreditNote = $this->creditNoteRepo->save($updatedCreditNote);

            $existingItems = $this->creditNoteRepo->findItems($id, $orgId);
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

                $item = new CreditNoteItem(
                    id: $itemId,
                    organizationId: $orgId,
                    creditNoteId: $id,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    discountType: isset($itemData['discount_type']) ? (!empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null) : null,
                    discountTypeValue: isset($itemData['discount_type_value']) ? (float)$itemData['discount_type_value'] : 0.0,
                    discountAmount: isset($itemData['discount_amount']) ? (float)$itemData['discount_amount'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : 0.0,
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : 0.0,
                    total: isset($itemData['total']) ? (float)$itemData['total'] : 0.0,
                    createdBy: $userId,
                );
                $this->creditNoteRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->creditNoteRepo->deleteItemsByIds($deletedIds, $id, $orgId);
            }

            $recomputed = $this->computeNoteTotals($itemsData, $data);
            $recomputedHeader = new CreditNote(
                id: $creditNote->id,
                organizationId: $savedCreditNote->organizationId,
                creditNoteNo: $savedCreditNote->creditNoteNo,
                creditNoteDate: $savedCreditNote->creditNoteDate,
                creditNoteStatus: $savedCreditNote->creditNoteStatus,
                referenceNo: $savedCreditNote->referenceNo,
                customerId: $savedCreditNote->customerId,
                invoiceId: $savedCreditNote->invoiceId,
                warehouseId: $savedCreditNote->warehouseId,
                subject: $savedCreditNote->subject,
                paymentTerm: $savedCreditNote->paymentTerm,
                expiryDate: $savedCreditNote->expiryDate,
                expectedShipmentDate: $savedCreditNote->expectedShipmentDate,
                shipmentType: $savedCreditNote->shipmentType,
                salesPerson: $savedCreditNote->salesPerson,
                jobReferenceNo: $savedCreditNote->jobReferenceNo,
                masterAwbNo: $savedCreditNote->masterAwbNo,
                shipper: $savedCreditNote->shipper,
                consignee: $savedCreditNote->consignee,
                origin: $savedCreditNote->origin,
                destination: $savedCreditNote->destination,
                noOfPacks: $savedCreditNote->noOfPacks,
                grossWeight: $savedCreditNote->grossWeight,
                chargeableWeight: $savedCreditNote->chargeableWeight,
                volume: $savedCreditNote->volume,
                customerNotes: $savedCreditNote->customerNotes,
                termsAndConditions: $savedCreditNote->termsAndConditions,
                grandSubtotal: $recomputed['grandSubtotal'],
                grandDiscountType: $recomputed['grandDiscountType'],
                grandDiscountTypeValue: $recomputed['grandDiscountTypeValue'],
                grandDiscountAmount: $recomputed['grandDiscountAmount'],
                grandAfterDiscount: $recomputed['grandAfterDiscount'],
                grandTax: $recomputed['grandTax'],
                grandTotal: $recomputed['grandTotal'],
                publish: $savedCreditNote->publish,
                isActive: $savedCreditNote->isActive,
                createdAt: $creditNote->createdAt,
                createdBy: $creditNote->createdBy,
                updatedBy: $userId,
            );
            $savedCreditNote = $this->creditNoteRepo->save($recomputedHeader);

            $this->db->commit();

            return $savedCreditNote;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteNote(int $id, int $orgId): bool
    {
        $this->getCreditNote($id, $orgId);

        $this->db->beginTransaction();
        try {
            $creditNoteJournals = $this->journalRepo->findByReference('credit_note', $id, $orgId);
            foreach ($creditNoteJournals as $journal) {
                $this->journalRepo->delete($journal->id, $orgId);
            }
            $voidJournals = $this->journalRepo->findByReference('credit_note_void', $id, $orgId);
            foreach ($voidJournals as $journal) {
                $this->journalRepo->delete($journal->id, $orgId);
            }
            $result = $this->creditNoteRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cloneNote(int $id, int $orgId, int $userId): CreditNote
    {
        $creditNote = $this->getCreditNote($id, $orgId);
        $items = $this->getCreditNoteItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            $prefix = 'FL-CN' . date('ym');
            $lastNoteNo = $this->creditNoteRepo->getLastNoteNoForMonth($prefix, $orgId);
            $newSerial = $lastNoteNo !== null ? ((int)substr($lastNoteNo, -4) + 1) : 1;
            $creditNoteNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $clone = new CreditNote(
                id: null,
                organizationId: $orgId,
                creditNoteNo: $creditNoteNo,
                creditNoteDate: date('Y-m-d'),
                creditNoteStatus: 'draft',
                referenceNo: $creditNote->referenceNo,
                customerId: $creditNote->customerId,
                invoiceId: $creditNote->invoiceId,
                warehouseId: $creditNote->warehouseId,
                subject: $creditNote->subject,
                paymentTerm: $creditNote->paymentTerm,
                expiryDate: $creditNote->expiryDate,
                expectedShipmentDate: $creditNote->expectedShipmentDate,
                shipmentType: $creditNote->shipmentType,
                salesPerson: $creditNote->salesPerson,
                jobReferenceNo: $creditNote->jobReferenceNo,
                masterAwbNo: $creditNote->masterAwbNo,
                shipper: $creditNote->shipper,
                consignee: $creditNote->consignee,
                origin: $creditNote->origin,
                destination: $creditNote->destination,
                noOfPacks: $creditNote->noOfPacks,
                grossWeight: $creditNote->grossWeight,
                chargeableWeight: $creditNote->chargeableWeight,
                volume: $creditNote->volume,
                customerNotes: $creditNote->customerNotes,
                termsAndConditions: $creditNote->termsAndConditions,
                grandSubtotal: $creditNote->grandSubtotal,
                grandDiscountType: $creditNote->grandDiscountType,
                grandDiscountTypeValue: $creditNote->grandDiscountTypeValue,
                grandDiscountAmount: $creditNote->grandDiscountAmount,
                grandAfterDiscount: $creditNote->grandAfterDiscount,
                grandTax: $creditNote->grandTax,
                grandTotal: $creditNote->grandTotal,
                publish: $creditNote->publish,
                isActive: $creditNote->isActive,
                createdBy: $userId,
            );

            $saved = $this->creditNoteRepo->save($clone);
            $newId = $saved->id;
            if ($newId === null) {
                throw new \RuntimeException("Failed to clone credit note header.");
            }

            foreach ($items as $item) {
                $itemClone = new CreditNoteItem(
                    id: null,
                    organizationId: $orgId,
                    creditNoteId: $newId,
                    service: $item->service,
                    description: $item->description,
                    qty: $item->qty,
                    rate: $item->rate,
                    discountType: $item->discountType,
                    discountTypeValue: $item->discountTypeValue,
                    discountAmount: $item->discountAmount,
                    subTotal: $item->subTotal,
                    tax: $item->tax,
                    taxAmount: $item->taxAmount,
                    total: $item->total,
                    createdBy: $userId,
                );
                $this->creditNoteRepo->saveItem($itemClone);
            }

            $this->db->commit();
            return $this->getCreditNote($newId, $orgId);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function convertToInvoice(int $id, int $orgId, int $userId): int
    {
        $creditNote = $this->getCreditNote($id, $orgId);
        $items = $this->getCreditNoteItems($id, $orgId);

        $this->db->beginTransaction();
        try {
            $prefix = 'FL-IN' . date('ym');
            $lastInvoiceNo = $this->db->fetchOne(
                "SELECT invoice_no FROM `{DB::INVOICES}` WHERE invoice_no LIKE :prefix AND organization_id = :org_id ORDER BY invoice_no DESC LIMIT 1",
                ['prefix' => $prefix . '-%', 'org_id' => $orgId]
            );
            $newSerial = $lastInvoiceNo !== null ? ((int)substr((string)$lastInvoiceNo['invoice_no'], -4) + 1) : 1;
            $invoiceNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $invoiceId = (int)$this->db->insert(
                "INSERT INTO `{DB::INVOICES}` (
                    organization_id, invoice_no, customer_id, warehouse_id, subject, reference_no,
                    invoice_date, expiry_date, expected_shipment_date, payment_term, shipment_type,
                    sales_person, job_reference_no, master_awb_no, shipper, consignee, origin,
                    destination, no_of_packs, gross_weight, chargeable_weight, volume,
                    customer_notes, terms_and_conditions, invoice_status,
                    grand_subtotal, grand_discount_type, grand_discount_type_value,
                    grand_discount_amount, grand_after_discount, grand_tax, grand_total,
                    balance_due, publish, is_active, created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :invoice_no, :customer_id, :warehouse_id, :subject, :reference_no,
                    :invoice_date, :expiry_date, :expected_shipment_date, :payment_term, :shipment_type,
                    :sales_person, :job_reference_no, :master_awb_no, :shipper, :consignee, :origin,
                    :destination, :no_of_packs, :gross_weight, :chargeable_weight, :volume,
                    :customer_notes, :terms_and_conditions, :invoice_status,
                    :grand_subtotal, :grand_discount_type, :grand_discount_type_value,
                    :grand_discount_amount, :grand_after_discount, :grand_tax, :grand_total,
                    :balance_due, :publish, :is_active, NOW(), NOW(), :created_by
                )",
                [
                    'organization_id' => $orgId,
                    'invoice_no' => $invoiceNo,
                    'customer_id' => $creditNote->customerId,
                    'warehouse_id' => $creditNote->warehouseId,
                    'subject' => $creditNote->subject,
                    'reference_no' => $creditNote->referenceNo,
                    'invoice_date' => $creditNote->creditNoteDate,
                    'expiry_date' => $creditNote->expiryDate,
                    'expected_shipment_date' => $creditNote->expectedShipmentDate !== null ? $creditNote->expectedShipmentDate : '1970-01-01',
                    'payment_term' => $creditNote->paymentTerm,
                    'shipment_type' => $creditNote->shipmentType,
                    'sales_person' => $creditNote->salesPerson,
                    'job_reference_no' => $creditNote->jobReferenceNo,
                    'master_awb_no' => $creditNote->masterAwbNo,
                    'shipper' => $creditNote->shipper,
                    'consignee' => $creditNote->consignee,
                    'origin' => $creditNote->origin,
                    'destination' => $creditNote->destination,
                    'no_of_packs' => $creditNote->noOfPacks,
                    'gross_weight' => $creditNote->grossWeight,
                    'chargeable_weight' => $creditNote->chargeableWeight,
                    'volume' => $creditNote->volume,
                    'customer_notes' => $creditNote->customerNotes,
                    'terms_and_conditions' => $creditNote->termsAndConditions,
                    'invoice_status' => 'draft',
                    'grand_subtotal' => $creditNote->grandSubtotal,
                    'grand_discount_type' => $creditNote->grandDiscountType,
                    'grand_discount_type_value' => $creditNote->grandDiscountTypeValue,
                    'grand_discount_amount' => $creditNote->grandDiscountAmount,
                    'grand_after_discount' => $creditNote->grandAfterDiscount,
                    'grand_tax' => $creditNote->grandTax,
                    'grand_total' => $creditNote->grandTotal,
                    'balance_due' => $creditNote->grandTotal,
                    'publish' => $creditNote->publish ? 1 : 0,
                    'is_active' => $creditNote->isActive ? 1 : 0,
                    'created_by' => $userId,
                ]
            );

            foreach ($items as $item) {
                $this->db->insert(
                    "INSERT INTO `{DB::INVOICE_ITEMS}` (
                        organization_id, invoice_id, service, description, qty, rate,
                        discount_type, discount_type_value, discount_amount, tax, tax_amount,
                        sub_total, total, created_at, updated_at, created_by
                    ) VALUES (
                        :organization_id, :invoice_id, :service, :description, :qty, :rate,
                        :discount_type, :discount_type_value, :discount_amount, :tax, :tax_amount,
                        :sub_total, :total, NOW(), NOW(), :created_by
                    )",
                    [
                        'organization_id' => $orgId,
                        'invoice_id' => $invoiceId,
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

            $this->db->commit();
            return $invoiceId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $status, int $orgId): CreditNote
    {
        $creditNote = $this->getCreditNote($id, $orgId);
        $allowed = ['draft', 'sent', 'approved', 'open', 'paid', 'void', 'not_confirmed'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['credit_note_status' => "Invalid credit note status."]);
        }

        $updated = new CreditNote(
            id: $creditNote->id,
            organizationId: $creditNote->organizationId,
            creditNoteNo: $creditNote->creditNoteNo,
            creditNoteDate: $creditNote->creditNoteDate,
            creditNoteStatus: $status,
            referenceNo: $creditNote->referenceNo,
            customerId: $creditNote->customerId,
            invoiceId: $creditNote->invoiceId,
            warehouseId: $creditNote->warehouseId,
            subject: $creditNote->subject,
            paymentTerm: $creditNote->paymentTerm,
            expiryDate: $creditNote->expiryDate,
            expectedShipmentDate: $creditNote->expectedShipmentDate,
            shipmentType: $creditNote->shipmentType,
            salesPerson: $creditNote->salesPerson,
            jobReferenceNo: $creditNote->jobReferenceNo,
            masterAwbNo: $creditNote->masterAwbNo,
            shipper: $creditNote->shipper,
            consignee: $creditNote->consignee,
            origin: $creditNote->origin,
            destination: $creditNote->destination,
            noOfPacks: $creditNote->noOfPacks,
            grossWeight: $creditNote->grossWeight,
            chargeableWeight: $creditNote->chargeableWeight,
            volume: $creditNote->volume,
            customerNotes: $creditNote->customerNotes,
            termsAndConditions: $creditNote->termsAndConditions,
            grandSubtotal: $creditNote->grandSubtotal,
            grandDiscountType: $creditNote->grandDiscountType,
            grandDiscountTypeValue: $creditNote->grandDiscountTypeValue,
            grandDiscountAmount: $creditNote->grandDiscountAmount,
            grandAfterDiscount: $creditNote->grandAfterDiscount,
            grandTax: $creditNote->grandTax,
            grandTotal: $creditNote->grandTotal,
            publish: $creditNote->publish,
            isActive: $creditNote->isActive,
            createdAt: $creditNote->createdAt,
            createdBy: $creditNote->createdBy,
        );

        return $this->creditNoteRepo->save($updated);
    }

    public function voidNote(int $id, int $orgId, int $userId): CreditNote
    {
        $creditNote = $this->getCreditNote($id, $orgId);

        $this->db->beginTransaction();
        try {
            $existing = $this->journalRepo->findByReference('credit_note_void', $id, $orgId);
            if (empty($existing)) {
                $original = $this->journalRepo->findByReference('credit_note', $id, $orgId);
                if (!empty($original)) {
                    $journal = $original[0];
                    $originalItems = $this->journalRepo->findItemsByJournal($journal->id, $orgId);
                    $reversalItems = [];
                    foreach ($originalItems as $ji) {
                        $reversalItems[] = [
                            'account' => $ji->account,
                            'debit' => $ji->credit,
                            'credit' => $ji->debit,
                            'description' => 'VOID - Reversal of Credit Note #' . $creditNote->creditNoteNo,
                        ];
                    }
                    $this->journalService->createJournal(
                        [
                            'journal_date' => date('Y-m-d'),
                            'journal_status' => 'posted',
                            'reference_no' => $creditNote->creditNoteNo . ' (VOID)',
                            'notes' => 'VOID - Reversal of Credit Note #' . $creditNote->creditNoteNo,
                            'reporting_method' => 'accrual',
                            'reference_type' => 'credit_note_void',
                            'reference_id' => $id,
                            'currency' => 'AED',
                            'warehouse_id' => $creditNote->warehouseId,
                            'grand_subtotal' => -$creditNote->grandSubtotal,
                            'grand_total' => -$creditNote->grandTotal,
                        ],
                        $reversalItems,
                        $orgId,
                        $userId
                    );
                }
            }

            $updated = $this->updateStatus($id, 'void', $orgId);
            $this->db->commit();
            return $updated;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function openNote(int $id, int $orgId, int $userId): CreditNote
    {
        $creditNote = $this->getCreditNote($id, $orgId);

        $this->db->beginTransaction();
        try {
            $existing = $this->journalRepo->findByReference('credit_note', $id, $orgId);
            if (empty($existing) && $creditNote->grandTotal > 0) {
                $salesReturns = $this->db->fetchOne(
                    "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('4160') OR account_name LIKE '%Returns%' OR account_name LIKE '%Allowances%' ORDER BY (account_code='4160') DESC LIMIT 1"
                );
                $ar = $this->db->fetchOne(
                    "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1"
                );
                if ($salesReturns !== null && $ar !== null) {
                    $this->journalService->createJournal(
                        [
                            'journal_date' => date('Y-m-d'),
                            'journal_status' => 'posted',
                            'reference_no' => $creditNote->creditNoteNo,
                            'notes' => 'Credit Note #' . $creditNote->creditNoteNo . ' - Customer ID: ' . $creditNote->customerId,
                            'reporting_method' => 'accrual',
                            'reference_type' => 'credit_note',
                            'reference_id' => $id,
                            'currency' => 'AED',
                            'warehouse_id' => $creditNote->warehouseId,
                            'grand_subtotal' => $creditNote->grandSubtotal,
                            'grand_total' => $creditNote->grandTotal,
                        ],
                        [
                            ['account' => (int)$salesReturns['id'], 'debit' => $creditNote->grandTotal, 'credit' => 0.0, 'description' => 'Credit Note #' . $creditNote->creditNoteNo],
                            ['account' => (int)$ar['id'], 'debit' => 0.0, 'credit' => $creditNote->grandTotal, 'description' => 'Credit Note #' . $creditNote->creditNoteNo],
                        ],
                        $orgId,
                        $userId
                    );
                }
            }

            $updated = $this->updateStatus($id, 'open', $orgId);
            $this->db->commit();
            return $updated;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateNoteData(array $data, int $orgId): void
    {
        if (empty($data['customer_id']) || $data['customer_id'] === 'Please select') {
            throw new ValidationException(['customer_id' => "Please select Customer."]);
        }
        if (empty($data['credit_note_date'])) {
            throw new ValidationException(['credit_note_date' => "Please select Credit Note Date."]);
        }

        $customerId = (int)$data['customer_id'];
        $customer = $this->customerRepo->find($customerId, $orgId);
        if ($customer === null) {
            throw new ValidationException(['customer_id' => "Selected customer does not exist in your organization."]);
        }

        $status = isset($data['credit_note_status']) ? trim((string)$data['credit_note_status']) : '';
        if ($status !== '' && !in_array($status, ['draft', 'sent', 'approved', 'open', 'paid', 'void', 'not_confirmed'], true)) {
            throw new ValidationException(['credit_note_status' => "Invalid credit note status: {$status}"]);
        }
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

    private function computeNoteTotals(array $itemsData, array $data): array
    {
        $grandSubtotal = 0.0;
        $grandTax = 0.0;
        foreach ($itemsData as $itemData) {
            if (empty($itemData['service'])) {
                continue;
            }
            $itemQty = isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0;
            $itemRate = isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0;
            $itemSub = $itemQty * $itemRate;
            $itemTaxPct = isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0;
            $grandSubtotal += $itemSub;
            $grandTax += $itemSub * $itemTaxPct / 100;
        }
        $discountType = !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '';
        $discountValue = (float)($data['grand_discount_type_value'] ?? 0.0);
        $discountAmount = 0.0;
        if ($discountType === 'percent') {
            $discountAmount = $grandSubtotal * $discountValue / 100;
        } elseif ($discountType === 'fixed') {
            $discountAmount = $discountValue;
        }
        $grandAfterDiscount = $grandSubtotal - $discountAmount;
        $grandTotal = $grandAfterDiscount + $grandTax;

        return [
            'grandSubtotal' => $grandSubtotal,
            'grandDiscountType' => $discountType,
            'grandDiscountTypeValue' => $discountValue,
            'grandDiscountAmount' => $discountAmount,
            'grandAfterDiscount' => $grandAfterDiscount,
            'grandTax' => $grandTax,
            'grandTotal' => $grandTotal,
        ];
    }
}
