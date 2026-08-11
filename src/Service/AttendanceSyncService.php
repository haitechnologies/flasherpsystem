<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Repository\AttendanceDeviceRepository;
use App\Repository\AttendancePunchRepository;
use App\Core\ErrorCapture;

/**
 * AttendanceSyncService — Syncs attendance data from ZKTeco devices.
 *
 * Connects to configured devices via ZK protocol, downloads raw punch logs,
 * stores them in erp_attendance_punches, and derives daily erp_attendance summaries.
 */
class AttendanceSyncService
{
    private Database $db;
    private AttendanceDeviceRepository $deviceRepo;
    private AttendancePunchRepository $punchRepo;

    public function __construct(
        Database $db,
        AttendanceDeviceRepository $deviceRepo,
        AttendancePunchRepository $punchRepo,
    ) {
        $this->db = $db;
        $this->deviceRepo = $deviceRepo;
        $this->punchRepo = $punchRepo;
    }

    /**
     * Sync attendance from all active devices across all organizations.
     *
     * @return array<string, array> Results per device: [device_id => ['log' => str, 'punches' => int, 'synced' => int, 'errors' => str[]]]
     */
    public function syncAll(): array
    {
        $results = [];
        $devices = $this->deviceRepo->findActiveDevices();

        foreach ($devices as $device) {
            try {
                $results[$device->id] = $this->syncDevice($device->id);
            } catch (\Throwable $e) {
                $results[$device->id] = [
                    'device_name' => $device->deviceName,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'punches_pulled' => 0,
                    'punches_inserted' => 0,
                ];
            }
        }

        return $results;
    }

    /**
     * Sync attendance from a single device.
     *
     * @return array{device_name: string, status: string, error?: string, punches_pulled: int, punches_inserted: int, attendance_derived: int}
     */
    public function syncDevice(int $deviceId): array
    {
        $device = $this->deviceRepo->find($deviceId);
        if ($device === null) {
            return [
                'device_name' => "ID:$deviceId",
                'status' => 'error',
                'error' => 'Device not found',
                'punches_pulled' => 0,
                'punches_inserted' => 0,
                'attendance_derived' => 0,
            ];
        }

        $orgId = $device->organizationId;
        $client = new ZKTecoClient();

        // Establish connection to device
        if (!$client->connect($device->ipAddress, $device->port, $device->devicePassword)) {
            $error = $client->getLastError();
            return [
                'device_name' => $device->deviceName,
                'status' => 'error',
                'error' => 'Connection failed: ' . ($error['message'] ?? 'Unknown error'),
                'punches_pulled' => 0,
                'punches_inserted' => 0,
                'attendance_derived' => 0,
            ];
        }

        try {
            // Disable real-time events during bulk data transfer
            $client->disableDevice();

            // Pull attendance logs
            $logs = $client->getAttendance();
            $punchesPulled = count($logs);

            if (empty($logs)) {
                // Update last sync time even if no new data
                $this->deviceRepo->update($deviceId, [
                    'last_sync_at' => date('Y-m-d H:i:s'),
                ]);
                $client->enableDevice();
                $client->disconnect();
                return [
                    'device_name' => $device->deviceName,
                    'status' => 'ok',
                    'punches_pulled' => 0,
                    'punches_inserted' => 0,
                    'attendance_derived' => 0,
                ];
            }

            // Map ZK user IDs to ERP employee IDs
            $zkUserMap = $this->loadEmployeeMapping($orgId);

            // Prepare punch records for insert
            $punches = [];
            $uniqueDates = [];
            $maxPunchTime = '';

            foreach ($logs as $log) {
                $zkUserId = $log['user_id'];
                $employeeId = $zkUserMap[$zkUserId] ?? 0;
                $punchTime = $log['timestamp'];

                $punches[] = [
                    'organization_id' => $orgId,
                    'device_id' => $deviceId,
                    'employee_id' => $employeeId,
                    'zk_user_id' => $zkUserId,
                    'punch_time' => $punchTime,
                    'punch_type' => $log['type'],
                    'verification_mode' => $log['verification_mode'],
                    'status' => $log['status'],
                ];

                // Track last punch time and unique dates for derivation
                if ($punchTime > $maxPunchTime) {
                    $maxPunchTime = $punchTime;
                }
                $dateKey = substr($punchTime, 0, 10);
                if ($employeeId > 0) {
                    $uniqueDates[$dateKey] = true;
                }
            }

            // Batch insert punches (dedup handled by DB UNIQUE KEY)
            $inserted = $this->punchRepo->batchInsert($punches);

            // Mark newly inserted punches as synced
            // (future enhancement: more granular tracking)

            // Derive daily attendance for affected dates
            $derivedCount = 0;
            foreach (array_keys($uniqueDates) as $date) {
                $derivedCount += $this->deriveDailyAttendance($orgId, $date);
            }

            // Update device sync info
            $updateData = [
                'last_sync_at' => date('Y-m-d H:i:s'),
            ];
            if ($maxPunchTime !== '') {
                $updateData['last_punch_at'] = $maxPunchTime;
            }
            $this->deviceRepo->update($deviceId, $updateData);

            // Re-enable device and disconnect
            $client->enableDevice();
            $client->disconnect();

            return [
                'device_name' => $device->deviceName,
                'status' => 'ok',
                'punches_pulled' => $punchesPulled,
                'punches_inserted' => $inserted,
                'attendance_derived' => $derivedCount,
            ];
        } catch (\Throwable $e) {
            // Attempt clean disconnect after error
            try {
                $client->enableDevice();
                $client->disconnect();
            } catch (\Throwable $ignore) {
            }

            return [
                'device_name' => $device->deviceName,
                'status' => 'error',
                'error' => $e->getMessage(),
                'punches_pulled' => 0,
                'punches_inserted' => 0,
                'attendance_derived' => 0,
            ];
        }
    }

    /**
     * Derive daily attendance summaries from raw punches for a given org and date.
     *
     * For each employee who has punches on the date:
     * - check_in = earliest punch time
     * - check_out = latest punch time
     * - total_hours = hours between first and last punch
     * - status = 'present'
     *
     * @return int Number of attendance records created/updated
     */
    public function deriveDailyAttendance(int $orgId, string $workDate): int
    {
        $from = $workDate . ' 00:00:00';
        $to = $workDate . ' 23:59:59';

        // Get all punches for this org and date, grouped by employee
        $sql = "SELECT employee_id, MIN(punch_time) AS first_punch, MAX(punch_time) AS last_punch, COUNT(*) AS punch_count
                FROM `" . DB::ATTENDANCE_PUNCHES . "`
                WHERE organization_id = :org_id
                  AND punch_time >= :from
                  AND punch_time <= :to
                  AND employee_id > 0
                GROUP BY employee_id
                HAVING punch_count > 0";

        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId, 'from' => $from, 'to' => $to]);

        if (empty($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            $employeeId = (int)$row['employee_id'];
            $checkIn = $row['first_punch'];
            $checkOut = $row['last_punch'];
            $totalHours = $this->calculateHours($checkIn, $checkOut);

            // Check if attendance record already exists for this employee + date
            $existing = $this->db->fetchOne(
                "SELECT id FROM `" . DB::ATTENDANCE . "`
                 WHERE employee_id = :emp_id AND work_date = :work_date AND organization_id = :org_id",
                ['emp_id' => $employeeId, 'work_date' => $workDate, 'org_id' => $orgId]
            );

            try {
                if ($existing !== null) {
                    $this->db->execute(
                        "UPDATE `" . DB::ATTENDANCE . "`
                         SET check_in = :check_in, check_out = :check_out, total_hours = :total_hours, status = 'present'
                         WHERE id = :id",
                        [
                            'check_in' => $checkIn,
                            'check_out' => $checkOut,
                            'total_hours' => $totalHours,
                            'id' => (int)$existing['id'],
                        ]
                    );
                } else {
                    $this->db->execute(
                        "INSERT INTO `" . DB::ATTENDANCE . "`
                         (organization_id, employee_id, work_date, check_in, check_out, total_hours, status, created_by)
                         VALUES (:org_id, :emp_id, :work_date, :check_in, :check_out, :total_hours, 'present', 0)",
                        [
                            'org_id' => $orgId,
                            'emp_id' => $employeeId,
                            'work_date' => $workDate,
                            'check_in' => $checkIn,
                            'check_out' => $checkOut,
                            'total_hours' => $totalHours,
                        ]
                    );
                }
                $count++;
            } catch (\Throwable $e) {
                ErrorCapture::record("AttendanceSyncService: deriveDailyAttendance failed for emp $employeeId: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Calculate hours between two datetime strings.
     */
    private function calculateHours(string $checkIn, string $checkOut): float
    {
        try {
            $in = new \DateTime($checkIn);
            $out = new \DateTime($checkOut);
            $diff = $in->diff($out);
            $hours = $diff->h + ($diff->i / 60) + ($diff->s / 3600);
            return round(max(0, $hours), 2);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Load the ZK user ID → ERP employee ID mapping for an organization.
     *
     * @return array<string, int> e.g. ['12' => 45]
     */
    private function loadEmployeeMapping(int $orgId): array
    {
        $sql = "SELECT id, zk_user_id FROM " . DB::USERS . "
                WHERE organization_id = :org_id
                  AND is_active = 1
                  AND zk_user_id IS NOT NULL
                  AND zk_user_id != ''";

        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);

        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['zk_user_id']] = (int)$row['id'];
        }
        return $map;
    }
}
