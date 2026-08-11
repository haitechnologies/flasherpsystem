<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SaleType;
use App\Core\ErrorCapture;

class SaleTypeRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?SaleType
    {
        $sql = "SELECT id, name AS sale_type, description, is_active, created_by, created_at
                FROM `{DB::DOCUMENT_TYPES}` WHERE id = :id AND organization_id = :org_id AND context = 'sale'";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(int $orgId): array
    {
        $sql = "SELECT id, name AS sale_type, description, is_active, created_by, created_at
                FROM `{DB::DOCUMENT_TYPES}` WHERE context = 'sale' AND organization_id = :org_id ORDER BY name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId]));
    }

    public function exists(string $name, int $orgId, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::DOCUMENT_TYPES}` WHERE name = :name AND context = 'sale' AND organization_id = :org_id AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::DOCUMENT_TYPES}` WHERE name = :name AND context = 'sale' AND organization_id = :org_id LIMIT 1";
        $params = $excludeId !== null
            ? ['name' => $name, 'org_id' => $orgId, 'exclude_id' => $excludeId]
            : ['name' => $name, 'org_id' => $orgId];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(SaleType $item, int $orgId): int
    {
        $sql = "INSERT INTO `{DB::DOCUMENT_TYPES}` (organization_id, context, name, description, is_active, created_by)
                VALUES (:org_id, 'sale', :name, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'org_id'      => $orgId,
            'name'        => $item->saleType,
            'description' => $item->description,
            'is_active'   => $item->isActive ? 1 : 0,
            'created_by'  => $item->createdBy,
        ]);
    }

    public function update(int $id, array $data, int $orgId): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $key = 'u_' . str_replace('.', '_', $col);
            $sets[] = "`{$col}` = :{$key}";
            $params[$key] = $val;
        }
        $params['id'] = $id;
        $params['org_id'] = $orgId;
        $sql = "UPDATE `{DB::DOCUMENT_TYPES}` SET " . implode(', ', $sets) . " WHERE id = :id AND organization_id = :org_id AND context = 'sale'";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SaleTypeRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->db->execute(
            "DELETE FROM `{DB::DOCUMENT_TYPES}` WHERE id = :id AND organization_id = :org_id AND context = 'sale'",
            ['id' => $id, 'org_id' => $orgId]
        );
        return true;
    }

    private function mapRowToDto(array $row): SaleType
    {
        return new SaleType(
            id: (int)$row['id'],
            saleType: (string)($row['sale_type'] ?? $row['name'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
