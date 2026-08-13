<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Model\Purchase;
use App\Model\PurchaseItem;
use App\Repository\PurchaseRepository;
use App\Repository\VendorRepository;
use App\Repository\JournalRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class PurchaseService
{
    private PurchaseRepository $purchaseRepo;
    private VendorRepository $vendorRepo;
    private JournalRepository $journalRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(PurchaseRepository $purchaseRepo, VendorRepository $vendorRepo, JournalRepository $journalRepo, JournalService $journalService, Database $db)
    {
        $this->purchaseRepo = $purchaseRepo;
        $this->vendorRepo = $vendorRepo;
        $this->journalRepo = $journalRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    public function getPurchase(int $id, int $orgId): Purchase
    {
        $purchase = $this->purchaseRepo->find($id, $orgId);
        if ($purchase === null) {
            throw new NotFoundException("Purchase with ID {$id} not found.");
        }
        return $purchase;
    }

    public function getPurchaseItems(int $purchaseId): array
    {
        return $this->purchaseRepo->findItemsByPurchase($purchaseId);
    }

    public function createPurchase(array $data, array $itemsData, int $orgId, int $userId): Purchase
    {
        $this->validatePurchaseData($data, $orgId);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $purchaseDate = $this->parseDate((string)($data['purchase_date'] ?? ''));

            $purchase = new Purchase(
                id: null,
                organizationId: $orgId,
                purchaseDate: $purchaseDate,
                vendorId: (int)($data['vendor_id'] ?? 0),
                purchaseNo: $this->purchaseRepo->generatePurchaseNo($orgId),
                purchaseStatus: !empty($data['purchase_status']) ? trim((string)$data['purchase_status']) : 'draft',
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                subject: !empty($data['subject']) ? trim((string)$data['subject']) : null,
                warehouseId: (int)($data['warehouse_id'] ?? 0),
                vendorNotes: !empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null,
                termsAndConditions: !empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null,
                grandSubtotal: (float)($data['grand_subtotal'] ?? 0.0),
                grandDiscountType: !empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00',
                grandDiscountTypeValue: (float)($data['grand_discount_type_value'] ?? 0.0),
                grandDiscountAmount: (float)($data['grand_discount_amount'] ?? 0.0),
                grandAfterDiscount: (float)($data['grand_after_discount'] ?? 0.0),
                grandTax: (float)($data['grand_tax'] ?? 0.0),
                grandTotal: (float)($data['grand_total'] ?? 0.0),
                createdBy: $userId,
            );

            $savedPurchase = $this->purchaseRepo->save($purchase);
            $purchaseId = $savedPurchase->id;

            if ($purchaseId === null) {
                throw new \RuntimeException("Failed to insert purchase header.");
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['service']) || (int)$itemData['service'] <= 0) {
                    continue;
                }
                $item = new PurchaseItem(
                    id: null,
                    organizationId: $orgId,
                    purchaseId: $purchaseId,
                    service: (int)$itemData['service'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    discountType: !empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null,
                    discountTypeValue: (float)($itemData['discount_type_value'] ?? 0.0),
                    discountAmount: (float)($itemData['discount_amount'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->purchaseRepo->saveItem($item);
            }

            $this->createPurchaseJournal($purchaseId, $purchaseDate, $orgId);

            $this->db->commit();

            PdfGeneratorHelper::ensure('purchases', (int)$purchaseId);

            return $savedPurchase;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updatePurchase(int $id, array $data, array $itemsData, int $orgId, int $userId): Purchase
    {
        $purchase = $this->getPurchase($id, $orgId);
        $this->validatePurchaseData($data, $orgId);

        $this->db->beginTransaction();
        try {
            $purchaseDate = isset($data['purchase_date']) ? $this->parseDate((string)$data['purchase_date']) : $purchase->purchaseDate;

            $updatedPurchase = new Purchase(
                id: $purchase->id,
                organizationId: $purchase->organizationId,
                purchaseDate: $purchaseDate,
                vendorId: isset($data['vendor_id']) ? (int)$data['vendor_id'] : $purchase->vendorId,
                purchaseNo: $purchase->purchaseNo,
                purchaseStatus: isset($data['purchase_status']) ? (!empty($data['purchase_status']) ? trim((string)$data['purchase_status']) : 'draft') : $purchase->purchaseStatus,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $purchase->referenceNo,
                subject: isset($data['subject']) ? (!empty($data['subject']) ? trim((string)$data['subject']) : null) : $purchase->subject,
                warehouseId: isset($data['warehouse_id']) ? (int)$data['warehouse_id'] : $purchase->warehouseId,
                vendorNotes: isset($data['vendor_notes']) ? (!empty($data['vendor_notes']) ? trim((string)$data['vendor_notes']) : null) : $purchase->vendorNotes,
                termsAndConditions: isset($data['terms_and_conditions']) ? (!empty($data['terms_and_conditions']) ? trim((string)$data['terms_and_conditions']) : null) : $purchase->termsAndConditions,
                grandSubtotal: isset($data['grand_subtotal']) ? (float)$data['grand_subtotal'] : $purchase->grandSubtotal,
                grandDiscountType: isset($data['grand_discount_type']) ? (!empty($data['grand_discount_type']) ? trim((string)$data['grand_discount_type']) : '0.00') : $purchase->grandDiscountType,
                grandDiscountTypeValue: isset($data['grand_discount_type_value']) ? (float)$data['grand_discount_type_value'] : $purchase->grandDiscountTypeValue,
                grandDiscountAmount: isset($data['grand_discount_amount']) ? (float)$data['grand_discount_amount'] : $purchase->grandDiscountAmount,
                grandAfterDiscount: isset($data['grand_after_discount']) ? (float)$data['grand_after_discount'] : $purchase->grandAfterDiscount,
                grandTax: isset($data['grand_tax']) ? (float)$data['grand_tax'] : $purchase->grandTax,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $purchase->grandTotal,
                createdAt: $purchase->createdAt,
                createdBy: $purchase->createdBy,
                updatedBy: $userId,
            );

            $savedPurchase = $this->purchaseRepo->save($updatedPurchase);

            $existingItems = $this->purchaseRepo->findItemsByPurchase($id);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                $itemService = isset($itemData['service']) ? (int)$itemData['service'] : 0;

                $itemId = !empty($itemData['id']) ? (int)$itemData['id'] : null;

                if ($itemId === null && $itemService <= 0) {
                    continue;
                }

                if ($itemId !== null) {
                    $incomingIds[] = $itemId;
                }

                $item = new PurchaseItem(
                    id: $itemId,
                    organizationId: $orgId,
                    purchaseId: $id,
                    service: $itemService,
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    qty: (float)($itemData['qty'] ?? 1.0),
                    rate: (float)($itemData['rate'] ?? 0.0),
                    discountType: !empty($itemData['discount_type']) ? trim((string)$itemData['discount_type']) : null,
                    discountTypeValue: (float)($itemData['discount_type_value'] ?? 0.0),
                    discountAmount: (float)($itemData['discount_amount'] ?? 0.0),
                    subTotal: (float)($itemData['sub_total'] ?? 0.0),
                    tax: (float)($itemData['tax'] ?? 0.0),
                    taxAmount: (float)($itemData['tax_amount'] ?? 0.0),
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->purchaseRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->purchaseRepo->deleteItemsByIds($deletedIds, $id);
            }

            $this->deletePurchaseJournal($id);
            $this->createPurchaseJournal($id, $purchaseDate, $orgId);

            $this->db->commit();

            PdfGeneratorHelper::ensure('purchases', (int)$id);

            return $savedPurchase;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deletePurchase(int $id, int $orgId): bool
    {
        $this->getPurchase($id, $orgId);

        $this->db->beginTransaction();
        try {
            $this->deletePurchaseJournal($id);
            $result = $this->purchaseRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validatePurchaseData(array $data, int $orgId): void
    {
        if (empty($data['vendor_id']) || (int)$data['vendor_id'] <= 0) {
            throw new ValidationException(['vendor_id' => "Please select Vendor."]);
        }
        // Verify vendor exists
        $vendor = $this->vendorRepo->find((int)$data['vendor_id'], $orgId);
        if ($vendor === null) {
            throw new ValidationException(['vendor_id' => "Selected vendor does not exist."]);
        }
        if (empty($data['purchase_date'])) {
            throw new ValidationException(['purchase_date' => "Please select Purchase Date."]);
        }
        if (empty($data['warehouse_id']) || $data['warehouse_id'] === 'Please select') {
            throw new ValidationException(['warehouse_id' => "Please select Warehouse."]);
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

    private function deletePurchaseJournal(int $purchaseId): void
    {
        $journalId = $this->db->fetchOne(
            "SELECT id FROM `{DB::JOURNALS}` WHERE reference_type = 'purchase' AND reference_id = :ref_id LIMIT 1",
            ['ref_id' => $purchaseId]
        );

        if ($journalId !== null) {
            $jid = (int)$journalId['id'];
            $this->db->execute("DELETE FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :jid", ['jid' => $jid]);
            $this->db->execute("DELETE FROM `{DB::JOURNALS}` WHERE id = :jid", ['jid' => $jid]);
        }
    }

    private function createPurchaseJournal(int $purchaseId, string $purchaseDate, int $orgId): void
    {
        $purchase = $this->getPurchase($purchaseId, $orgId);
        if ($purchase->grandTotal <= 0) {
            return;
        }

        $ap = $this->db->fetchOne(
            "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('2100','2110','2200') OR account_name LIKE '%Payable%' OR account_name LIKE '%Liability%' LIMIT 1"
        );
        $expense = $this->db->fetchOne(
            "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('5100','5200','5000') OR account_name LIKE '%Purchases%' OR account_name LIKE '%Expense%' LIMIT 1"
        );

        if ($ap === null || $expense === null) {
            return;
        }

        $vendorName = 'Vendor ID: ' . $purchase->vendorId;
        if ($purchase->vendorId > 0) {
            $vRow = $this->db->fetchOne(
                "SELECT display_name FROM `{DB::VENDORS}` WHERE id = :id LIMIT 1",
                ['id' => $purchase->vendorId]
            );
            if ($vRow !== null) {
                $vendorName = (string)$vRow['display_name'];
            }
        }

        $journalItems = [
            ['account' => (int)$expense['id'], 'debit' => $purchase->grandTotal, 'credit' => 0.0, 'description' => 'Purchase #' . $purchase->purchaseNo],
            ['account' => (int)$ap['id'], 'debit' => 0.0, 'credit' => $purchase->grandTotal, 'description' => 'Purchase #' . $purchase->purchaseNo . ' - ' . $vendorName],
        ];

        $this->journalService->createJournal(
            [
                'reference_type' => 'purchase',
                'reference_id' => $purchaseId,
                'reference_no' => $purchase->purchaseNo,
                'journal_date' => $purchaseDate,
                'notes' => 'Purchase #' . $purchase->purchaseNo . ' - ' . $vendorName,
                'currency' => 'AED',
                'journal_status' => 'posted',
                'reporting_method' => 'accrual',
                'warehouse_id' => $purchase->warehouseId,
                'grand_subtotal' => $purchase->grandTotal,
                'grand_total' => $purchase->grandTotal,
            ],
            $journalItems,
            $orgId,
            0
        );
    }
}
