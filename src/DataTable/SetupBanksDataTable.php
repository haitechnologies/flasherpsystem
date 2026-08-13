<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class SetupBanksDataTable extends BaseDataTable
{
    protected $table = DB::SETUP_BANKS;
    protected $searchFields = ['institution_name', 'head_office'];
    protected $sortableColumns = [
        0 => 'id',
        1 => 'institution_name',
        2 => 'head_office',
        3 => 'is_active',
        4 => 'id',
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT id, institution_name, head_office, is_active, created_at FROM `" . $this->table . "` WHERE id > 0" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int) $row['id'];
        $name = (string) ($row['institution_name'] ?? '');
        $headOffice = (string) ($row['head_office'] ?? '');
        $publish = (int) $row['is_active'];

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            $this->rowNumber,
            htmlspecialchars($name),
            htmlspecialchars($headOffice),
            $publishBadge,
            $this->getActionButtons($id, 'setup_banks', $publish),
        ];
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton($id, 'setup_banks.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
