<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;

class CustomerTransactionsDataTable extends BaseDataTable
{
    protected $table = DB::CUSTOMER_TRANSACTIONS;
    protected $searchFields = ['ct.transaction_id', 'ct.status', 'c.company_name', 'c.display_name'];
    protected $sortableColumns = [
        0 => 'ct.id', 1 => 'customer', 2 => 'ct.amount',
        3 => 'ct.transaction_date', 4 => 'ct.status'
    ];

    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }
        $this->params['active_org_id'] = (int)$this->organizationId;
        return ' AND ct.organization_id = :active_org_id';
    }

    protected function buildBaseQuery($requestData)
    {
        $orgFilter = $this->getOrgIdWhereClause();
        $where = "WHERE 1=1 $orgFilter";

        if (!empty($requestData['customer_id'])) {
            $where .= " AND ct.customer_id = :customer_id";
            $this->params[':customer_id'] = (int)$requestData['customer_id'];
        }

        return "SELECT ct.*, COALESCE(c.company_name, c.display_name) AS customer
                FROM `" . $this->table . "` ct
                LEFT JOIN `" . DB::CUSTOMERS . "` c ON ct.customer_id = c.id
                $where";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $customer = (string)($row['customer'] ?? '');
        $amount   = number_format((float)($row['amount'] ?? 0), 2);
        $date     = (string)($row['transaction_date'] ?? '');
        $status   = (string)($row['status'] ?? '');
        $statusBadge = match (strtolower($status)) {
            'completed' => BadgeHelper::success($status),
            'pending'   => BadgeHelper::warning($status),
            'failed', 'cancelled' => BadgeHelper::danger($status),
            default     => BadgeHelper::secondary($status),
        };
        return [
            $this->rowNumber,
            htmlspecialchars($customer),
            $amount,
            $date,
            $statusBadge,
        ];
    }
}
