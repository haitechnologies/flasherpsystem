<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Journal;
use App\Model\JournalItem;

class JournalRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?Journal
    {
        $sql = "SELECT * FROM `{DB::JOURNALS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToJournal($row);
    }

    public function findItemsByJournal(int $journalId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :journal_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['journal_id' => $journalId, 'org_id' => $orgId]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToJournalItem($row);
        }
        return $items;
    }

    public function save(Journal $journal): Journal
    {
        if ($journal->id === null) {
            return $this->insert($journal);
        }
        return $this->update($journal);
    }

    private function insert(Journal $journal): Journal
    {
        $sql = "INSERT INTO `{DB::JOURNALS}` (
                    organization_id, journal_status, journal_no, journal_date, reference_no,
                    reference_type, reference_id, notes, reporting_method, currency,
                    grand_subtotal, grand_total, warehouse_id, is_active, created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :journal_status, :journal_no, :journal_date, :reference_no,
                    :reference_type, :reference_id, :notes, :reporting_method, :currency,
                    :grand_subtotal, :grand_total, :warehouse_id, :is_active, NOW(), NOW(), :created_by
                )";

        $params = $journal->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $journal->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted journal.");
        }

        return $inserted;
    }

    private function update(Journal $journal): Journal
    {
        $sql = "UPDATE `{DB::JOURNALS}` SET
                    journal_status = :journal_status,
                    journal_no = :journal_no,
                    journal_date = :journal_date,
                    reference_no = :reference_no,
                    reference_type = :reference_type,
                    reference_id = :reference_id,
                    notes = :notes,
                    reporting_method = :reporting_method,
                    currency = :currency,
                    grand_subtotal = :grand_subtotal,
                    grand_total = :grand_total,
                    warehouse_id = :warehouse_id,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $journal->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$journal->id, $journal->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated journal.");
        }

        return $updated;
    }

    public function saveItem(JournalItem $item): JournalItem
    {
        if ($item->id === null) {
            return $this->insertItem($item);
        }
        return $this->updateItem($item);
    }

    private function insertItem(JournalItem $item): JournalItem
    {
        $sql = "INSERT INTO `{DB::JOURNAL_ITEMS}` (
                    organization_id, journal_id, account, description, debit, credit, reference_no,
                    created_at, updated_at, created_by
                ) VALUES (
                    :organization_id, :journal_id, :account, :description, :debit, :credit, :reference_no,
                    NOW(), NOW(), :created_by
                )";

        $params = $item->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->findItem($insertId, $item->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted journal item.");
        }

        return $inserted;
    }

    private function updateItem(JournalItem $item): JournalItem
    {
        $sql = "UPDATE `{DB::JOURNAL_ITEMS}` SET
                    account = :account,
                    description = :description,
                    debit = :debit,
                    credit = :credit,
                    reference_no = :reference_no,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :organization_id";

        $params = $item->toArray();
        unset($params['journal_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findItem((int)$item->id, $item->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated journal item.");
        }

        return $updated;
    }

    public function findItem(int $id, int $orgId): ?JournalItem
    {
        $sql = "SELECT * FROM `{DB::JOURNAL_ITEMS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToJournalItem($row);
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->deleteItemsByJournal($id, $orgId);
        $sql = "DELETE FROM `{DB::JOURNALS}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function deleteItemsByJournal(int $journalId, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id = :journal_id AND organization_id = :org_id";
        $this->db->execute($sql, ['journal_id' => $journalId, 'org_id' => $orgId]);
        return true;
    }

    public function deleteItemsByIds(array $ids, int $journalId, int $orgId): void
    {
        if (empty($ids)) {
            return;
        }
        $placeholders = [];
        $params = ['journal_id' => $journalId, 'org_id' => $orgId];
        foreach ($ids as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int)$id;
        }
        $inClause = implode(', ', $placeholders);
        $sql = "DELETE FROM `{DB::JOURNAL_ITEMS}` 
                WHERE id IN ($inClause) AND journal_id = :journal_id AND organization_id = :org_id";
        $this->db->execute($sql, $params);
    }

    public function getLastJournalNo(string $prefix, int $orgId): ?string
    {
        $sql = "SELECT journal_no FROM `{DB::JOURNALS}` WHERE journal_no LIKE :prefix AND organization_id = :org_id ORDER BY id DESC LIMIT 1";
        $row = $this->db->fetchOne($sql, ['prefix' => $prefix . '%', 'org_id' => $orgId]);
        return $row !== null ? (string)$row['journal_no'] : null;
    }

    /**
     * Find journals by their reference (e.g. payment_received / payment_received_void).
     */
    public function findByReference(string $referenceType, int $referenceId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::JOURNALS}`
                WHERE reference_type = :reference_type AND reference_id = :reference_id AND organization_id = :org_id
                ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['reference_type' => $referenceType, 'reference_id' => $referenceId, 'org_id' => $orgId]);
        $journals = [];
        foreach ($rows as $row) {
            $journals[] = $this->mapRowToJournal($row);
        }
        return $journals;
    }

    private function mapRowToJournal(array $row): Journal
    {
        return new Journal(
            id: (int)$row['id'],
            organizationId: (int)($row['organization_id'] ?? 0),
            journalStatus: (string)($row['journal_status'] ?? ''),
            journalNo: (string)($row['journal_no'] ?? ''),
            journalDate: (string)($row['journal_date'] ?? ''),
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            notes: (string)($row['notes'] ?? ''),
            reportingMethod: (string)($row['reporting_method'] ?? 'accrual'),
            referenceType: $row['reference_type'] !== null ? (string)$row['reference_type'] : null,
            referenceId: (int)($row['reference_id'] ?? 0),
            currency: (string)($row['currency'] ?? 'AED'),
            grandSubtotal: (float)($row['grand_subtotal'] ?? 0.0),
            grandTotal: (float)($row['grand_total'] ?? 0.0),
            warehouseId: (int)($row['warehouse_id'] ?? 0),
            isActive: (bool)($row['is_active'] ?? true),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }

    private function mapRowToJournalItem(array $row): JournalItem
    {
        return new JournalItem(
            id: (int)$row['id'],
            organizationId: (int)($row['organization_id'] ?? 0),
            journalId: (int)$row['journal_id'],
            account: (int)$row['account'],
            description: $row['description'] !== null ? (string)$row['description'] : null,
            debit: (float)($row['debit'] ?? 0.0),
            credit: (float)($row['credit'] ?? 0.0),
            referenceNo: $row['reference_no'] !== null ? (string)$row['reference_no'] : null,
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }
}
