<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class VendorContactsDataTable extends BaseDataTable
{
    protected $table = DB::VENDOR_CONTACTS;
    protected $searchFields = ['first_name', 'last_name', 'email', 'phone'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'first_name', 2 => 'email', 3 => 'phone', 4 => 'position', 5 => 'created_at', 6 => 'is_active', 7 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE id > 0 AND contactable_type = 'Vendor'" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $firstName = htmlspecialchars($row['first_name'] ?? '');
        $lastName = htmlspecialchars($row['last_name'] ?? '');
        $email = htmlspecialchars($row['email'] ?? '');
        $phone = htmlspecialchars($row['phone'] ?? '');
        $position = htmlspecialchars($row['position'] ?? '');
        $publish = (int)$row['is_active'];
        $createdAt = $row['created_at'] ?? '';

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');
        $fullName = trim($firstName . ' ' . $lastName);

        $timeAgoStr = !empty($createdAt) ? $this->formatTimeAgo($createdAt) : '';

        return [
            $this->rowNumber,
            $fullName,
            $email,
            $phone,
            $position,
            $timeAgoStr,
            $publishBadge,
            $this->getActionButtons($id, 'vendor_contacts', $publish)
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? count($this->sortableColumns) - 2);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $buttons = [];
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'vendor_contacts.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
