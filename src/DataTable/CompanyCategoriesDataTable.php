<?php

/**
 * CompanyCategoriesDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class CompanyCategoriesDataTable extends BaseDataTable
{
    protected $table = DB::CATEGORIES;
    protected $searchFields = ['category', 'slug'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'category', 2 => 'slug', 3 => 'total_companies', 4 => 'main', 5 => 'created_at', 6 => 'is_active', 7 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $category = $row['category'] ?? '';
        $slug = $row['slug'] ?? '';
        $totalCompanies = (int)($row['total_companies'] ?? 0);
        $main = (int)($row['main'] ?? 0);
        $publish = (int)($row['is_active'] ?? 0);
        $createdAt = $row['created_at'] ?? '';

        $categoryDisplay = substr($category, 0, 50) . (strlen($category) > 50 ? '...' : '');
        $mainBadge = $main == 1 ? BadgeHelper::info('Main') : BadgeHelper::secondary('Sub');
        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            $this->rowNumber,
            htmlspecialchars($categoryDisplay),
            $slug,
            number_format($totalCompanies),
            $mainBadge,
            $this->formatTimeAgo($createdAt),
            $publishBadge,
            $this->getActionButtons($id, 'company_categories', $publish)
        ];
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $buttons = [];
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'company_categories.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
