<?php

/**
 * VendorLogsDataTable Handler
 *
 * Manages server-side DataTable processing for vendor activity logs
 *
 * @package DataTable
 * @subpackage Handlers
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class VendorLogsDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::ENTITY_LOGS;

    /**
     * Searchable fields
     */
    protected $searchFields = [
        'cl.module',
        'cl.action',
        'v.display_name',
        'v.email'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'cl.id',
        1 => 'v.display_name',
        2 => 'cl.module',
        3 => 'cl.action',
        4 => 'cl.created_at',
        5 => 'cl.id'
    ];

    /**
     * Build base query with vendor join
     *
     * @param array $requestData Request data
     * @return string Base SQL query
     */
    protected function buildBaseQuery($requestData)
    {
        return "SELECT cl.*, v.display_name, v.email "
            . "FROM `" . DB::ENTITY_LOGS . "` cl "
            . "LEFT JOIN `" . DB::VENDORS . "` v ON v.id = cl.entity_id "
            . "WHERE cl.entity_type = 'vendor'";
    }

    /**
     * Build search clause
     *
     * @param array $requestData Request data
     * @return string Search clause
     */
    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        $searchValue = '%' . $searchValue . '%';
        $conditions = [];
        foreach ($this->searchFields as $i => $field) {
            $key = 'search_val_' . $i;
            $conditions[] = "{$field} LIKE :{$key}";
            $this->params[$key] = $searchValue;
        }
        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Format row data
     *
     * @param array $row Database row
     * @param array $requestData Request data
     * @return array Formatted row
     */
    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $vendorId = (int)($row['entity_id'] ?? 0);
        $displayName = $this->sanitize($row['display_name'] ?? '') ?: 'Unknown';
        $module = $this->sanitize($row['module'] ?? '') ?: '-';
        $action = $this->sanitize($row['action'] ?? '') ?: '-';
        $createdAt = $row['created_at'] ?? '';

        $createdDisplay = $createdAt !== '' ? $this->formatDate($createdAt, 'd M Y') : '-';

        return [
            $this->rowNumber,
            '<a href="vendor_overview.php?vendor_id=' . $vendorId . '" class="text-primary">' . htmlspecialchars($displayName) . '</a>',
            htmlspecialchars(ucwords(str_replace('_', ' ', $module))),
            htmlspecialchars(ucwords($action)),
            $createdDisplay,
            $this->getActionButtons($id, 'vendor_logs', $vendorId)
        ];
    }

    /**
     * Build order clause
     *
     * @param array $requestData Request data
     * @return string Order clause
     */
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

    protected function getActionButtons($id, $module, $vendorId)
    {
        $actions = '';

        if ($this->isGranted('edit', 'vendors')) {
            $actions .= '<a href="vendor_logs.php?vendor_id=' . $vendorId . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }

        return $actions;
    }
}
