<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class StorageSubtypesDataTable extends BaseDataTable
{
    protected $table = DB::STORAGE_TYPES;
    protected $searchFields = ['st.name', 'parent.name'];
    protected $sortableColumns = [0 => 'st.id', 1 => 'st.name', 2 => 'parent.name', 3 => 'st.created_at', 4 => 'st.is_active', 5 => 'st.id'];

    protected function buildBaseQuery($requestData)
    {
        $orgClause = '';
        if ($this->organizationId !== null) {
            $orgClause = ' AND st.organization_id = :active_org_id';
        }
        return "SELECT st.*, parent.name AS parent_name
                FROM `" . $this->table . "` st
                LEFT JOIN `" . $this->table . "` parent ON parent.id = st.parent_id
                WHERE st.parent_id IS NOT NULL" . $orgClause;
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $sub     = (string)($row['name'] ?? '');
        $type    = (string)($row['parent_name'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $active  = (int)($row['is_active'] ?? 0);
        $badge   = $active ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($sub),
            htmlspecialchars($type),
            $this->formatTimeAgo($created),
            $badge,
            $this->getActionButtons($id, 'storage_subtypes'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'storage_subtypes.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
