<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\AttendancePunch;
use App\Core\ErrorCapture;

class AttendancePunchRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?AttendancePunch
    {
        $sql = "SELECT id, organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced, created_at
                FROM `" . DB::ATTENDANCE_PUNCHES . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findByDateRange(int $orgId, string $from, string $to): array
    {
        $sql = "SELECT id, organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced, created_at
                FROM `" . DB::ATTENDANCE_PUNCHES . "`
                WHERE organization_id = :org_id AND punch_time >= :from AND punch_time < :to
                ORDER BY employee_id, punch_time ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId, 'from' => $from, 'to' => $to]));
    }

    public function findByEmployeeAndDate(int $employeeId, string $workDate): array
    {
        $sql = "SELECT id, organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced, created_at
                FROM `" . DB::ATTENDANCE_PUNCHES . "`
                WHERE employee_id = :emp_id AND punch_time >= :from AND punch_time < :to
                ORDER BY punch_time ASC";
        $from = $workDate . ' 00:00:00';
        $to = $workDate . ' 23:59:59';
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['emp_id' => $employeeId, 'from' => $from, 'to' => $to]));
    }

    public function findByDeviceAfter(int $deviceId, int $orgId, string $after): array
    {
        $sql = "SELECT id, organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced, created_at
                FROM `" . DB::ATTENDANCE_PUNCHES . "`
                WHERE device_id = :device_id AND organization_id = :org_id AND punch_time > :after
                ORDER BY punch_time ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['device_id' => $deviceId, 'org_id' => $orgId, 'after' => $after]));
    }

    public function findByDeviceAfterUnsynced(int $deviceId, int $orgId, string $after): array
    {
        $sql = "SELECT id, organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced, created_at
                FROM `" . DB::ATTENDANCE_PUNCHES . "`
                WHERE device_id = :device_id AND organization_id = :org_id AND punch_time > :after AND is_synced = 0
                ORDER BY punch_time ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['device_id' => $deviceId, 'org_id' => $orgId, 'after' => $after]));
    }

    public function batchInsert(array $punches): int
    {
        if (empty($punches)) {
            return 0;
        }

        $inserted = 0;
        foreach ($punches as $punch) {
            try {
                $sql = "INSERT IGNORE INTO `" . DB::ATTENDANCE_PUNCHES . "` (organization_id, device_id, employee_id, zk_user_id, punch_time, punch_type, verification_mode, status, is_synced)
                        VALUES (:organization_id, :device_id, :employee_id, :zk_user_id, :punch_time, :punch_type, :verification_mode, :status, 0)";
                $this->db->insert($sql, [
                    'organization_id' => $punch['organization_id'],
                    'device_id' => $punch['device_id'],
                    'employee_id' => $punch['employee_id'] ?? 0,
                    'zk_user_id' => (string)$punch['zk_user_id'],
                    'punch_time' => $punch['punch_time'],
                    'punch_type' => (int)($punch['punch_type'] ?? 0),
                    'verification_mode' => (int)($punch['verification_mode'] ?? 0),
                    'status' => (int)($punch['status'] ?? 0),
                ]);
                $inserted++;
            } catch (\Throwable $e) {
                ErrorCapture::record("AttendancePunchRepository: batchInsert error: " . $e->getMessage());
            }
        }
        return $inserted;
    }

    public function markSynced(int $id): bool
    {
        try {
            $this->db->execute("UPDATE `" . DB::ATTENDANCE_PUNCHES . "` SET is_synced = 1 WHERE id = :id", ['id' => $id]);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AttendancePunchRepository: markSynced failed: " . $e->getMessage());
            return false;
        }
    }

    public function markBatchSynced(array $ids): bool
    {
        if (empty($ids)) {
            return true;
        }
        try {
            $placeholders = [];
            $params = [];
            foreach ($ids as $i => $id) {
                $key = 'id_' . $i;
                $placeholders[] = ':' . $key;
                $params[$key] = (int)$id;
            }
            $sql = "UPDATE `" . DB::ATTENDANCE_PUNCHES . "` SET is_synced = 1 WHERE id IN (" . implode(',', $placeholders) . ")";
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AttendancePunchRepository: markBatchSynced failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::ATTENDANCE_PUNCHES . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): AttendancePunch
    {
        return new AttendancePunch(
            id: (int)$row['id'],
            organizationId: (int)($row['organization_id'] ?? 0),
            deviceId: (int)($row['device_id'] ?? 0),
            employeeId: (int)($row['employee_id'] ?? 0),
            zkUserId: (string)($row['zk_user_id'] ?? ''),
            punchTime: (string)($row['punch_time'] ?? ''),
            punchType: (int)($row['punch_type'] ?? 0),
            verificationMode: (int)($row['verification_mode'] ?? 0),
            status: (int)($row['status'] ?? 0),
            isSynced: (int)($row['is_synced'] ?? 0),
            createdAt: isset($row['created_at']) && $row['created_at'] !== '' ? (string)$row['created_at'] : null,
        );
    }
}
