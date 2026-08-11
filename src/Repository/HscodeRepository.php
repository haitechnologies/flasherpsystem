<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Hscode;
use App\Core\ErrorCapture;

class HscodeRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Hscode
    {
        $sql = "SELECT id, code, old_code, level, duty_rate, is_active, created_by, created_at
                FROM `" . DB::HS_CODES . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, code, old_code, level, duty_rate, is_active, created_by, created_at
                FROM `" . DB::HS_CODES . "` ORDER BY code ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $code, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `" . DB::HS_CODES . "` WHERE code = :code AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `" . DB::HS_CODES . "` WHERE code = :code LIMIT 1";
        $params = $excludeId !== null ? ['code' => $code, 'exclude_id' => $excludeId] : ['code' => $code];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Hscode $item): int
    {
        $sql = "INSERT INTO `" . DB::HS_CODES . "` (code, old_code, level, duty_rate, is_active, created_by)
                VALUES (:code, :old_code, :level, :duty_rate, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'code' => $item->code,
            'old_code' => $item->oldCode,
            'level' => $item->level,
            'duty_rate' => $item->dutyRate,
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
        $sql = "UPDATE `" . DB::HS_CODES . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("HscodeRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::HS_CODES . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Hscode
    {
        return new Hscode(
            id: (int)$row['id'],
            code: (string)($row['code'] ?? ''),
            oldCode: (string)($row['old_code'] ?? ''),
            level: (int)($row['level'] ?? 0),
            dutyRate: (string)($row['duty_rate'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
