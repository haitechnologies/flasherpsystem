<?php

/**
 * PaymentsReceivedDataTable Handler
 *
 * Manages server-side DataTable processing for the Payments Received module.
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class PaymentsReceivedDataTable extends BaseDataTable
{
    /**
     * Table name
     */
    protected $table = DB::PAYMENTS_RECEIVED;

    /**
     * Search fields
     */
    protected $searchFields = [
        'payment_no',
        'reference_no'
    ];

    /**
     * Sortable columns
     */
    protected $sortableColumns = [
        0 => 'payment_date',
        1 => 'payment_no',
        2 => 'reference_no',
        3 => 'customer_id',
        4 => 'payment_status',
        5 => 'payment_method',
        6 => 'total_amount_received'
    ];

    /**
     * Build base query with status filtering and organization check
     */
    protected function buildBaseQuery($requestData)
    {
        $query = "SELECT id, payment_no, payment_date, reference_no, customer_id, payment_status, payment_method, total_amount_received, bank_charges, deposit_to FROM `" . $this->table . "` WHERE id > 0" . $this->getOrgIdWhereClause();

        $paymentStatus = $requestData['payment_status'] ?? '';
        if (!empty($paymentStatus)) {
            $query .= " AND payment_status = :payment_status";
            $this->params['payment_status'] = $paymentStatus;
        }

        return $query;
    }

    /**
     * Build search clause by checking customer names first or searching by payment fields
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
            ErrorCapture::record("PaymentsReceivedDataTable::buildSearchClause() customer search failed: " . $e->getMessage());
        }

        // If no customer found, search by payment number or reference
        $this->params['search_payment_no'] = '%' . $searchValue . '%';
        $this->params['search_reference_no'] = '%' . $searchValue . '%';
        return " AND (payment_no LIKE :search_payment_no OR reference_no LIKE :search_reference_no)";
    }

    /**
     * Pre-fetch related data (customer names, payment methods) to prevent N+1 queries
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $customerIds = array_unique(array_filter(array_map(fn($r) => (int)($r['customer_id'] ?? 0), $rows)));
        $methodIds = array_unique(array_filter(array_map(fn($r) => (int)($r['payment_method'] ?? 0), $rows)));

        $this->relatedDataCache['customers'] = [];
        $this->relatedDataCache['methods'] = [];

        if (!empty($customerIds)) {
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

            try {
                $customerRows = $this->db->fetchAll($customerQuery, $params);
                foreach ($customerRows as $cRow) {
                    $this->relatedDataCache['customers'][(int)$cRow['id']] = $cRow['display_name'] ?? '-';
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("PaymentsReceivedDataTable::prepareRelatedData() customers failed: " . $e->getMessage());
            }
        }

        if (!empty($methodIds)) {
            $placeholders = [];
            $params = [];
            foreach ($methodIds as $index => $id) {
                $key = 'method_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $placeholdersStr = implode(',', $placeholders);

            try {
                $methodRows = $this->db->fetchAll(
                    "SELECT id, payment_method FROM `" . DB::PAYMENT_METHODS . "` WHERE id IN ({$placeholdersStr})",
                    $params
                );
                foreach ($methodRows as $mRow) {
                    $this->relatedDataCache['methods'][(int)$mRow['id']] = $mRow['payment_method'] ?? '-';
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("PaymentsReceivedDataTable::prepareRelatedData() methods failed: " . $e->getMessage());
            }
        }
    }

    /**
     * Format row data
     */
    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $paymentDate = $row['payment_date'] ?? '';
        $paymentNo = $row['payment_no'] ?? '';
        $referenceNo = $row['reference_no'] ?? '';
        $customerId = (int)$row['customer_id'];
        $paymentStatus = $row['payment_status'] ?? 'draft';
        $paymentMethodId = (int)($row['payment_method'] ?? 0);
        $totalAmount = (float)($row['total_amount_received'] ?? 0.0);

        $customerName = $this->relatedDataCache['customers'][$customerId] ?? '-';
        $methodName = $this->relatedDataCache['methods'][$paymentMethodId] ?? '-';

        // 1. Format Date according to strict standards: DD MMM YYYY
        $dateDisplay = '-';
        if (!empty($paymentDate) && $paymentDate !== '1970-01-01') {
            try {
                $dt = new \DateTime($paymentDate);
                $dateDisplay = $dt->format('d M Y');
            } catch (\Throwable $e) {
                $dateDisplay = $paymentDate;
            }
        }

        // 2. Format Currency: AED 1,250.00
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        $formattedAmount = $currencyCode . ' ' . number_format($totalAmount, 2);

        // 3. Status badge
        $statusDisplay = ucfirst($paymentStatus);
        switch (strtolower($paymentStatus)) {
            case 'paid':
                $statusDisplay = '<span class="badge bg-success bg-opacity-10 text-success">Paid</span>';
                break;
            case 'draft':
                $statusDisplay = '<span class="badge bg-secondary bg-opacity-10 text-secondary">Draft</span>';
                break;
            case 'void':
                $statusDisplay = '<span class="badge bg-danger bg-opacity-10 text-danger">Void</span>';
                break;
            case 'refund':
                $statusDisplay = '<span class="badge bg-warning bg-opacity-10 text-warning">Refund</span>';
                break;
            default:
                $statusDisplay = '<span class="badge bg-secondary bg-opacity-10 text-secondary">' . ucfirst($paymentStatus) . '</span>';
                break;
        }

        return [
            $dateDisplay,          // [0]
            $paymentNo,            // [1]
            $referenceNo,          // [2]
            $customerName,         // [3]
            $statusDisplay,        // [4]
            $methodName,           // [5]
            $formattedAmount,      // [6]
            $id,                   // [7]
            hash('sha512', 'bushogai' . $id)  // [8] PDF download token
        ];
    }

    /**
     * Get action buttons
     */
    protected function getActionButtons($id, $module)
    {
        $actions = '';

        if ($this->isGranted('edit', $module)) {
            $actions .= '<a href="payment_received_overview.php?payment_received_id=' . $id . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
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
