<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Category;
use App\Core\ErrorCapture;

class CategoryRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Category
    {
        $sql = "SELECT id, name, slug, description, icon, meta_title, meta_description, is_active, created_by, created_at
                FROM `{DB::CATEGORIES}`
                WHERE id = :id";

        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, name, slug, description, icon, meta_title, meta_description, is_active, created_by, created_at
                FROM `{DB::CATEGORIES}`
                ORDER BY name ASC";

        $rows = $this->db->fetchAll($sql);
        return array_map($this->mapRowToDto(...), $rows);
    }

    public function exists(string $slug, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::CATEGORIES}` WHERE slug = :slug AND id != :exclude_id LIMIT 1";
            $params = ['slug' => $slug, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::CATEGORIES}` WHERE slug = :slug LIMIT 1";
            $params = ['slug' => $slug];
        }

        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Category $category): int
    {
        $sql = "INSERT INTO `{DB::CATEGORIES}`
                (name, slug, description, icon, meta_title, meta_description, is_active, created_by)
                VALUES (:name, :slug, :description, :icon, :meta_title, :meta_description, :is_active, :created_by)";

        $params = [
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon' => $category->icon,
            'meta_title' => $category->metaTitle,
            'meta_description' => $category->metaDescription,
            'is_active' => $category->isActive ? 1 : 0,
            'created_by' => $category->createdBy,
        ];

        return (int)$this->db->insert($sql, $params);
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];

        foreach ($data as $column => $value) {
            $key = 'u_' . str_replace('.', '_', $column);
            $sets[] = "`{$column}` = :{$key}";
            $params[$key] = $value;
        }

        $params['id'] = $id;
        $sql = "UPDATE `{DB::CATEGORIES}` SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = :id";

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("CategoryRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::CATEGORIES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Category
    {
        return new Category(
            id: (int)$row['id'],
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
