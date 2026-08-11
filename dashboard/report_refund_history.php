<?php

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'credit_notes';
$module_caption = 'Refund History';
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

$limit              = 10;
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
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : '');
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : '');
$report_basis           = ((isset($_REQUEST['report_basis']) && !empty($_REQUEST['report_basis'])) ? e_s__($_REQUEST['report_basis']) : '');


if (!empty($filter_by) && $filter_by != '0') {
    if (!function_exists('getDateRangeByFilter')) {
        function getDateRangeByFilter($filter_by)
        {
            $today = new DateTimeImmutable('today');
            $start = null;
            $end   = null;
            switch ($filter_by) {
                case 'today':
                    $start = $today;
                    $end   = $today;
                    break;
                case 'this_week':
                    $start = $today->modify('monday this week');
                    $end   = $today->modify('sunday this week');
                    break;
                case 'this_month':
                    $start = $today->modify('first day of this month');
                    $end   = $today->modify('last day of this month');
                    break;
                case 'this_quarter':
                    $quarterStart = (int)floor(($today->format('n') - 1) / 3) * 3 + 1;
                    $start = $today->setDate($today->format('Y'), $quarterStart, 1);
                    $end   = $start->modify('+2 months')->modify('last day of this month');
                    break;
                case 'this_year':
                    $start = $today->setDate($today->format('Y'), 1, 1);
                    $end   = $today->setDate($today->format('Y'), 12, 31);
                    break;
                case 'yesterday':
                    $start = $today->modify('-1 day');
                    $end   = $start;
                    break;
                case 'previous_week':
                    $start = $today->modify('monday last week');
                    $end   = $today->modify('sunday last week');
                    break;
                case 'previous_month':
                    $start = $today->modify('first day of last month');
                    $end   = $today->modify('last day of last month');
                    break;
                case 'previous_quarter':
                    $quarterStart = (int)floor(($today->format('n') - 1) / 3) * 3 + 1 - 3;
                    $year = $today->format('Y');
                    if ($quarterStart < 1) {
                        $quarterStart += 12;
                        $year--;
                    }
                    $start = $today->setDate((int)$year, $quarterStart, 1);
                    $end   = $start->modify('+2 months')->modify('last day of this month');
                    break;
                case 'previous_year':
                    $start = $today->setDate((int)($today->format('Y') - 1), 1, 1);
                    $end   = $today->setDate((int)($today->format('Y') - 1), 12, 31);
                    break;
                default:
                    break;
            }
            return [$start ? $start->format('d-m-Y') : null, $end ? $end->format('d-m-Y') : null];
        }
    }
    list($date_from, $date_to) = getDateRangeByFilter($filter_by);
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


$targetpage            = 'report_refund_history.php?action=run_report&date_from=' . $date_from . '&date_to=' . $date_to . '&report_basis=' . $report_basis;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}

// -------------------
$search_query = '';
// -------------------


// Convert date format from DD-MM-YYYY to YYYY-MM-DD if needed
if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_from)) {
    $date_from = processDateDtoY($date_from);
}
if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $date_to)) {
    $date_to = processDateDtoY($date_to);
}

$date_from             = processDateYtoD($date_from);
$date_to             = processDateYtoD($date_to);

// ----------------------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


// if ($action == "run_report") {

// Build search query for date filtering
$refund_search_query = '';
if (!empty($date_from) && !empty($date_to)) {
    $date_from_sql = processDateDtoY($date_from);
    $date_to_sql = processDateDtoY($date_to);
    $refund_search_query = " AND j.journal_date BETWEEN '$date_from_sql' AND '$date_to_sql'";
}
$refund_search_query .= " AND j.organization_id = " . (int)$activeOrganizationId;

//COUNT QUERY
$result         = $mysqli->query("SELECT j.id FROM `" . tbl_journals . "` j WHERE (j.reference_type='payment_received_refund' OR j.reference_type='credit_note_refund') " . $refund_search_query);
$total_rows     = $result->num_rows;

//NORMAL QUERY
$result_customers  = $mysqli->query("SELECT j.id, j.reference_type, j.reference_id, j.journal_date, j.grand_total,
    c.display_name as customer_name,
    CASE
        WHEN j.reference_type = 'payment_received_refund' THEN pr.payment_method
        WHEN j.reference_type = 'credit_note_refund' THEN (SELECT je.account FROM `" . tbl_journal_items . "` je WHERE je.journal_id = j.id AND je.credit > 0 LIMIT 1)
    END as payment_method_or_account
    FROM `" . tbl_journals . "` j
    LEFT JOIN `" . tbl_customers . "` c ON (
        (j.reference_type = 'payment_received_refund' AND j.reference_id IN (SELECT id FROM `" . tbl_payments_received . "` WHERE customer_id = c.id AND organization_id = " . (int)$activeOrganizationId . "))
        OR (j.reference_type = 'credit_note_refund' AND j.reference_id IN (SELECT id FROM `" . tbl_credit_notes . "` WHERE customer_id = c.id AND organization_id = " . (int)$activeOrganizationId . "))
    )
    LEFT JOIN `" . tbl_payments_received . "` pr ON (j.reference_type = 'payment_received_refund' AND j.reference_id = pr.id AND pr.organization_id = " . (int)$activeOrganizationId . ")
    WHERE (j.reference_type='payment_received_refund' OR j.reference_type='credit_note_refund') " . $refund_search_query . " ORDER BY j.journal_date DESC, j.id DESC LIMIT $start, $limit");

//EXPORT EXCEL
$result_customers_ = $mysqli->query("SELECT j.id, j.reference_type, j.reference_id, j.journal_date, j.grand_total,
    c.display_name as customer_name,
    CASE
        WHEN j.reference_type = 'payment_received_refund' THEN pr.payment_method
        WHEN j.reference_type = 'credit_note_refund' THEN (SELECT je.account FROM `" . tbl_journal_items . "` je WHERE je.journal_id = j.id AND je.credit > 0 LIMIT 1)
    END as payment_method_or_account
    FROM `" . tbl_journals . "` j
    LEFT JOIN `" . tbl_customers . "` c ON (
        (j.reference_type = 'payment_received_refund' AND j.reference_id IN (SELECT id FROM `" . tbl_payments_received . "` WHERE customer_id = c.id AND organization_id = " . (int)$activeOrganizationId . "))
        OR (j.reference_type = 'credit_note_refund' AND j.reference_id IN (SELECT id FROM `" . tbl_credit_notes . "` WHERE customer_id = c.id AND organization_id = " . (int)$activeOrganizationId . "))
    )
    LEFT JOIN `" . tbl_payments_received . "` pr ON (j.reference_type = 'payment_received_refund' AND j.reference_id = pr.id AND pr.organization_id = " . (int)$activeOrganizationId . ")
    WHERE (j.reference_type='payment_received_refund' OR j.reference_type='credit_note_refund') " . $refund_search_query . " ORDER BY j.journal_date DESC, j.id DESC"); // Remove Limit
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'refund_history'");
$mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");



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
                        <div class="text-muted">Sales</div>
                        <div class="mb-0">
                            <span class="fw-semibold">Refund History</span> - <span class="fw-normal">From <?php echo ddm_($date_from); ?> To <?php echo ddm_($date_to); ?></span>
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
                    document.getElementById('report_basis').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="request" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_refund_history.php">
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
                <h5 class="mb-0">Refund History</h5>
                <p><span class="text-muted">From</span> <?php echo ddm_($date_from); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">DATE</th>
                            <th class="table-light">REFERENCE#</th>
                            <th class="table-light">TRANSACTION#</th>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light">MODE</th>
                            <th class="table-light">NOTES</th>
                            <th class="table-light">AMOUNT (FCY)</th>
                            <th class="table-light text-end">AMOUNT (BCY)</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        $total_amount_fcy = 0;
                        $total_amount_bcy = 0;
                        
                        while ($row_refunds = $result_customers->fetch_array()) {

                            $journal_id         = $row_refunds['id'];
                            $reference_type     = $row_refunds['reference_type'];
                            $reference_id       = $row_refunds['reference_id'];
                            $reference_no       = $row_refunds['reference_no'];
                            $journal_date       = $row_refunds['journal_date'];
                            $description        = $row_refunds['description'];
                            $grand_total        = $row_refunds['grand_total'];
                            $currency           = $row_refunds['currency'];
                            $customer_name      = $row_refunds['customer_name'];
                            $payment_method_or_account = $row_refunds['payment_method_or_account'];
                            
                            // Get mode/method name
                            $mode = '';
                            if ($reference_type == 'payment_received_refund' && !empty($payment_method_or_account)) {
                                $mode = getTableAttr('payment_method', tbl_payment_methods, $payment_method_or_account);
                            } elseif ($reference_type == 'credit_note_refund' && !empty($payment_method_or_account)) {
                                $mode = getTableAttr('display_name', tbl_accounts, $payment_method_or_account);
                            }
                            
                            // Determine transaction number
                            $transaction_no = '';
                            if ($reference_type == 'payment_received_refund') {
                                $transaction_no = getTableAttr('reference_no', tbl_payments_received, $reference_id);
                            } elseif ($reference_type == 'credit_note_refund') {
                                $transaction_no = getTableAttr('credit_note_no', tbl_credit_notes, $reference_id);
                            }
                            
                            $total_amount_fcy += $grand_total;
                            $total_amount_bcy += $grand_total;

                        ?>
                            <tr>
                                <td><?php echo ddm_($journal_date); ?></td>
                                <td><?php echo $reference_no; ?></td>
                                <td><?php echo $transaction_no; ?></td>
                                <td><?php echo $customer_name; ?></td>
                                <td><?php echo $mode; ?></td>
                                <td><?php echo $description; ?></td>
                                <td><?php echo $currency; ?> <?php echo number_format($grand_total, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_total, 2); ?></td>
                            </tr>
                        <?php } //while 
                        ?>
                        
                        <tr class="table-light">
                            <td colspan="6" class="text-end"><strong>Total</strong></td>
                            <td><strong><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_amount_fcy, 2); ?></strong></td>
                            <td class="text-end"><strong><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_amount_bcy, 2); ?></strong></td>
                        </tr>

                    </tbody>
                </table>
            </div>

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
        ?></div>


    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<script src="../assets/js/reports_filterby.js"></script>
<?php include('admin_elements/admin_footer.php'); ?>