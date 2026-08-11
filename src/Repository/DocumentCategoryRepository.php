<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\DocumentCategory;
use App\Core\ErrorCapture;

class DocumentCategoryRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?DocumentCategory
    {
        $sql = "SELECT id, document_category, document_category_type, is_active, is_mandatory, created_by, updated_by, created_at, updated_at
                FROM `{DB::DOCUMENT_CATEGORIES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, document_category, document_category_type, is_active, is_mandatory, created_by, updated_by, created_at, updated_at
                FROM `{DB::DOCUMENT_CATEGORIES}` ORDER BY document_category_type DESC, document_category ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::DOCUMENT_CATEGORIES}` WHERE document_category = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::DOCUMENT_CATEGORIES}` WHERE document_category = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(DocumentCategory $item): int
    {
        $sql = "INSERT INTO `{DB::DOCUMENT_CATEGORIES}` (document_category, document_category_type, is_active, is_mandatory, created_by)
                VALUES (:document_category, :document_category_type, :is_active, :is_mandatory, :created_by)";
        return (int)$this->db->insert($sql, [
            'document_category' => $item->documentCategory,
            'document_category_type' => $item->documentCategoryType,
            'is_active' => $item->isActive ? 1 : 0,
            'is_mandatory' => $item->isMandatory ? 1 : 0,
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
        $sql = "UPDATE `{DB::DOCUMENT_CATEGORIES}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("DocumentCategoryRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::DOCUMENT_CATEGORIES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): DocumentCategory
    {
        return new DocumentCategory(
            id: (int)$row['id'],
            documentCategory: (string)($row['document_category'] ?? ''),
            documentCategoryType: (string)($row['document_category_type'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            isMandatory: (bool)($row['is_mandatory'] ?? false),
            createdBy: (int)($row['created_by'] ?? 0),
            updatedBy: (int)($row['updated_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
            updatedAt: (string)($row['updated_at'] ?? ''),
        );
    }
}
