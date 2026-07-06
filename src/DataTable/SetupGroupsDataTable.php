<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class SetupGroupsDataTable extends BaseDataTable
{
    protected $table = DB::TAXONOMIES;
    protected $searchFields = ['value', 'description'];
    protected $sortableColumns = [0 => 'id', 1 => 'value', 2 => 'description', 3 => 'created_at', 4 => 'is_active', 5 => 'id'];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE type = 'setup_group'" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['value'] ?? '');
        $desc    = (string)($row['description'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $publish = (int)($row['is_active'] ?? 0);
        $badge   = $publish ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            htmlspecialchars($desc),
            $this->formatTimeAgo($created),
            $badge,
            $this->getActionButtons($id, 'setup_groups'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'setup_groups.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
