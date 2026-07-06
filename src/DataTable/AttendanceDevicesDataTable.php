<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class AttendanceDevicesDataTable extends BaseDataTable
{
    protected $table = DB::ATTENDANCE_DEVICES;
    protected $searchFields = ['device_name', 'ip_address', 'serial_number', 'device_model', 'location'];
    protected $sortableColumns = [
        0 => 'id',
        1 => 'device_name',
        2 => 'ip_address',
        3 => 'serial_number',
        4 => 'device_model',
        5 => 'location',
        6 => 'last_sync_at',
        7 => 'is_active',
    ];

    protected function getOrgIdWhereClause(): string
    {
        return '';
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        return [
            $this->rowNumber,
            s__($row['device_name'] ?? ''),
            s__($row['ip_address'] ?? '') . ':' . (int)($row['port'] ?? 4370),
            s__($row['serial_number'] ?? ''),
            s__($row['device_model'] ?? ''),
            s__($row['location'] ?? ''),
            $row['last_sync_at'] ?? '-',
            (int)($row['is_active'] ?? 0) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>',
            $this->getActionButtons($id, 'attendance_devices'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'attendance_devices.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
