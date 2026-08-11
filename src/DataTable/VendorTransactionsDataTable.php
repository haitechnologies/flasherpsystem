<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;

class VendorTransactionsDataTable extends BaseDataTable
{
    protected $table = DB::VENDOR_TRANSACTIONS;
    protected $searchFields = ['vt.transaction_id', 'vt.status', 'v.company_name', 'v.display_name'];
    protected $sortableColumns = [
        0 => 'vt.id', 1 => 'vendor', 2 => 'vt.amount',
        3 => 'vt.transaction_date', 4 => 'vt.status'
    ];

    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }
        $this->params['active_org_id'] = (int)$this->organizationId;
        return ' AND vt.organization_id = :active_org_id';
    }

    protected function buildBaseQuery($requestData)
    {
        $orgFilter = $this->getOrgIdWhereClause();
        $where = "WHERE 1=1 $orgFilter";

        if (!empty($requestData['vendor_id'])) {
            $where .= " AND vt.vendor_id = :vendor_id";
            $this->params[':vendor_id'] = (int)$requestData['vendor_id'];
        }

        return "SELECT vt.*, COALESCE(v.company_name, v.display_name) AS vendor
                FROM `" . $this->table . "` vt
                LEFT JOIN `" . DB::VENDORS . "` v ON vt.vendor_id = v.id
                $where";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $vendor   = (string)($row['vendor'] ?? '');
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
            htmlspecialchars($vendor),
            $amount,
            $date,
            $statusBadge,
        ];
    }
}
