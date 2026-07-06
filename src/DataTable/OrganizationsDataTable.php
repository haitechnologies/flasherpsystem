<?php

/**
 * OrganizationsDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class OrganizationsDataTable extends BaseDataTable
{
    protected $table = DB::ORGANIZATIONS;
    protected $searchFields = ['warehouse_name'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'photo', 2 => 'warehouse_name', 3 => 'phone',
        4 => 'email', 5 => 'created_at', 6 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $warehouseName = $row['warehouse_name'] ?? '';
        $phone = $row['phone'] ?? '';
        $email = $row['email'] ?? '';
        $createdAt = $row['created_at'] ?? '';

        return [
            $this->rowNumber,
            htmlspecialchars($warehouseName),
            htmlspecialchars($phone),
            htmlspecialchars($email),
            $this->formatTimeAgo($createdAt),
            $this->getActionButtons($id, 'organizations')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton($id, 'organizations.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
