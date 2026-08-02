<?php

/**
 * CustomersDataTable Handler
 *
 * Manages server-side DataTable processing for the Customers module
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class CustomersDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::CUSTOMERS;

    /**
     * Search fields
     */
    protected $searchFields = [
        'first_name',
        'last_name',
        'display_name'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'display_name',
        1 => 'email',
        2 => 'phone',
        3 => 'id', // mapping receivables column to id sorting or opening_balance
        4 => 'is_active',
        5 => 'approved',
        6 => 'id'
    ];

    /**
     * Build base query with status filtering and organization check
     */
    protected function buildBaseQuery($requestData)
    {
        $query = "SELECT * FROM `" . $this->table . "` WHERE id > 0" . $this->getOrgIdWhereClause();

        $customerStatus = isset($requestData['customer_status']) ? (int)$requestData['customer_status'] : 0;
        if ($customerStatus > 0) {
            $query .= " AND customer_status = :customer_status";
            $this->params['customer_status'] = $customerStatus;
        }

        return $query;
    }

    /**
     * Pre-fetch receivables total in a single query to prevent N+1 queries
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $customerIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));

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

        // Fetch receivables for all displayed customers
        $invoiceQuery = "
            SELECT customer_id, COALESCE(SUM(grand_total), 0) as total 
            FROM `" . DB::INVOICES . "` 
            WHERE customer_id IN ({$placeholdersStr})
            AND invoice_status IN ('sent', 'partially_paid', 'overdue')
        ";
        if ($this->organizationId !== null) {
            $invoiceQuery .= " AND organization_id = :invoice_org_id";
            $params['invoice_org_id'] = (int)$this->organizationId;
        }
        $invoiceQuery .= " GROUP BY customer_id";

        $this->relatedDataCache['receivables'] = [];
        try {
            $receivableRows = $this->db->fetchAll($invoiceQuery, $params);
            foreach ($receivableRows as $rRow) {
                $this->relatedDataCache['receivables'][(int)$rRow['customer_id']] = max(0, (float)$rRow['total']);
            }
        } catch (\Throwable $e) {
            error_log("CustomersDataTable::prepareRelatedData() failed: " . $e->getMessage());
        }
    }

    /**
     * Format row data
     */
    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $displayName = $row['display_name'] ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $email = $row['email'] ?? '';
        $phone = $row['phone'] ?? '';
        $approved = (int)($row['approved'] ?? 0);
        $publish = (int)($row['is_active'] ?? 1);

        $customerReceivables = $this->relatedDataCache['receivables'][$id] ?? 0.00;

        // Build approval status badge
        $approvalBadge = match ($approved) {
            0 => BadgeHelper::warning('Approval Requested'),
            1 => BadgeHelper::success('Approved'),
            2 => BadgeHelper::danger('Not Approved'),
            default => BadgeHelper::secondary('Unknown')
        };

        // Build publish status badge
        $publishBadge = $publish == 0
            ? BadgeHelper::danger('Inactive')
            : BadgeHelper::success('Active');

        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        $formattedReceivables = $this->formatDecimal((float)$customerReceivables);

        return [
            '<a href="customer_overview.php?customer_id=' . $id . '" class="text-primary"> ' . htmlspecialchars($displayName) . ' </a>',
            '<a href="customer_overview.php?customer_id=' . $id . '" class="text-black"> ' . htmlspecialchars($email) . ' </a>',
            '<a href="customer_overview.php?customer_id=' . $id . '" class="text-black"> ' . htmlspecialchars($phone) . ' </a>',
            '<a href="customer_overview.php?customer_id=' . $id . '" class="text-black"> ' . $currencyCode . ' ' . $formattedReceivables . '</a>',
            $publishBadge,
            $approvalBadge,
            $this->getActionButtons($id, 'customers')
        ];
    }

    /**
     * Get action buttons
     */
    protected function getActionButtons($id, $module)
    {
        $actions = '';

        if ($this->isGranted('edit', $module)) {
            $actions .= '<a href="customer_overview.php?customer_id=' . $id . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module, 'Are you sure you want to delete this customer?');
        }

        return $actions;
    }
}
