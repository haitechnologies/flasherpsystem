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
use App\Core\ErrorCapture;

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
        $query = "SELECT id, display_name, first_name, last_name, email, phone, approved, is_active FROM `" . $this->table . "` WHERE id > 0" . $this->getOrgIdWhereClause();

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
            AND invoice_status NOT IN ('draft', 'void', 'cancelled', 'writeoff')
        ";
        if ($this->organizationId !== null) {
            $invoiceQuery .= " AND organization_id = :invoice_org_id";
            $params['invoice_org_id'] = (int)$this->organizationId;
        }
        $invoiceQuery .= " GROUP BY customer_id";

        $this->relatedDataCache['receivables'] = [];
        $this->relatedDataCache['opening_balances'] = [];
        $this->relatedDataCache['payment_sums'] = [];
        $this->relatedDataCache['credit_sums'] = [];
        try {
            $receivableParams = [];
            foreach ($params as $k => $v) {
                $receivableParams[$k] = $v;
            }
            $receivableRows = $this->db->fetchAll($invoiceQuery, $receivableParams);
            foreach ($receivableRows as $rRow) {
                $this->relatedDataCache['receivables'][(int)$rRow['customer_id']] = max(0, (float)$rRow['total']);
            }

            $obQuery = "SELECT id, COALESCE(opening_balance, 0) as ob FROM `" . DB::CUSTOMERS . "` WHERE id IN ({$placeholdersStr})";
            $obParams = [];
            foreach ($params as $k => $v) {
                if ($k === 'invoice_org_id') continue;
                $obParams[$k] = $v;
            }
            if ($this->organizationId !== null) {
                $obQuery .= " AND organization_id = :ob_org_id";
                $obParams['ob_org_id'] = (int)$this->organizationId;
            }
            $obRows = $this->db->fetchAll($obQuery, $obParams);
            foreach ($obRows as $obRow) {
                $this->relatedDataCache['opening_balances'][(int)$obRow['id']] = max(0, (float)$obRow['ob']);
            }

            $payQuery = "SELECT customer_id, COALESCE(SUM(total_amount_received), 0) as total FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE customer_id IN ({$placeholdersStr}) AND payment_status != 'void'";
            $payParams = [];
            foreach ($params as $k => $v) {
                if ($k === 'invoice_org_id' || $k === 'ob_org_id') continue;
                $payParams[$k] = $v;
            }
            if ($this->organizationId !== null) {
                $payQuery .= " AND organization_id = :pay_org_id";
                $payParams['pay_org_id'] = (int)$this->organizationId;
            }
            $payQuery .= " GROUP BY customer_id";
            $payRows = $this->db->fetchAll($payQuery, $payParams);
            foreach ($payRows as $payRow) {
                $this->relatedDataCache['payment_sums'][(int)$payRow['customer_id']] = (float)$payRow['total'];
            }

            $crQuery = "SELECT customer_id, COALESCE(SUM(grand_total), 0) as total FROM `" . DB::CREDIT_NOTES . "` WHERE customer_id IN ({$placeholdersStr}) AND credit_note_status NOT IN ('draft', 'void')";
            $crParams = [];
            foreach ($params as $k => $v) {
                if (in_array($k, ['invoice_org_id', 'ob_org_id', 'pay_org_id'])) continue;
                $crParams[$k] = $v;
            }
            if ($this->organizationId !== null) {
                $crQuery .= " AND organization_id = :cr_org_id";
                $crParams['cr_org_id'] = (int)$this->organizationId;
            }
            $crQuery .= " GROUP BY customer_id";
            $crRows = $this->db->fetchAll($crQuery, $crParams);
            foreach ($crRows as $crRow) {
                $this->relatedDataCache['credit_sums'][(int)$crRow['customer_id']] = (float)$crRow['total'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("CustomersDataTable::prepareRelatedData() failed: " . $e->getMessage());
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

        $customerReceivables = ($this->relatedDataCache['receivables'][$id] ?? 0.00) + ($this->relatedDataCache['opening_balances'][$id] ?? 0.00) - ($this->relatedDataCache['payment_sums'][$id] ?? 0.00) - ($this->relatedDataCache['credit_sums'][$id] ?? 0.00);

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

        if ($this->isGranted('view', $module)) {
            $actions .= '<a href="customer_overview.php?customer_id=' . $id . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }

        if ($this->isGranted('edit', $module)) {
            $actions .= '<a href="customers.php?id=' . $id . '&action=edit_customers" title="Edit"><span class="text-primary"><i class="ph-pencil-simple"></i></span></a> ';
        }

        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module, 'Are you sure you want to delete this customer?');
        }

        return $actions;
    }
}
