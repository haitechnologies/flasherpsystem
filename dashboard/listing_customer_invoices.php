<?php


use App\Core\DB;
use App\Security\Roles;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/InputValidator.php';

$module             = 'invoices';
$module_caption     = 'Invoice';
$tbl_name             = $tbl_prefix . $module;
$error_message         = '';
$success_message     = '';
$hide_add_button = true;


/*
|--------------------------------------------------------------------------
| GENEATE QR CODE AND PDF BOOKING
|--------------------------------------------------------------------------
|
*/
// --- Get From DB where qrcode=''
?>
<!-- <img src="generate_qrcode.php" alt=""> -->
<!-- <img src="generate.php?code=12345" alt=""> -->

<iframe src="generate_invoice_qrcode.php" width="1" height="1"></iframe>

<?php
// --- Get From DB where pdf=''
// include_once('pdf_invoice.php');

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
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
| Validate CSRF token for all POST requests involving actions
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_customer_invoices.php', 'WARNING', __FILE__, __LINE__);
        $_POST['action'] = '';
    }
}


// print_r($_REQUEST);

if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id     = e_s__($_REQUEST['customer_id']);
} else {
    $customer_id = 0;
}


/*
|--------------------------------------------------------------------------
| 	PAGINATION
|--------------------------------------------------------------------------
|
*/

$limit              = 25;
$stages             = 2;


if (isset($_GET['page_no']) && !empty($_GET['page_no'])) {
    $page_no            = e_s__($_GET['page_no']);
} else {
    $page_no            = 1;
}

$targetpage = 'listing_customer_invoices.php?customer_id=' . $customer_id;

// $targetpage            = 'report_bookings.php?action=generate_report&date_from=' . $date_from . '&date_to=' . $date_to . '&ticket_enumber=' . $ticket_enumber . '&booking_full_name=' . $booking_full_name . '&booking_mobile=' . $booking_mobile . '&is_free=' . $is_free . '&ticket_type=' . $ticket_type . '&check=' . $check . '&pax=' . $pax . '&batch_id=' . $batch_id . '&total_cost=' . $total_cost . '&grand_total=' . $grand_total . '&booking_status=' . $booking_status;



if ($page_no) {
    $start   = ($page_no - 1) * $limit;
} else {
    $start   = 0;
}



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/





/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
if (($action == "delete_$module" && !empty($id))) {
    // INPUT VALIDATION: Validate invoice ID
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        $error_message = "Invalid invoice ID: " . $idResult['error'];
    } else {
        $validInvoiceId = $idResult['value'];
        try {
            $invoiceService = \App\Core\Container::getInstance()->get(\App\Service\InvoiceService::class);
            
            // Check ownership if not superadmin
            if (!Roles::hasFullAccess($session_role_id)) {
                $invoice = $invoiceService->getInvoice($validInvoiceId, $activeOrganizationId);
                if ($invoice->createdBy !== (int)Session::userId()) {
                    throw new \Exception("You do not have permission to delete this invoice");
                }
            }

            if ($invoiceService->deleteInvoice($validInvoiceId, $activeOrganizationId)) {
                $success_message = "$module_caption Deleted Successfully.";
                flash_success($success_message);
                header("Location:listing_customer_invoices.php?customer_id=$customer_id");
                exit;
            } else {
                $error_message = "Sorry! $module Could Not Be Deleted.";
            }
        } catch (\Throwable $e) {
            $error_message = $e->getMessage();
            log_error("Delete failed for invoice $validInvoiceId: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
        }
    }
}



// $q_s = getTableAttr('invoice_status', tbl_customer_invoices, $id);
// if ($q_s == 'booked') header("Location:listing_$module.php?error_message=invoice is already booked.");;


if (isset($_POST['is_active']))                                 $is_active = 1;
else $is_active = 0;


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

$db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);

//COUNT QUERY
$countRow = $db->fetchOne(
    "SELECT COUNT(id) as cnt FROM `erp_invoices` WHERE customer_id = :customer_id AND organization_id = :org_id",
    ['customer_id' => $customer_id, 'org_id' => $activeOrganizationId]
);
$total_pages = (int)($countRow['cnt'] ?? 0);

//NORMAL QUERY
$result_customer_invoices = $db->fetchAll(
    "SELECT * FROM `erp_invoices` WHERE customer_id = :customer_id AND organization_id = :org_id ORDER BY id DESC LIMIT :start, :limit",
    [
        'customer_id' => $customer_id,
        'org_id' => $activeOrganizationId,
        'start' => (int)$start,
        'limit' => (int)$limit
    ]
);



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>
<div class="content-wrapper">


    <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
            <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
        <?php } else { ?>
            <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
        <?php } ?>

        <!-- Page header -->
        <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content py-2 px-3">
                <div class="d-flex">
                    <div class="breadcrumb py-2">
                        <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                        <a href="index.php" class="breadcrumb-item">Home</a>
                        <a href="listing_customers.php" class="breadcrumb-item">Customers</a>
                        <span class="breadcrumb-item active">Invoices</span>
                    </div>

                    <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                        <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                    </a>
                </div>

                <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                    <div class="collapse d-lg-block ms-lg-auto mt-1" id="breadcrumb_elements">
                        <div class="d-lg-flex mb-2 mb-lg-0">
                            <button type="button" class="btn btn-info my-1 me-2 nav-link" data-href="<?php echo $admin_base_url; ?>/customer_invoices.php?customer_id=<?php echo $customer_id; ?>">Create Invoice</button>
                        </div>
                    </div>
                <?php } ?>

            </div>
        </div>
        <!-- /page header -->


        <div class="content-inner">
            <div class="content datatable-enhanced">

                <?php include('admin_elements/breadcrumb.php'); ?>

                <div class="row">

                    <?php include(__DIR__ . '/admin_elements/customer_navbar.php'); ?>

                    <div class="col-lg-10">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><?php echo $total_pages; ?> Invoices Found.</h5>
                            </div>


                            <div class="card-body">

                                <div class="table datatable-professional-responsive">
                                    <table class="table datatable-professional">

                                        <thead>
                                            <tr>
                                                <th width="150">DATE</th>
                                                <th>INVOICE#</th>
                                                <th>ORDER NUMBER</th>
                                                <th>CUSTOMER NAME</th>
                                                <th>STATUS</th>
                                                <th>DUE DATE</th>
                                                <th class="text-end p3">AMOUNT</th>
                                                <th class="text-end p3">BALANCE DUE</th>
                                                <!-- <th width="120">ACTION</th> -->
                                            </tr>
                                        </thead>


                                        <tbody>

                                            <?php

                                            // Calculate serial number start based on page number and limit
                                            $serial_no = ($page_no - 1) * $limit + 1;

                                            // ---------------------------------------------------------------------------------------
                                            foreach ($result_customer_invoices as $row) {

                                                $id                     = $row["id"];

                                                $customer_id            = s__($row['customer_id']);
                                                $display_name           = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);

                                                $invoice_no             = s__($row['invoice_no']);
                                                $invoice_date           = s__($row['invoice_date']);
                                                $sale_order_id          = s__($row['sale_order_id']);
                                                $expiry_date            = s__($row['expiry_date']);
                                                $invoice_status         = s__($row['invoice_status']);
                                                $subject                = s__($row['subject']);

                                                $grand_subtotal         = s__($row['grand_subtotal']);
                                                $grand_tax              = s__($row['grand_tax']);
                                                $grand_total            = s__($row['grand_total']);
                                                $created_at             = s__($row['created_at']);
                                                $is_active = s__($row['is_active']);

                                                $invoice_date       = ddm_($invoice_date);
                                                $expiry_date        = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : ddm_($expiry_date);

                                                $created_at     = dd__($created_at);

                                                // ---------------------------------------------------------------------------------------
                                            ?>

                                                <tr>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo $invoice_date; ?></a></td>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo $invoice_no; ?></a></td>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo $sale_order_id; ?></a></td>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo $display_name; ?></a></td>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>" target="_blank"><span class="badge text-dark"><?php echo ucwords($invoice_status); ?></a></span></td>
                                                    <td><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo $expiry_date; ?></a></td>
                                                    <td class="text-end p3"><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo BASE_CURRENCY['code']; ?><?php echo $grand_total; ?></a></td>
                                                    <td class="text-end p3"><a href="invoice_overview.php?id=<?php echo $id; ?>"><?php echo BASE_CURRENCY['code']; ?><?php echo '0'; ?></a></td>
                                                </tr>

                                            <?php
                                            } //while
                                            ?>


                                        </tbody>
                                    </table>
                                </div>

                            </div>


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

                    $lastpage     = ceil($total_pages / $limit);
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


                </div>
            </div>


        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
    </form>

</div>

<?php include('admin_elements/admin_footer.php'); ?>


