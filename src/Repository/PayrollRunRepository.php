<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\PayrollRun;
use App\Core\ErrorCapture;

class PayrollRunRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?PayrollRun
    {
        $sql = "SELECT id, period_start, description, created_by, created_at
                FROM `{DB::PAYROLL_RUNS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, period_start, description, created_by, created_at
                FROM `{DB::PAYROLL_RUNS}` ORDER BY period_start ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::PAYROLL_RUNS}` WHERE period_start = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::PAYROLL_RUNS}` WHERE period_start = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(PayrollRun $item): int
    {
        $sql = "INSERT INTO `{DB::PAYROLL_RUNS}` (period_start, description, created_by)
                VALUES (:period_start, :description, :created_by)";
        return (int)$this->db->insert($sql, [
            'period_start' => $item->periodStart,
            'description' => $item->description,
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
        $sql = "UPDATE `{DB::PAYROLL_RUNS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("PayrollRunRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::PAYROLL_RUNS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): PayrollRun
    {
        return new PayrollRun(
            id: (int)$row['id'],
            periodStart: (string)($row['period_start'] ?? ''),
            description: (string)($row['description'] ?? ''),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
