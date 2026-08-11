<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Attendance;
use App\Core\ErrorCapture;

class AttendanceRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Attendance
    {
        $sql = "SELECT id, employee_id, work_date, check_in, check_out, total_hours, status, created_by, created_at
                FROM `" . DB::ATTENDANCE . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, employee_id, work_date, check_in, check_out, total_hours, status, created_by, created_at
                FROM `" . DB::ATTENDANCE . "` ORDER BY work_date DESC, id DESC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function insert(Attendance $item): int
    {
        $sql = "INSERT INTO `" . DB::ATTENDANCE . "` (employee_id, work_date, check_in, check_out, total_hours, status, created_by)
                VALUES (:employee_id, :work_date, :check_in, :check_out, :total_hours, :status, :created_by)";
        return (int)$this->db->insert($sql, [
            'employee_id' => $item->employeeId,
            'work_date' => $item->workDate,
            'check_in' => $item->checkIn,
            'check_out' => $item->checkOut,
            'total_hours' => $item->totalHours,
            'status' => $item->status,
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
        $sql = "UPDATE `" . DB::ATTENDANCE . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AttendanceRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::ATTENDANCE . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Attendance
    {
        return new Attendance(
            id: (int)$row['id'],
            employeeId: (int)($row['employee_id'] ?? 0),
            workDate: (string)($row['work_date'] ?? ''),
            checkIn: isset($row['check_in']) && $row['check_in'] !== '' ? (string)$row['check_in'] : null,
            checkOut: isset($row['check_out']) && $row['check_out'] !== '' ? (string)$row['check_out'] : null,
            totalHours: (float)($row['total_hours'] ?? 0),
            status: (string)($row['status'] ?? 'present'),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: isset($row['created_at']) && $row['created_at'] !== '' ? (string)$row['created_at'] : null,
        );
    }
}
