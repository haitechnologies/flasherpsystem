<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class AccountsReportCategoriesDataTable extends BaseDataTable
{
    protected $table = DB::ACCOUNTS_REPORT_CATEGORIES;
    protected $searchFields = ['category_name'];
    protected $sortableColumns = [0 => 'id', 1 => 'category_name', 2 => 'created_at', 3 => 'is_active', 4 => 'id'];

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['category_name'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $publish = (int)($row['is_active'] ?? 0);
        $badge   = $publish ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            0,
            $this->formatTimeAgo($created),
            $badge,
            $this->getActionButtons($id, 'accounts_report_categories'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'accounts_report_categories.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
