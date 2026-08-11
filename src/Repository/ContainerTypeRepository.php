<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\ContainerType;
use App\Core\ErrorCapture;

class ContainerTypeRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?ContainerType
    {
        $sql = "SELECT id, container_type, description, is_active, created_by, created_at
                FROM `{DB::CONTAINER_TYPES}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, container_type, description, is_active, created_by, created_at
                FROM `{DB::CONTAINER_TYPES}` ORDER BY container_type ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::CONTAINER_TYPES}` WHERE container_type = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::CONTAINER_TYPES}` WHERE container_type = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(ContainerType $item): int
    {
        $sql = "INSERT INTO `{DB::CONTAINER_TYPES}` (container_type, description, is_active, created_by)
                VALUES (:container_type, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'container_type' => $item->containerType,
            'description' => $item->description,
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
        $sql = "UPDATE `{DB::CONTAINER_TYPES}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("ContainerTypeRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::CONTAINER_TYPES}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): ContainerType
    {
        return new ContainerType(
            id: (int)$row['id'],
            containerType: (string)($row['container_type'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
