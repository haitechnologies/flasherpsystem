<?php

/**
 * SetupTagsDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class SetupTagsDataTable extends BaseDataTable
{
    protected $table = DB::TAXONOMIES;
    protected $searchFields = ['value', 'type'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'value', 2 => 'type', 3 => 'created_at', 4 => 'is_active', 5 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE type IN ('customer_tag', 'lead_tag', 'job_tag')" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $tag = $row['value'] ?? '';
        $typeVal = $row['type'] ?? '';
        $tagType = ($typeVal === 'customer_tag') ? 'customers' : (($typeVal === 'lead_tag') ? 'leads' : (($typeVal === 'job_tag') ? 'jobs' : $typeVal));
        $publish = (int)$row['is_active'];
        $createdAt = $row['created_at'] ?? '';

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            $this->rowNumber,
            htmlspecialchars($tag),
            ucwords($tagType),
            $this->formatTimeAgo($createdAt),
            $publishBadge,
            $this->getActionButtons($id, 'setup_tags', $publish)
        ];
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton($id, 'setup_tags.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
