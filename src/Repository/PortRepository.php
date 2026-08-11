<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Port;
use App\Core\ErrorCapture;

class PortRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Port
    {
        $sql = "SELECT id, port_name, port_code, country_id, is_active, created_by, created_at
                FROM `{DB::PORTS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, port_name, port_code, country_id, is_active, created_by, created_at
                FROM `{DB::PORTS}` ORDER BY port_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::PORTS}` WHERE port_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::PORTS}` WHERE port_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Port $item): int
    {
        $sql = "INSERT INTO `{DB::PORTS}` (port_name, port_code, country_id, is_active, created_by)
                VALUES (:port_name, :port_code, :country_id, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'port_name' => $item->portName,
            'port_code' => $item->portCode,
            'country_id' => $item->countryId,
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
        $sql = "UPDATE `{DB::PORTS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("PortRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::PORTS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Port
    {
        return new Port(
            id: (int)$row['id'],
            portName: (string)($row['port_name'] ?? ''),
            portCode: (string)($row['port_code'] ?? ''),
            countryId: (int)($row['country_id'] ?? 0),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
