<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\CategoryHsCode;
use App\Core\ErrorCapture;

class CategoryHsCodeRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?CategoryHsCode
    {
        $sql = "SELECT id, notes, description, is_active, created_by, created_at
                FROM `{DB::CATEGORY_HS_CODES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, notes, description, is_active, created_by, created_at
                FROM `{DB::CATEGORY_HS_CODES}` ORDER BY notes ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::CATEGORY_HS_CODES}` WHERE notes = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::CATEGORY_HS_CODES}` WHERE notes = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(CategoryHsCode $item): int
    {
        $sql = "INSERT INTO `{DB::CATEGORY_HS_CODES}` (notes, description, is_active, created_by)
                VALUES (:notes, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'notes' => $item->notes,
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
        $sql = "UPDATE `{DB::CATEGORY_HS_CODES}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("CategoryHsCodeRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::CATEGORY_HS_CODES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): CategoryHsCode
    {
        return new CategoryHsCode(
            id: (int)$row['id'],
            notes: (string)($row['notes'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
