<?php
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'quotations';
$module_caption = 'Quote Details';
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


$filter_by              = ((isset($_REQUEST['filter_by']) && !empty($_REQUEST['filter_by'])) ? e_s__($_REQUEST['filter_by']) : 'this_month');
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : date('01-m-Y', time()));
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : date('t-m-Y', time()));
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


$targetpage            = 'report_quote_details.php?action=run_report&date_from=' . $date_from . '&date_to=' . $date_to . '&report_basis=' . $report_basis;



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
    $search_query .= " AND quotation_date >= '" . $date_from_ymd . "'";
}

if (!empty($date_to_ymd)) {
    $search_query .= " AND quotation_date <= '" . $date_to_ymd . "'";
    $max_date = $date_to;
}

$search_query .= " AND organization_id = " . (int)$activeOrganizationId;


// ----------------------------------------------------------------------------------------------------


// if ($action == "run_report") {

//COUNT QUERY
$result         = $mysqli->query("SELECT id FROM `" . tbl_quotations . "` WHERE id>0 " . $search_query);
$total_rows     = $result->num_rows;

//NORMAL QUERY
// echo "SELECT * FROM `" . tbl_quotations . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit";
$result_quotations  = $mysqli->query("SELECT * FROM `" . tbl_quotations . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit");

//EXPORT EXCEL
// $result_quotations_ = $mysqli->query("SELECT * FROM `" . tbl_quotations . "` WHERE id>0 " . $search_query . " ORDER BY id ASC"); // Remove Limit
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'quote_details'");
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
                            <span class="fw-semibold">Quote Details</span> - <span class="small">From <?php echo ddm_($date_from); ?> To <?php echo ddm_($date_to); ?></span>
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

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_quote_details.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-0">
                                            <!-- <label class="form-label fw-semibold">&nbsp; </label> -->
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <select name="filter_by" id="filter_by" class="form-select">
                                                    <!-- <option value='0'>Filter By</option> -->
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
                                                    <option value='custom' <?php echo (($filter_by == 'custom') ? 'selected' : '') ?>>Custom</option>
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
                <h5 class="mb-0">Quote Details</h5>
                <p><span class="text-muted">From</span> <?php echo ddm_($date_from); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">STATUS</th>
                            <th class="table-light">QUOTE DATE</th>
                            <th class="table-light">EXPIRY DATE</th>
                            <th class="table-light">QUOTE#</th>
                            <th class="table-light">REFERENCE#</th>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light">INVOICE#</th>
                            <th class="table-light">PROJECT NAME</th>
                            <th class="table-light text-end">QUOTE AMOUNT</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        // $result_quotations     = $mysqli->query("SELECT * FROM `" . tbl_quotations . "` ");

                        // Initialize total
                        $grand_total_sum = 0;

                        while ($row = $result_quotations->fetch_array()) {

                            $quotation_id       = $row["id"];

                            $quotation_date     = $row["quotation_date"];
                            $quotation_date     = ddm_($quotation_date);

                            $expiry_date        = $row["expiry_date"];
                            $expiry_date        = (($expiry_date == '1970-01-01') ? '' : ddm_($expiry_date));

                            $quotation_no       = $row["quotation_no"];
                            $job_reference_no   = $row["job_reference_no"];
                            $project_name       = $row["project_name"] ?? '';

                            // Get invoice number if quotation was converted to invoice
                            $invoice_no = '';
                            $invoice_result = $mysqli->query("SELECT invoice_no FROM `" . tbl_invoices . "` WHERE quotation_id = $quotation_id LIMIT 1");
                            if ($invoice_result && $invoice_row = $invoice_result->fetch_array()) {
                                $invoice_no = $invoice_row['invoice_no'];
                            }

                            $customer_id        = (int)($row["customer_id"] ?? 0);
                            // ------------------------------------------------------------------------------------------------
                            if ($customer_id > 0) {
                                $result_customer    = $mysqli->query("SELECT * FROM `" . tbl_customers . "` WHERE id=$customer_id");
                                $row_customer       = $result_customer->fetch_array();
                                $display_name       = ($row_customer && isset($row_customer["display_name"])) ? $row_customer["display_name"] : 'N/A';
                            } else {
                                $display_name       = 'N/A';
                            }


                            $quotation_status   = $row["quotation_status"];
                            $quotation_status   = ((!empty($quotation_status)) ? ucwords($quotation_status) : '');

                            $grand_tax          = $row["grand_tax"];

                            $grand_total        = $row["grand_total"];
                            $grand_total        = (empty($grand_total) ? '0' : $grand_total);

                            // Add to running total
                            $grand_total_sum += (float)$grand_total;

                            $qrcode             = $row["qrcode"];
                            $pdf                = $row["pdf"];
                            $publish            = $row["is_active"];
                            $created_at         = $row["created_at"];

                            // -----------------------------------------------------------------------------------
                            // $quotation_id    = $row_quotations['id'];
                            // $display_name    = $row_customers['display_name'];
                            // $account    = getTableAttr('display_name', tbl_accounts, $account);

                        ?>
                            <tr>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><span class="small text-muted"><?php echo $quotation_status; ?></span></a></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo $quotation_date; ?></a></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo $expiry_date; ?></a></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo $quotation_no; ?></a></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo $job_reference_no; ?></a></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo $display_name; ?></a></td>
                                <td><?php if (!empty($invoice_no)) { ?><a href="invoice_overview.php?invoice_no=<?php echo urlencode($invoice_no); ?>"><?php echo $invoice_no; ?></a><?php } else { ?>-<?php } ?></td>
                                <td><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo !empty($project_name) ? s__($project_name) : '-'; ?></a></td>
                                <td class="text-end"><a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_total, 2); ?></a></td>
                            </tr>
                        <?php } //while
                        ?>

                        <!-- Totals Row -->
                        <?php if ($grand_total_sum > 0) { ?>
                        <tr class="table-light fw-bold">
                            <td colspan="8" class="text-end">TOTAL</td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_total_sum, 2); ?></td>
                        </tr>
                        <?php } ?>

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

<?php include('admin_elements/admin_footer.php'); ?>