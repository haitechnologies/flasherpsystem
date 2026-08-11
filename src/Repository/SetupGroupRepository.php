<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SetupGroup;
use App\Helper\SlugHelper;
use App\Core\ErrorCapture;

class SetupGroupRepository
{
    private const TYPE = 'setup_group';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?SetupGroup
    {
        $sql = "SELECT id, type, value, `key`, description, is_active, created_by, created_at
                FROM `{DB::SETUP_GROUPS}`
                WHERE id = :id AND type = :type";

        $row = $this->db->fetchOne($sql, ['id' => $id, 'type' => self::TYPE]);
        if ($row === null) {
            return null;
        }

        return $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, type, value, `key`, description, is_active, created_by, created_at
                FROM `{DB::SETUP_GROUPS}`
                WHERE type = :type
                ORDER BY value ASC";

        $rows = $this->db->fetchAll($sql, ['type' => self::TYPE]);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToDto($row);
        }

        return $items;
    }

    public function exists(string $value, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::SETUP_GROUPS}` WHERE type = :type AND value = :value AND id != :exclude_id LIMIT 1";
            $params = ['type' => self::TYPE, 'value' => $value, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::SETUP_GROUPS}` WHERE type = :type AND value = :value LIMIT 1";
            $params = ['type' => self::TYPE, 'value' => $value];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    public function insert(SetupGroup $group): int
    {
        $sql = "INSERT INTO `{DB::SETUP_GROUPS}` (type, value, `key`, description, is_active, created_by)
                VALUES (:type, :value, :key, :description, :is_active, :created_by)";

        $params = [
            'type' => self::TYPE,
            'value' => $group->groupName,
            'key' => SlugHelper::slugify($group->groupName),
            'description' => $group->description,
            'is_active' => $group->isActive ? 1 : 0,
            'created_by' => $group->createdBy,
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

        $sql = "UPDATE `{DB::SETUP_GROUPS}` SET " . implode(', ', $sets) . " WHERE id = :id AND type = :type";
        $params['type'] = self::TYPE;

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SetupGroupRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{DB::SETUP_GROUPS}` WHERE id = :id AND type = :type";
        $stmt = $this->db->execute($sql, ['id' => $id, 'type' => self::TYPE]);
        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): SetupGroup
    {
        return new SetupGroup(
            id: (int)$row['id'],
            groupName: (string)$row['value'],
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
