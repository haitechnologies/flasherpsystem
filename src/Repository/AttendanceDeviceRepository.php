<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\AttendanceDevice;
use App\Core\ErrorCapture;

class AttendanceDeviceRepository
{
    private const COLUMNS = 'id, organization_id, device_name, ip_address, port, '
        . 'serial_number, device_password, device_model, location, '
        . 'last_sync_at, last_punch_at, is_active, created_by, created_at, updated_at';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?AttendanceDevice
    {
        $sql = "SELECT " . self::COLUMNS . "
                FROM `" . DB::ATTENDANCE_DEVICES . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT " . self::COLUMNS . "
                FROM `" . DB::ATTENDANCE_DEVICES . "` ORDER BY device_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function findByOrg(int $orgId): array
    {
        $sql = "SELECT " . self::COLUMNS . "
                FROM `" . DB::ATTENDANCE_DEVICES . "` WHERE organization_id = :org_id ORDER BY device_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId]));
    }

    public function findActiveDevices(): array
    {
        $sql = "SELECT " . self::COLUMNS . "
                FROM `" . DB::ATTENDANCE_DEVICES . "` WHERE is_active = 1 ORDER BY organization_id, device_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function findActiveByOrg(int $orgId): array
    {
        $sql = "SELECT " . self::COLUMNS . "
                FROM `" . DB::ATTENDANCE_DEVICES . "` WHERE organization_id = :org_id AND is_active = 1 ORDER BY device_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql, ['org_id' => $orgId]));
    }

    public function insert(AttendanceDevice $item): int
    {
        $cols = 'organization_id, device_name, ip_address, port, serial_number, '
            . 'device_password, device_model, location, last_sync_at, '
            . 'last_punch_at, is_active, created_by';
        $vals = ':organization_id, :device_name, :ip_address, :port, :serial_number, '
            . ':device_password, :device_model, :location, :last_sync_at, '
            . ':last_punch_at, :is_active, :created_by';
        $sql = "INSERT INTO `" . DB::ATTENDANCE_DEVICES . "` ({$cols}) VALUES ({$vals})";
        return (int)$this->db->insert($sql, [
            'organization_id' => $item->organizationId,
            'device_name' => $item->deviceName,
            'ip_address' => $item->ipAddress,
            'port' => $item->port,
            'serial_number' => $item->serialNumber,
            'device_password' => $item->devicePassword,
            'device_model' => $item->deviceModel,
            'location' => $item->location,
            'last_sync_at' => $item->lastSyncAt,
            'last_punch_at' => $item->lastPunchAt,
            'is_active' => $item->isActive,
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
        $sql = "UPDATE `" . DB::ATTENDANCE_DEVICES . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("AttendanceDeviceRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::ATTENDANCE_DEVICES . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): AttendanceDevice
    {
        return new AttendanceDevice(
            id: (int)$row['id'],
            organizationId: (int)($row['organization_id'] ?? 0),
            deviceName: (string)($row['device_name'] ?? ''),
            ipAddress: (string)($row['ip_address'] ?? ''),
            port: (int)($row['port'] ?? 4370),
            serialNumber: (string)($row['serial_number'] ?? ''),
            devicePassword: (string)($row['device_password'] ?? '0'),
            deviceModel: (string)($row['device_model'] ?? ''),
            location: (string)($row['location'] ?? ''),
            lastSyncAt: isset($row['last_sync_at']) && $row['last_sync_at'] !== '' ? (string)$row['last_sync_at'] : null,
            lastPunchAt: isset($row['last_punch_at']) && $row['last_punch_at'] !== '' ? (string)$row['last_punch_at'] : null,
            isActive: (int)($row['is_active'] ?? 1),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: isset($row['created_at']) && $row['created_at'] !== '' ? (string)$row['created_at'] : null,
            updatedAt: isset($row['updated_at']) && $row['updated_at'] !== '' ? (string)$row['updated_at'] : null,
        );
    }
}
