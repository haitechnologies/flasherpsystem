<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\TaxTreatment;
use App\Core\ErrorCapture;

class TaxTreatmentRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?TaxTreatment
    {
        $sql = "SELECT id, tax_treatment, description, is_active, created_by, created_at
                FROM `{DB::TAX_TREATMENTS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, tax_treatment, description, is_active, created_by, created_at
                FROM `{DB::TAX_TREATMENTS}` ORDER BY tax_treatment ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::TAX_TREATMENTS}` WHERE tax_treatment = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::TAX_TREATMENTS}` WHERE tax_treatment = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(TaxTreatment $item): int
    {
        $sql = "INSERT INTO `{DB::TAX_TREATMENTS}` (tax_treatment, description, is_active, created_by)
                VALUES (:tax_treatment, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'tax_treatment' => $item->taxTreatment,
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
        $sql = "UPDATE `{DB::TAX_TREATMENTS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("TaxTreatmentRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::TAX_TREATMENTS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): TaxTreatment
    {
        return new TaxTreatment(
            id: (int)$row['id'],
            taxTreatment: (string)($row['tax_treatment'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
