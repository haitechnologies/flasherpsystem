<?php

use App\Core\DB;
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Trial Balance';
$tbl_name = DB::TRIAL_BALANCE;
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
$as_of_date             = ((isset($_REQUEST['as_of_date']) && !empty($_REQUEST['as_of_date'])) ? e_s__($_REQUEST['as_of_date']) : date('d-m-Y'));
$report_basis           = ((isset($_REQUEST['report_basis']) && !empty($_REQUEST['report_basis'])) ? e_s__($_REQUEST['report_basis']) : 'accrual');

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
    if (!empty($filter_end)) {
        $as_of_date = $filter_end; // Trial balance uses the end date only
    }
}

if (!function_exists('normalizeDateToYmd')) {
    function normalizeDateToYmd($date)
    {
        if (empty($date)) return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
        return processDateDtoY($date);
    }
}
$as_of_date_ymd = (!empty($as_of_date) ? normalizeDateToYmd($as_of_date) : '');

// Build subquery conditions for filtering transactions
$transaction_filter = '';
if (!empty($as_of_date_ymd)) {
    $transaction_filter .= " AND j.journal_date <= '" . $as_of_date_ymd . "'";
}
if (!empty($report_basis)) {
    $transaction_filter .= " AND j.reporting_method = '" . e_s__($report_basis) . "'";
}
if (!empty($activeOrganizationId)) {
    $transaction_filter .= " AND j.organization_id = " . (int)$activeOrganizationId;
}

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", DB::ACCOUNTS_REPORT_SUBCATEGORIES, " slug = 'trial_balance'");
if (!empty($accounts_report_subcategory_id)) {
    $mysqli->query("UPDATE `" . DB::ACCOUNTS_REPORT_SUBCATEGORIES . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
    $accounts_report_category_id    = getTableAttr('category_id', DB::ACCOUNTS_REPORT_SUBCATEGORIES, $accounts_report_subcategory_id);
    $accounts_report_category_name  = getTableAttr('category_name', DB::ACCOUNTS_REPORT_CATEGORIES, $accounts_report_category_id);
} else {
    $accounts_report_category_name = 'Financial Reports';
}

// Build trial balance rows using subqueries for correct date filtering
$trial_rows = [];
$trial_sql = "SELECT a.id, a.account_code, a.account_name, a.account_type, a.parent_id, a.level,
                     COALESCE((SELECT SUM(ji.debit)
                              FROM `" . DB::JOURNAL_ITEMS . "` ji
                              INNER JOIN `" . DB::JOURNALS . "` j ON j.id = ji.journal_id
                              WHERE ji.account = a.id" . $transaction_filter . "), 0) AS debit,
                     COALESCE((SELECT SUM(ji.credit)
                              FROM `" . DB::JOURNAL_ITEMS . "` ji
                              INNER JOIN `" . DB::JOURNALS . "` j ON j.id = ji.journal_id
                              WHERE ji.account = a.id" . $transaction_filter . "), 0) AS credit
              FROM `" . DB::ACCOUNTS . "` a
              WHERE a.parent_id IS NOT NULL
              ORDER BY FIELD(a.account_type, 'Assets', 'Liability', 'Equity', 'Income', 'Expense'),
                       a.account_name ASC";

$trial_result = $mysqli->query($trial_sql);
if ($trial_result) {
    while ($row = $trial_result->fetch_array()) {
        $trial_rows[] = $row;
    }
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
                            <span class="fw-semibold">Trial Balance</span> - <span class="small">As of <?php echo ddm_($as_of_date); ?></span>
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
                    document.getElementById('as_of_date').value = '';
                    document.getElementById('report_basis').value = 'accrual';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_trial_balance.php">
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
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="as_of_date" id="as_of_date" placeholder="As of Date" value="<?php echo $as_of_date; ?>">
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
                <p class="text-muted">Flash Logistics FZC</p>
                <h5 class="mb-0">Trial Balance</h5>
                <p class="small"><span class="text-muted">Basis</span> : <?php echo ucfirst($report_basis); ?></p>
                <p class="small"><span class="text-muted">As of</span> <?php echo ddm_($as_of_date); ?></p>

                <!-- DEBUG INFO - Remove this after testing -->
                <div style="background: #f0f0f0; padding: 10px; margin: 10px; text-align: left; font-size: 11px;">
                    <strong>Debug Info:</strong><br>
                    Filter By: <?php echo $filter_by ? $filter_by : 'None'; ?><br>
                    As of Date (Display): <?php echo $as_of_date; ?><br>
                    As of Date (SQL): <?php echo $as_of_date_ymd; ?><br>
                    Report Basis: <?php echo $report_basis; ?><br>
                    Transaction Filter: <code><?php echo htmlspecialchars($transaction_filter); ?></code><br>
                    Total Rows Returned: <?php echo count($trial_rows); ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">Account</th>
                            <th class="table-light text-end">Debit</th>
                            <th class="table-light text-end">Credit</th>
                        </tr>

                        <?php
                        $total_debit = 0;
                        $total_credit = 0;
                        $current_type = '';

                        if (!empty($trial_rows)) {
                            foreach ($trial_rows as $row) {
                                $debit = (float)($row['debit'] ?? 0);
                                $credit = (float)($row['credit'] ?? 0);

                                // Skip accounts with zero balance
                                if ($debit == 0 && $credit == 0) {
                                    continue;
                                }

                                $total_debit += $debit;
                                $total_credit += $credit;

                                // Add type separator
                                if ($current_type !== $row['account_type']) {
                                    if ($current_type !== '') {
                                        echo '<tr><td colspan="3" class="border-0">&nbsp;</td></tr>';
                                    }
                                    $current_type = $row['account_type'];
                        ?>
                                    <tr class="table-secondary">
                                        <td colspan="3" class="fw-bold"><?php echo strtoupper($row['account_type']); ?></td>
                                    </tr>
                        <?php
                                }
                        ?>
                                <tr>
                                    <td><?php echo s__($row['account_name']); ?></td>
                                    <td class="text-end"><?php echo dec_($debit, BASE_CURRENCY['code']); ?></td>
                                    <td class="text-end"><?php echo dec_($credit, BASE_CURRENCY['code']); ?></td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr>
                                <td colspan="3" class="text-muted text-center">No account transactions found.</td>
                            </tr>
                        <?php } ?>

                        <tr class="fw-semibold table-light">
                            <td>TOTAL</td>
                            <td class="text-end"><?php echo dec_($total_debit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($total_credit, BASE_CURRENCY['code']); ?></td>
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