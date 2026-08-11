<?php

/**
 * RolesDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class RolesDataTable extends BaseDataTable
{
    protected $table = DB::ROLES;
    protected $searchFields = ['role_name', 'role_description'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'role_name', 2 => 'role_description', 3 => 'created_at', 4 => 'id'
    ];

    /**
     * OPTIMIZATION: Pre-fetch user counts to prevent N+1 queries
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $roleIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));

        if (empty($roleIds)) {
            return;
        }

        $idList = implode(',', $roleIds);

        // OPTIMIZATION: Fetch user counts in ONE query
        $userQuery = "
            SELECT role_id, COUNT(*) as cnt 
            FROM " . DB::USERS . " 
            WHERE role_id IN ({$idList})
            GROUP BY role_id
        ";

        $this->relatedDataCache['users'] = [];
        try {
            $userRows = $this->db->fetchAll($userQuery);
            foreach ($userRows as $row) {
                $this->relatedDataCache['users'][(int)$row['role_id']] = (int)$row['cnt'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("RolesDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {

        $id = (int)$row['id'];
        $roleName = $row['role_name'] ?? '';
        $roleDesc = $row['role_description'] ?? '';
        $createdAt = $row['created_at'] ?? '';

        // OPTIMIZATION: Use pre-fetched count instead of per-row query
        $userCount = $this->relatedDataCache['users'][$id] ?? 0;

        $descDisplay = strlen($roleDesc) > 50 ? substr($roleDesc, 0, 50) . '...' : $roleDesc;

        // Return associative array matching frontend columns
        return [
            'id' => $id,
            'role_name' => htmlspecialchars($roleName),
            'role_description' => htmlspecialchars($descDisplay),
            'user_count' => number_format($userCount),
            'created_at' => $this->formatTimeAgo($createdAt),
            'actions' => $this->getActionButtons($id, 'roles')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        // System roles cannot be deleted
        if ($id < 4) {
            return '<span class="text-muted">System Role</span>';
        }

        $buttons = [];
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'roles.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
