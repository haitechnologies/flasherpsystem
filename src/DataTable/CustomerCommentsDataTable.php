<?php

/**
 * CustomerCommentsDataTable Handler
 *
 * Manages server-side DataTable processing for customer comments
 *
 * @package DataTable
 * @subpackage Handlers
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class CustomerCommentsDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::ENTITY_NOTES;

    /**
     * Searchable fields
     */
    protected $searchFields = [
        'cc.comments',
        'c.display_name',
        'c.email'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'cc.id',
        1 => 'c.display_name',
        2 => 'cc.comments',
        3 => 'cc.created_at',
        4 => 'cc.id'
    ];

    /**
     * Build base query with customer join
     *
     * @param array $requestData Request data
     * @return string Base SQL query
     */
    protected function buildBaseQuery($requestData)
    {
        return "SELECT cc.*, c.display_name, c.email "
            . "FROM `" . DB::ENTITY_NOTES . "` cc "
            . "LEFT JOIN `" . DB::CUSTOMERS . "` c ON c.id = cc.entity_id "
            . "WHERE cc.entity_type = 'customer'";
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

        $searchKey = 'search_val';
        $this->params[$searchKey] = '%' . $searchValue . '%';
        $conditions = [];
        foreach ($this->searchFields as $field) {
            $conditions[] = "{$field} LIKE :{$searchKey}";
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
        $customerId = (int)($row['entity_id'] ?? 0);
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
            '<a href="customer_overview.php?customer_id=' . $customerId . '" class="text-primary">' . htmlspecialchars($displayName) . '</a>',
            htmlspecialchars($commentPreview),
            $createdDisplay,
            $this->getActionButtons($id, 'customer_comments', $customerId)
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

    protected function getActionButtons($id, $module, $customerId)
    {
        $actions = '';

        if ($this->isGranted('edit', 'customers')) {
            $actions .= '<a href="customer_comments.php?customer_id=' . $customerId . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }

        return $actions;
    }
}
