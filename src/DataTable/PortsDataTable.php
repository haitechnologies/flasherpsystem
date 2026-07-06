<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class PortsDataTable extends BaseDataTable
{
    protected $table = DB::PORTS;

    protected $searchFields = [
        'port_name',
        'port_code',
    ];

    protected $sortableColumns = [
        0 => 'id',
        1 => 'port_name',
        2 => 'port_code',
        3 => 'country_id',
        4 => 'created_at',
        5 => 'is_active',
        6 => 'id',
    ];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $countryIds = array_filter(array_map(static function ($row) {
            return (int)($row['country_id'] ?? 0);
        }, $rows));

        if (empty($countryIds)) {
            return;
        }

        $uniqueIds = array_unique($countryIds);
        $placeholders = [];
        $params = [];
        foreach ($uniqueIds as $index => $id) {
            $key = 'country_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholdersStr = implode(',', $placeholders);

        $this->relatedDataCache['countries'] = [];
        $query = "SELECT id, country AS country_name FROM `" . DB::GEO_COUNTRIES . "` WHERE id IN ({$placeholdersStr})";
        try {
            $cRows = $this->db->fetchAll($query, $params);
            foreach ($cRows as $cRow) {
                $this->relatedDataCache['countries'][(int)$cRow['id']] = (string)($cRow['country_name'] ?? '');
            }
        } catch (\Throwable $e) {
            error_log("PortsDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $portName = (string)($row['port_name'] ?? '');
        $portCode = (string)($row['port_code'] ?? '');
        $countryId = (int)($row['country_id'] ?? 0);
        $createdAt = (string)($row['created_at'] ?? '');
        $publish = (int)($row['is_active'] ?? 0);

        $countryName = $this->relatedDataCache['countries'][$countryId] ?? ('Country #' . $countryId);
        $publishBadge = ($publish === 1) ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');

        $timeAgoStr = '';
        if (!empty($createdAt)) {
            $timeAgoStr = $this->formatTimeAgo($createdAt);
        }

        $portLink = '<a href="#" class="view-port-details text-primary fw-semibold" data-id="' . $id . '">' . htmlspecialchars($portName) . '</a>';
        return [
            $this->rowNumber,
            $portLink,
            htmlspecialchars($portCode),
            htmlspecialchars($countryName),
            $timeAgoStr,
            $publishBadge,
            $this->getActionButtons($id, 'ports'),
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function getActionButtons($id, $module)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton((int)$id, 'ports.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $actions;
    }
}
