<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class OrganizationRolesDataTable extends BaseDataTable
{
    protected $table = DB::ORGANIZATION_ROLES;
    protected $searchFields = ['role_name', 'description'];
    protected $sortableColumns = [0 => 'id', 1 => 'role_name', 2 => 'description', 3 => 'id', 4 => 'is_active', 5 => 'created_at', 6 => 'id'];

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $roleName = (string)($row['role_name'] ?? '');
        $desc     = (string)($row['description'] ?? '');
        $active   = (int)($row['is_active'] ?? 0);
        $created  = (string)($row['created_at'] ?? '');
        $badge    = $active ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($roleName),
            htmlspecialchars($desc),
            0,
            $badge,
            $this->formatTimeAgo($created),
            $this->getActionButtons($id, 'organization_roles'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'organization_roles.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
