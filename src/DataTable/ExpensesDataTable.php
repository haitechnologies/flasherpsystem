<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class ExpensesDataTable extends BaseDataTable
{
    protected $table = DB::EXPENSES;
    protected $searchFields = ['reference_no'];
    protected $sortableColumns = [0 => 'expense_date', 1 => 'id', 2 => 'reference_no', 3 => 'vendor_id', 4 => 'paid_through', 5 => 'customer_id', 6 => 'expense_status', 7 => 'grand_total'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $vendorIds = [];
        $customerIds = [];
        $accountIds = [];
        $expenseIds = [];

        foreach ($rows as $row) {
            $vendorIds[] = (int)($row['vendor_id'] ?? 0);
            $customerIds[] = (int)($row['customer_id'] ?? 0);
            $accountIds[] = (int)($row['paid_through'] ?? 0);
            $expenseIds[] = (int)($row['id'] ?? 0);
        }

        $this->relatedDataCache['vendors'] = $this->fetchLookupMap(DB::VENDORS, $vendorIds, 'display_name');
        $this->relatedDataCache['customers'] = $this->fetchLookupMap(DB::CUSTOMERS, $customerIds, 'display_name');
        $this->relatedDataCache['accounts'] = $this->fetchLookupMap(DB::ACCOUNTS, $accountIds, 'account_name');
        $this->relatedDataCache['expense_accounts'] = [];
        $expenseItemsTable = DB::table('expense_items');

        $expenseIds = array_values(array_filter(array_unique(array_map('intval', $expenseIds))));
        if (!$expenseIds || $expenseItemsTable === '') {
            return;
        }

        $sql = "SELECT expense_id, expense_account FROM `" . $expenseItemsTable . "` WHERE expense_id IN (" . implode(',', $expenseIds) . ") ORDER BY id ASC";
        $items = $this->db->fetchAll($sql, []);

        $expenseAccountIds = [];
        $expenseAccountsByExpense = [];
        foreach ($items as $item) {
            $expenseId = (int)($item['expense_id'] ?? 0);
            $accountId = (int)($item['expense_account'] ?? 0);
            if ($expenseId <= 0 || $accountId <= 0) {
                continue;
            }

            if (!isset($expenseAccountsByExpense[$expenseId])) {
                $expenseAccountsByExpense[$expenseId] = [];
            }

            if (!in_array($accountId, $expenseAccountsByExpense[$expenseId], true)) {
                $expenseAccountsByExpense[$expenseId][] = $accountId;
                $expenseAccountIds[] = $accountId;
            }
        }

        $expenseAccountNames = $this->fetchLookupMap(DB::ACCOUNTS, $expenseAccountIds, 'account_name');
        foreach ($expenseAccountsByExpense as $expenseId => $ids) {
            $names = [];
            foreach ($ids as $accountId) {
                if (!empty($expenseAccountNames[$accountId])) {
                    $names[] = $expenseAccountNames[$accountId];
                }
            }
            $this->relatedDataCache['expense_accounts'][$expenseId] = implode(', ', $names);
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $date = (string)($row['expense_date'] ?? '');
        $vendorId = (int)($row['vendor_id'] ?? 0);
        $customerId = (int)($row['customer_id'] ?? 0);
        $paidThroughId = (int)($row['paid_through'] ?? 0);
        $status = trim((string)($row['expense_status'] ?? ''));
        $statusBadge = $status !== '' ? BadgeHelper::info(htmlspecialchars($status)) : BadgeHelper::secondary('Draft');
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';

        return [
            $this->formatDate($date) ?: '-',
            '<a href="expense_overview.php?id=' . $id . '" class="text-decoration-none">' . htmlspecialchars((string)($this->relatedDataCache['expense_accounts'][$id] ?? '')) . '</a>',
            '<a href="expense_overview.php?id=' . $id . '" class="text-decoration-none">' . htmlspecialchars((string)($row['reference_no'] ?? '')) . '</a>',
            htmlspecialchars((string)($this->relatedDataCache['vendors'][$vendorId] ?? '')),
            htmlspecialchars((string)($this->relatedDataCache['accounts'][$paidThroughId] ?? '')),
            htmlspecialchars((string)($this->relatedDataCache['customers'][$customerId] ?? '')),
            $statusBadge,
            $currencyCode . ' ' . number_format((float)($row['grand_total'] ?? 0), 2),
        ];
    }

}
