<?php

/**
 * SetupStatusesDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class SetupStatusesDataTable extends BaseDataTable
{
    protected $table = DB::TAXONOMIES;
    protected $searchFields = ['value', 'type'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'value', 2 => 'type', 3 => 'created_at', 4 => 'is_active', 5 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE type IN ('customer_status', 'lead_status', 'vendor_status')" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $status = $row['value'] ?? '';
        $typeVal = $row['type'] ?? '';
        $statusType = ($typeVal === 'customer_status') ? 'customers' : (($typeVal === 'lead_status') ? 'leads' : (($typeVal === 'vendor_status') ? 'vendors' : $typeVal));
        $publish = (int)$row['is_active'];
        $createdAt = $row['created_at'] ?? '';

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            $this->rowNumber,
            htmlspecialchars($status),
            ucwords($statusType),
            $this->formatTimeAgo($createdAt),
            $publishBadge,
            $this->getActionButtons($id, 'setup_statuses', $publish)
        ];
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton($id, 'setup_statuses.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
