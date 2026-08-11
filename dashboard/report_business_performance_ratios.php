<?php

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Business Performance Ratios';
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

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS FOR RATIO CALCULATIONS
|--------------------------------------------------------------------------
|
*/

function getFinancialMetrics($mysqli, $date_from_ymd, $date_to_ymd, $activeOrganizationId) {
    $date_join = " AND j.journal_date >= '" . $date_from_ymd . "' AND j.journal_date <= '" . $date_to_ymd . "' AND j.organization_id = " . (int)$activeOrganizationId;
    
    $metrics = array(
        'total_assets' => 0,
        'total_liabilities' => 0,
        'total_equity' => 0,
        'total_current_assets' => 0,
        'total_current_liabilities' => 0,
        'total_income' => 0,
        'total_expenses' => 0,
        'total_net_profit' => 0
    );
    
    // Total Assets
    $sql = "SELECT COALESCE(SUM(ji.debit), 0) - COALESCE(SUM(ji.credit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account" . $date_join . "
            WHERE a.account_type = 'Assets'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $metrics['total_assets'] = (float)$row['balance'];
    
    // Total Liabilities
    $sql = "SELECT COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account" . $date_join . "
            WHERE a.account_type = 'Liability'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $metrics['total_liabilities'] = (float)$row['balance'];
    
    // Total Equity
    $sql = "SELECT COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account" . $date_join . "
            WHERE a.account_type = 'Equity'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $metrics['total_equity'] = (float)$row['balance'];
    
    // Total Income
    $sql = "SELECT COALESCE(SUM(ji.credit), 0) - COALESCE(SUM(ji.debit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account" . $date_join . "
            WHERE a.account_type = 'Income'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $metrics['total_income'] = (float)$row['balance'];
    
    // Total Expenses
    $sql = "SELECT COALESCE(SUM(ji.debit), 0) - COALESCE(SUM(ji.credit), 0) AS balance
            FROM `" . tbl_journal_items . "` ji
            LEFT JOIN `" . tbl_journals . "` j ON j.id = ji.journal_id
            LEFT JOIN `" . tbl_accounts . "` a ON a.id = ji.account" . $date_join . "
            WHERE a.account_type = 'Expense'";
    $result = $mysqli->query($sql);
    $row = $result->fetch_array(MYSQLI_ASSOC);
    $metrics['total_expenses'] = (float)$row['balance'];
    
    // Net Profit/Loss
    $metrics['total_net_profit'] = $metrics['total_income'] - $metrics['total_expenses'];
    
    return $metrics;
}

function calculateRatios($metrics) {
    $ratios = array(
        'liquidity' => array(),
        'profitability' => array(),
        'efficiency' => array(),
        'leverage' => array()
    );
    
    // Liquidity Ratios (Solvency)
    $ratios['liquidity']['debt_to_assets'] = $metrics['total_assets'] != 0 ? ($metrics['total_liabilities'] / $metrics['total_assets']) * 100 : 0;
    $ratios['liquidity']['equity_ratio'] = $metrics['total_assets'] != 0 ? ($metrics['total_equity'] / $metrics['total_assets']) * 100 : 0;
    
    // Profitability Ratios
    $ratios['profitability']['profit_margin'] = $metrics['total_income'] != 0 ? ($metrics['total_net_profit'] / $metrics['total_income']) * 100 : 0;
    $ratios['profitability']['roa'] = $metrics['total_assets'] != 0 ? ($metrics['total_net_profit'] / $metrics['total_assets']) * 100 : 0;
    $ratios['profitability']['roe'] = $metrics['total_equity'] != 0 ? ($metrics['total_net_profit'] / $metrics['total_equity']) * 100 : 0;
    
    // Efficiency Ratios
    $ratios['efficiency']['expense_ratio'] = $metrics['total_income'] != 0 ? ($metrics['total_expenses'] / $metrics['total_income']) * 100 : 0;
    $ratios['efficiency']['income_to_assets'] = $metrics['total_assets'] != 0 ? ($metrics['total_income'] / $metrics['total_assets']) : 0;
    
    // Leverage Ratios
    $ratios['leverage']['debt_to_equity'] = $metrics['total_equity'] != 0 ? ($metrics['total_liabilities'] / $metrics['total_equity']) : 0;
    $ratios['leverage']['multiplier'] = $metrics['total_equity'] != 0 ? ($metrics['total_assets'] / $metrics['total_equity']) : 0;
    
    return $ratios;
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

// Get financial metrics and calculate ratios
$metrics = getFinancialMetrics($mysqli, $date_from_ymd, $date_to_ymd, $activeOrganizationId);
$ratios = calculateRatios($metrics);

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'business_performance_ratios'");
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
                            <span class="fw-semibold">Business Performance Ratios</span> - <span class="fw-normal">From <?php echo ddm_($date_from_ymd); ?> to <?php echo ddm_($date_to_ymd); ?></span>
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

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_business_performance_ratios.php">
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

    <script src="<?php echo $base_url; ?>/dashboard/js/reports_filterby.js"></script>

    <div class="content">

        <div class="card">
            <div class="card-header text-center">
                <p>Flash Logistics FZC</p>
                <h5 class="mb-0">Business Performance Ratios Report</h5>
                <p><span class="text-muted">From</span> <?php echo ddm_($date_from_ymd); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to_ymd); ?></p>
            </div>

            <!-- Financial Metrics Summary -->
            <div class="row mt-3 ms-2 me-2">
                <div class="col-lg-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="fw-semibold">Financial Metrics</h6>
                            <div class="table-responsive">
<table class="table table-sm">
                                <tr>
                                    <td>Total Assets:</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_assets'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Liabilities:</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_liabilities'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Equity:</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_equity'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Income:</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_income'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr>
                                    <td>Total Expenses:</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_expenses'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <td>Net Profit/Loss:</td>
                                    <td class="text-end fw-semibold <?php echo ($metrics['total_net_profit'] >= 0) ? 'text-success' : 'text-danger'; ?>"><?php echo dec_($metrics['total_net_profit'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                            </table>
</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="fw-semibold">Balance Sheet Equation</h6>
                            <div class="table-responsive">
<table class="table table-sm">
                                <tr>
                                    <td class="fw-semibold">Assets</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_assets'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="text-center"><small>=</small></td>
                                </tr>
                                <tr>
                                    <td class="fw-semibold">Liabilities + Equity</td>
                                    <td class="text-end fw-semibold"><?php echo dec_($metrics['total_liabilities'] + $metrics['total_equity'], BASE_CURRENCY['code']); ?></td>
                                </tr>
                                <tr class="table-light">
                                    <td colspan="2" class="text-center">
                                        <?php 
                                        $difference = abs($metrics['total_assets'] - ($metrics['total_liabilities'] + $metrics['total_equity']));
                                        if ($difference < 0.01) {
                                            echo '<span class="text-success fw-semibold"><i class="ph-check-circle"></i> Balanced</span>';
                                        } else {
                                            echo '<span class="text-danger fw-semibold"><i class="ph-x-circle"></i> Difference: ' . dec_($difference, BASE_CURRENCY['code']) . '</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liquidity/Solvency Ratios -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Liquidity & Solvency Ratios</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-light">
                        <tbody>
                            <tr>
                                <th class="table-light">Ratio</th>
                                <th class="table-light">Formula</th>
                                <th class="table-light text-end">Value</th>
                                <th class="table-light text-end">Interpretation</th>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Debt to Assets</td>
                                <td><small>Total Liabilities / Total Assets</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['liquidity']['debt_to_assets'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['liquidity']['debt_to_assets'] <= 50 ? 'Low debt' : 'High debt'; ?></small></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Equity Ratio</td>
                                <td><small>Total Equity / Total Assets</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['liquidity']['equity_ratio'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['liquidity']['equity_ratio'] >= 50 ? 'Strong equity' : 'Low equity'; ?></small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Profitability Ratios -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Profitability Ratios</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-light">
                        <tbody>
                            <tr>
                                <th class="table-light">Ratio</th>
                                <th class="table-light">Formula</th>
                                <th class="table-light text-end">Value</th>
                                <th class="table-light text-end">Interpretation</th>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Profit Margin</td>
                                <td><small>Net Profit / Total Income</small></td>
                                <td class="text-end fw-semibold <?php echo $ratios['profitability']['profit_margin'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($ratios['profitability']['profit_margin'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['profitability']['profit_margin'] >= 10 ? 'Healthy' : 'Low margin'; ?></small></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">ROA (Return on Assets)</td>
                                <td><small>Net Profit / Total Assets</small></td>
                                <td class="text-end fw-semibold <?php echo $ratios['profitability']['roa'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($ratios['profitability']['roa'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['profitability']['roa'] >= 5 ? 'Good efficiency' : 'Needs improvement'; ?></small></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">ROE (Return on Equity)</td>
                                <td><small>Net Profit / Total Equity</small></td>
                                <td class="text-end fw-semibold <?php echo $ratios['profitability']['roe'] >= 0 ? 'text-success' : 'text-danger'; ?>"><?php echo number_format($ratios['profitability']['roe'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['profitability']['roe'] >= 15 ? 'Strong returns' : 'Improve ROE'; ?></small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Efficiency Ratios -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Efficiency Ratios</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-light">
                        <tbody>
                            <tr>
                                <th class="table-light">Ratio</th>
                                <th class="table-light">Formula</th>
                                <th class="table-light text-end">Value</th>
                                <th class="table-light text-end">Interpretation</th>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Expense Ratio</td>
                                <td><small>Total Expenses / Total Income</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['efficiency']['expense_ratio'], 2) . '%'; ?></td>
                                <td class="text-end"><small><?php echo $ratios['efficiency']['expense_ratio'] <= 75 ? 'Efficient' : 'High expenses'; ?></small></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Income to Assets</td>
                                <td><small>Total Income / Total Assets</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['efficiency']['income_to_assets'], 2); ?></td>
                                <td class="text-end"><small><?php echo $ratios['efficiency']['income_to_assets'] >= 1 ? 'Good asset use' : 'Underutilized'; ?></small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Leverage Ratios -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Leverage Ratios</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-light">
                        <tbody>
                            <tr>
                                <th class="table-light">Ratio</th>
                                <th class="table-light">Formula</th>
                                <th class="table-light text-end">Value</th>
                                <th class="table-light text-end">Interpretation</th>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Debt to Equity</td>
                                <td><small>Total Liabilities / Total Equity</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['leverage']['debt_to_equity'], 2); ?></td>
                                <td class="text-end"><small><?php echo $ratios['leverage']['debt_to_equity'] <= 1 ? 'Conservative' : 'Aggressive'; ?></small></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">Equity Multiplier</td>
                                <td><small>Total Assets / Total Equity</small></td>
                                <td class="text-end fw-semibold"><?php echo number_format($ratios['leverage']['multiplier'], 2); ?></td>
                                <td class="text-end"><small><?php echo $ratios['leverage']['multiplier'] <= 2.5 ? 'Moderate leverage' : 'High leverage'; ?></small></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        </div>


    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>