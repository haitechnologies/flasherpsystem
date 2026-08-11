<?php

/**
 * ModulesDataTable Handler
 * Handles DataTable requests for system modules listing
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class ModulesDataTable extends BaseDataTable
{
    protected $table = DB::MODULES;
    protected $searchFields = ['module_name', 'slug', 'systems'];
    protected $sortableColumns = [
        0 => 'id',
        1 => 'module_name',
        2 => 'module_name',  // Permissions column - not sortable but needs index
        3 => 'systems',
        4 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE 1=1";
    }

    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        $searchVal = '%' . $searchValue . '%';
        $fields = ['module_name', 'slug', 'systems'];
        $conditions = [];
        foreach ($fields as $i => $field) {
            $key = 'search_val_' . $i;
            $conditions[] = "{$field} LIKE :{$key}";
            $this->params[$key] = $searchVal;
        }
        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = isset($requestData['order'][0]['column']) ? (int)$requestData['order'][0]['column'] : 0;
        $orderDir = isset($requestData['order'][0]['dir']) ? strtoupper($requestData['order'][0]['dir']) : 'ASC';

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $moduleName = htmlspecialchars($row['module_name'] ?? '');
        $moduleSlug = htmlspecialchars($row['slug'] ?? '');
        $systems = htmlspecialchars($row['systems'] ?? '');
        $moduleIcon = 'ph-cube';

        // Get permissions count for this module
        $permissionsCount = $this->getPermissionsCount($id);

        // Build permissions display - single line to avoid JSON issues
        $permissionsDisplay = '<span class="badge bg-info bg-opacity-20 text-info">' . $permissionsCount . ' permissions</span>';
        if ($this->isGranted('edit', 'modules')) {
            $permissionsDisplay .= ' <a href="module_permissions.php?module_id=' . $id . '" class="btn btn-sm btn-light"><i class="ph-shield-check"></i> Manage</a>';
        }

        // Systems display
        $systemsDisplay = $systems !== ''
            ? BadgeHelper::primary($systems)
            : BadgeHelper::secondary('N/A');

        // Action buttons
        $actions = '';
        if ($this->isGranted('edit', 'modules')) {
            $actions .= ActionButtonHelper::editButton($id, 'modules.php', 'modules', 'Edit', false);
        }
        if ($this->isGranted('delete', 'modules')) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, 'modules');
        }

        if (empty($actions)) {
            $actions = '&mdash;';
        }

        // Build module info - single line to avoid JSON encoding issues
        $moduleInfo = '<div><i class="' . $moduleIcon . ' me-2"></i><strong>' . $moduleName . '</strong></div><div class="text-muted small">' . $moduleSlug . '</div>';

        // Return indexed array format (position-based)
        return [
            $this->rowNumber,
            1 => $moduleInfo,
            2 => $permissionsDisplay,
            3 => $systemsDisplay,
            4 => $actions
        ];
    }

    /**
     * Get count of roles with permissions for this module
     */
    private function getPermissionsCount($moduleId)
    {
        try {
            $sql = "SELECT COUNT(*) as count FROM " . DB::MODULE_PERMISSIONS . " WHERE module_id = :module_id";
            $row = $this->db->fetchOne($sql, ['module_id' => $moduleId]);
            return (int)($row['count'] ?? 0);
        } catch (\Throwable $e) {
            ErrorCapture::record('[ModulesDataTable] Failed permission count query: ' . $e->getMessage());
            return 0;
        }
    }
}
