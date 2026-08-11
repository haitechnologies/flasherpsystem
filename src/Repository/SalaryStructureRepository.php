<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SalaryStructure;
use App\Core\ErrorCapture;

class SalaryStructureRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?SalaryStructure
    {
        $sql = "SELECT id, organization_id, employee_id, component_id, amount, effective_from, effective_to, is_basic, created_by, created_at
                FROM `{DB::SALARY_STRUCTURES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findByEmployee(int $employeeId, int $orgId): array
    {
        $sql = "SELECT id, organization_id, employee_id, component_id, amount, effective_from, effective_to, is_basic, created_by, created_at
                FROM `{DB::SALARY_STRUCTURES}` WHERE employee_id = :employee_id AND organization_id = :org_id
                ORDER BY is_basic DESC, effective_from DESC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['employee_id' => $employeeId, 'org_id' => $orgId]));
    }

    public function findByEmployeeIndexed(int $employeeId, int $orgId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT id, organization_id, employee_id, component_id, amount, effective_from, effective_to, is_basic, created_by, created_at
             FROM `{DB::SALARY_STRUCTURES}` WHERE employee_id = :employee_id AND organization_id = :org_id
             ORDER BY is_basic DESC, effective_from DESC",
            ['employee_id' => $employeeId, 'org_id' => $orgId]
        );
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int)$row['component_id']] = $this->mapRowToDto($row);
        }
        return $indexed;
    }

    public function findAll(int $orgId): array
    {
        $sql = "SELECT id, organization_id, employee_id, component_id, amount, effective_from, effective_to, is_basic, created_by, created_at
                FROM `{DB::SALARY_STRUCTURES}` WHERE organization_id = :org_id ORDER BY employee_id, is_basic DESC, effective_from DESC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId]));
    }

    public function insert(SalaryStructure $item): int
    {
        $sql = "INSERT INTO `{DB::SALARY_STRUCTURES}` (organization_id, employee_id, component_id, amount, effective_from, effective_to, is_basic, created_by)
                VALUES (:org_id, :employee_id, :component_id, :amount, :effective_from, :effective_to, :is_basic, :created_by)";
        return (int)$this->db->insert($sql, [
            'org_id' => $item->organizationId,
            'employee_id' => $item->employeeId,
            'component_id' => $item->componentId,
            'amount' => $item->amount,
            'effective_from' => $item->effectiveFrom,
            'effective_to' => $item->effectiveTo,
            'is_basic' => $item->isBasic ? 1 : 0,
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
        $sql = "UPDATE `{DB::SALARY_STRUCTURES}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("SalaryStructureRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function deleteByEmployee(int $employeeId, int $orgId): void
    {
        $this->db->execute(
            "DELETE FROM `{DB::SALARY_STRUCTURES}` WHERE employee_id = :employee_id AND organization_id = :org_id",
            ['employee_id' => $employeeId, 'org_id' => $orgId]
        );
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->db->execute("DELETE FROM `{DB::SALARY_STRUCTURES}` WHERE id = :id AND organization_id = :org_id", ['id' => $id, 'org_id' => $orgId]);
        return true;
    }

    private function mapRowToDto(array $row): SalaryStructure
    {
        return new SalaryStructure(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            employeeId: (int)$row['employee_id'],
            componentId: (int)$row['component_id'],
            amount: (float)$row['amount'],
            effectiveFrom: $row['effective_from'] !== null ? (string)$row['effective_from'] : null,
            effectiveTo: $row['effective_to'] !== null ? (string)$row['effective_to'] : null,
            isBasic: !empty($row['is_basic']),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: $row['created_at'] ?? null,
        );
    }
}
