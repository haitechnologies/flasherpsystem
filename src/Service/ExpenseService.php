<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Model\Expense;
use App\Model\ExpenseItem;
use App\Repository\ExpenseRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Helper\DateHelper;
use App\Helper\PdfGeneratorHelper;

class ExpenseService
{
    private ExpenseRepository $expenseRepo;
    private JournalService $journalService;
    private Database $db;

    public function __construct(ExpenseRepository $expenseRepo, JournalService $journalService, Database $db)
    {
        $this->expenseRepo = $expenseRepo;
        $this->journalService = $journalService;
        $this->db = $db;
    }

    public function getExpense(int $id, int $orgId): Expense
    {
        $expense = $this->expenseRepo->find($id, $orgId);
        if ($expense === null) {
            throw new NotFoundException("Expense with ID {$id} not found.");
        }
        return $expense;
    }

    public function getExpenseItems(int $expenseId, int $orgId): array
    {
        return $this->expenseRepo->findItemsByExpense($expenseId, $orgId);
    }

    public function createExpense(array $data, array $itemsData, int $orgId, int $userId): Expense
    {
        $this->validateExpenseData($data);

        if (empty($itemsData)) {
            throw new ValidationException(['items' => "No items added. Please add at least one item."]);
        }

        $this->db->beginTransaction();
        try {
            $expenseDate = $this->parseDate((string)($data['expense_date'] ?? ''));

            $expense = new Expense(
                id: null,
                organizationId: $orgId,
                expenseDate: $expenseDate,
                paidThrough: (int)($data['paid_through'] ?? 0),
                vendorId: (int)($data['vendor_id'] ?? 0),
                referenceNo: !empty($data['reference_no']) ? trim((string)$data['reference_no']) : null,
                customerId: (int)($data['customer_id'] ?? 0),
                billable: !empty($data['billable']),
                grandTotal: (float)($data['grand_total'] ?? 0.0),
                createdBy: $userId,
            );

            $savedExpense = $this->expenseRepo->save($expense);
            $expenseId = $savedExpense->id;

            if ($expenseId === null) {
                throw new \RuntimeException("Failed to insert expense header.");
            }

            foreach ($itemsData as $itemData) {
                if (empty($itemData['expense_account']) || (int)$itemData['expense_account'] <= 0) {
                    continue;
                }
                $item = new ExpenseItem(
                    id: null,
                    organizationId: $orgId,
                    expenseId: $expenseId,
                    expenseAccount: (int)$itemData['expense_account'],
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    total: (float)($itemData['total'] ?? 0.0),
                    createdBy: $userId,
                );
                $this->expenseRepo->saveItem($item);
            }

            $this->createJournalEntry($expenseId, $expenseDate, (int)($data['paid_through'] ?? 0), (int)($data['vendor_id'] ?? 0), !empty($data['billable']), (float)($data['grand_total'] ?? 0.0));

            $this->db->commit();

            PdfGeneratorHelper::ensure('expenses', (int)$expenseId);

            return $savedExpense;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateExpense(int $id, array $data, array $itemsData, int $orgId, int $userId): Expense
    {
        $expense = $this->getExpense($id, $orgId);
        $this->validateExpenseData($data);

        $this->db->beginTransaction();
        try {
            $expenseDate = isset($data['expense_date']) ? $this->parseDate((string)$data['expense_date']) : $expense->expenseDate;

            $updatedExpense = new Expense(
                id: $expense->id,
                organizationId: $expense->organizationId,
                expenseDate: $expenseDate,
                paidThrough: isset($data['paid_through']) ? (int)$data['paid_through'] : $expense->paidThrough,
                vendorId: isset($data['vendor_id']) ? (int)$data['vendor_id'] : $expense->vendorId,
                referenceNo: isset($data['reference_no']) ? (!empty($data['reference_no']) ? trim((string)$data['reference_no']) : null) : $expense->referenceNo,
                customerId: isset($data['customer_id']) ? (int)$data['customer_id'] : $expense->customerId,
                billable: isset($data['billable']) ? !empty($data['billable']) : $expense->billable,
                grandTotal: isset($data['grand_total']) ? (float)$data['grand_total'] : $expense->grandTotal,
                createdAt: $expense->createdAt,
                createdBy: $expense->createdBy,
                updatedBy: $userId,
            );

            $savedExpense = $this->expenseRepo->save($updatedExpense);

            $existingItems = $this->expenseRepo->findItemsByExpense($id, $orgId);
            $existingIds = array_map(fn($item) => $item->id, $existingItems);
            $incomingIds = [];

            foreach ($itemsData as $itemData) {
                $itemAccount = isset($itemData['expense_account']) ? (int)$itemData['expense_account'] : 0;
                $itemTotal = isset($itemData['total']) ? (float)$itemData['total'] : 0.0;

                $itemId = !empty($itemData['item_id']) ? (int)$itemData['item_id'] : null;

                if ($itemId !== null && $itemAccount <= 0 && $itemTotal <= 0.0) {
                    continue;
                }
                if ($itemId === null && ($itemAccount <= 0 || $itemTotal <= 0.0)) {
                    continue;
                }

                if ($itemId !== null) {
                    $incomingIds[] = $itemId;
                }

                $item = new ExpenseItem(
                    id: $itemId,
                    organizationId: $orgId,
                    expenseId: $id,
                    expenseAccount: $itemAccount,
                    description: !empty($itemData['description']) ? trim((string)$itemData['description']) : null,
                    total: $itemTotal,
                    createdBy: $userId,
                );
                $this->expenseRepo->saveItem($item);
            }

            $deletedIds = array_diff($existingIds, $incomingIds);
            if (!empty($deletedIds)) {
                $this->expenseRepo->deleteItemsByIds($deletedIds, $id, $orgId);
            }

            $this->deleteJournalEntry($id);
            $this->createJournalEntry($id, $expenseDate, (int)($data['paid_through'] ?? $expense->paidThrough), (int)($data['vendor_id'] ?? $expense->vendorId), $updatedExpense->billable, (float)($data['grand_total'] ?? $expense->grandTotal));

            $this->db->commit();

            PdfGeneratorHelper::ensure('expenses', (int)$id);

            return $savedExpense;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteExpense(int $id, int $orgId): bool
    {
        $this->getExpense($id, $orgId);

        $this->db->beginTransaction();
        try {
            $this->deleteJournalEntry($id);
            $result = $this->expenseRepo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function deleteJournalEntry(int $expenseId): void
    {
        $journalId = $this->db->fetchOne(
            "SELECT id FROM `{DB::JOURNALS}` WHERE reference_type = 'expense' AND reference_id = :ref_id LIMIT 1",
            ['ref_id' => $expenseId]
        );

        if ($journalId !== null) {
            $jid = (int)$journalId['id'];
            $this->db->execute("DELETE FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :jid", ['jid' => $jid]);
            $this->db->execute("DELETE FROM `{DB::JOURNALS}` WHERE id = :jid", ['jid' => $jid]);
        }
    }

    private function createJournalEntry(int $expenseId, string $expenseDate, int $paidThrough, int $vendorId, bool $billable, float $grandTotal): void
    {
        $orgId = (int)($this->db->fetchOne(
            "SELECT organization_id FROM `{DB::EXPENSES}` WHERE id = :id",
            ['id' => $expenseId]
        )['organization_id'] ?? 0);

        $items = $this->expenseRepo->findItemsByExpense($expenseId, $orgId);

        $journalItems = [];

        foreach ($items as $item) {
            $debitAccountId = $item->expenseAccount;
            if ($billable) {
                $arRow = $this->db->fetchOne(
                    "SELECT id FROM `{DB::ACCOUNTS}` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1"
                );
                $debitAccountId = $arRow !== null ? (int)$arRow['id'] : $item->expenseAccount;
            }

            $accountExists = $this->db->fetchOne(
                "SELECT id FROM `{DB::ACCOUNTS}` WHERE id = :id LIMIT 1",
                ['id' => $debitAccountId]
            );

            if ($accountExists !== null) {
                $journalItems[] = [
                    'account' => $debitAccountId,
                    'debit' => $item->total,
                    'credit' => 0.0,
                    'description' => $item->description ?? '',
                ];
            }
        }

        if ($paidThrough > 0) {
            $journalItems[] = [
                'account' => $paidThrough,
                'debit' => 0.0,
                'credit' => $grandTotal,
                'description' => 'Payment for expense',
            ];
        }

        if (count($journalItems) < 2) {
            return;
        }

        $vendorName = 'Vendor ID: ' . $vendorId;
        if ($vendorId > 0) {
            $vRow = $this->db->fetchOne(
                "SELECT display_name FROM `{DB::VENDORS}` WHERE id = :id LIMIT 1",
                ['id' => $vendorId]
            );
            if ($vRow !== null) {
                $vendorName = (string)$vRow['display_name'];
            }
        }

        $this->journalService->createJournal(
            [
                'reference_type' => 'expense',
                'reference_id' => $expenseId,
                'reference_no' => 'EXP-' . $expenseId,
                'journal_date' => $expenseDate,
                'notes' => ($billable ? 'Billable ' : 'Non-Billable ') . 'Expense - ' . $vendorName,
                'currency' => 'AED',
                'journal_status' => 'posted',
                'reporting_method' => 'accrual',
            ],
            $journalItems,
            $orgId,
            0
        );
    }

    private function validateExpenseData(array $data): void
    {
        if (empty($data['expense_date'])) {
            throw new ValidationException(['expense_date' => "Please select expense Date."]);
        }
        if (empty($data['paid_through']) || $data['paid_through'] === 'Please select') {
            throw new ValidationException(['paid_through' => "Please select Paid Through."]);
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
}
