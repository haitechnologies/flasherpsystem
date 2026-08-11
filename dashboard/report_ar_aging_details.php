<?php
// Force opcache clear for this file
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'customers';
$module_caption = 'AR Aging Details';
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

$today = new DateTime('today');
$range_from = null;
$range_to = null;

if (!empty($filter_by)) {
    switch ($filter_by) {
        case 'today':
            $range_from = new DateTime('today');
            $range_to = new DateTime('today');
            break;
        case 'yesterday':
            $range_from = new DateTime('yesterday');
            $range_to = new DateTime('yesterday');
            break;
        case 'this_week':
            $range_from = new DateTime('monday this week');
            $range_to = new DateTime('sunday this week');
            break;
        case 'previous_week':
            $range_from = new DateTime('monday last week');
            $range_to = new DateTime('sunday last week');
            break;
        case 'this_month':
            $range_from = new DateTime('first day of this month');
            $range_to = new DateTime('last day of this month');
            break;
        case 'previous_month':
            $range_from = new DateTime('first day of last month');
            $range_to = new DateTime('last day of last month');
            break;
        case 'this_quarter':
            $current_month = (int)$today->format('n');
            $current_year = (int)$today->format('Y');
            $quarter = (int)ceil($current_month / 3);
            $start_month = (($quarter - 1) * 3) + 1;
            $range_from = new DateTime($current_year . '-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $range_to = (clone $range_from)->modify('+2 months')->modify('last day of this month');
            break;
        case 'previous_quarter':
            $current_month = (int)$today->format('n');
            $current_year = (int)$today->format('Y');
            $quarter = (int)ceil($current_month / 3) - 1;
            if ($quarter <= 0) {
                $quarter = 4;
                $current_year -= 1;
            }
            $start_month = (($quarter - 1) * 3) + 1;
            $range_from = new DateTime($current_year . '-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $range_to = (clone $range_from)->modify('+2 months')->modify('last day of this month');
            break;
        case 'this_year':
            $range_from = new DateTime(date('Y-01-01'));
            $range_to = new DateTime(date('Y-12-31'));
            break;
        case 'previous_year':
            $previous_year = (int)date('Y') - 1;
            $range_from = new DateTime($previous_year . '-01-01');
            $range_to = new DateTime($previous_year . '-12-31');
            break;
    }
}

if ($range_from && $range_to) {
    $date_from = $range_from->format('d-m-Y');
    $date_to = $range_to->format('d-m-Y');
} else {
    $date_from = $date_from;
    $date_to = $date_to;
}

$date_from_sql = '';
$date_to_sql = '';

if (!empty($date_from)) {
    $dt_from = DateTime::createFromFormat('d-m-Y', $date_from);
    if ($dt_from) {
        $date_from_sql = $dt_from->format('Y-m-d');
    }
}

if (!empty($date_to)) {
    $dt_to = DateTime::createFromFormat('d-m-Y', $date_to);
    if ($dt_to) {
        $date_to_sql = $dt_to->format('Y-m-d');
    }
}

$targetpage = 'report_ar_aging_details.php?action=run_report&date_from=' . $date_from . '&date_to=' . $date_to;

if ($page_no) {
    $start = ($page_no - 1) * $limit;
} else {
    $start = 0;
}

// -------------------
$search_query = '';
// -------------------

if (!empty($date_from_sql) && !empty($date_to_sql)) {
    $search_query .= " AND DATE(i.invoice_date) BETWEEN '" . $date_from_sql . "' AND '" . $date_to_sql . "'";
} else if (!empty($date_from_sql)) {
    $search_query .= " AND DATE(i.invoice_date) >= '" . $date_from_sql . "'";
} else if (!empty($date_to_sql)) {
    $search_query .= " AND DATE(i.invoice_date) <= '" . $date_to_sql . "'";
}

$search_query .= " AND i.organization_id = " . (int)$activeOrganizationId;

$invoice_total_column = 'grand_total';
$db_name = $GLOBALS['DB']['DATABASE'] ?? '';
if (!empty($db_name)) {
    $col_check = $mysqli->query(
        "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA='" . $db_name . "' 
         AND TABLE_NAME='" . tbl_invoices . "' 
         AND COLUMN_NAME='grand_total'"
    );
    $col_row = $col_check ? $col_check->fetch_assoc() : null;
    if (empty($col_row['cnt'])) {
        $alt_check = $mysqli->query(
            "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA='" . $db_name . "' 
             AND TABLE_NAME='" . tbl_invoices . "' 
             AND COLUMN_NAME='total_amount'"
        );
        $alt_row = $alt_check ? $alt_check->fetch_assoc() : null;
        if (!empty($alt_row['cnt'])) {
            $invoice_total_column = 'total_amount';
        }
    }
}

// ----------------------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


// if ($action == "run_report") {

//COUNT QUERY
$result_count = $mysqli->query(
    "SELECT COUNT(*) as total_rows FROM (
        SELECT i.id, i.`" . $invoice_total_column . "` as grand_total
        FROM `" . tbl_invoices . "` i
        LEFT JOIN `" . tbl_customers . "` c ON c.id = i.customer_id
        LEFT JOIN `" . tbl_payment_received_items . "` pri ON pri.invoice_id = i.id
        LEFT JOIN `" . tbl_payments_received . "` pr ON pr.id = pri.payment_id
        WHERE i.id > 0 
        AND i.invoice_status IN ('sent', 'partially_paid', 'overdue')
        " . $search_query . "
        GROUP BY i.id
        HAVING (grand_total - COALESCE(SUM(CASE WHEN pr.payment_status='paid' THEN pri.amount_received ELSE 0 END), 0)) > 0
    ) as t"
);
$count_row = $result_count->fetch_assoc();
$total_rows = (int)($count_row['total_rows'] ?? 0);

//NORMAL QUERY
$result_invoices = $mysqli->query(
    "SELECT 
        i.id,
        i.invoice_no,
        i.invoice_date,
        i.expiry_date,
        i.invoice_status,
        i.customer_id,
        c.display_name,
        i.`" . $invoice_total_column . "` as grand_total,
        COALESCE(SUM(CASE WHEN pr.payment_status='paid' THEN pri.amount_received ELSE 0 END), 0) as total_paid
    FROM `" . tbl_invoices . "` i
    LEFT JOIN `" . tbl_customers . "` c ON c.id = i.customer_id
    LEFT JOIN `" . tbl_payment_received_items . "` pri ON pri.invoice_id = i.id
    LEFT JOIN `" . tbl_payments_received . "` pr ON pr.id = pri.payment_id
    WHERE i.id > 0 
    AND i.invoice_status IN ('sent', 'partially_paid', 'overdue')
    " . $search_query . "
    GROUP BY i.id
    HAVING (grand_total - COALESCE(SUM(CASE WHEN pr.payment_status='paid' THEN pri.amount_received ELSE 0 END), 0)) > 0
    ORDER BY i.invoice_date DESC
    LIMIT $start, $limit"
);

//EXPORT EXCEL
$result_invoices_ = $mysqli->query(
    "SELECT 
        i.id,
        i.invoice_no,
        i.invoice_date,
        i.expiry_date,
        i.invoice_status,
        i.customer_id,
        c.display_name,
        i.`" . $invoice_total_column . "` as grand_total,
        COALESCE(SUM(CASE WHEN pr.payment_status='paid' THEN pri.amount_received ELSE 0 END), 0) as total_paid
    FROM `" . tbl_invoices . "` i
    LEFT JOIN `" . tbl_customers . "` c ON c.id = i.customer_id
    LEFT JOIN `" . tbl_payment_received_items . "` pri ON pri.invoice_id = i.id
    LEFT JOIN `" . tbl_payments_received . "` pr ON pr.id = pri.payment_id
    WHERE i.id > 0 
    AND i.invoice_status IN ('sent', 'partially_paid', 'overdue')
    " . $search_query . "
    GROUP BY i.id
    HAVING (grand_total - COALESCE(SUM(CASE WHEN pr.payment_status='paid' THEN pri.amount_received ELSE 0 END), 0)) > 0
    ORDER BY i.invoice_date DESC"
); // Remove Limit
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'ar_aging_details'");
$mysqli->query("UPDATE `" . tbl_accounts_report_subcategories . "` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");

$report_range_text = 'All Dates';
if (!empty($date_from) && !empty($date_to)) {
    $report_range_text = 'From ' . $date_from . ' To ' . $date_to;
} else if (!empty($date_from)) {
    $report_range_text = 'From ' . $date_from;
} else if (!empty($date_to)) {
    $report_range_text = 'To ' . $date_to;
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
                        <div class="text-muted">Receivables</div>
                        <div class="mb-0">
                            <span class="fw-semibold">AR Aging Details</span> - <span class="fw-normal"><?php echo $report_range_text; ?></span>
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
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_ar_aging_details.php">
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
                <h5 class="mb-0">AR Aging Details</h5>
                <p><span class="text-muted"><?php echo $report_range_text; ?></span></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">DATE</th>
                            <th class="table-light">DUE DATE</th>
                            <th class="table-light">TRANSACTION#</th>
                            <th class="table-light">TYPE</th>
                            <th class="table-light">STATUS</th>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light">AGE</th>
                            <th class="table-light text-end">AMOUNT</th>
                            <th class="table-light text-end">BALANCE DUE</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        if ($result_invoices && $result_invoices->num_rows > 0) {
                            while ($row_invoice = $result_invoices->fetch_assoc()) {
                                $invoice_id = (int)$row_invoice['id'];
                                $invoice_no = $row_invoice['invoice_no'];
                                $invoice_date = $row_invoice['invoice_date'];
                                $expiry_date = $row_invoice['expiry_date'];
                                $invoice_status = $row_invoice['invoice_status'];
                                $customer_name = $row_invoice['display_name'];
                                $grand_total = (float)($row_invoice['grand_total'] ?? 0);
                                $total_paid = (float)($row_invoice['total_paid'] ?? 0);

                                $balance_due = $grand_total - $total_paid;
                                if ($balance_due < 0) {
                                    $balance_due = 0;
                                }

                                $due_date = $expiry_date;
                                if (empty($due_date) || $due_date == '1970-01-01') {
                                    $due_date = $invoice_date;
                                }

                                $age_days = 0;
                                if (!empty($due_date) && $due_date != '0000-00-00') {
                                    $age_days = (int)floor((strtotime(date('Y-m-d')) - strtotime($due_date)) / 86400);
                                    if ($age_days < 0) {
                                        $age_days = 0;
                                    }
                                }

                                $status_label = ucwords(str_replace('_', ' ', $invoice_status));
                        ?>
                            <tr>
                                <td><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo ddm_($invoice_date); ?></a></td>
                                <td><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo ddm_($due_date); ?></a></td>
                                <td><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo $invoice_no; ?></a></td>
                                <td>Invoice</td>
                                <td><?php echo $status_label; ?></td>
                                <td><?php echo $customer_name; ?></td>
                                <td><?php echo $age_days; ?> days</td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($grand_total); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($balance_due); ?></td>
                            </tr>
                        <?php
                            }
                        } else {
                        ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No records found for the selected period.</td>
                            </tr>
                        <?php
                        }
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