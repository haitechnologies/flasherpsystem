<?php

/**
 * AlertsDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class AlertsDataTable extends BaseDataTable
{
    protected $table = DB::ALERTS;
    protected $searchFields = ['alert_name'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'alert_name', 2 => 'type', 3 => 'created_at', 4 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $alertName = $row['alert_name'] ?? '';
        $type = $row['type'] ?? 'general';
        $createdAt = $row['created_at'] ?? '';

        $badgeMap = [
            'general' => 'bg-secondary',
            'system' => 'bg-primary',
            'warning' => 'bg-warning text-dark',
            'info' => 'bg-info text-dark',
        ];
        $badgeClass = $badgeMap[$type] ?? 'bg-secondary';

        return [
            $this->rowNumber,
            htmlspecialchars($alertName),
            '<span class="badge ' . $badgeClass . '">' . htmlspecialchars(ucfirst($type)) . '</span>',
            $this->formatTimeAgo($createdAt),
            $this->getActionButtons($id, 'alerts')
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? count($this->sortableColumns) - 2);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function getActionButtons($id, $module)
    {
        $buttons = [];
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'alerts.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
