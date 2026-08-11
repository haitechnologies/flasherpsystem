<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Account;
use App\Core\ErrorCapture;

class AccountRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Account
    {
        $sql = "SELECT id, parent_id, account_type, account_name, account_code, description, is_active, created_by, created_at
                FROM `" . DB::ACCOUNTS . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, parent_id, account_type, account_name, account_code, description, is_active, created_by, created_at
                FROM `" . DB::ACCOUNTS . "` ORDER BY account_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function findParentAccounts(): array
    {
        $sql = "SELECT id, account_name FROM `" . DB::ACCOUNTS . "` WHERE is_active = 1 ORDER BY account_name ASC";
        return $this->db->fetchAll($sql);
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Account $item): int
    {
        $sql = "INSERT INTO `" . DB::ACCOUNTS . "` (parent_id, account_type, account_name, account_code, description, is_active, created_by)
                VALUES (:parent_id, :account_type, :account_name, :account_code, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'parent_id' => $item->parentId,
            'account_type' => $item->accountType,
            'account_name' => $item->accountName,
            'account_code' => $item->accountCode,
            'description' => $item->description,
            'is_active' => $item->isActive ? 1 : 0,
            'created_by' => $item->createdBy,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $key = 'u_' . str_replace('.', '_', $col);
            $sets[] = "`{$col}` = :{$key}";
            $params[$key] = $val;
        }
        $params['id'] = $id;
        $sql = "UPDATE `" . DB::ACCOUNTS . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AccountRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::ACCOUNTS . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Account
    {
        return new Account(
            id: (int)$row['id'],
            parentId: (int)($row['parent_id'] ?? 0),
            accountType: (string)($row['account_type'] ?? ''),
            accountName: (string)($row['account_name'] ?? ''),
            accountCode: (string)($row['account_code'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
