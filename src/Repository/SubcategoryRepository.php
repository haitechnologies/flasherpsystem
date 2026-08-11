<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Subcategory;
use App\Core\ErrorCapture;

class SubcategoryRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Subcategory
    {
        $sql = "SELECT id, category_id, name, slug, description, icon, meta_title, meta_description, is_active, created_by, created_at
                FROM `{DB::SUBCATEGORIES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, category_id, name, slug, description, icon, meta_title, meta_description, is_active, created_by, created_at
                FROM `{DB::SUBCATEGORIES}` ORDER BY name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $slug, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::SUBCATEGORIES}` WHERE slug = :slug AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::SUBCATEGORIES}` WHERE slug = :slug LIMIT 1";
        $params = $excludeId !== null ? ['slug' => $slug, 'exclude_id' => $excludeId] : ['slug' => $slug];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Subcategory $item): int
    {
        $sql = "INSERT INTO `{DB::SUBCATEGORIES}` (category_id, name, slug, description, icon, meta_title, meta_description, is_active, created_by)
                VALUES (:category_id, :name, :slug, :description, :icon, :meta_title, :meta_description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'category_id' => $item->categoryId,
            'name' => $item->name,
            'slug' => $item->slug,
            'description' => $item->description,
            'icon' => $item->icon,
            'meta_title' => $item->metaTitle,
            'meta_description' => $item->metaDescription,
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
        $sql = "UPDATE `{DB::SUBCATEGORIES}` SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SubcategoryRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::SUBCATEGORIES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Subcategory
    {
        return new Subcategory(
            id: (int)$row['id'],
            categoryId: (int)($row['category_id'] ?? 0),
            name: (string)($row['name'] ?? ''),
            slug: (string)($row['slug'] ?? ''),
            description: (string)($row['description'] ?? ''),
            icon: (string)($row['icon'] ?? ''),
            metaTitle: (string)($row['meta_title'] ?? ''),
            metaDescription: (string)($row['meta_description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
