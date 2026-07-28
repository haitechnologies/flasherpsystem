<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'General Ledger';
$tbl_name = DB::GENERAL_LEDGER;
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
    if (!empty($filter_start) && !empty($filter_end)) {
        $date_from = $filter_start;
        $date_to = $filter_end;
    }
}

// Process dates
$date_from_ymd = processDateDtoY($date_from);
$date_to_ymd = processDateDtoY($date_to);

// Build query conditions for period transactions
$period_condition_j2 = '';
if (!empty($date_from_ymd)) {
    $period_condition_j2 .= " AND j2.journal_date >= '" . $date_from_ymd . "'";
}
if (!empty($date_to_ymd)) {
    $period_condition_j2 .= " AND j2.journal_date <= '" . $date_to_ymd . "'";
}
if (!empty($report_basis)) {
    $period_condition_j2 .= " AND j2.reporting_method = '" . e_s__($report_basis) . "'";
}

// Build query conditions for opening balance (before period start)
$opening_condition_j1 = '';
if (!empty($date_from_ymd)) {
    $opening_condition_j1 .= " AND j1.journal_date < '" . $date_from_ymd . "'";
}
if (!empty($report_basis)) {
    $opening_condition_j1 .= " AND j1.reporting_method = '" . e_s__($report_basis) . "'";
}

// Get all accounts and calculate balances
$sql = "SELECT a.id, a.account_code, a.account_name, a.account_type,
               -- Opening balance (before period)
               COALESCE((SELECT SUM(ji1.debit - ji1.credit)
                        FROM `" . DB::JOURNAL_ITEMS . "` ji1
                        INNER JOIN `" . DB::JOURNALS . "` j1 ON j1.id = ji1.journal_id
                        WHERE ji1.account = a.id" . $opening_condition_j1 . "), 0) AS opening_balance,
               -- Period debits
               COALESCE((SELECT SUM(ji2.debit)
                        FROM `" . DB::JOURNAL_ITEMS . "` ji2
                        INNER JOIN `" . DB::JOURNALS . "` j2 ON j2.id = ji2.journal_id
                        WHERE ji2.account = a.id" . $period_condition_j2 . "), 0) AS period_debit,
               -- Period credits
               COALESCE((SELECT SUM(ji2.credit)
                        FROM `" . DB::JOURNAL_ITEMS . "` ji2
                        INNER JOIN `" . DB::JOURNALS . "` j2 ON j2.id = ji2.journal_id
                        WHERE ji2.account = a.id" . $period_condition_j2 . "), 0) AS period_credit
        FROM `" . DB::ACCOUNTS . "` a
        WHERE a.parent_id IS NOT NULL
        ORDER BY FIELD(a.account_type, 'Assets', 'Liability', 'Equity', 'Income', 'Expense'), a.account_code ASC, a.account_name ASC";

$result_accounts = $mysqli->query($sql);
$ledger_data = [];

while ($row = $result_accounts->fetch_array()) {
    $opening = (float)$row['opening_balance'];
    $debit = (float)$row['period_debit'];
    $credit = (float)$row['period_credit'];
    $closing = $opening + ($debit - $credit);

    $ledger_data[] = [
        'id' => $row['id'],
        'code' => $row['account_code'],
        'name' => $row['account_name'],
        'type' => $row['account_type'],
        'opening' => $opening,
        'debit' => $debit,
        'credit' => $credit,
        'closing' => $closing
    ];
}

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", DB::ACCOUNTS_REPORT_SUBCATEGORIES, " slug = 'general_ledger'");
if ($accounts_report_subcategory_id > 0) {
    $mysqli->query("UPDATE `" . DB::ACCOUNTS_REPORT_SUBCATEGORIES . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
}

$accounts_report_category_id    = getTableAttr('category_id', DB::ACCOUNTS_REPORT_SUBCATEGORIES, $accounts_report_subcategory_id);
$accounts_report_category_name  = getTableAttr('category_name', DB::ACCOUNTS_REPORT_CATEGORIES, $accounts_report_category_id);

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
                            <span class="fw-semibold">General Ledger</span> - <span class="small">From <?php echo dd_($date_from); ?> To <?php echo dd_($date_to); ?></span>
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
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_general_ledger.php">
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
                <h5 class="mb-0">General Ledger</h5>
                <p class="small"><span class="text-muted">Basis</span> : <?php echo ucfirst($report_basis); ?></p>
                <p class="small"><span class="text-muted">From</span> <?php echo dd_($date_from); ?> <span class="text-muted">To</span> <?php echo dd_($date_to); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">ACCOUNT CODE</th>
                            <th class="table-light">ACCOUNT NAME</th>
                            <th class="table-light">TYPE</th>
                            <th class="table-light text-end">OPENING BALANCE</th>
                            <th class="table-light text-end">DEBIT</th>
                            <th class="table-light text-end">CREDIT</th>
                            <th class="table-light text-end">CLOSING BALANCE</th>
                        </tr>

                        <?php
                        $total_opening = 0;
                        $total_debit = 0;
                        $total_credit = 0;
                        $total_closing = 0;

                        $current_type = '';

                        foreach ($ledger_data as $account) {
                            // Add type separator
                            if ($current_type !== $account['type']) {
                                if ($current_type !== '') {
                                    echo '<tr><td colspan="7" class="border-0">&nbsp;</td></tr>';
                                }
                                $current_type = $account['type'];
                                ?>
                                <tr class="table-secondary">
                                    <td colspan="7" class="fw-bold"><?php echo strtoupper($account['type']); ?></td>
                                </tr>
                                <?php
                            }

                            $total_opening += $account['opening'];
                            $total_debit += $account['debit'];
                            $total_credit += $account['credit'];
                            $total_closing += $account['closing'];
                        ?>
                            <tr>
                                <td><?php echo s__($account['code']); ?></td>
                                <td><?php echo s__($account['name']); ?></td>
                                <td><span class="badge bg-secondary"><?php echo s__($account['type']); ?></span></td>
                                <td class="text-end <?php echo ($account['opening'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo dec_($account['opening'], BASE_CURRENCY['code']); ?>
                                </td>
                                <td class="text-end"><?php echo dec_($account['debit'], BASE_CURRENCY['code']); ?></td>
                                <td class="text-end"><?php echo dec_($account['credit'], BASE_CURRENCY['code']); ?></td>
                                <td class="text-end fw-semibold <?php echo ($account['closing'] >= 0) ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo dec_($account['closing'], BASE_CURRENCY['code']); ?>
                                </td>
                            </tr>
                        <?php } ?>

                        <tr class="table-light fw-semibold">
                            <td colspan="3">TOTAL</td>
                            <td class="text-end"><?php echo dec_($total_opening, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($total_debit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($total_credit, BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php echo dec_($total_closing, BASE_CURRENCY['code']); ?></td>
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
