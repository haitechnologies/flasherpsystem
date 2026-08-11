<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Carrier;
use App\Core\ErrorCapture;

class CarrierRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Carrier
    {
        $sql = "SELECT id, carrier_name, is_active, created_by, created_at
                FROM `{DB::CARRIERS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, carrier_name, is_active, created_by, created_at
                FROM `{DB::CARRIERS}` ORDER BY carrier_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::CARRIERS}` WHERE carrier_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::CARRIERS}` WHERE carrier_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Carrier $item): int
    {
        $sql = "INSERT INTO `{DB::CARRIERS}` (carrier_name, is_active, created_by)
                VALUES (:carrier_name, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'carrier_name' => $item->carrierName,
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
        $sql = "UPDATE `{DB::CARRIERS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("CarrierRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::CARRIERS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Carrier
    {
        return new Carrier(
            id: (int)$row['id'],
            carrierName: (string)($row['carrier_name'] ?? ''),
            description: '',
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
