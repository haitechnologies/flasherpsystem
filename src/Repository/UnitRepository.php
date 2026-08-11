<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Unit;
use App\Core\ErrorCapture;

class UnitRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Unit
    {
        $sql = "SELECT id, unit, publish, is_active, created_by, created_at
                FROM `{DB::UNITS}`
                WHERE id = :id";

        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }

        return $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, unit, publish, is_active, created_by, created_at
                FROM `{DB::UNITS}`
                ORDER BY unit ASC";

        $rows = $this->db->fetchAll($sql);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->mapRowToDto($row);
        }

        return $items;
    }

    public function exists(string $unit, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::UNITS}` WHERE unit = :unit AND id != :exclude_id LIMIT 1";
            $params = ['unit' => $unit, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::UNITS}` WHERE unit = :unit LIMIT 1";
            $params = ['unit' => $unit];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    public function insert(Unit $unit): int
    {
        $sql = "INSERT INTO `{DB::UNITS}` (unit, publish, is_active, created_by)
                VALUES (:unit, :publish, :is_active, :created_by)";

        $params = [
            'unit' => $unit->unitName,
            'publish' => $unit->isActive ? 1 : 0,
            'is_active' => $unit->isActive ? 1 : 0,
            'created_by' => $unit->createdBy,
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

        $sql = "UPDATE `{DB::UNITS}` SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("UnitRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{DB::UNITS}` WHERE id = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): Unit
    {
        return new Unit(
            id: (int)$row['id'],
            unitName: (string)$row['unit'],
            isActive: (bool)(($row['is_active'] ?? $row['publish'] ?? true)),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
