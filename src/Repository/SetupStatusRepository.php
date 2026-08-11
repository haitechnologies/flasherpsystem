<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SetupStatus;
use App\Helper\SlugHelper;
use App\Core\ErrorCapture;

class SetupStatusRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?SetupStatus
    {
        $sql = "SELECT id, type, value, `key`, is_active, created_by, created_at
                FROM `{DB::SETUP_STATUSES}`
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
                    FROM `{DB::SETUP_STATUSES}`
                    WHERE type = :type
                    ORDER BY value ASC";
            $params = ['type' => $type];
        } else {
            $sql = "SELECT id, type, value, `key`, is_active, created_by, created_at
                    FROM `{DB::SETUP_STATUSES}`
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
            $sql = "SELECT id FROM `{DB::SETUP_STATUSES}` WHERE type = :type AND value = :value AND id != :exclude_id LIMIT 1";
            $params = ['type' => $type, 'value' => $value, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::SETUP_STATUSES}` WHERE type = :type AND value = :value LIMIT 1";
            $params = ['type' => $type, 'value' => $value];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    public function insert(SetupStatus $status): int
    {
        $sql = "INSERT INTO `{DB::SETUP_STATUSES}` (type, value, `key`, is_active, created_by)
                VALUES (:type, :value, :key, :is_active, :created_by)";

        $params = [
            'type' => $status->statusType,
            'value' => $status->statusName,
            'key' => SlugHelper::slugify($status->statusName),
            'is_active' => $status->isActive ? 1 : 0,
            'created_by' => $status->createdBy,
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

        $sql = "UPDATE `{DB::SETUP_STATUSES}` SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SetupStatusRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{DB::SETUP_STATUSES}` WHERE id = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): SetupStatus
    {
        return new SetupStatus(
            id: (int)$row['id'],
            statusName: (string)$row['value'],
            statusType: (string)($row['type'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
