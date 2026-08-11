<?php

include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'customers';
$module_caption = 'Time to Get Paid';
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


$targetpage            = 'report_time_to_get_paid.php?action=run_report';



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}

// Current date for "As of" display
$current_date = date('Y-m-d');

// -------------------
$search_query = '';
// -------------------



/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/


//COUNT QUERY - Count customers with payments
$result = $mysqli->query("SELECT COUNT(DISTINCT c.id) as total FROM `" . tbl_customers . "` c 
                          INNER JOIN `" . tbl_invoices . "` i ON c.id = i.customer_id 
                          LEFT JOIN `" . tbl_payment_received_items . "` pri ON i.id = pri.invoice_id
                          WHERE i.id > 0 AND pri.amount_received > 0 AND i.organization_id = " . (int)$activeOrganizationId . " AND c.organization_id = " . (int)$activeOrganizationId);
$row = $result->fetch_array();
$total_rows = $row['total'];

//NORMAL QUERY - Get customers with payment timing analysis
$result_customers = $mysqli->query("SELECT c.id, c.display_name,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 0 AND 15 THEN pri.amount_received ELSE 0 END) as days_0_15,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 16 AND 30 THEN pri.amount_received ELSE 0 END) as days_16_30,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 31 AND 45 THEN pri.amount_received ELSE 0 END) as days_31_45,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) > 45 THEN pri.amount_received ELSE 0 END) as days_above_45
    FROM `" . tbl_customers . "` c
    INNER JOIN `" . tbl_invoices . "` i ON c.id = i.customer_id
    LEFT JOIN `" . tbl_payment_received_items . "` pri ON i.id = pri.invoice_id
    WHERE i.id > 0 AND i.organization_id = " . (int)$activeOrganizationId . " AND c.organization_id = " . (int)$activeOrganizationId . "
    GROUP BY c.id, c.display_name
    HAVING (days_0_15 + days_16_30 + days_31_45 + days_above_45) > 0
    ORDER BY c.display_name ASC
    LIMIT $start, $limit");

//EXPORT EXCEL - Same query without limit
$result_customers_ = $mysqli->query("SELECT c.id, c.display_name,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 0 AND 15 THEN pri.amount_received ELSE 0 END) as days_0_15,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 16 AND 30 THEN pri.amount_received ELSE 0 END) as days_16_30,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) BETWEEN 31 AND 45 THEN pri.amount_received ELSE 0 END) as days_31_45,
    SUM(CASE WHEN DATEDIFF(pri.amount_received_on, i.invoice_date) > 45 THEN pri.amount_received ELSE 0 END) as days_above_45
    FROM `" . tbl_customers . "` c
    INNER JOIN `" . tbl_invoices . "` i ON c.id = i.customer_id
    LEFT JOIN `" . tbl_payment_received_items . "` pri ON i.id = pri.invoice_id
    WHERE i.id > 0 AND i.organization_id = " . (int)$activeOrganizationId . " AND c.organization_id = " . (int)$activeOrganizationId . "
    GROUP BY c.id, c.display_name
    HAVING (days_0_15 + days_16_30 + days_31_45 + days_above_45) > 0
    ORDER BY c.display_name ASC");


// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", tbl_accounts_report_subcategories, " slug = 'time_to_get_paid'");
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
                            <span class="fw-semibold">Time to Get Paid</span> - <span class="fw-normal">As of <?php echo date('d M Y'); ?></span>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="col-lg-12 text-end">
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
</div>
    <!-- /page header -->


    <div class="content">

        <div class="card">
            <div class="card-header text-center">
                <p>Flash Logistics FZC</p>
                <h5 class="mb-0">Time to Get Paid</h5>
                <p>As of <?php echo date('d M Y'); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">CUSTOMER NAME</th>
                            <th class="table-light text-end">0 - 15 DAYS</th>
                            <th class="table-light text-end">16 - 30 DAYS</th>
                            <th class="table-light text-end">31 - 45 DAYS</th>
                            <th class="table-light text-end">ABOVE 45 DAYS</th>
                        </tr>

                        <?php
                        // -----------------------------------------------------------------------------------
                        $total_0_15 = 0;
                        $total_16_30 = 0;
                        $total_31_45 = 0;
                        $total_above_45 = 0;
                        
                        while ($row_customers = $result_customers->fetch_array()) {

                            // -----------------------------------------------------------------------------------
                            $customer_id    = $row_customers['id'];
                            $display_name   = $row_customers['display_name'];
                            $days_0_15      = number_format($row_customers['days_0_15'], 2);
                            $days_16_30     = number_format($row_customers['days_16_30'], 2);
                            $days_31_45     = number_format($row_customers['days_31_45'], 2);
                            $days_above_45  = number_format($row_customers['days_above_45'], 2);
                            
                            $total_0_15 += $row_customers['days_0_15'];
                            $total_16_30 += $row_customers['days_16_30'];
                            $total_31_45 += $row_customers['days_31_45'];
                            $total_above_45 += $row_customers['days_above_45'];

                        ?>
                            <tr>
                                <td><?php echo $display_name; ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo $days_0_15; ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo $days_16_30; ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo $days_31_45; ?></td>
                                <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo $days_above_45; ?></td>
                            </tr>
                        <?php } //while 
                        ?>
                        
                        <!-- Total Row -->
                        <tr class="table-light fw-bold">
                            <td>Total</td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_0_15, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_16_30, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_31_45, 2); ?></td>
                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_above_45, 2); ?></td>
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