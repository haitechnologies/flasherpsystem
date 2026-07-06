<?php

/**
 * AuthenticationActivityDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class AuthenticationActivityDataTable extends BaseDataTable
{
    protected $table = DB::AUTHENTICATION_ACTIVITY;
    protected $searchFields = ['activity_type', 'ip_address'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'user_id', 2 => 'activity_type', 3 => 'ip_address', 5 => 'created_at', 6 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT ul.*, u.full_name FROM `" . $this->table . "` ul 
                LEFT JOIN " . DB::USERS . " u ON ul.user_id = u.id 
                WHERE ul.id > 0";
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = isset($requestData['order'][0]['column']) ? (int)$requestData['order'][0]['column'] : 0;
        $orderDir = isset($requestData['order'][0]['dir']) ? strtoupper($requestData['order'][0]['dir']) : 'DESC';
        $column = isset($this->sortableColumns[$orderColumn]) ? $this->sortableColumns[$orderColumn] : 'created_at';
        return 'ORDER BY ul.' . $column . ' ' . $orderDir;
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $userId = (int)$row['user_id'];
        $fullName = $row['full_name'] ?? 'System';
        $activityType = $row['activity_type'] ?? '';
        $ipAddress = $row['ip_address'] ?? '';
        $createdAt = $row['created_at'] ?? '';

        return [
            $this->rowNumber,
            $userId . ' (' . htmlspecialchars($fullName) . ')',
            htmlspecialchars($activityType),
            htmlspecialchars($ipAddress),
            $this->formatTimeAgo($createdAt),
            $this->getActionButtons($id, 'authentication_activity')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        return '';
    }
}
