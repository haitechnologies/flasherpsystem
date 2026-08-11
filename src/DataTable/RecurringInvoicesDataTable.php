<?php

/**
 * RecurringInvoicesDataTable Handler
 *
 * Manages server-side DataTable processing for the Recurring Invoices module.
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class RecurringInvoicesDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::INVOICES;

    /**
     * Search fields
     */
    protected $searchFields = [
        'profile_name',
        'frequency'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'invoice_date',
        1 => 'invoice_no',
        2 => 'customer_id',
        3 => 'profile_name',
        4 => 'frequency',
        5 => 'last_invoice_date',
        6 => 'next_invoice_date',
        7 => 'grand_total',
        8 => 'recurring_status'
    ];

    /**
     * Build base query with recurring filter and organization check
     */
    protected function buildBaseQuery($requestData)
    {
        $query = "SELECT id, invoice_no, invoice_date, customer_id, profile_name, frequency, last_invoice_date, next_invoice_date, grand_total, recurring_status FROM `" . $this->table . "` WHERE id > 0 AND recurring = 1" . $this->getOrgIdWhereClause();

        $customerId = isset($requestData['customer_id']) ? (int)$requestData['customer_id'] : 0;
        $recurringStatus = $requestData['recurring_status'] ?? '';

        if ($customerId > 0) {
            $query .= " AND customer_id = :customer_id";
            $this->params['customer_id'] = $customerId;
        }

        if ($recurringStatus !== '') {
            $query .= " AND recurring_status = :recurring_status";
            $this->params['recurring_status'] = (int)$recurringStatus;
        }

        return $query;
    }

    /**
     * Build search clause by checking customer names first or searching by profile fields
     */
    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        // First try to find customer by name in active organization
        $customerQuery = "SELECT id FROM `" . DB::CUSTOMERS . "` 
                          WHERE display_name LIKE :search_val";
        $custParams = ['search_val' => '%' . $searchValue . '%'];
        if ($this->organizationId !== null) {
            $customerQuery .= " AND organization_id = :cust_org_id";
            $custParams['cust_org_id'] = (int)$this->organizationId;
        }
        $customerQuery .= " LIMIT 1";

        try {
            $customerRow = $this->db->fetchOne($customerQuery, $custParams);
            if ($customerRow !== null) {
                $this->params['search_customer_id'] = (int)$customerRow['id'];
                return " AND customer_id = :search_customer_id";
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("RecurringInvoicesDataTable::buildSearchClause() customer search failed: " . $e->getMessage());
        }

        // If no customer found, search by profile name, invoice no, or frequency
        $this->params['search_profile_name'] = '%' . $searchValue . '%';
        $this->params['search_invoice_no'] = '%' . $searchValue . '%';
        $this->params['search_frequency'] = '%' . $searchValue . '%';
        return " AND (profile_name LIKE :search_profile_name OR invoice_no LIKE :search_invoice_no OR frequency LIKE :search_frequency)";
    }

    /**
     * Pre-fetch customer names to prevent N+1 queries
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $customerIds = array_unique(array_filter(array_map(fn($r) => (int)($r['customer_id'] ?? 0), $rows)));

        if (empty($customerIds)) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($customerIds as $index => $id) {
            $key = 'cust_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholdersStr = implode(',', $placeholders);

        $customerQuery = "
            SELECT id, display_name 
            FROM `" . DB::CUSTOMERS . "` 
            WHERE id IN ({$placeholdersStr})
        ";
        if ($this->organizationId !== null) {
            $customerQuery .= " AND organization_id = :cust_org_id";
            $params['cust_org_id'] = (int)$this->organizationId;
        }

        $this->relatedDataCache['customers'] = [];
        try {
            $customerRows = $this->db->fetchAll($customerQuery, $params);
            foreach ($customerRows as $cRow) {
                $this->relatedDataCache['customers'][(int)$cRow['id']] = $cRow['display_name'] ?? '-';
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("RecurringInvoicesDataTable::prepareRelatedData() failed: " . $e->getMessage());
        }
    }

    /**
     * Format row data
     */
    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $invoiceDate = $row['invoice_date'] ?? '';
        $invoiceNo = $row['invoice_no'] ?? '';
        $customerId = (int)$row['customer_id'];
        $profileName = $row['profile_name'] ?? '';
        $frequency = $row['frequency'] ?? 'monthly';
        $lastInvoiceDate = $row['last_invoice_date'] ?? '';
        $nextInvoiceDate = $row['next_invoice_date'] ?? '';
        $grandTotal = (float)($row['grand_total'] ?? 0.0);
        $recurringStatus = (int)($row['recurring_status'] ?? 0);

        $customerName = $this->relatedDataCache['customers'][$customerId] ?? '-';

        // Format dates: DD MMM YYYY (skip default date values)
        $formatDate = static function ($value) {
            if (empty($value) || $value === '1970-01-01' || $value === '0000-00-00') {
                return '-';
            }
            try {
                return (new \DateTime($value))->format('d M Y');
            } catch (\Throwable $e) {
                return $value;
            }
        };

        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        $formattedGrandTotal = $currencyCode . ' ' . number_format($grandTotal, 2);

        // Status badge
        $statusBadge = $recurringStatus === 1
            ? '<span class="badge bg-success">Active</span>'
            : '<span class="badge bg-secondary">Inactive</span>';

        return [
            $formatDate($invoiceDate),          // [0]
            $invoiceNo,                          // [1]
            $customerName,                       // [2]
            $profileName,                        // [3]
            $frequency,                          // [4]
            $formatDate($lastInvoiceDate),       // [5]
            $formatDate($nextInvoiceDate),       // [6]
            $formattedGrandTotal,                // [7]
            $statusBadge,                        // [8]
            $id                                  // [9]
        ];
    }

    /**
     * Get action buttons
     */
    protected function getActionButtons($id, $module)
    {
        $actions = '';

        if ($this->isGranted('edit', $module)) {
            $actions .= '<a href="recurring_invoice_overview.php?recurring_invoice_id=' . $id . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }

        return $actions;
    }

    /**
     * Build order clause
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
}
