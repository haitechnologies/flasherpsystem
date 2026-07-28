<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Profit & Loss';
$tbl_name = DB::JOURNALS; // Reports use journal data, no dedicated table needed
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

$filter_by              = ((isset($_REQUEST['filter_by']) && !empty($_REQUEST['filter_by'])) ? e_s__($_REQUEST['filter_by']) : '');
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : date('d-m-Y', strtotime('first day of this month')));
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : date('d-m-Y', strtotime('last day of this month')));

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

if (!function_exists('normalizeDateToYmd')) {
    function normalizeDateToYmd($date)
    {
        if (empty($date)) {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        return processDateDtoY($date);
    }
}

$date_from_ymd = normalizeDateToYmd($date_from);
$date_to_ymd   = normalizeDateToYmd($date_to);

$date_join = '';
if (!empty($date_from_ymd)) {
    $date_join .= " AND j.journal_date >= '" . $date_from_ymd . "'";
}
if (!empty($date_to_ymd)) {
    $date_join .= " AND j.journal_date <= '" . $date_to_ymd . "'";
}

if (!function_exists('getProfitLossAccounts')) {
    function getProfitLossAccounts($mysqli, $account_types, $date_join)
    {
        $rows = [];
        $types = (array)$account_types;
        $types = array_map(function ($type) {
            return "'" . $type . "'";
        }, $types);
        $type_clause = implode(', ', $types);

        $sql = "SELECT a.id, a.account_name, a.account_code, a.account_type, a.parent_id, a.level,
                       COALESCE(SUM(ji.debit), 0) AS debit,
                       COALESCE(SUM(ji.credit), 0) AS credit
                FROM `" . DB::ACCOUNTS . "` a
                LEFT JOIN `" . DB::JOURNAL_ITEMS . "` ji ON ji.account = a.id
                LEFT JOIN `" . DB::JOURNALS . "` j ON j.id = ji.journal_id" . $date_join . "
                WHERE a.account_type IN (" . $type_clause . ")
                GROUP BY a.id
                ORDER BY a.account_name ASC";

        $result = $mysqli->query($sql);
        if ($result) {
            while ($row = $result->fetch_array()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}

if (!function_exists('calculateProfitLossBalance')) {
    function calculateProfitLossBalance($row, $normal_side = 'credit')
    {
        $debit = (float)($row['debit'] ?? 0);
        $credit = (float)($row['credit'] ?? 0);
        return ($normal_side === 'credit') ? ($credit - $debit) : ($debit - $credit);
    }
}

$income_accounts   = getProfitLossAccounts($mysqli, ['Income', 'Other Income'], $date_join);
$expense_accounts  = getProfitLossAccounts($mysqli, ['Expense', 'Other Expense'], $date_join);

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", DB::ACCOUNTS_REPORT_SUBCATEGORIES, " slug = 'profit_and_loss'");
if (!empty($accounts_report_subcategory_id)) {
    $mysqli->query("UPDATE `" . DB::ACCOUNTS_REPORT_SUBCATEGORIES . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
    $accounts_report_category_id    = getTableAttr('category_id', DB::ACCOUNTS_REPORT_SUBCATEGORIES, $accounts_report_subcategory_id);
    $accounts_report_category_name  = getTableAttr('category_name', DB::ACCOUNTS_REPORT_CATEGORIES, $accounts_report_category_id);
} else {
    $accounts_report_category_name = 'Financial Reports';
}

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
                            <span class="fw-semibold">Profit &amp; Loss</span> - <span class="small">From <?php echo dd_($date_from); ?> To <?php echo dd_($date_to); ?></span>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="col-lg-12 text-end">
                            <button type="button" onclick="resetForm();" class="btn btn-light my-1 me-2"><i class="ph-arrows-clockwise"></i></button>
                            <!-- <a href="<?php //echo $targetpage; 
                                            ?>&export=excel">
                                <button type="button" class="btn btn-light btn-labeled btn-labeled-start">
                                    <span class="btn-labeled-icon bg-light bg-opacity-20"><i class="ph-file-csv"></i></span>Export Excel
                                </button>
                            </a> -->
                        </div>
                    </div>

                </div>
            </div>

            <script>
                function resetForm() {
                    document.getElementById('filter_by').value = '0';
                    document.getElementById('filter_by').text = 'Please select';
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_profit_and_loss.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-0">
                                            <!-- <label class="form-label fw-semibold">&nbsp; </label> -->

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
                                            <!-- <label class="form-label fw-semibold">Date From: </label> -->

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
                                            <!-- <label class="form-label fw-semibold">Date To: </label> -->

                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <div class="">
                                            <button type="submit" class="btn btn-light">Run Report</button>
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
                <p>Flash Logistics FZC</p>
                <h5 class="mb-0">Profit &amp; Loss</h5>
                <p><span class="text-muted">From</span> <?php echo dd_($date_from); ?> <span class="text-muted">To</span> <?php echo dd_($date_to); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">Account</th>
                            <th class="table-light text-end">Amount</th>
                        </tr>

                        <tr>
                            <th class="table-light" colspan="2">Income</th>
                        </tr>
                        <?php
                        $total_income = 0;
                        if (!empty($income_accounts)) {
                            foreach ($income_accounts as $row) {
                                $balance = calculateProfitLossBalance($row, 'credit');
                                $total_income += $balance;
                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', max(0, (int)$row['level'] - 1));
                                $formatted_balance = ($balance < 0 ? '-' : '') . BASE_CURRENCY['code'] . dec_(abs($balance));
                        ?>
                                <tr>
                                    <td><?php echo $indent . s__($row['account_name']); ?></td>
                                    <td class="text-end"><?php echo $formatted_balance; ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="2" class="text-muted">No income accounts found.</td>
                            </tr>
                        <?php } ?>
                        <tr class="fw-semibold">
                            <td>Total Income</td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code'] . dec_($total_income); ?></td>
                        </tr>

                        <tr>
                            <th class="table-light" colspan="2">Expenses</th>
                        </tr>
                        <?php
                        $total_expense = 0;
                        if (!empty($expense_accounts)) {
                            foreach ($expense_accounts as $row) {
                                $balance = calculateProfitLossBalance($row, 'debit');
                                $total_expense += $balance;
                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;', max(0, (int)$row['level'] - 1));
                                $formatted_balance = ($balance < 0 ? '-' : '') . BASE_CURRENCY['code'] . dec_(abs($balance));
                        ?>
                                <tr>
                                    <td><?php echo $indent . s__($row['account_name']); ?></td>
                                    <td class="text-end"><?php echo $formatted_balance; ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="2" class="text-muted">No expense accounts found.</td>
                            </tr>
                        <?php } ?>
                        <tr class="fw-semibold">
                            <td>Total Expenses</td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code'] . dec_($total_expense); ?></td>
                        </tr>

                        <?php
                        $net_profit = $total_income - $total_expense;
                        $net_label = ($net_profit >= 0) ? 'Net Profit' : 'Net Loss';
                        ?>
                        <tr class="fw-semibold table-light">
                            <td><?php echo $net_label; ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code'] . dec_(abs($net_profit)); ?></td>
                        </tr>

                    </tbody>
                </table>
            </div>

        </div>

    </div>


    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>