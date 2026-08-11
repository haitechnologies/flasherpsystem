<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\AccountReportCategory;
use App\Core\ErrorCapture;

class AccountReportCategoryRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?AccountReportCategory
    {
        $sql = "SELECT id, category_name, publish, is_active, created_by, created_at, updated_at, updated_by
                FROM `{DB::ACCOUNTS_REPORT_CATEGORIES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, category_name, publish, is_active, created_by, created_at, updated_at, updated_by
                FROM `{DB::ACCOUNTS_REPORT_CATEGORIES}` ORDER BY category_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::ACCOUNTS_REPORT_CATEGORIES}` WHERE category_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::ACCOUNTS_REPORT_CATEGORIES}` WHERE category_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(AccountReportCategory $item): int
    {
        $sql = "INSERT INTO `{DB::ACCOUNTS_REPORT_CATEGORIES}` (category_name, is_active, created_by)
                VALUES (:category_name, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'category_name' => $item->categoryName,
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
        $sql = "UPDATE `{DB::ACCOUNTS_REPORT_CATEGORIES}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AccountReportCategoryRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::ACCOUNTS_REPORT_CATEGORIES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): AccountReportCategory
    {
        return new AccountReportCategory(
            id: (int)$row['id'],
            categoryName: (string)($row['category_name'] ?? ''),
            publish: (bool)($row['publish'] ?? true),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
            updatedAt: (string)($row['updated_at'] ?? ''),
            updatedBy: (int)($row['updated_by'] ?? 0),
        );
    }
}
