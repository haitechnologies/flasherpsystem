<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Expense;
use App\Model\ExpenseItem;

class ExpenseRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?Expense
    {
        $sql = "SELECT id, organization_id, expense_date, paid_through, vendor_id, reference_no,
                       customer_id, billable, grand_total, expense_status, publish, is_active,
                       created_at, updated_at, created_by, updated_by
                FROM `{DB::EXPENSES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToExpense($row);
    }

    public function findItemsByExpense(int $expenseId, int $orgId): array
    {
        $sql = "SELECT id, organization_id, expense_id, expense_account, description, total,
                       created_at, updated_at, created_by
                FROM `{DB::EXPENSE_ITEMS}` WHERE expense_id = :expense_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['expense_id' => $expenseId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToExpenseItem($row);
        }
        return $items;
    }

    public function save(Expense $expense): Expense
    {
        if ($expense->id === null) {
            return $this->insert($expense);
        }
        return $this->update($expense);
    }

    private function insert(Expense $expense): Expense
    {
        $sql = "INSERT INTO `{DB::EXPENSES}` (
                    organization_id, expense_date, paid_through, vendor_id, reference_no,
                    customer_id, billable, grand_total, is_active, created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :expense_date, :paid_through, :vendor_id, :reference_no,
                    :customer_id, :billable, :grand_total, :is_active, NOW(), NOW(), :created_by
                )";

        $params = $expense->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $expense->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted expense.");
        }

        return $inserted;
    }

    private function update(Expense $expense): Expense
    {
        $sql = "UPDATE `{DB::EXPENSES}` SET
                    expense_date = :expense_date,
                    paid_through = :paid_through,
                    vendor_id = :vendor_id,
                    reference_no = :reference_no,
                    customer_id = :customer_id,
                    billable = :billable,
                    grand_total = :grand_total,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $expense->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$expense->id, $expense->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated expense.");
        }

        return $updated;
    }

    public function saveItem(ExpenseItem $item): ExpenseItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(ExpenseItem $item): ExpenseItem
    {
        $sql = "INSERT INTO `{DB::EXPENSE_ITEMS}` (
                    organization_id, expense_id, expense_account, description, total,
                    created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :expense_id, :expense_account, :description, :total,
                    NOW(), NOW(), :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted expense item.");
        }

        return $inserted;
    }

    private function updateItem(ExpenseItem $item): ExpenseItem
    {
        $sql = "UPDATE `{DB::EXPENSE_ITEMS}` SET
                    expense_account = :expense_account,
                    description = :description,
                    total = :total,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :organization_id";

        $params = $item->toArray();
        unset($params['expense_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id, $item->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated expense item.");
        }

        return $updated;
    }

    public function findItem(int $id, int $orgId): ?ExpenseItem
    {
        $sql = "SELECT id, organization_id, expense_id, expense_account, description, total,
                       created_at, updated_at, created_by
                FROM `{DB::EXPENSE_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToExpenseItem($row);
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByExpense($id, $orgId);
        $sql = "DELETE FROM `{DB::EXPENSES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByExpense(int $expenseId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::EXPENSE_ITEMS}` WHERE expense_id = :expense_id AND organization_id = :org_id";
        $this->db->execute($sql, ['expense_id' => $expenseId, 'org_id' => $orgId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $expenseId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['expense_id' => $expenseId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::EXPENSE_ITEMS}` 
                WHERE id IN ($inClause) AND expense_id = :expense_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    private function mapRowToExpense(array $row): Expense
    {
        return new Expense(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            expenseDate: (string)$row['expense_date'],
            paidThrough: (int)$row['paid_through'],
            vendorId: (int)($row['vendor_id'] ?? 0),
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            customerId: (int)($row['customer_id'] ?? 0),
            billable: (bool)($row['billable'] ?? false),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            isActive: (bool)($row['is_active'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }

    private function mapRowToExpenseItem(array $row): ExpenseItem
    {
        return new ExpenseItem(
            id: (int)$row['id'],
            organizationId: (int)($row['organization_id'] ?? 0),
            expenseId: (int)$row['expense_id'],
            expenseAccount: (int)$row['expense_account'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            total: (float)($row['total'] ?? 0.0),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }
}
