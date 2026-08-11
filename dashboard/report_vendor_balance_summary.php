<?php
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'vendors';
$module_caption = 'Vendor Balance Summary';
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
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : '');
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : '');

// Handle filter_by quick date selection
if (!empty($filter_by) && $filter_by != '0') {
    // Define function to get date range by filter
    if (!function_exists('getDateRangeByFilter')) {
        function getDateRangeByFilter($filter_by) {
            $today = new DateTimeImmutable('today');

            switch ($filter_by) {
                case 'today':
                    $start = $today;
                    $end = $today;
                    break;
                case 'this_week':
                    $start = $today->modify('monday this week');
                    $end = $today->modify('sunday this week');
                    break;
                case 'this_month':
                    $start = $today->modify('first day of this month');
                    $end = $today->modify('last day of this month');
                    break;
                case 'this_quarter':
                    $month = (int)$today->format('n');
                    $quarter_start_month = (floor(($month - 1) / 3) * 3) + 1;
                    $start = $today->setDate((int)$today->format('Y'), $quarter_start_month, 1);
                    $end = $start->modify('+2 months')->modify('last day of this month');
                    break;
                case 'this_year':
                    $start = $today->setDate((int)$today->format('Y'), 1, 1);
                    $end = $today->setDate((int)$today->format('Y'), 12, 31);
                    break;
                case 'yesterday':
                    $start = $today->modify('-1 day');
                    $end = $start;
                    break;
                case 'previous_week':
                    $start = $today->modify('monday last week');
                    $end = $today->modify('sunday last week');
                    break;
                case 'previous_month':
                    $start = $today->modify('first day of last month');
                    $end = $today->modify('last day of last month');
                    break;
                case 'previous_quarter':
                    $month = (int)$today->format('n');
                    $quarter_start_month = (floor(($month - 1) / 3) * 3) + 1 - 3;
                    if ($quarter_start_month < 1) {
                        $quarter_start_month += 12;
                        $year = (int)$today->format('Y') - 1;
                    } else {
                        $year = (int)$today->format('Y');
                    }
                    $start = $today->setDate($year, $quarter_start_month, 1);
                    $end = $start->modify('+2 months')->modify('last day of this month');
                    break;
                case 'previous_year':
                    $year = (int)$today->format('Y') - 1;
                    $start = $today->setDate($year, 1, 1);
                    $end = $today->setDate($year, 12, 31);
                    break;
                default:
                    return [null, null];
            }

            return [$start->format('d-m-Y'), $end->format('d-m-Y')];
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


$targetpage            = 'report_vendor_balance_summary.php?action=run_report&filter_by=' . $filter_by . '&date_from=' . $date_from . '&date_to=' . $date_to;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}

// -------------------
$search_query = '';
// -------------------


$date_from             = processDateDtoY($date_from);
$date_to             = processDateDtoY($date_to);



$date_from             = processDateYtoD($date_from);
$date_to             = processDateYtoD($date_to);

// ----------------------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'vendor_balance_summary'");
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
                        <div class="text-muted">Payables</div>
                        <div class="mb-0">
                            <span class="fw-semibold">Vendor Balance Summary</span>
                            <?php if (!empty($date_from) || !empty($date_to)) { ?>
                                - <span class="fw-normal">
                                    <?php if (!empty($date_from)) echo 'From ' . ddm_($date_from); ?>
                                    <?php if (!empty($date_to)) echo ' To ' . ddm_($date_to); ?>
                                </span>
                            <?php } ?>
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

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_vendor_balance_summary.php">
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


                                    <div class="col-lg-4">
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
                <h5 class="mb-0">Vendor Balance Summary</h5>
                <?php if (!empty($date_from) || !empty($date_to)) { ?>
                    <p>
                        <?php if (!empty($date_from)) { ?><span class="text-muted">From</span> <?php echo ddm_($date_from); ?><?php } ?>
                        <?php if (!empty($date_to)) { ?> <span class="text-muted">To</span> <?php echo ddm_($date_to); ?><?php } ?>
                    </p>
                <?php } ?>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">VENDOR NAME</th>
                            <th class="table-light text-end">BILLED AMOUNT</th>
                            <th class="table-light text-end">AMOUNT PAID</th>
                            <th class="table-light text-end">CLOSING BALANCE</th>
                        </tr>

                        <?php
                        // Build date filter for queries
                        $date_from_ymd = processDateDtoY($date_from);
                        $date_to_ymd = processDateDtoY($date_to);

                        $purchase_date_filter = '';
                        if (!empty($date_from_ymd)) {
                            $purchase_date_filter .= " AND purchase_date >= '" . $date_from_ymd . "'";
                        }
                        if (!empty($date_to_ymd)) {
                            $purchase_date_filter .= " AND purchase_date <= '" . $date_to_ymd . "'";
                        }
                        $purchase_date_filter .= " AND p.organization_id = " . (int)$activeOrganizationId;

                        // Count total vendors for pagination
                        $count_result = $mysqli->query("SELECT COUNT(*) as total FROM `" . tbl_vendors . "` WHERE id > 0 AND organization_id = " . (int)$activeOrganizationId);
                        $count_row = $count_result->fetch_array();
                        $total_rows = $count_row['total'];

                        // Get vendors with pagination
                        $result_vendors = $mysqli->query("SELECT * FROM `" . tbl_vendors . "` WHERE id > 0 AND organization_id = " . (int)$activeOrganizationId . " ORDER BY display_name ASC LIMIT $start, $limit");

                        while ($row_vendors = $result_vendors->fetch_array()) {
                            $vendor_id = $row_vendors['id'];
                            $display_name = $row_vendors['display_name'];

                            // Calculate total billed amount for this vendor
                            $billed_result = $mysqli->query("
                                SELECT COALESCE(SUM(grand_total), 0) as total_billed
                                FROM `" . tbl_purchases . "` p
                                WHERE p.vendor_id = $vendor_id
                                $purchase_date_filter
                            ");
                            $billed_row = $billed_result->fetch_array();
                            $total_billed = (float)$billed_row['total_billed'];

                            // Calculate total payments made for this vendor
                            $paid_result = $mysqli->query("
                                SELECT COALESCE(SUM(pmi.amount_paid), 0) as total_paid
                                FROM `" . tbl_payment_made_items . "` pmi
                                INNER JOIN `" . tbl_payments_made . "` pm ON pm.id = pmi.payment_id
                                INNER JOIN `" . tbl_purchases . "` p ON p.id = pmi.purchase_id
                                WHERE p.vendor_id = $vendor_id
                                AND pm.payment_status = 'paid'
                                $purchase_date_filter
                            ");
                            $paid_row = $paid_result->fetch_array();
                            $total_paid = (float)$paid_row['total_paid'];

                            // Calculate closing balance
                            $closing_balance = $total_billed - $total_paid;

                        ?>
                            <tr>
                                <td><a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>" class="text-primary"><?php echo s__($display_name); ?></a></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_billed, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_paid, 2); ?></td>
                                <td class="text-end">
                                    <span class="<?php echo ($closing_balance > 0) ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($closing_balance, 2); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php } //while
                        ?>

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