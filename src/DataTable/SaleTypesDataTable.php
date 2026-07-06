<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class SaleTypesDataTable extends BaseDataTable
{
    protected $table = DB::DOCUMENT_TYPES;
    protected $searchFields = ['name', 'description'];
    protected $sortableColumns = [0 => 'id', 1 => 'name', 2 => 'description', 3 => 'created_at', 4 => 'is_active', 5 => 'id'];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE context = 'sale'" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['name'] ?? '');
        $desc    = (string)($row['description'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $active  = (int)($row['is_active'] ?? 0);
        $badge   = $active ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            htmlspecialchars($desc),
            $this->formatTimeAgo($created),
            $badge,
            $this->getActionButtons($id, 'sale_types'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'sale_types.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
