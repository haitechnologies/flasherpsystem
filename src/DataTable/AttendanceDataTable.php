<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class AttendanceDataTable extends BaseDataTable
{
    protected $table = DB::ATTENDANCE;
    protected $searchFields = ['work_date', 'status']; // employee search needs custom handling if it's a join, but let's keep it simple
    protected $sortableColumns = [
        0 => 'id',
        1 => 'employee_id',
        2 => 'work_date',
        3 => 'check_in',
        4 => 'check_out',
        5 => 'total_hours',
        6 => 'status',
        7 => 'id'
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
            getTableAttr('full_name', DB::USERS, $row['employee_id']),
            s__($row['work_date']),
            s__($row['check_in']),
            s__($row['check_out']),
            s__($row['total_hours']),
            s__($row['status']),
            $this->getActionButtons($id, 'attendance'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'attendance.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
