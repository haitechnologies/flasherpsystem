<?php

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'journals';
$module_caption = 'Journal Report';
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

$limit              = 50;
$stages             = 2;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


if (isset($_REQUEST['export']) && !empty($_REQUEST['export']))    $export     = e_s__($_REQUEST['export']);
else $export = '';


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/


$filter_by              = ((isset($_REQUEST['filter_by']) && !empty($_REQUEST['filter_by'])) ? e_s__($_REQUEST['filter_by']) : '');
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : date('d-m-Y', strtotime('first day of this month')));
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : date('d-m-Y', strtotime('last day of this month')));
$report_basis           = ((isset($_REQUEST['report_basis']) && !empty($_REQUEST['report_basis'])) ? e_s__($_REQUEST['report_basis']) : '');

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


/*
|--------------------------------------------------------------------------
| 	PAGINATION
|--------------------------------------------------------------------------
|
*/

if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}


$targetpage            = 'report_journal_report.php?action=run_report&filter_by=' . $filter_by . '&date_from=' . $date_from . '&date_to=' . $date_to . '&report_basis=' . $report_basis;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/



// -------------------
$search_query = '';
// -------------------


// $date_from              = processDateDtoY($date_from);
// $date_to                = processDateDtoY($date_to);

$date_from_ymd = (!empty($date_from)) ? processDateDtoY($date_from) : '';
$date_to_ymd   = (!empty($date_to))   ? processDateDtoY($date_to)   : '';

if (!empty($date_from_ymd)) {
    $search_query .= " AND journal_date >= '" . $date_from_ymd . "'";
}

if (!empty($date_to_ymd)) {
    $search_query .= " AND journal_date <= '" . $date_to_ymd . "'";
    $max_date = $date_to;
}

// Add report basis filter (accrual or cash)
if (!empty($report_basis)) {
    $search_query .= " AND reporting_method = '" . e_s__($report_basis) . "'";
} else {
    // Default to accrual if not specified
    $search_query .= " AND reporting_method = 'accrual'";
}

$search_query .= " AND organization_id = " . (int)$activeOrganizationId;


// if ($action == "run_report") {

//COUNT QUERY
$result         = $mysqli->query("SELECT id FROM `" . tbl_journals . "` WHERE id>0 " . $search_query);
$total_rows     = $result->num_rows;

//NORMAL QUERY
$result_journals  = $mysqli->query("SELECT * FROM `" . tbl_journals . "` WHERE id>0 " . $search_query . " ORDER BY id DESC LIMIT $start, $limit");

//EXPORT EXCEL
$result_journals_ = $mysqli->query("SELECT * FROM `" . tbl_journals . "` WHERE id>0 " . $search_query . " ORDER BY id DESC"); // Remove Limit
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'journal_report'");
$mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");


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
                            <span class="fw-semibold">Journal Report</span> - <span class="small">From <?php echo ddm_($date_from); ?> To <?php echo ddm_($date_to); ?></span>
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
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                    document.getElementById('report_basis').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_journal_report.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-0">
                                            <!-- <label class="form-label fw-semibold">&nbsp; </label> -->

                                            <div class="form-control-feedback form-control-feedback-start">
                                                <select name="filter_by" id="filter_by" class="form-select">
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


                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <!-- <label class="form-label fw-semibold">Report Basis: </label> -->

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
                <h5 class="mb-0">Journal Report</h5>
                <p><span class="text-muted">From</span> <?php echo ddm_($date_from); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to); ?></p>
                <p class="small"><span class="text-muted">Basis</span> : <?php echo ucfirst(!empty($report_basis) ? $report_basis : 'Accrual'); ?></p>
            </div>
        </div>


        <div class="pb-3">
            <div class="card-header d-flex align-items-center">
                <h6 class="mb-1 text-muted">Total Count: <?php echo $total_rows; ?></h5>

                    <div class="ms-auto">

                        <!-- <a href="export_pdf.php?<?php echo str_replace('listing_reports.php?', '', $targetpage); ?>&export=pdf" target="_blank">
							<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
								<span class="btn-labeled-icon bg-black bg-opacity-20">
									<i class="ph-file-pdf"></i>
								</span>Export PDF
							</button>
						</a> -->

                        <!-- <a href="<?php //echo $targetpage; 
                                        ?>&export=excel"> -->
                        <!-- <button type="button" class="btn btn-info btn-labeled btn-labeled-start">
                        <span class="btn-labeled-icon bg-black bg-opacity-20">
                            <i class="ph-file-csv"></i>
                        </span>Export Excel
                    </button> -->
                        <!-- </a> -->

                    </div>
            </div>

            <?php
            while ($row_journals = $result_journals->fetch_array()) {
                $journal_id     = $row_journals['id'];
                $journal_date   = $row_journals['journal_date'];
                $reference_type = $row_journals['reference_type'];
                $reference_id   = $row_journals['reference_id'];
                $reference_no   = isset($row_journals['reference_no']) ? $row_journals['reference_no'] : '';
                $journal_desc   = isset($row_journals['description']) ? $row_journals['description'] : '';
                $currency       = isset($row_journals['currency']) ? $row_journals['currency'] : '';

                if (empty($currency)) {
                    $currency = 'AED';
                }

                $source_link = '#';
                $source_label = 'Reference';
                $is_void = ($reference_type === 'invoice_void' || $reference_type === 'payment_received_void' || $reference_type === 'credit_note_void' || $reference_type === 'debit_note_void');
                $is_refund = ($reference_type === 'payment_received_refund');
                $is_writeoff = ($reference_type === 'invoice_writeoff');

                if ($reference_type == 'invoice' || $reference_type == 'invoice_void' || $reference_type == 'invoice_writeoff') {
                    $customer_id    = getTableAttr('customer_id', tbl_invoices, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_customers, $customer_id);
                    $invoice_id     = getTableAttr('id', tbl_invoices, $reference_id);
                    $invoice_no     = getTableAttr('invoice_no', tbl_invoices, $reference_id);
                    $source_link    = "invoice_overview.php?invoice_id=" . $invoice_id;
                    $label_prefix = ($is_void ? 'VOID - ' : '') . ($is_writeoff ? 'WRITE-OFF - ' : '');
                    $source_label   = $label_prefix . 'Invoice ' . $invoice_no . ' - ' . $display_name;
                } else if ($reference_type == 'credit_note' || $reference_type == 'credit_note_void') {
                    $customer_id    = getTableAttr('customer_id', tbl_credit_notes, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_customers, $customer_id);
                    $credit_note_id = getTableAttr('id', tbl_credit_notes, $reference_id);
                    $credit_note_no = getTableAttr('credit_note_no', tbl_credit_notes, $reference_id);
                    $source_link    = "credit_note_overview.php?credit_note_id=" . $credit_note_id;
                    $label_prefix = ($is_void ? 'VOID - ' : '');
                    $source_label   = $label_prefix . 'Credit Note ' . $credit_note_no . ' - ' . $display_name;
                } else if ($reference_type == 'debit_note' || $reference_type == 'debit_note_void') {
                    $vendor_id      = getTableAttr('vendor_id', tbl_debit_notes, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_vendors, $vendor_id);
                    $debit_note_id  = getTableAttr('id', tbl_debit_notes, $reference_id);
                    $debit_note_no  = getTableAttr('debit_note_no', tbl_debit_notes, $reference_id);
                    $source_link    = "debit_note_overview.php?debit_note_id=" . $debit_note_id;
                    $label_prefix = ($is_void ? 'VOID - ' : '');
                    $source_label   = $label_prefix . 'Debit Note ' . $debit_note_no . ' - ' . $display_name;
                } else if ($reference_type == 'payment_received' || $reference_type == 'payment_received_void' || $reference_type == 'payment_received_refund') {
                    $customer_id    = getTableAttr('customer_id', tbl_payments_received, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_customers, $customer_id);
                    $source_link    = "payment_received_overview.php?payment_id=" . $reference_id;
                    $label_prefix = ($is_void ? 'VOID - ' : '') . ($is_refund ? 'REFUND - ' : '');
                    $source_label   = $label_prefix . 'Payment #' . $reference_id . ' - ' . $display_name;
                } else if ($reference_type == 'payment_made' || $reference_type == 'payment_made_void') {
                    $vendor_id      = getTableAttr('vendor_id', tbl_payments_made, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_vendors, $vendor_id);
                    $source_link    = "payments_made_overview.php?payment_id=" . $reference_id;
                    $label_prefix = ($reference_type == 'payment_made_void' ? 'VOID - ' : '');
                    $source_label   = $label_prefix . 'Payment Made #' . $reference_id . ' - ' . $display_name;
                } else if ($reference_type == 'expense') {
                    $vendor_id      = getTableAttr('vendor_id', tbl_expenses, $reference_id);
                    $billable       = getTableAttr('billable', tbl_expenses, $reference_id);
                    $display_name   = getTableAttr('display_name', tbl_vendors, $vendor_id);
                    $expense_id     = getTableAttr('id', tbl_expenses, $reference_id);
                    $source_link    = "expense_overview.php?expense_id=" . $expense_id;
                    $expense_type   = ($billable == 1 ? 'BILLABLE - ' : 'NON-BILLABLE - ');
                    $source_label   = $expense_type . 'Expense #' . $expense_id . ' - ' . $display_name;
                } else if ($reference_type == 'payslip' || $reference_type == 'payroll') {
                    $employee_id    = getTableAttr('employee_id', 'fls_hr_payslips', $reference_id);
                    $employee_name  = getTableAttr('full_name', tbl_users, $employee_id);
                    $payslip_id     = $reference_id;
                    $source_link    = "view_payslip.php?id=" . $payslip_id;
                    $source_label   = 'Payslip #' . str_pad($payslip_id, 6, '0', STR_PAD_LEFT) . ' - ' . $employee_name;
                } else if ($reference_type == 'purchase') {
                    $source_label   = 'Purchase #' . $reference_no;
                } else if ($reference_type == 'payment') {
                    $source_label   = 'Payment #' . $reference_no;
                } else {
                    $source_label   = 'Reference: ' . $reference_no;
                }
            ?>

                <div class="card <?php echo $is_void ? 'border-danger' : ($is_writeoff ? 'border-warning' : ($is_refund ? 'border-info' : '')); ?>">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <colgroup>
                                <col style="width: 60%;">
                                <col style="width: 20%;">
                                <col style="width: 20%;">
                            </colgroup>
                            <thead>
                                <tr>
                                    <td><small>
                                            <?php if ($is_void) { ?>
                                                <span class="badge bg-danger me-2">VOID</span>
                                            <?php } elseif ($is_writeoff) { ?>
                                                <span class="badge bg-warning me-2">WRITE-OFF</span>
                                            <?php } elseif ($is_refund) { ?>
                                                <span class="badge bg-info me-2">REFUND</span>
                                            <?php } ?>
                                            <span class="opacity-50"><?php echo ddm_($journal_date); ?></span> -
                                            <a href="<?php echo $source_link; ?>" class="fw-semibold"><?php echo strtoupper($source_label); ?></a>
                                            <span class="badge bg-secondary ms-2"><?php echo strtoupper($currency); ?></span>
                                            <?php if (!empty($journal_desc)): ?>
                                                <br><small class="text-muted"><?php echo $journal_desc; ?></small>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td class="text-end opacity-50 fw-semibold">DEBIT</td>
                                    <td class="text-end opacity-50 fw-semibold">CREDIT</td>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $total_debit = 0;
                                $total_credit = 0;

                                $result_journal_items     = $mysqli->query("SELECT * FROM `" . tbl_journal_items . "` WHERE journal_id=$journal_id ORDER BY id ASC");
                                while ($row_journal_items = $result_journal_items->fetch_array()) {

                                    $account         = $row_journal_items['account'];
                                    $account_name   = getTableAttr('account_name', tbl_accounts, $account);
                                    $account_code   = getTableAttr('account_code', tbl_accounts, $account);
                                    $item_desc      = isset($row_journal_items['description']) ? $row_journal_items['description'] : '';
                                    $debit          = $row_journal_items['debit'];
                                    $credit         = $row_journal_items['credit'];

                                    $total_debit += $debit;
                                    $total_credit += $credit;

                                    $display_account = !empty($account_code) ? $account_code . ' - ' . $account_name : $account_name;
                                ?>
                                    <tr>
                                        <td>
                                            <?php echo $display_account; ?>
                                            <?php if (!empty($item_desc)): ?>
                                                <br><small class="text-muted"><?php echo $item_desc; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo dec_($debit); ?></td>
                                        <td class="text-end"><?php echo dec_($credit); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr>
                                    <td><strong>Entry Total:</strong></td>
                                    <td class="text-end fw-semibold">
                                        <a href="<?php echo $source_link; ?>" class="text-decoration-none text-primary" title="View journal entry">
                                            <?php echo dec_($total_debit); ?>
                                        </a>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        <a href="<?php echo $source_link; ?>" class="text-decoration-none text-primary" title="View journal entry">
                                            <?php echo dec_($total_credit); ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php if ($total_debit != $total_credit): ?>
                                    <tr style="background-color: #ffe0e0;">
                                        <td><strong>⚠️ IMBALANCE:</strong></td>
                                        <td colspan="2" class="text-end text-danger fw-semibold">Debit ≠ Credit (Diff: <?php echo dec_($total_debit - $total_credit); ?>)</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            <?php } // while
            ?>

        </div>


        <!--Pagination -->
        <?php
        // @@@ PAGINATION ALGO @@@ //

        if ($page_no == 0) {
            $page_no = 1;
        }

        // echo $page_no;

        $prev = $page_no - 1;
        $next = $page_no + 1;

        $lastpage     = ceil($total_rows / $limit);
        $LastPagem1 = $lastpage - 1;

        $pagination = '';

        if ($lastpage > 1) {
            $pagination .= '<div class="center-block text-center">';
            $pagination .= '<ul class="pagination mb-5 mb-lg-0">';

            // PREVIOUS
            if ($page_no > 1) {
                $pagination    .= '<li class="page-item page-prev"><a class="page-link" href="' . $targetpage . '&page_no=' . $prev . '" tabindex="-1">Prev</a></li>';
            } else {
                $pagination    .= '<li class="page-item page-prev disabled"><a class="page-link" href="#" tabindex="-1">Prev</a></li>';
            }

            // Pages
            if ($lastpage < 7 + ($stages * 2))    // Not enough pages to breaking it up
            {
                for ($counter = 1; $counter <= $lastpage; $counter++) {
                    if ($counter == $page_no) {
                        $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                    } else {
                        $pagination    .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                    }
                }
            } else if ($lastpage > 5 + ($stages * 2))    // Enough pages to hide a few?
            {
                // Beginning only hide later pages
                if ($page_no < 1 + ($stages * 2)) {

                    for ($counter = 1; $counter < 4 + ($stages * 2); $counter++) {
                        if ($counter == $page_no) {
                            $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                        } else {
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "'>" . $counter . "</a></li>";
                        }
                    }

                    $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $LastPagem1 . "'>$LastPagem1</a></li>";
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $lastpage . "'>$lastpage</a></li>";
                }
                // Middle hide some front and some back
                elseif ($lastpage - ($stages * 2) > $page_no && $page_no > ($stages * 2)) {
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=1'>1</a></li>";
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=2'>2</a></li>";
                    $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';

                    for ($counter = $page_no - $stages; $counter <= $page_no + $stages; $counter++) {
                        if ($counter == $page_no) {
                            $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $counter . '</a></li>';
                        } else {
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                        }
                    }

                    $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $LastPagem1 . "'>$LastPagem1</a></li>";
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $lastpage . "'>$lastpage</a></li>";
                }
                // End only hide early pages
                else {
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='." . $targetpage . "&page_no=1'>1</a></li>";
                    $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=2'>2</a></li>";
                    $pagination .= '<li class="page-item"><a class="page-link" href="#">...</a></li>';

                    for ($counter = $lastpage - (2 + ($stages * 2)); $counter <= $lastpage; $counter++) {
                        if ($counter == $page_no) {
                            $pagination .= '<li class="page-item active"><a class="page-link" href="#">1' . $counter . '</a></li>';
                        } else {
                            $pagination .= "<li class=\"page-item\"><a class=\"page-link\" href='" . $targetpage . "&page_no=" . $counter . "'>" . $counter . "</a></li>";
                        }
                    }
                }
            }
            // Next
            if ($page_no < $counter - 1) {
                $pagination .= '<li class="page-item page-next"><a class="page-link" href="' . $targetpage . '&page_no=' . $next . '">Next</a></li>';
            } else {
                $pagination .= '<li class="page-item page-next"><a class="page-link" href="#">Next</a></li>';
            }

            $pagination .= "</ul>";
            $pagination .= "</div>";
        } //endif

        echo $pagination;
        ?>
        <!--/Pagination -->

        <?php
        // } // endif
        ?>


        <div class="mt-5 alert alert-info border-0 fade show small">
            <i class="ph-info me-2"></i>
            <span class="fw-semibold">Journal Report Information:</span>
            <div class="ms-auto small text-muted mt-2">
                <ul style="margin-bottom: 0; margin-left: 20px;">
                    <li>All amounts are displayed in the currency specified in each journal entry (Base: <span class="badge bg-success"><?php echo BASE_CURRENCY['code']; ?></span>)</li>
                    <li>Account codes have been added where available for better GL reference</li>
                    <li>Each journal entry includes descriptions for complete audit trail tracking</li>
                    <li>Debits must equal Credits for each journal entry (Double-Entry Bookkeeping)</li>
                    <li>This report reflects entries from the JournalService implementation</li>
                </ul>
            </div>
        </div>





    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>