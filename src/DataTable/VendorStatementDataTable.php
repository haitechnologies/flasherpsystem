<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;

class VendorStatementDataTable extends BaseDataTable
{
    protected $table = DB::VENDORS;

    protected $searchFields = [
        'v.company_name',
        'v.display_name',
        'v.first_name',
        'v.last_name',
    ];

    protected $sortableColumns = [
        0 => 'vendor',
        1 => 'balance',
        2 => 'last_transaction',
        3 => 'id',
    ];

    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }
        $this->params['active_org_id'] = (int)$this->organizationId;
        return ' AND v.organization_id = :active_org_id';
    }

    protected function buildBaseQuery($requestData)
    {
        $orgFilter = $this->getOrgIdWhereClause();
        $orgId = (int)$this->organizationId;
        $this->params['stmt_org_pur'] = $orgId;
        $this->params['stmt_org_pay'] = $orgId;
        $this->params['stmt_org_dn'] = $orgId;
        $this->params['stmt_org_last_pur'] = $orgId;
        $this->params['stmt_org_last_pay'] = $orgId;
        $this->params['stmt_org_last_dn'] = $orgId;

        $purchasedSub = "SELECT SUM(p.grand_total) FROM `" . DB::PURCHASES . "` p
                        WHERE p.vendor_id = v.id
                          AND p.purchase_status NOT IN ('draft', 'declined', 'expired')
                          AND p.organization_id = :stmt_org_pur";

        $paidSub = "SELECT SUM(pm.total_amount_paid) FROM `" . DB::PAYMENTS_MADE . "` pm
                    WHERE pm.vendor_id = v.id
                      AND pm.payment_status <> 'void'
                      AND pm.organization_id = :stmt_org_pay";

        $debitSub = "SELECT SUM(dn.grand_total) FROM `" . DB::DEBIT_NOTES . "` dn
                      WHERE dn.debit_note_status NOT IN ('draft', 'void')
                        AND dn.vendor_id = v.id
                        AND dn.organization_id = :stmt_org_dn";

        $lastPurchaseSub = "SELECT MAX(p.purchase_date) FROM `" . DB::PURCHASES . "` p
                           WHERE p.vendor_id = v.id
                             AND p.purchase_status NOT IN ('draft', 'declined', 'expired')
                             AND p.organization_id = :stmt_org_last_pur";

        $lastPaymentSub = "SELECT MAX(pm.payment_date) FROM `" . DB::PAYMENTS_MADE . "` pm
                           WHERE pm.vendor_id = v.id
                             AND pm.payment_status <> 'void'
                             AND pm.organization_id = :stmt_org_last_pay";

        $lastDebitSub = "SELECT MAX(dn.debit_note_date) FROM `" . DB::DEBIT_NOTES . "` dn
                          WHERE dn.debit_note_status NOT IN ('draft', 'void')
                            AND dn.vendor_id = v.id
                            AND dn.organization_id = :stmt_org_last_dn";

        return "SELECT v.id,
                    COALESCE(v.company_name, v.display_name) AS vendor,
                    (COALESCE(v.opening_balance, 0)
                     + COALESCE((" . $purchasedSub . "), 0)
                     - COALESCE((" . $paidSub . "), 0)
                     - COALESCE((" . $debitSub . "), 0)) AS balance,
                    COALESCE(GREATEST(
                        COALESCE((" . $lastPurchaseSub . "), '0000-00-00'),
                        COALESCE((" . $lastPaymentSub . "), '0000-00-00'),
                        COALESCE((" . $lastDebitSub . "), '0000-00-00')
                    ), '') AS last_transaction
                FROM `" . $this->table . "` v
                WHERE v.id > 0 $orgFilter";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $vendor = (string)($row['vendor'] ?? '');
        $balance = (float)($row['balance'] ?? 0);
        $lastTransaction = (string)($row['last_transaction'] ?? '');

        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        $formattedBalance = number_format($balance, 2);

        $actions = '<a href="vendor_transactions.php?vendor_id=' . $id . '" class="btn btn-sm btn-light" title="View Transactions"><i class="ph-list-dashes"></i> View</a>';

        return [
            htmlspecialchars($vendor),
            $currencyCode . ' ' . $formattedBalance,
            $lastTransaction ?: '—',
            $actions,
        ];
    }
}
