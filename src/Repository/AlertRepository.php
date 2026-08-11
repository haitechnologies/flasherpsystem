<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Alert;
use App\Core\ErrorCapture;

class AlertRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Alert
    {
        $sql = "SELECT id, alert_name, description, type, is_active, created_by, created_at
                FROM `{DB::ALERTS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, alert_name, description, type, is_active, created_by, created_at
                FROM `{DB::ALERTS}` ORDER BY alert_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::ALERTS}` WHERE alert_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::ALERTS}` WHERE alert_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Alert $item): int
    {
        $sql = "INSERT INTO `{DB::ALERTS}` (alert_name, description, type, is_active, created_by)
                VALUES (:alert_name, :description, :type, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'alert_name' => $item->alertName,
            'description' => $item->description,
            'type' => $item->type,
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
        $sql = "UPDATE `{DB::ALERTS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AlertRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::ALERTS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Alert
    {
        return new Alert(
            id: (int)$row['id'],
            alertName: (string)($row['alert_name'] ?? ''),
            description: (string)($row['description'] ?? ''),
            type: (string)($row['type'] ?? 'general'),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
