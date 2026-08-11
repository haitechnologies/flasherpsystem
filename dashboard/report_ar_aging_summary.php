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
$module_caption = 'AR Aging Summary';
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
$as_of_date             = ((isset($_REQUEST['as_of_date']) && !empty($_REQUEST['as_of_date'])) ? e_s__($_REQUEST['as_of_date']) : date('d-m-Y'));


// Add getDateRangeByFilter function if not exists
if (!function_exists('getDateRangeByFilter')) {
    function getDateRangeByFilter($filter_by) {
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

// Apply filter_by if selected
if (!empty($filter_by) && $filter_by !== '0') {
    [$filter_start, $filter_end] = getDateRangeByFilter($filter_by);
    if (!empty($filter_start) && !empty($filter_end)) {
        $date_from = $filter_start;
        $date_to = $filter_end;
    }
}

// Convert dates to SQL format
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


$targetpage            = 'report_ar_aging_summary.php?action=run_report&as_of_date=' . $as_of_date . '&date_from=' . $date_from . '&date_to=' . $date_to . '&filter_by=' . $filter_by;


if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}

// -------------------
$search_query = '';
// -------------------

$search_query .= " AND organization_id = " . (int)$activeOrganizationId;

// ----------------------------------------------------------------------------------------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


// if ($action == "run_report") {

//COUNT QUERY
$result         = $mysqli->query("SELECT id FROM `" . tbl_customers . "` WHERE id>0 " . $search_query);
$total_rows     = $result->num_rows;

//NORMAL QUERY
$result_customers  = $mysqli->query("SELECT * FROM `" . tbl_customers . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit");

//EXPORT EXCEL
$result_customers_ = $mysqli->query("SELECT * FROM `" . tbl_customers . "` WHERE id>0 " . $search_query . " ORDER BY id ASC"); // Remove Limit
// }


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'ar_aging_summary'");
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
                        <div class="text-muted">Receivables</div>
                        <div class="mb-0">
                            <span class="fw-semibold">AR Aging Summary</span> - <span class="fw-normal">As of <?php echo ddm_($as_of_date); ?></span>
                            <?php if (!empty($date_from) || !empty($date_to)) { ?>
                                <br><span class="small text-muted">Invoice Date:
                                <?php
                                if (!empty($date_from) && !empty($date_to)) {
                                    echo ddm_($date_from) . ' to ' . ddm_($date_to);
                                } elseif (!empty($date_from)) {
                                    echo 'From ' . ddm_($date_from);
                                } elseif (!empty($date_to)) {
                                    echo 'To ' . ddm_($date_to);
                                }
                                ?>
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
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                    document.getElementById('as_of_date').value = '<?php echo date('d-m-Y'); ?>';
                    document.getElementById('frmcustomers').submit();
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frmcustomers" name="frmcustomers" autocomplete="off" action="report_ar_aging_summary.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-3">
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
                                                <input type="text" class="form-control" name="date_from" id="date_from" placeholder="Date From" value="<?php echo $date_from; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_to" id="date_to" placeholder="Date To" value="<?php echo $date_to; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="as_of_date" id="as_of_date" placeholder="As of Date" value="<?php echo $as_of_date; ?>">
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
                <h5 class="mb-0">AR Aging Summary</h5>
                <p class="small"><span class="text-muted">As of</span> <?php echo ddm_($as_of_date); ?></p>
                <?php if (!empty($date_from) || !empty($date_to)) { ?>
                    <p class="small text-muted">
                        Invoice Date:
                        <?php
                        if (!empty($date_from) && !empty($date_to)) {
                            echo ddm_($date_from) . ' to ' . ddm_($date_to);
                        } elseif (!empty($date_from)) {
                            echo 'From ' . ddm_($date_from);
                        } elseif (!empty($date_to)) {
                            echo 'To ' . ddm_($date_to);
                        }
                        ?>
                    </p>
                <?php } ?>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light text-end">CURRENT</th>
                            <th class="table-light text-end">1 - 15 DAYS</th>
                            <th class="table-light text-end">16 - 30 DAYS</th>
                            <th class="table-light text-end">31 - 45 DAYS</th>
                            <th class="table-light text-end">> 45 DAYS</th>
                            <th class="table-light text-end">TOTAL</th>
                            <th class="table-light text-end">TOTAL (FCY)</th>
                            <th class="table-light">EXPIRY DATE</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        // Initialize totals
                        $grand_current = 0;
                        $grand_1_15 = 0;
                        $grand_16_30 = 0;
                        $grand_31_45 = 0;
                        $grand_over_45 = 0;
                        $grand_total = 0;
                        $grand_total_fcy = 0;

                        // Use as_of_date instead of today
                        $as_of_date_ymd = processDateDtoY($as_of_date);
                        $today = new DateTime($as_of_date_ymd);
                        $today->setTime(0, 0, 0);

                        // Build date filter for invoices
                        $invoice_date_filter = '';
                        if (!empty($date_from_sql)) {
                            $invoice_date_filter .= " AND i.invoice_date >= '" . $date_from_sql . "'";
                        }
                        if (!empty($date_to_sql)) {
                            $invoice_date_filter .= " AND i.invoice_date <= '" . $date_to_sql . "'";
                        }
                        $invoice_date_filter .= " AND i.organization_id = " . (int)$activeOrganizationId;

                        // Get customers who have invoices
                        $result_customers = $mysqli->query("
                            SELECT DISTINCT c.id, c.display_name, c.currency
                            FROM `" . tbl_customers . "` c
                            INNER JOIN `" . tbl_invoices . "` i ON c.id = i.customer_id
                            WHERE i.invoice_status = 'sent'
                            " . $invoice_date_filter . "
                            ORDER BY c.display_name ASC
                        ");
                        
                        while ($row_customers = $result_customers->fetch_array()) {
                            $customer_id = $row_customers['id'];
                            $display_name = $row_customers['display_name'];
                            $customer_currency_id = $row_customers['currency'];
                            
                            // Get currency code from currencies table
                            $customer_currency = '';
                            if (!empty($customer_currency_id)) {
                                $customer_currency = getTableAttr('currency', tbl_currencies, $customer_currency_id);
                            }
                            
                            // Initialize aging buckets for this customer
                            $current = 0;
                            $days_1_15 = 0;
                            $days_16_30 = 0;
                            $days_31_45 = 0;
                            $days_over_45 = 0;
                            $oldest_due_date = null;
                            
                            // Track foreign currency totals
                            $current_fcy = 0;
                            $days_1_15_fcy = 0;
                            $days_16_30_fcy = 0;
                            $days_31_45_fcy = 0;
                            $days_over_45_fcy = 0;
                            
                            // Get all unpaid/partially paid invoices for this customer
                            $result_invoices = $mysqli->query("
                                SELECT id, invoice_no, invoice_date, payment_term, grand_total
                                FROM `" . tbl_invoices . "`
                                WHERE customer_id = $customer_id
                                AND invoice_status = 'sent'
                                " . str_replace('i.', '', $invoice_date_filter) . "
                                ORDER BY invoice_date ASC
                            ");
                            
                            while ($row_invoice = $result_invoices->fetch_array()) {
                                $invoice_id = $row_invoice['id'];
                                $invoice_date = $row_invoice['invoice_date'];
                                $payment_term_duration = getTableAttr('payment_term', tbl_payment_terms, $row_invoice['payment_term']);
                                $invoice_total = $row_invoice['grand_total'];
                                
                                // Calculate amount paid for this invoice
                                $payments_result = $mysqli->query("
                                    SELECT COALESCE(SUM(amount_received), 0) as total_paid
                                    FROM `" . tbl_payment_received_items . "`
                                    WHERE invoice_id = $invoice_id
                                ");
                                $payments_row = $payments_result->fetch_array();
                                $amount_paid = $payments_row['total_paid'];
                                
                                // Calculate balance due
                                $balance_due = $invoice_total - $amount_paid;
                                
                                // Skip fully paid invoices for both accrual and cash basis
                                // AR Aging shows OUTSTANDING amounts (what customers owe)
                                if ($balance_due <= 0) continue;
                                
                                // Calculate due date
                                $due_date_str = calculateInvoiceDueDate('sent', $invoice_date, $payment_term_duration);
                                
                                if (!empty($due_date_str)) {
                                    $due_date = new DateTime($due_date_str);
                                    $due_date->setTime(0, 0, 0);
                                    
                                    // Track oldest due date for expiry column
                                    if ($oldest_due_date === null || $due_date < $oldest_due_date) {
                                        $oldest_due_date = $due_date;
                                    }
                                    
                                    // Calculate days overdue
                                    $interval = $due_date->diff($today);
                                    $days_diff = ($today > $due_date) ? $interval->days : -$interval->days;
                                    
                                    // Categorize into aging buckets
                                    if ($days_diff < 0) {
                                        // Not yet due (current)
                                        $current += $balance_due;
                                    } else if ($days_diff >= 0 && $days_diff <= 15) {
                                        $days_1_15 += $balance_due;
                                    } else if ($days_diff >= 16 && $days_diff <= 30) {
                                        $days_16_30 += $balance_due;
                                    } else if ($days_diff >= 31 && $days_diff <= 45) {
                                        $days_31_45 += $balance_due;
                                    } else {
                                        // Over 45 days
                                        $days_over_45 += $balance_due;
                                    }
                                } else {
                                    // No due date means due on receipt (current)
                                    $current += $balance_due;
                                }
                            }
                            
                            // Calculate row total
                            $row_total = $current + $days_1_15 + $days_16_30 + $days_31_45 + $days_over_45;
                            
                            // Skip customers with no outstanding balance
                            if ($row_total <= 0) continue;
                            
                            // Update grand totals
                            $grand_current += $current;
                            $grand_1_15 += $days_1_15;
                            $grand_16_30 += $days_16_30;
                            $grand_31_45 += $days_31_45;
                            $grand_over_45 += $days_over_45;
                            $grand_total += $row_total;
                            
                            // Format expiry date
                            $expiry_display = ($oldest_due_date !== null) ? $oldest_due_date->format('d M Y') : '-';
                            
                            // Get currency code and exchange rate for foreign currency conversion
                            $currency_code = !empty($customer_currency) ? $customer_currency : BASE_CURRENCY['code'];
                            $is_foreign_currency = ($currency_code != BASE_CURRENCY['code']);
                            
                            // Get exchange rate if foreign currency
                            $exchange_rate = 1;
                            if ($is_foreign_currency && !empty($customer_currency_id)) {
                                $rate_result = $mysqli->query("SELECT exchange_rate FROM `" . tbl_currencies . "` WHERE id = $customer_currency_id");
                                if ($rate_result && $rate_row = $rate_result->fetch_array()) {
                                    $exchange_rate = !empty($rate_row['exchange_rate']) ? floatval($rate_row['exchange_rate']) : 1;
                                }
                            }
                            
                            // Calculate foreign currency amounts if applicable
                            $row_total_fcy = $is_foreign_currency && $exchange_rate > 0 ? $row_total / $exchange_rate : 0;
                        ?>
                            <tr>
                                <td><a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>"><?php echo $display_name; ?></a></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($current, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($days_1_15, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($days_16_30, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($days_31_45, 2); ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($days_over_45, 2); ?></td>
                                <td class="text-end"><strong><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($row_total, 2); ?></strong></td>
                                <td class="text-end"><?php if ($is_foreign_currency && $row_total_fcy > 0) echo $currency_code . ' ' . number_format($row_total_fcy, 2); else echo '-'; ?></td>
                                <td><?php echo $expiry_display; ?></td>
                            </tr>
                        <?php } //while 
                        ?>
                        
                        <!-- Grand Total Row -->
                        <tr class="fw-bold">
                            <td>TOTAL</td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_current, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_1_15, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_16_30, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_31_45, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_over_45, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($grand_total, 2); ?></td>
                            <td class="text-end">-</td>
                            <td>-</td>
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

<?php include('admin_elements/admin_footer.php'); ?>