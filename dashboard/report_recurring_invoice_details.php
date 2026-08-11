<?php
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'invoices';
$module_caption = 'Recurring Invoice Details';
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


$targetpage            = 'report_recurring_invoice_details.php?action=run_report&filter_by=' . $filter_by . '&date_from=' . $date_from . '&date_to=' . $date_to;



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
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'recurring_invoice_details'");
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
                            <span class="fw-semibold">Recurring Invoice Details</span>
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

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_recurring_invoice_details.php">
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
                <h5 class="mb-0">Recurring Invoice Details</h5>
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
                            <th class="table-light">STATUS</th>
                            <th class="table-light">PROFILE NAME</th>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light">FREQUENCY</th>
                            <th class="table-light">LAST INVOICE DATE</th>
                            <th class="table-light">NEXT INVOICE DATE</th>
                            <th class="table-light">EXPIRY DATE</th>
                            <th class="table-light text-end">AMOUNT</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        // Build date filter for queries (based on start_date or invoice_date)
                        $date_from_ymd = processDateDtoY($date_from);
                        $date_to_ymd = processDateDtoY($date_to);

                        $date_filter = '';
                        if (!empty($date_from_ymd)) {
                            $date_filter .= " AND (i.start_date >= '" . $date_from_ymd . "' OR i.invoice_date >= '" . $date_from_ymd . "')";
                        }
                        if (!empty($date_to_ymd)) {
                            $date_filter .= " AND (i.end_date <= '" . $date_to_ymd . "' OR i.invoice_date <= '" . $date_to_ymd . "')";
                        }
                        $date_filter .= " AND i.organization_id = " . (int)$activeOrganizationId;

                        // Count total recurring invoices for pagination
                        $count_result = $mysqli->query("
                            SELECT COUNT(*) as total
                            FROM `" . tbl_invoices . "` i
                            WHERE i.recurring = 1 " . $date_filter . "
                        ");
                        $count_row = $count_result->fetch_array();
                        $total_rows = $count_row['total'];

                        // Get recurring invoices with pagination
                        $result_invoices = $mysqli->query("
                            SELECT i.*, c.display_name as customer_name
                            FROM `" . tbl_invoices . "` i
                            LEFT JOIN `" . tbl_customers . "` c ON c.id = i.customer_id
                            WHERE i.recurring = 1 " . $date_filter . "
                            ORDER BY i.invoice_date DESC, c.display_name ASC
                            LIMIT $start, $limit
                        ");

                        while ($row = $result_invoices->fetch_array()) {
                            $invoice_id = $row['id'];
                            $profile_name = $row['profile_name'] ?? '-';
                            $customer_name = $row['customer_name'] ?? 'Unknown';
                            $frequency = $row['frequency'] ?? '-';

                            // Use available date fields - try multiple column names as they may vary
                            $last_invoice_date = $row['last_invoice_date'] ?? $row['invoice_date'] ?? '';
                            $next_invoice_date = $row['next_invoice_date'] ?? $row['start_date'] ?? '';
                            $expiry_date = $row['end_date'] ?? $row['expiry_date'] ?? '';

                            $grand_total = (float)($row['grand_total'] ?? 0);

                            // Determine status (recurring_status or invoice_status)
                            $status = '';
                            if (isset($row['recurring_status'])) {
                                $status = ($row['recurring_status'] == 1) ? 'active' : 'inactive';
                            } else {
                                $status = $row['invoice_status'] ?? 'unknown';
                            }

                        ?>
                            <tr>
                                <td>
                                    <?php if ($status == 'active') { ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php } elseif ($status == 'inactive') { ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary"><?php echo ucfirst($status); ?></span>
                                    <?php } ?>
                                </td>
                                <td><a href="recurring_invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>" class="text-primary"><?php echo s__($profile_name); ?></a></td>
                                <td><a href="customers.php?customer_id=<?php echo $row['customer_id']; ?>" class="text-primary"><?php echo s__($customer_name); ?></a></td>
                                <td><?php echo ucwords($frequency); ?></td>
                                <td><?php echo !empty($last_invoice_date) ? ddm_($last_invoice_date) : '-'; ?></td>
                                <td><strong><?php echo !empty($next_invoice_date) ? ddm_($next_invoice_date) : '-'; ?></strong></td>
                                <td><?php echo !empty($expiry_date) ? ddm_($expiry_date) : '-'; ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_total, 2); ?></td>
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
