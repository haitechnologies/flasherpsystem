<?php

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Movement of Equity';
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
| HELPER FUNCTIONS FOR EQUITY MOVEMENT
|--------------------------------------------------------------------------
|
*/

function getEquityAccounts($mysqli, $date_join) {
    $sql = "SELECT a.id, a.account_name, a.account_code, a.account_type, a.parent_id, a.level,
                   COALESCE(SUM(ji.debit), 0) AS debit, COALESCE(SUM(ji.credit), 0) AS credit
            FROM `" . tbl_accounts . "` a
            LEFT JOIN `" . tbl_journal_items . "` ji ON ji.account = a.id
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id" . $date_join . "
            WHERE a.account_type = 'Equity'
            GROUP BY a.id
            ORDER BY a.account_name ASC";
    $result = $mysqli->query($sql);
    $accounts = array();
    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $accounts[] = $row;
    }
    return $accounts;
}

function calculateBeginningBalance($mysqli, $date_from_ymd) {
    $sql = "SELECT a.id, a.account_name, COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS balance
            FROM `" . tbl_accounts . "` a
            LEFT JOIN `" . tbl_journal_items . "` ji ON ji.account = a.id
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            WHERE a.account_type = 'Equity' AND j.journal_date < '" . $date_from_ymd . "'
            GROUP BY a.id
            ORDER BY a.account_name ASC";
    $result = $mysqli->query($sql);
    $total = 0;
    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        $total += (float)$row['balance'];
    }
    return $total;
}

function calculateEquityMovements($mysqli, $date_from_ymd, $date_to_ymd) {
    $movements = array(
        'total_income' => 0,
        'total_expenses' => 0,
        'net_income' => 0,
        'total_dividends' => 0,
        'other_changes' => 0
    );
    
    // Total Income during period
    $sql = "SELECT COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS income
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account
            WHERE a.account_type = 'Income' 
            AND j.journal_date >= '" . $date_from_ymd . "' AND j.journal_date <= '" . $date_to_ymd . "'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $movements['total_income'] = (float)$row['income'];
    
    // Total Expenses during period
    $sql = "SELECT COALESCE(SUM(ji.debit), 0) - COALESCE(SUM(ji.credit), 0) AS expense
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account
            WHERE a.account_type = 'Expense' 
            AND j.journal_date >= '" . $date_from_ymd . "' AND j.journal_date <= '" . $date_to_ymd . "'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $movements['total_expenses'] = (float)$row['expense'];
    
    // Net Income
    $movements['net_income'] = $movements['total_income'] - $movements['total_expenses'];
    
    // Look for dividend accounts (typically named 'Dividends' or 'Drawings')
    $sql = "SELECT COALESCE(SUM(ji.debit), 0) AS dividends
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account
            WHERE (a.account_name LIKE '%Dividend%' OR a.account_name LIKE '%Drawing%' OR a.account_name LIKE '%Distribution%')
            AND j.journal_date >= '" . $date_from_ymd . "' AND j.journal_date <= '" . $date_to_ymd . "'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $movements['total_dividends'] = (float)$row['dividends'];
    
    return $movements;
}

function getEndingEquityBalance($mysqli, $date_to_ymd) {
    $sql = "SELECT COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account
            WHERE a.account_type = 'Equity' AND j.journal_date <= '" . $date_to_ymd . "'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    return (float)$row['balance'];
}


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

// Process dates
$date_from_ymd = processDateDtoY($date_from);
$date_to_ymd = processDateDtoY($date_to);

// Get equity movement data
$beginning_balance = calculateBeginningBalance($mysqli, $date_from_ymd);
$movements = calculateEquityMovements($mysqli, $date_from_ymd, $date_to_ymd);
$ending_balance = getEndingEquityBalance($mysqli, $date_to_ymd);
$equity_accounts = getEquityAccounts($mysqli, "");

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'movement_of_equity'");
if ($accounts_report_subcategory_id > 0) {
    $mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content">

                <div class="row mt-2">
                    <div class="col-lg-6">
                        <div class="text-muted">Financial Reporting</div>
                        <div class="mb-0">
                            <span class="fw-semibold">Movement of Equity</span> - <span class="fw-normal">From <?php echo processDateYtoD($date_from_ymd); ?> to <?php echo processDateYtoD($date_to_ymd); ?></span>
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
                    document.getElementById('filter_by').text = 'Please select';
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_movement_of_equity.php">
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
                                                <input type="date" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="date" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>

                                        </div>
                                    </div>

                                    <div class="col-lg-3">
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

    <script src="reports_filterby.js"></script>

    <div class="content">

        <div class="card">
            <div class="card-header text-center">
                <p>Flash Logistics FZC</p>
                <h5 class="mb-0">Statement of Changes in Equity</h5>
                <p><span class="text-muted">For the Period From</span> <?php echo processDateYtoD($date_from_ymd); ?> <span class="text-muted">To</span> <?php echo processDateYtoD($date_to_ymd); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table table-light">
                    <tbody>
                        <tr>
                            <th class="table-light">Description</th>
                            <th class="table-light text-end">Amount</th>
                        </tr>
                        <tr class="table-light fw-semibold">
                            <td>EQUITY AT BEGINNING OF PERIOD</td>
                            <td class="text-end fw-semibold"><?php echo dec_($beginning_balance, BASE_CURRENCY['code']); ?></td>
                        </tr>
                        <tr>
                            <td colspan="2" class="fw-semibold pt-3">Add: During the Period</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 40px;">Total Income</td>
                            <td class="text-end text-success fw-semibold"><?php echo dec_($movements['total_income'], BASE_CURRENCY['code']); ?></td>
                        </tr>
                        <tr>
                            <td style="padding-left: 40px;">Less: Total Expenses</td>
                            <td class="text-end text-danger fw-semibold">(<?php echo dec_($movements['total_expenses'], BASE_CURRENCY['code']); ?>)</td>
                        </tr>
                        <tr class="table-light">
                            <td style="padding-left: 40px;" class="fw-semibold">Net Income for Period</td>
                            <td class="text-end fw-semibold <?php echo $movements['net_income'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo dec_($movements['net_income'], BASE_CURRENCY['code']); ?></td>
                        </tr>
                        <?php if ($movements['total_dividends'] > 0) { ?>
                        <tr>
                            <td colspan="2" class="fw-semibold pt-3">Less: Distributions to Owners</td>
                        </tr>
                        <tr>
                            <td style="padding-left: 40px;">Dividends / Drawings</td>
                            <td class="text-end text-danger fw-semibold">(<?php echo dec_($movements['total_dividends'], BASE_CURRENCY['code']); ?>)</td>
                        </tr>
                        <?php } ?>
                        <tr class="table-light fw-semibold">
                            <td>EQUITY AT END OF PERIOD</td>
                            <td class="text-end fw-semibold <?php echo $ending_balance >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo dec_($ending_balance, BASE_CURRENCY['code']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Equity Accounts Breakdown -->
        <div class="card mt-3">
            <div class="card-header">
                <h5 class="mb-0">Equity Accounts Detail (End of Period)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-light">
                    <tbody>
                        <tr>
                            <th class="table-light">Equity Account</th>
                            <th class="table-light">Account Code</th>
                            <th class="table-light text-end">Period Activity (Debit)</th>
                            <th class="table-light text-end">Period Activity (Credit)</th>
                            <th class="table-light text-end">Net Movement</th>
                        </tr>
                        <?php
                        $total_debit = 0;
                        $total_credit = 0;
                        $total_movement = 0;
                        
                        foreach ($equity_accounts as $account) {
                            $net_movement = $account['credit'] - $account['debit'];
                            $total_debit += $account['debit'];
                            $total_credit += $account['credit'];
                            $total_movement += $net_movement;
                        ?>
                        <tr>
                            <td><?php echo s__($account['account_name']); ?></td>
                            <td><?php echo s__($account['account_code']); ?></td>
                            <td class="text-end"><?php echo dec_($account['debit'], BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($account['credit'], BASE_CURRENCY['code']); ?></td>
                            <td class="text-end fw-semibold <?php echo $net_movement >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo dec_($net_movement, BASE_CURRENCY['code']); ?></td>
                        </tr>
                        <?php } ?>
                        <tr class="table-light fw-semibold">
                            <td colspan="2">TOTALS</td>
                            <td class="text-end fw-semibold"><?php echo dec_($total_debit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end fw-semibold"><?php echo dec_($total_credit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end fw-semibold text-success"><?php echo dec_($total_movement, BASE_CURRENCY['code']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>
</div>

<?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>