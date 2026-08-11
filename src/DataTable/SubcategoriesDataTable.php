<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class SubcategoriesDataTable extends BaseDataTable
{
    protected $table = DB::SUBCATEGORIES;
    protected $searchFields = ['name'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'category_id', 2 => 'name',
        3 => 'id', 4 => 'id', 5 => 'is_active', 6 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT s.*, c.name as parent_category 
                FROM `" . $this->table . "` s 
                LEFT JOIN `" . DB::CATEGORIES . "` c ON s.category_id = c.id 
                WHERE s.id > 0";
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = isset($requestData['order'][0]['column']) ? (int)$requestData['order'][0]['column'] : 0;
        $orderDir = isset($requestData['order'][0]['dir']) ? strtoupper($requestData['order'][0]['dir']) : 'DESC';

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = isset($this->sortableColumns[$orderColumn]) ? $this->sortableColumns[$orderColumn] : 'id';
        return 'ORDER BY s.' . $column . ' ' . $orderDir;
    }

    /**
     * OPTIMIZATION: Pre-fetch item and company counts to prevent N+1 queries
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $subcategoryIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));

        if (empty($subcategoryIds)) {
            return;
        }

        $idList = implode(',', $subcategoryIds);

        $this->relatedDataCache['items'] = [];
        if ($this->tableExists(DB::CATEGORY_ITEMS)) {
            // OPTIMIZATION 1: Fetch item counts in ONE query
            $itemQuery = "
                SELECT subcategory_id, COUNT(*) as cnt 
                FROM " . DB::CATEGORY_ITEMS . " 
                WHERE subcategory_id IN ({$idList})
                GROUP BY subcategory_id
            ";

            try {
                $itemRows = $this->db->fetchAll($itemQuery);
                foreach ($itemRows as $itemRow) {
                    $this->relatedDataCache['items'][(int)$itemRow['subcategory_id']] = (int)$itemRow['cnt'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("SubcategoriesDataTable::prepareRelatedData items error: " . $e->getMessage());
            }
        }

        $this->relatedDataCache['companies'] = [];
        if ($this->tableExists(DB::COMPANIES)) {
            // OPTIMIZATION 2: Fetch company counts in ONE query
            $companyQuery = "
                SELECT primary_subcategory_id, COUNT(*) as cnt 
                FROM " . DB::COMPANIES . " 
                WHERE primary_subcategory_id IN ({$idList}) AND is_active = 1
                GROUP BY primary_subcategory_id
            ";

            try {
                $companyRows = $this->db->fetchAll($companyQuery);
                foreach ($companyRows as $companyRow) {
                    $this->relatedDataCache['companies'][(int)$companyRow['primary_subcategory_id']] = (int)$companyRow['cnt'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("SubcategoriesDataTable::prepareRelatedData companies error: " . $e->getMessage());
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
        $slug = $row['slug'] ?? '';
        $parentCategory = $row['parent_category'] ?? null;
        $name = $row['name'] ?? '';
        $publish = (int)$row['is_active'];

        // OPTIMIZATION: Use pre-fetched counts instead of per-row queries
        $itemCount = $this->relatedDataCache['items'][$id] ?? 0;
        $companyCount = $this->relatedDataCache['companies'][$id] ?? 0;

        $parentDisplay = $parentCategory
            ? htmlspecialchars((string)$parentCategory, ENT_QUOTES, 'UTF-8')
            : '<span class="badge bg-light text-dark">No Parent</span>';
        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        return [
            'id' => $id,
            'parent_category' => $parentDisplay,
            'name' => htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8'),
            'items_count' => BadgeHelper::primary($itemCount),
            'companies_count' => BadgeHelper::success($companyCount),
            'is_active' => $publishBadge,
            'actions' => $this->getActionButtons($id, 'subcategories', $publish, $slug)
        ];
    }

    protected function getActionButtons($id, $module, $publish, $slug = '')
    {
        $buttons = [];
        if (!empty($slug)) {
            $buttons[] = ActionButtonHelper::publicLinkButton('/subcategory/' . rawurlencode($slug), 'Open Public Subcategory Page');
        }
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'subcategories.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
