<?php

/**
 * CategoriesDataTable Handler
 *
 * Manages server-side DataTable for product categories.
 * Includes subcategory, item, and company counts.
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class CategoriesDataTable extends BaseDataTable
{
    protected $table = DB::CATEGORIES;
    protected $searchFields = ['name'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'name', 2 => 'id',
        3 => 'id', 4 => 'id', 5 => 'is_active', 6 => 'id'
    ];

    /**
     * OPTIMIZATION: Pre-fetch all counts for categories in bulk
     * Prevents 3 N+1 queries per category
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $categoryIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));

        if (empty($categoryIds)) {
            return;
        }

        $idList = implode(',', $categoryIds);

        // OPTIMIZATION 1: Fetch subcategory counts in ONE query
        $subQuery = "
            SELECT category_id, COUNT(*) as cnt 
            FROM " . DB::SUBCATEGORIES . " 
            WHERE category_id IN ({$idList})
            GROUP BY category_id
        ";

        $this->relatedDataCache['subcategories'] = [];
        try {
            $subRows = $this->db->fetchAll($subQuery);
            foreach ($subRows as $subRow) {
                $this->relatedDataCache['subcategories'][(int)$subRow['category_id']] = (int)$subRow['cnt'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("CategoriesDataTable::prepareRelatedData subcategories error: " . $e->getMessage());
        }

        $this->relatedDataCache['items'] = [];
        if ($this->tableExists(DB::CATEGORY_ITEMS)) {
            // OPTIMIZATION 2: Fetch item counts in ONE query
            $itemQuery = "
                SELECT category_id, COUNT(*) as cnt 
                FROM " . DB::CATEGORY_ITEMS . " 
                WHERE category_id IN ({$idList})
                GROUP BY category_id
            ";

            try {
                $itemRows = $this->db->fetchAll($itemQuery);
                foreach ($itemRows as $itemRow) {
                    $this->relatedDataCache['items'][(int)$itemRow['category_id']] = (int)$itemRow['cnt'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("CategoriesDataTable::prepareRelatedData items error: " . $e->getMessage());
            }
        }

        $this->relatedDataCache['companies'] = [];
        if ($this->tableExists(DB::COMPANIES)) {
            // OPTIMIZATION 3: Fetch company counts in ONE query
            $companyQuery = "
                SELECT primary_category_id, COUNT(*) as cnt 
                FROM " . DB::COMPANIES . " 
                WHERE primary_category_id IN ({$idList}) AND is_active = 1
                GROUP BY primary_category_id
            ";

            try {
                $companyRows = $this->db->fetchAll($companyQuery);
                foreach ($companyRows as $companyRow) {
                    $this->relatedDataCache['companies'][(int)$companyRow['primary_category_id']] = (int)$companyRow['cnt'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("CategoriesDataTable::prepareRelatedData companies error: " . $e->getMessage());
            }
        }
    }

    private function tableExists(string $tableName): bool
    {
        static $cache = [];
        if (isset($cache[$tableName])) {
            return $cache[$tableName];
        }

        try {
            $sql = "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name LIMIT 1";
            $row = $this->db->fetchOne($sql, ['table_name' => $tableName]);
            $cache[$tableName] = ($row !== null);
        } catch (\Throwable $e) {
            $cache[$tableName] = false;
        }

        return $cache[$tableName];
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $name = (string)($row['name'] ?? '');
        $slug = (string)($row['slug'] ?? '');
        $publish = (int)$row['is_active'];

        // OPTIMIZATION: Use pre-fetched counts instead of per-row queries
        $subCount = $this->relatedDataCache['subcategories'][$id] ?? 0;
        $itemCount = $this->relatedDataCache['items'][$id] ?? 0;
        $companyCount = $this->relatedDataCache['companies'][$id] ?? 0;

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            $this->rowNumber,
            $name,
            BadgeHelper::info($subCount),
            BadgeHelper::primary($itemCount),
            BadgeHelper::success($companyCount),
            $publishBadge,
            $this->getActionButtons($id, 'categories', $publish, $slug)
        ];
    }

    protected function getActionButtons($id, $module, $publish, $slug = '')
    {
        $buttons = [];
        if (!empty($slug)) {
            $buttons[] = ActionButtonHelper::publicLinkButton('/category/' . rawurlencode($slug), 'Open Public Category Page');
        }
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'categories.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
