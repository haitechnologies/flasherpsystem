<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;

class CustomerStatementDataTable extends BaseDataTable
{
    protected $table = DB::CUSTOMERS;

    protected $searchFields = [
        'c.company_name',
        'c.display_name',
        'c.first_name',
        'c.last_name',
    ];

    protected $sortableColumns = [
        0 => 'customer',
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
        return ' AND c.organization_id = :active_org_id';
    }

    protected function buildBaseQuery($requestData)
    {
        $orgFilter = $this->getOrgIdWhereClause();
        $orgId = (int)$this->organizationId;
        $this->params['stmt_org_inv'] = $orgId;
        $this->params['stmt_org_pay'] = $orgId;
        $this->params['stmt_org_cn'] = $orgId;
        $this->params['stmt_org_last_inv'] = $orgId;
        $this->params['stmt_org_last_pay'] = $orgId;
        $this->params['stmt_org_last_cn'] = $orgId;

        $invoicedSub = "SELECT SUM(i.grand_total) FROM `" . DB::INVOICES . "` i
                        WHERE i.customer_id = c.id
                          AND i.invoice_status NOT IN ('draft', 'void', 'cancelled', 'writeoff')
                          AND i.organization_id = :stmt_org_inv";

        $paidSub = "SELECT SUM(pri.amount_received) FROM `" . DB::PAYMENT_RECEIVED_ITEMS . "` pri
                    INNER JOIN `" . DB::PAYMENTS_RECEIVED . "` pr ON pr.id = pri.payment_id
                    WHERE pr.customer_id = c.id
                      AND pr.payment_status = 'paid'
                      AND pr.organization_id = :stmt_org_pay";

        $creditSub = "SELECT SUM(cn.grand_total) FROM `" . DB::CREDIT_NOTES . "` cn
                      WHERE cn.credit_note_status NOT IN ('draft', 'void')
                        AND (cn.customer_id = c.id
                             OR EXISTS (SELECT 1 FROM `" . DB::INVOICES . "` invx
                                        WHERE invx.id = cn.invoice_id
                                          AND invx.customer_id = c.id))
                        AND cn.organization_id = :stmt_org_cn";

        $lastInvoiceSub = "SELECT MAX(i.invoice_date) FROM `" . DB::INVOICES . "` i
                           WHERE i.customer_id = c.id
                             AND i.invoice_status NOT IN ('draft', 'void', 'cancelled', 'writeoff')
                             AND i.organization_id = :stmt_org_last_inv";

        $lastPaymentSub = "SELECT MAX(pr.payment_date) FROM `" . DB::PAYMENTS_RECEIVED . "` pr
                           WHERE pr.customer_id = c.id
                             AND pr.payment_status <> 'void'
                             AND pr.organization_id = :stmt_org_last_pay";

        $lastCreditSub = "SELECT MAX(cn.created_at) FROM `" . DB::CREDIT_NOTES . "` cn
                          WHERE cn.credit_note_status NOT IN ('draft', 'void')
                            AND (cn.customer_id = c.id
                                 OR EXISTS (SELECT 1 FROM `" . DB::INVOICES . "` invx
                                            WHERE invx.id = cn.invoice_id
                                              AND invx.customer_id = c.id))
                            AND cn.organization_id = :stmt_org_last_cn";

        return "SELECT c.id,
                    COALESCE(c.company_name, c.display_name) AS customer,
                    (COALESCE(c.opening_balance, 0)
                     + COALESCE((" . $invoicedSub . "), 0)
                     - COALESCE((" . $paidSub . "), 0)
                     - COALESCE((" . $creditSub . "), 0)) AS balance,
                    COALESCE(GREATEST(
                        COALESCE((" . $lastInvoiceSub . "), '0000-00-00'),
                        COALESCE((" . $lastPaymentSub . "), '0000-00-00'),
                        COALESCE((" . $lastCreditSub . "), '0000-00-00')
                    ), '') AS last_transaction
                FROM `" . $this->table . "` c
                WHERE c.id > 0 $orgFilter";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $customer = (string)($row['customer'] ?? '');
        $balance = (float)($row['balance'] ?? 0);
        $lastTransaction = (string)($row['last_transaction'] ?? '');

        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        $formattedBalance = number_format($balance, 2);

        $actions = '<a href="customer_transactions.php?customer_id=' . $id . '" class="btn btn-sm btn-light" title="View Transactions"><i class="ph-list-dashes"></i> View</a>';

        return [
            htmlspecialchars($customer),
            $currencyCode . ' ' . $formattedBalance,
            $lastTransaction ?: '—',
            $actions,
        ];
    }
}
