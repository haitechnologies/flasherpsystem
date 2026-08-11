<?php

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'expenses';
$module_caption = 'Expense Details';
$tbl_name = $tbl_prefix . $module;
// Fix: {$TBL_EXPENSES}/{$TBL_VENDORS} are PHP constants that need variable interpolation in SQL
$TBL_EXPENSES = \App\Core\DB::EXPENSES;
$TBL_VENDORS = \App\Core\DB::VENDORS;
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

// Handle filter_by quick date selection
if (!empty($filter_by) && $filter_by != '0') {
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


$targetpage            = 'report_expense_details.php?action=run_report&date_from=' . $date_from . '&date_to=' . $date_to . '&report_basis=' . $report_basis;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}

// -------------------
$search_query = '';
// -------------------

if (!function_exists('normalizeDateToYmd')) {
    function normalizeDateToYmd($date)
    {
        if (empty($date)) return '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return $date;
        if (!preg_match('/^\d{1,2}-\d{1,2}-\d{4}$/', $date)) return '';
        return processDateDtoY($date);
    }
}
$date_from = normalizeDateToYmd($date_from);
$date_to = normalizeDateToYmd($date_to);

// ----------------------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


// if ($action == "run_report") {

// Build dynamic search query for expenses
$expense_search_query = '';
if (!empty($date_from) && $date_from != '0000-00-00') {
    $expense_search_query .= " AND e.expense_date >= '" . $date_from . "'";
}
if (!empty($date_to) && $date_to != '0000-00-00') {
    $expense_search_query .= " AND e.expense_date <= '" . $date_to . "'";
}
$expense_search_query .= " AND e.organization_id = " . (int)$activeOrganizationId;

//COUNT QUERY - Get expenses with related data
$result         = $mysqli->query("SELECT e.id FROM `{$TBL_EXPENSES}` e 
                                  LEFT JOIN `{$TBL_VENDORS}` v ON e.vendor_id = v.id 
                                  WHERE e.id > 0 " . $expense_search_query);
$total_rows     = $result->num_rows;

//NORMAL QUERY - Get expenses with pagination
$result_expenses  = $mysqli->query("SELECT e.id, e.reference_no, e.expense_date, e.vendor_id, 
                                    e.grand_total as amount, 0 as tax, e.expense_status as status, e.customer_id,
                                    'General' as category_name, COALESCE(v.display_name, 'Internal') as vendor_name
                                    FROM `{$TBL_EXPENSES}` e 
                                    LEFT JOIN `{$TBL_VENDORS}` v ON e.vendor_id = v.id 
                                    WHERE e.id > 0 " . $expense_search_query . " 
                                    ORDER BY e.expense_date DESC LIMIT $start, $limit");

//EXPORT EXCEL - Get all expenses without limit
$result_expenses_ = $mysqli->query("SELECT e.id, e.reference_no, e.expense_date, e.vendor_id, 
                                    e.grand_total as amount, 0 as tax, e.expense_status as status, e.customer_id,
                                    'General' as category_name, COALESCE(v.display_name, 'Internal') as vendor_name
                                    FROM `{$TBL_EXPENSES}` e 
                                    LEFT JOIN `{$TBL_VENDORS}` v ON e.vendor_id = v.id 
                                    WHERE e.id > 0 " . $expense_search_query . " 
                                    ORDER BY e.expense_date DESC");
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'expense_details'");
$mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");



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
                        <div class="text-muted">Purchases and Expenses</div>
                        <div class="mb-0">
                            <span class="fw-semibold">Expense Details</span> - <span class="small">From <?php echo ddm_($date_from); ?> To <?php echo ddm_($date_to); ?></span>
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

                        <form class="steps-basic clearfix" method="request" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_expense_details.php">
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
                <p class="text-muted">Flash Logistics FZC</p>
                <h5 class="mb-0">Expense Details</h5>
                <p class="small"><span class="text-muted">From</span> <?php echo ddm_($date_from); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">STATUS</th>
                            <th class="table-light">DATE</th>
                            <th class="table-light">TRANSACTION TYPE</th>
                            <th class="table-light">TRANSACTION#</th>
                            <th class="table-light">VENDOR NAME</th>
                            <th class="table-light">CATEGORY</th>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light text-end">AMOUNT</th>
                            <th class="table-light text-end">AMOUNT WITH TAX</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        $total_expenses = 0;
                        $total_tax = 0;
                        $total_with_tax = 0;
                        
                        while ($row_expenses = $result_expenses->fetch_array(MYSQLI_ASSOC)) {

                            // Extract data from expense record
                            $expense_id         = $row_expenses['id'];
                            $reference_no       = $row_expenses['reference_no'];
                            $expense_date       = $row_expenses['expense_date'];
                            $category_name      = !empty($row_expenses['category_name']) ? $row_expenses['category_name'] : 'Uncategorized';
                            $vendor_name        = !empty($row_expenses['vendor_name']) ? $row_expenses['vendor_name'] : 'Internal';
                            $amount             = floatval($row_expenses['amount']);
                            $tax                = floatval($row_expenses['tax']);
                            $status             = !empty($row_expenses['status']) ? ucfirst($row_expenses['status']) : 'Pending';
                            $amount_with_tax    = $amount + $tax;
                            
                            // Accumulate totals
                            $total_expenses    += $amount;
                            $total_tax         += $tax;
                            $total_with_tax    += $amount_with_tax;
                            
                            // Set status badge color
                            $status_badge = 'badge-warning';
                            if ($status == 'Paid' || $status == 'Approved') {
                                $status_badge = 'badge-success';
                            } elseif ($status == 'Rejected' || $status == 'Cancelled') {
                                $status_badge = 'badge-danger';
                            } elseif ($status == 'Pending') {
                                $status_badge = 'badge-info';
                            }
                            
                            // Format date
                            $formatted_date = date('d-M-Y', strtotime($expense_date));
                        ?>
                            <tr>
                                <td>
                                    <span class="badge <?php echo $status_badge; ?>"><?php echo htmlspecialchars($status); ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($formatted_date); ?></td>
                                <td><?php echo htmlspecialchars('Expense'); ?></td>
                                <td>
                                    <a href="expense_overview.php?expense_id=<?php echo $expense_id; ?>">
                                        <?php echo htmlspecialchars($reference_no); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($vendor_name); ?></td>
                                <td><?php echo htmlspecialchars($category_name); ?></td>
                                <td><?php echo htmlspecialchars('-'); ?></td>
                                <td class="text-end">
                                    <strong><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($amount, 2); ?></strong>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($amount_with_tax, 2); ?></strong>
                                </td>
                            </tr>
                        <?php } //while 
                        ?>
                        
                        <?php if ($total_rows > 0) { ?>
                        <tr style="background-color: #f5f5f5; font-weight: bold;">
                            <td colspan="7" class="text-end">TOTAL:</td>
                            <td class="text-end">
                                <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_expenses, 2); ?>
                            </td>
                            <td class="text-end">
                                <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_with_tax, 2); ?>
                            </td>
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