<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Model\DebitNote;
use App\Model\DebitNoteItem;
use App\Repository\DebitNoteRepository;
use App\Repository\VendorRepository;
use App\Repository\JournalRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class DebitNoteService
{
    private DebitNoteRepository $debitNoteRepo;
    private VendorRepository $vendorRepo;
    private JournalRepository $journalRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(DebitNoteRepository $debitNoteRepo, VendorRepository $vendorRepo, JournalRepository $journalRepo, JournalService $journalService, Database $db)
    {
        $this->debitNoteRepo = $debitNoteRepo;
        $this->vendorRepo = $vendorRepo;
        $this->journalRepo = $journalRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    public function getDebitNote(int $id, int $orgId): DebitNote
    {
        $debitNote = $this->debitNoteRepo->find($id, $orgId);
        if ($debitNote === null) {
            throw new NotFoundException("Debit Note with ID {$id} not found.");
        }
        return $debitNote;
    }

    public function getDebitNoteItems(int $debitNoteId): array
    {
        return $this->debitNoteRepo->findItems($debitNoteId);
    }

    public function createNote(array $data, array $itemsData, int $orgId, int $userId): DebitNote
    {
        $this->validateNoteData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $prefix = 'FL-DN' . date('ym');
            $lastNoteNo = $this->debitNoteRepo->getLastNoteNoForMonth($prefix, $orgId);
            if ($lastNoteNo !== null) {
                $lastSerial = (int) substr($lastNoteNo, -4);
                $newSerial = $lastSerial + 1;
            } else {
                $newSerial = 1;
            }
            $debitNoteNo = $prefix . '-' . str_pad((string)$newSerial, 4, '0', STR_PAD_LEFT);

            $debitNoteDate = $this->parseDate((string)($data['debit_note_date'] ?? ''));

            $recomputed = $this->computeNoteTotals($itemsData, $data);

            $debitNote = new DebitNote(
                id: null,
                organizationId: $orgId,
                debitNoteNo: $debitNoteNo,
                debitNoteDate: $debitNoteDate,
                debitNoteStatus: !empty($data['debit_note_status']) ? trim((string)$data['debit_note_status']) : 'draft',
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                vendorId: (int)($data['vendor_id'] ?? 0),
                purchaseId: (int)($data['purchase_id'] ?? 0),
                warehouseId: (int)($data['warehouse_id'] ?? 0),
                purchasePerson: (int)($data['purchase_person'] ?? 0),
                vendorNotes: !empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: $recomputed['grandSubtotal'],
                grandDiscountType: $recomputed['grandDiscountType'],
                grandDiscountTypeValue: $recomputed['grandDiscountTypeValue'],
                grandDiscountAmount: $recomputed['grandDiscountAmount'],
                grandAfterDiscount: $recomputed['grandAfterDiscount'],
                grandTax: $recomputed['grandTax'],
                grandTotal: $recomputed['grandTotal'],
                publish: isset($data['publish']) ? (bool)$data['publish'] : true,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : true,
                createdBy: $userId,
            );

            $savedDebitNote = $this->debitNoteRepo->save($debitNote);
            $debitNoteId = $savedDebitNote->id;

            if ($debitNoteId === null) {
                throw new \RuntimeException("Failed to insert debit note header.");
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service'])) {
                    continue;
                }
                $item = new DebitNoteItem(
                    id: null,
                    organizationId: $orgId,
                    debitNoteId: $debitNoteId,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : 0.0,
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : 0.0,
                    total: isset($itemData['total']) ? (float)$itemData['total'] : 0.0,
                    createdBy: $userId,
                );
                $this->debitNoteRepo->saveItem($item);
            }

            $debitNoteStatus = $debitNote->debitNoteStatus;
            if ($debitNoteStatus === 'open' && $debitNote->grandTotal > 0) {
                $existing = $this->journalRepo->findByReference('debit_note', $debitNoteId, $orgId);
                if (empty($existing)) {
                    $purchaseReturns = $this->db->fetchOne(
                        "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('4160','4170') OR account_name LIKE '%Returns%' OR account_name LIKE '%Allowances%' ORDER BY (account_code='4160') DESC LIMIT 1"
                    );
                    $ap = $this->db->fetchOne(
                        "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('2100','2110','2200') OR account_name LIKE '%Payable%' LIMIT 1"
                    );
                    if ($purchaseReturns !== null && $ap !== null) {
                        $this->journalService->createJournal(
                            [
                                'journal_date' => $debitNote->debitNoteDate,
                                'journal_status' => 'posted',
                                'reference_no' => $debitNote->debitNoteNo,
                                'notes' => 'Debit Note #' . $debitNote->debitNoteNo . ' - Vendor ID: ' . $debitNote->vendorId,
                                'reporting_method' => 'accrual',
                                'reference_type' => 'debit_note',
                                'reference_id' => $debitNoteId,
                                'currency' => 'AED',
                                'warehouse_id' => $debitNote->warehouseId,
                                'grand_subtotal' => $debitNote->grandSubtotal,
                                'grand_total' => $debitNote->grandTotal,
                            ],
                            [
                                ['account' => (int)$ap['id'], 'debit' => $debitNote->grandTotal, 'credit' => 0.0, 'description' => 'Debit Note #' . $debitNote->debitNoteNo],
                                ['account' => (int)$purchaseReturns['id'], 'debit' => 0.0, 'credit' => $debitNote->grandTotal, 'description' => 'Debit Note #' . $debitNote->debitNoteNo],
                            ],
                            $orgId,
                            $userId
                        );
                    }
                }
            }

            $this->db->commit();

            PdfGeneratorHelper::ensure('debit_notes', (int)$debitNoteId);

            return $savedDebitNote;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateNote(int $id, array $data, array $itemsData, int $orgId, int $userId): DebitNote
    {
        $debitNote = $this->getDebitNote($id, $orgId);
        $this->validateNoteData($data, $orgId);

        $this->db->beginTransaction();
        try {
            $debitNoteDate = isset($data['debit_note_date']) ? $this->parseDate((string)$data['debit_note_date']) : $debitNote->debitNoteDate;

            $recomputed = $this->computeNoteTotals($itemsData, $data);

            $updatedDebitNote = new DebitNote(
                id: $debitNote->id,
                organizationId: $debitNote->organizationId,
                debitNoteNo: isset($data['debit_note_no']) ? trim((string)$data['debit_note_no']) : $debitNote->debitNoteNo,
                debitNoteDate: $debitNoteDate,
                debitNoteStatus: isset($data['debit_note_status']) ? trim((string)$data['debit_note_status']) : $debitNote->debitNoteStatus,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $debitNote->referenceNo,
                vendorId: isset($data['vendor_id']) ? (int)$data['vendor_id'] : $debitNote->vendorId,
                purchaseId: isset($data['purchase_id']) ? (int)$data['purchase_id'] : $debitNote->purchaseId,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $debitNote->warehouseId,
                purchasePerson: isset($data['purchase_person']) ? (int)$data['purchase_person'] : $debitNote->purchasePerson,
                vendorNotes: isset($data['vendor_notes']) ? (!empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null) : $debitNote->vendorNotes,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $debitNote->termsAndConditions,
                grandSubtotal: $recomputed['grandSubtotal'],
                grandDiscountType: $recomputed['grandDiscountType'],
                grandDiscountTypeValue: $recomputed['grandDiscountTypeValue'],
                grandDiscountAmount: $recomputed['grandDiscountAmount'],
                grandAfterDiscount: $recomputed['grandAfterDiscount'],
                grandTax: $recomputed['grandTax'],
                grandTotal: $recomputed['grandTotal'],
                publish: isset($data['publish']) ? (bool)$data['publish'] : $debitNote->publish,
                isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $debitNote->isActive,
                createdAt: $debitNote->createdAt,
                createdBy: $debitNote->createdBy,
                updatedBy: $userId,
            );

            $savedDebitNote = $this->debitNoteRepo->save($updatedDebitNote);

            $existingItems = $this->debitNoteRepo->findItems($id);
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

                $item = new DebitNoteItem(
                    id: $itemId,
                    organizationId: $orgId,
                    debitNoteId: $id,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: isset($itemData['qty']) ? (float)$itemData['qty'] : 1.0,
                    rate: isset($itemData['rate']) ? (float)$itemData['rate'] : 0.0,
                    subTotal: isset($itemData['sub_total']) ? (float)$itemData['sub_total'] : 0.0,
                    tax: isset($itemData['tax']) ? (float)$itemData['tax'] : 0.0,
                    taxAmount: isset($itemData['tax_amount']) ? (float)$itemData['tax_amount'] : 0.0,
                    total: isset($itemData['total']) ? (float)$itemData['total'] : 0.0,
                    createdBy: $userId,
                );
                $this->debitNoteRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->debitNoteRepo->deleteItemsByIds($deletedIds, $id);
            }

            $this->deleteDebitNoteJournal($id);
            $updatedStatus = $updatedDebitNote->debitNoteStatus;
            if ($updatedStatus === 'open' && $updatedDebitNote->grandTotal > 0) {
                $purchaseReturns = $this->db->fetchOne(
                    "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('4160','4170') OR account_name LIKE '%Returns%' OR account_name LIKE '%Allowances%' ORDER BY (account_code='4160') DESC LIMIT 1"
                );
                $ap = $this->db->fetchOne(
                    "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('2100','2110','2200') OR account_name LIKE '%Payable%' LIMIT 1"
                );
                if ($purchaseReturns !== null && $ap !== null) {
                    $this->journalService->createJournal(
                        [
                            'journal_date' => $updatedDebitNote->debitNoteDate,
                            'journal_status' => 'posted',
                            'reference_no' => $updatedDebitNote->debitNoteNo,
                            'notes' => 'Debit Note #' . $updatedDebitNote->debitNoteNo . ' - Vendor ID: ' . $updatedDebitNote->vendorId,
                            'reporting_method' => 'accrual',
                            'reference_type' => 'debit_note',
                            'reference_id' => $updatedDebitNote->id,
                            'currency' => 'AED',
                            'warehouse_id' => $updatedDebitNote->warehouseId,
                            'grand_subtotal' => $updatedDebitNote->grandSubtotal,
                            'grand_total' => $updatedDebitNote->grandTotal,
                        ],
                        [
                            ['account' => (int)$ap['id'], 'debit' => $updatedDebitNote->grandTotal, 'credit' => 0.0, 'description' => 'Debit Note #' . $updatedDebitNote->debitNoteNo],
                            ['account' => (int)$purchaseReturns['id'], 'debit' => 0.0, 'credit' => $updatedDebitNote->grandTotal, 'description' => 'Debit Note #' . $updatedDebitNote->debitNoteNo],
                        ],
                        $orgId,
                        $userId
                    );
                }
            }

            $this->db->commit();

            PdfGeneratorHelper::ensure('debit_notes', (int)$id);

            return $savedDebitNote;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteNote(int $id, int $orgId): bool
    {
        $this->getDebitNote($id, $orgId);

        $this->db->beginTransaction();
        try {
            $this->deleteDebitNoteJournal($id);
            $result = $this->debitNoteRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateNoteData(array $data, int $orgId): void
    {
        if (empty($data['vendor_id']) || $data['vendor_id'] === 'Please select') {
            throw new ValidationException(['vendor_id' => "Please select Vendor."]);
        }
        if (empty($data['debit_note_date'])) {
            throw new ValidationException(['debit_note_date' => "Please select Debit Note Date."]);
        }
        if (empty($data['warehouse_id']) || $data['warehouse_id'] === 'Please select') {
            throw new ValidationException(['warehouse_id' => "Please select Warehouse."]);
        }

        $vendorId = (int)$data['vendor_id'];
        $vendor = $this->vendorRepo->find($vendorId, $orgId);
        if ($vendor === null) {
            throw new ValidationException(['vendor_id' => "Selected vendor does not exist in your organization."]);
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

    public function updateStatus(int $id, string $status, int $orgId): DebitNote
    {
        $debitNote = $this->getDebitNote($id, $orgId);
        $allowed = ['draft', 'open', 'void', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new ValidationException(['debit_note_status' => "Invalid debit note status."]);
        }

        $updated = new DebitNote(
            id: $debitNote->id,
            organizationId: $debitNote->organizationId,
            debitNoteNo: $debitNote->debitNoteNo,
            debitNoteDate: $debitNote->debitNoteDate,
            debitNoteStatus: $status,
            referenceNo: $debitNote->referenceNo,
            vendorId: $debitNote->vendorId,
            purchaseId: $debitNote->purchaseId,
            warehouseId: $debitNote->warehouseId,
            purchasePerson: $debitNote->purchasePerson,
            vendorNotes: $debitNote->vendorNotes,
            termsAndConditions: $debitNote->termsAndConditions,
            grandSubtotal: $debitNote->grandSubtotal,
            grandDiscountType: $debitNote->grandDiscountType,
            grandDiscountTypeValue: $debitNote->grandDiscountTypeValue,
            grandDiscountAmount: $debitNote->grandDiscountAmount,
            grandAfterDiscount: $debitNote->grandAfterDiscount,
            grandTax: $debitNote->grandTax,
            grandTotal: $debitNote->grandTotal,
            publish: $debitNote->publish,
            isActive: $debitNote->isActive,
            createdAt: $debitNote->createdAt,
            createdBy: $debitNote->createdBy,
        );

        return $this->debitNoteRepo->save($updated);
    }

    public function voidNote(int $id, int $orgId, int $userId): DebitNote
    {
        $debitNote = $this->getDebitNote($id, $orgId);

        $this->db->beginTransaction();
        try {
            $existing = $this->journalRepo->findByReference('debit_note_void', $id, $orgId);
            if (empty($existing)) {
                $original = $this->journalRepo->findByReference('debit_note', $id, $orgId);
                if (!empty($original)) {
                    $journal = $original[0];
                    $originalItems = $this->journalRepo->findItemsByJournal($journal->id, $orgId);
                    $reversalItems = [];
                    foreach ($originalItems as $ji) {
                        $reversalItems[] = [
                            'account' => $ji->account,
                            'debit' => $ji->credit,
                            'credit' => $ji->debit,
                            'description' => 'VOID - Reversal of Debit Note #' . $debitNote->debitNoteNo,
                        ];
                    }
                    $this->journalService->createJournal(
                        [
                            'journal_date' => date('Y-m-d'),
                            'journal_status' => 'posted',
                            'reference_no' => $debitNote->debitNoteNo . ' (VOID)',
                            'notes' => 'VOID - Reversal of Debit Note #' . $debitNote->debitNoteNo,
                            'reporting_method' => 'accrual',
                            'reference_type' => 'debit_note_void',
                            'reference_id' => $id,
                            'currency' => 'AED',
                            'warehouse_id' => $debitNote->warehouseId,
                            'grand_subtotal' => -$debitNote->grandSubtotal,
                            'grand_total' => -$debitNote->grandTotal,
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

    public function openNote(int $id, int $orgId, int $userId): DebitNote
    {
        return $this->openOrPostDebitNote($id, $orgId, $userId);
    }

    private function openOrPostDebitNote(int $id, int $orgId, int $userId): DebitNote
    {
        $debitNote = $this->getDebitNote($id, $orgId);

        $existing = $this->journalRepo->findByReference('debit_note', $id, $orgId);
        if (empty($existing) && $debitNote->grandTotal > 0) {
            $purchaseReturns = $this->db->fetchOne(
                "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('4160','4170') OR account_name LIKE '%Returns%' OR account_name LIKE '%Allowances%' ORDER BY (account_code='4160') DESC LIMIT 1"
            );
            $ap = $this->db->fetchOne(
                "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('2100','2110','2200') OR account_name LIKE '%Payable%' LIMIT 1"
            );
            if ($purchaseReturns !== null && $ap !== null) {
                $this->journalService->createJournal(
                    [
                        'journal_date' => $debitNote->debitNoteDate,
                        'journal_status' => 'posted',
                        'reference_no' => $debitNote->debitNoteNo,
                        'notes' => 'Debit Note #' . $debitNote->debitNoteNo . ' - Vendor ID: ' . $debitNote->vendorId,
                        'reporting_method' => 'accrual',
                        'reference_type' => 'debit_note',
                        'reference_id' => $id,
                        'currency' => 'AED',
                        'warehouse_id' => $debitNote->warehouseId,
                        'grand_subtotal' => $debitNote->grandSubtotal,
                        'grand_total' => $debitNote->grandTotal,
                    ],
                    [
                        ['account' => (int)$ap['id'], 'debit' => $debitNote->grandTotal, 'credit' => 0.0, 'description' => 'Debit Note #' . $debitNote->debitNoteNo],
                        ['account' => (int)$purchaseReturns['id'], 'debit' => 0.0, 'credit' => $debitNote->grandTotal, 'description' => 'Debit Note #' . $debitNote->debitNoteNo],
                    ],
                    $orgId,
                    $userId
                );
            }
        }

        return $this->updateStatus($id, 'open', $orgId);
    }

    private function deleteDebitNoteJournal(int $debitNoteId): void
    {
        $journalId = $this->db->fetchOne(
            "SELECT id FROM `{DB::JOURNALS}` WHERE reference_type IN ('debit_note', 'debit_note_void') AND reference_id = :ref_id LIMIT 1",
            ['ref_id' => $debitNoteId]
        );

        if ($journalId !== null) {
            $jid = (int)$journalId['id'];
            $this->db->execute("DELETE FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :jid", ['jid' => $jid]);
            $this->db->execute("DELETE FROM `{DB::JOURNALS}` WHERE id = :jid", ['jid' => $jid]);
        }
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
