<?php
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Detailed General Ledger';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

$filter_by              = ((isset($_REQUEST['filter_by']) && !empty($_REQUEST['filter_by'])) ? e_s__($_REQUEST['filter_by']) : '');
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : date('d-m-Y', strtotime('first day of this month')));
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : date('d-m-Y', strtotime('last day of this month')));
$report_basis           = ((isset($_REQUEST['report_basis']) && !empty($_REQUEST['report_basis'])) ? e_s__($_REQUEST['report_basis']) : 'accrual');
$account_filter         = ((isset($_REQUEST['account_filter']) && !empty($_REQUEST['account_filter'])) ? e_s__($_REQUEST['account_filter']) : '');

if (!function_exists('getDateRangeByFilter')) {
    function getDateRangeByFilter($filter_by)
    {
        $today = new DateTimeImmutable('today');
        switch ($filter_by) {
            case 'today':
                $start = $today;
                $end = $today;
                break;
            case 'yesterday':
                $start = $today->modify('-1 day');
                $end = $start;
                break;
            case 'this_week':
                $start = $today->modify('monday this week');
                $end = $today->modify('sunday this week');
                break;
            case 'previous_week':
                $start = $today->modify('monday last week');
                $end = $today->modify('sunday last week');
                break;
            case 'this_month':
                $start = $today->modify('first day of this month');
                $end = $today->modify('last day of this month');
                break;
            case 'previous_month':
                $start = $today->modify('first day of last month');
                $end = $today->modify('last day of last month');
                break;
            case 'this_quarter':
                $month = (int)$today->format('n');
                $quarter = intdiv($month - 1, 3) + 1;
                $startMonth = ($quarter - 1) * 3 + 1;
                $start = DateTimeImmutable::createFromFormat('Y-m-d', $today->format('Y') . '-' . str_pad((string)$startMonth, 2, '0', STR_PAD_LEFT) . '-01');
                $end = $start->modify('+2 months')->modify('last day of this month');
                break;
            case 'previous_quarter':
                $month = (int)$today->format('n');
                $quarter = intdiv($month - 1, 3) + 1;
                $startMonth = ($quarter - 1) * 3 + 1;
                $start = DateTimeImmutable::createFromFormat('Y-m-d', $today->format('Y') . '-' . str_pad((string)$startMonth, 2, '0', STR_PAD_LEFT) . '-01');
                $start = $start->modify('-3 months');
                $end = $start->modify('+2 months')->modify('last day of this month');
                break;
            case 'this_year':
                $start = new DateTimeImmutable($today->format('Y') . '-01-01');
                $end = new DateTimeImmutable($today->format('Y') . '-12-31');
                break;
            case 'previous_year':
                $start = new DateTimeImmutable($today->format('Y') . '-01-01');
                $start = $start->modify('-1 year');
                $end = new DateTimeImmutable($today->format('Y') . '-12-31');
                $end = $end->modify('-1 year');
                break;
            default:
                return [null, null];
        }

        return [$start->format('d-m-Y'), $end->format('d-m-Y')];
    }
}

if (!empty($filter_by) && $filter_by !== '0') {
    [$filter_start, $filter_end] = getDateRangeByFilter($filter_by);
    if (!empty($filter_start) && !empty($filter_end)) {
        $date_from = $filter_start;
        $date_to = $filter_end;
    }
}

// Process dates
$date_from_ymd = processDateDtoY($date_from);
$date_to_ymd = processDateDtoY($date_to);

// Build query conditions
$period_condition = '';
if (!empty($date_from_ymd)) {
    $period_condition .= " AND j.journal_date >= '" . $date_from_ymd . "'";
}
if (!empty($date_to_ymd)) {
    $period_condition .= " AND j.journal_date <= '" . $date_to_ymd . "'";
}
if (!empty($report_basis)) {
    $period_condition .= " AND j.reporting_method = '" . e_s__($report_basis) . "'";
}

// Build query for opening balances (before period start)
$opening_condition = '';
if (!empty($date_from_ymd)) {
    $opening_condition .= " AND j.journal_date < '" . $date_from_ymd . "'";
}
if (!empty($report_basis)) {
    $opening_condition .= " AND j.reporting_method = '" . e_s__($report_basis) . "'";
}

// Get all accounts
$account_where = "WHERE a.parent_id IS NOT NULL";
if (!empty($account_filter)) {
    $account_where .= " AND a.id = '" . e_s__($account_filter) . "'";
}

$sql_accounts = "SELECT a.id, a.account_code, a.account_name, a.account_type
                FROM `" . tbl_accounts . "` a
                " . $account_where . "
                ORDER BY FIELD(a.account_type, 'Assets', 'Liability', 'Equity', 'Income', 'Expense'),
                         a.account_code ASC, a.account_name ASC";

$result_accounts = $mysqli->query($sql_accounts);
$accounts_data = [];

while ($account = $result_accounts->fetch_array()) {
    // Get opening balance for this account
    $sql_opening = "SELECT COALESCE(SUM(ji.debit - ji.credit), 0) AS opening_balance
                    FROM `" . tbl_journal_items . "` ji
                    INNER JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
                    WHERE ji.account = " . $account['id'] . $opening_condition;

    $result_opening = $mysqli->query($sql_opening);
    $row_opening = $result_opening->fetch_array();
    $opening_balance = (float)$row_opening['opening_balance'];

    // Get all transactions for this account in the period [Updated: 2026-02-07]
    $sql_transactions = "SELECT j.id as journal_id, j.journal_date,
                               j.notes as description,
                               ji.debit, ji.credit
                        FROM `" . tbl_journals . "` j
                        INNER JOIN `" . tbl_journal_items . "` ji ON ji.journal_id = j.id
                        WHERE ji.account = " . $account['id'] . $period_condition . "
                        ORDER BY j.journal_date ASC, j.id ASC";

    $result_transactions = $mysqli->query($sql_transactions);
    $transactions = [];

    while ($trans = $result_transactions->fetch_array()) {
        $transactions[] = $trans;
    }

    // Only include accounts with opening balance or transactions
    if ($opening_balance != 0 || count($transactions) > 0) {
        $accounts_data[] = [
            'account' => $account,
            'opening_balance' => $opening_balance,
            'transactions' => $transactions
        ];
    }
}

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'detailed_general_ledger'");
if ($accounts_report_subcategory_id > 0) {
    $mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
}

$accounts_report_category_id    = getTableAttr('category_id', tbl_accounts_report_subcategories, $accounts_report_subcategory_id);
$accounts_report_category_name  = getTableAttr('category_name', tbl_accounts_report_categories, $accounts_report_category_id);

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<script src="<?php echo $base_url; ?>/dashboard/js/reports_filterby.js"></script>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content">

                <div class="row mt-2">
                    <div class="col-lg-6">
                        <div class="text-muted"><?php echo $accounts_report_category_name; ?></div>
                        <div class="mb-0">
                            <span class="fw-semibold">Detailed General Ledger</span> - <span class="small">From <?php echo dd_($date_from); ?> To <?php echo dd_($date_to); ?></span>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="col-lg-12 text-end">
                            <button type="button" onclick="resetForm();" class="btn btn-light my-1 me-2"><i class="ph-arrows-clockwise"></i></button>
                        </div>
                    </div>

                </div>
            </div>

            <script>
                function resetForm() {
                    document.getElementById('filter_by').value = '0';
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                    document.getElementById('report_basis').value = 'accrual';
                    document.getElementById('account_filter').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_detailed_general_ledger.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-0">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <select name="filter_by" id="filter_by" class="form-select">
                                                    <option value='0'>Filter By</option>
                                                    <option value='today' <?php echo (($filter_by == 'today') ? 'selected' : '') ?>>Today</option>
                                                    <option value='this_week' <?php echo (($filter_by == 'this_week') ? 'selected' : '') ?>>This Week</option>
                                                    <option value='this_month' <?php echo (($filter_by == 'this_month') ? 'selected' : '') ?>>This Month</option>
                                                    <option value='this_quarter' <?php echo (($filter_by == 'this_quarter') ? 'selected' : '') ?>>This Quarter</option>
                                                    <option value='this_year' <?php echo (($filter_by == 'this_year') ? 'selected' : '') ?>>This Year</option>
                                                    <option value='yesterday' <?php echo (($filter_by == 'yesterday') ? 'selected' : '') ?>>Yesterday</option>
                                                    <option value='previous_week' <?php echo (($filter_by == 'previous_week') ? 'selected' : '') ?>>Previous Week</option>
                                                    <option value='previous_month' <?php echo (($filter_by == 'previous_month') ? 'selected' : '') ?>>Previous Month</option>
                                                    <option value='previous_quarter' <?php echo (($filter_by == 'previous_quarter') ? 'selected' : '') ?>>Previous Quarter</option>
                                                    <option value='previous_year' <?php echo (($filter_by == 'previous_year') ? 'selected' : '') ?>>Previous Year</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <select name="report_basis" id="report_basis" class="form-select">
                                                    <option value='accrual' <?php echo (($report_basis == 'accrual') ? 'selected' : '') ?>>Accrual</option>
                                                    <option value='cash' <?php echo (($report_basis == 'cash') ? 'selected' : '') ?>>Cash</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <select name="account_filter" id="account_filter" class="form-select">
                                                <option value=''>All Accounts</option>
                                                <?php
                                                $result_all_accounts = $mysqli->query("SELECT id, account_name, account_code FROM `" . tbl_accounts . "` WHERE parent_id IS NOT NULL ORDER BY account_name ASC");
                                                while ($row_acc = $result_all_accounts->fetch_array()) {
                                                ?>
                                                    <option value="<?php echo $row_acc['id']; ?>" <?php echo (($account_filter == $row_acc['id']) ? 'selected' : '') ?>><?php echo s__($row_acc['account_name']); ?> (<?php echo s__($row_acc['account_code']); ?>)</option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="">
                                            <button type="submit" class="btn btn-primary"><i class="ph-magnifying-glass me-2"></i>Generate Report</button>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>
</div>
    <!-- /page header -->


    <div class="content">

        <div class="card">
            <div class="card-header text-center">
                <p class="text-muted">Flash Logistics FZC</p>
                <h5 class="mb-0">Detailed General Ledger</h5>
                <p class="small"><span class="text-muted">Basis</span> : <?php echo ucfirst($report_basis); ?></p>
                <p class="small"><span class="text-muted">From</span> <?php echo dd_($date_from); ?> <span class="text-muted">To</span> <?php echo dd_($date_to); ?></p>
            </div>
        </div>

        <?php
        $current_type = '';
        $grand_total_debit = 0;
        $grand_total_credit = 0;

        foreach ($accounts_data as $data) {
            $account = $data['account'];
            $opening_balance = $data['opening_balance'];
            $transactions = $data['transactions'];

            // Add type separator
            if ($current_type !== $account['account_type']) {
                if ($current_type !== '') {
                    echo '<div class="mb-3"></div>';
                }
                $current_type = $account['account_type'];
                ?>
                <div class="card bg-secondary text-white mb-2">
                    <div class="card-body py-2">
                        <h6 class="mb-0 fw-bold"><?php echo strtoupper($account['account_type']); ?></h6>
                    </div>
                </div>
                <?php
            }

            $running_balance = $opening_balance;
            $account_total_debit = 0;
            $account_total_credit = 0;
        ?>

        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <span class="badge bg-info me-2"><?php echo s__($account['account_code']); ?></span>
                    <?php echo s__($account['account_name']); ?>
                </h6>
            </div>

            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr class="table-light">
                            <th>DATE</th>
                            <th>DESCRIPTION</th>
                            <th class="text-end">DEBIT</th>
                            <th class="text-end">CREDIT</th>
                            <th class="text-end">BALANCE</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($opening_balance != 0) { ?>
                        <tr class="table-warning">
                            <td colspan="2"><strong>Opening Balance</strong></td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end fw-semibold <?php echo ($opening_balance >= 0) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo dec_($opening_balance, BASE_CURRENCY['code']); ?>
                            </td>
                        </tr>
                        <?php } ?>

                        <?php foreach ($transactions as $trans) {
                            $debit = (float)$trans['debit'];
                            $credit = (float)$trans['credit'];
                            $running_balance += ($debit - $credit);
                            $account_total_debit += $debit;
                            $account_total_credit += $credit;
                        ?>
                        <tr>
                            <td><?php echo processDateYtoD($trans['journal_date']); ?></td>
                            <td><?php echo s__($trans['description']); ?></td>
                            <td class="text-end"><?php echo ($debit > 0) ? dec_($debit, BASE_CURRENCY['code']) : '-'; ?></td>
                            <td class="text-end"><?php echo ($credit > 0) ? dec_($credit, BASE_CURRENCY['code']) : '-'; ?></td>
                            <td class="text-end <?php echo ($running_balance >= 0) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo dec_($running_balance, BASE_CURRENCY['code']); ?>
                            </td>
                        </tr>
                        <?php } ?>

                        <tr class="table-light fw-semibold">
                            <td colspan="2"><strong>Closing Balance</strong></td>
                            <td class="text-end"><?php echo dec_($account_total_debit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($account_total_credit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end <?php echo ($running_balance >= 0) ? 'text-success' : 'text-danger'; ?>">
                                <?php echo dec_($running_balance, BASE_CURRENCY['code']); ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
            $grand_total_debit += $account_total_debit;
            $grand_total_credit += $account_total_credit;
        }

        if (empty($accounts_data)) {
        ?>
            <div class="card">
                <div class="card-body text-center text-muted">
                    No transactions found for the selected period.
                </div>
            </div>
        <?php } ?>

    </div>


    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
