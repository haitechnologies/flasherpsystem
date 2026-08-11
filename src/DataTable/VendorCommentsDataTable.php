<?php

/**
 * VendorCommentsDataTable Handler
 *
 * Manages server-side DataTable processing for vendor comments
 *
 * @package DataTable
 * @subpackage Handlers
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class VendorCommentsDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::ENTITY_NOTES;

    /**
     * Searchable fields
     */
    protected $searchFields = [
        'cc.notes',
        'v.display_name',
        'v.email'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'cc.id',
        1 => 'v.display_name',
        2 => 'cc.notes',
        3 => 'cc.created_at',
        4 => 'cc.id'
    ];

    /**
     * Build base query with vendor join
     *
     * @param array $requestData Request data
     * @return string Base SQL query
     */
    protected function buildBaseQuery($requestData)
    {
        return "SELECT cc.*, v.display_name, v.email "
            . "FROM `" . DB::ENTITY_NOTES . "` cc "
            . "LEFT JOIN `" . DB::VENDORS . "` v ON v.id = cc.entity_id "
            . "WHERE cc.entity_type = 'vendor'";
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
        $comments = $this->sanitize($row['notes'] ?? '');
        $createdAt = $row['created_at'] ?? '';

        $commentPreview = $comments;
        if (strlen($commentPreview) > 120) {
            $commentPreview = substr($commentPreview, 0, 117) . '...';
        }

        $createdDisplay = $this->formatDate($createdAt, 'd M Y') ?: '-';

        return [
            $this->rowNumber,
            '<a href="vendor_overview.php?vendor_id=' . $vendorId . '" class="text-primary">' . htmlspecialchars($displayName) . '</a>',
            htmlspecialchars($commentPreview),
            $createdDisplay,
            $this->getActionButtons($id, 'vendor_comments', $vendorId)
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
            $actions .= '<a href="vendor_comments.php?vendor_id=' . $vendorId . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }

        return $actions;
    }
}
