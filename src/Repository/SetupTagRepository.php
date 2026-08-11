<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SetupTag;
use App\Helper\SlugHelper;
use App\Core\ErrorCapture;

class SetupTagRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?SetupTag
    {
        $sql = "SELECT id, type, value, `key`, is_active, created_by, created_at
                FROM `{DB::SETUP_TAGS}`
                WHERE id = :id";

        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }

        return $this->mapRowToDto($row);
    }

    public function findAll(?string $type = null): array
    {
        if ($type !== null) {
            $sql = "SELECT id, type, value, `key`, is_active, created_by, created_at
                    FROM `{DB::SETUP_TAGS}`
                    WHERE type = :type
                    ORDER BY value ASC";
            $params = ['type' => $type];
        } else {
            $sql = "SELECT id, type, value, `key`, is_active, created_by, created_at
                    FROM `{DB::SETUP_TAGS}`
                    ORDER BY value ASC";
            $params = [];
        }

        $rows = $this->db->fetchAll($sql, $params);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToDto($row);
        }

        return $items;
    }

    public function exists(string $value, string $type, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::SETUP_TAGS}` WHERE type = :type AND value = :value AND id != :exclude_id LIMIT 1";
            $params = ['type' => $type, 'value' => $value, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::SETUP_TAGS}` WHERE type = :type AND value = :value LIMIT 1";
            $params = ['type' => $type, 'value' => $value];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    public function insert(SetupTag $tag): int
    {
        $sql = "INSERT INTO `{DB::SETUP_TAGS}` (type, value, `key`, is_active, created_by)
                VALUES (:type, :value, :key, :is_active, :created_by)";

        $params = [
            'type' => $tag->tagType,
            'value' => $tag->tagName,
            'key' => SlugHelper::slugify($tag->tagName),
            'is_active' => $tag->isActive ? 1 : 0,
            'created_by' => $tag->createdBy,
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

        $sql = "UPDATE `{DB::SETUP_TAGS}` SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SetupTagRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{DB::SETUP_TAGS}` WHERE id = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): SetupTag
    {
        return new SetupTag(
            id: (int)$row['id'],
            tagName: (string)$row['value'],
            tagType: (string)($row['type'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
