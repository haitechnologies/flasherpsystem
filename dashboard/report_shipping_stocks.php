<?php


use App\Core\DB;
include('admin_elements/admin_header.php');
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$module = 'shipping_stocks';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Invoice';
$tbl_name = DB::SHIPPING_STOCKS;
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


$limit                 = 50;
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


$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : '');
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : '');
$consignee_id           = ((isset($_REQUEST['consignee_id']) && !empty($_REQUEST['consignee_id'])) ? e_s__($_REQUEST['consignee_id']) : '');
$incoterm               = ((isset($_REQUEST['incoterm']) && !empty($_REQUEST['incoterm'])) ? e_s__($_REQUEST['incoterm']) : '');
$hs_code                = ((isset($_REQUEST['hs_code']) && !empty($_REQUEST['hs_code'])) ? e_s__($_REQUEST['hs_code']) : '');
$description            = ((isset($_REQUEST['description']) && !empty($_REQUEST['description'])) ? e_s__($_REQUEST['description']) : '');
$total_qty              = ((isset($_REQUEST['total_qty']) && !empty($_REQUEST['total_qty'])) ? e_s__($_REQUEST['total_qty']) : '');
$remaining_qty          = ((isset($_REQUEST['remaining_qty']) && !empty($_REQUEST['remaining_qty'])) ? e_s__($_REQUEST['remaining_qty']) : '');
$origin                 = ((isset($_REQUEST['origin']) && !empty($_REQUEST['origin'])) ? e_s__($_REQUEST['origin']) : '');
$value                  = ((isset($_REQUEST['value']) && !empty($_REQUEST['value'])) ? e_s__($_REQUEST['value']) : '');
$weight                 = ((isset($_REQUEST['weight']) && !empty($_REQUEST['weight'])) ? e_s__($_REQUEST['weight']) : '');
$invoice_no             = ((isset($_REQUEST['invoice_no']) && !empty($_REQUEST['invoice_no'])) ? e_s__($_REQUEST['invoice_no']) : '');
$admin_id               = (isset($_REQUEST['admin_id']) ? e_s__($_REQUEST['admin_id']) : '');
$action                 = (isset($_REQUEST['action']) ? e_s__($_REQUEST['action']) : '');
$agent_id               = (isset($_REQUEST['agent_id']) && !empty($_REQUEST['agent_id']) ? e_s__($_REQUEST['agent_id']) : '');
$vehicle_type           = (isset($_REQUEST['vehicle_type']) && !empty($_REQUEST['vehicle_type']) ? e_s__($_REQUEST['vehicle_type']) : '');
$driver_assigned        = (isset($_REQUEST['driver_assigned']) && !empty($_REQUEST['driver_assigned']) ? e_s__($_REQUEST['driver_assigned']) : '');
$invoice_status         = (isset($_REQUEST['invoice_status']) && !empty($_REQUEST['invoice_status']) ? e_s__($_REQUEST['invoice_status']) : '');


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


$targetpage            = 'report_shipping_stocks.php?action=generate_report&date_from=' . $date_from . '&date_to=' . $date_to . '&admin_id=' . $admin_id;



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



if (!empty($hs_code)) {
    $search_query .= " AND hs_code = $hs_code";
}

if (!empty($agent_id)) {
    $search_query .= " AND agent_id = $agent_id";
}


if (!empty($vehicle_type)) {
    $search_query .= " AND id IN (SELECT invoice_id FROM " . DB::INVOICE_ITEMS . " WHERE vehicle_type = $vehicle_type) ";
}

if (!empty($driver_assigned)) {

    if ($driver_assigned == 'yes') {
        $search_query .= " AND id IN (SELECT invoice_id FROM " . tbl_trips . " WHERE driver_id > 0) ";
    } else {
        $search_query .= " AND id IN (SELECT invoice_id FROM " . tbl_trips . " WHERE driver_id = 0) ";
    }
}


if (!empty($invoice_status)) {
    $search_query .= " AND invoice_status = '" . $invoice_status . "'";
}

if (!empty($admin_id)) {
    $search_query .= " AND created_by = '" . $admin_id . "'";
}

$search_query .= " AND organization_id = " . (int)$activeOrganizationId;


/*
|--------------------------------------------------------------------------
| SEARCH QUERY
|--------------------------------------------------------------------------
|
*/

//COUNT QUERY
// $result = $mysqli->query("SELECT id FROM `" . DB::SHIPPING_ADVICES . "` WHERE id=0 ");
// $total_pages  = $result->num_rows;

// //NORMAL QUERY
// $result_invoices = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICES . "` WHERE id=0 ");


if ($action == "generate_report") {

    //COUNT QUERY
    $result         = $mysqli->query("SELECT id FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE id>0 " . $search_query);
    $total_pages    = $result->num_rows;

    //NORMAL QUERY
    $result_shipping_advice_items  = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE id>0 " . $search_query . " ORDER BY id ASC LIMIT $start, $limit");

    //EXPORT EXCEL
    $result_invoices_ = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE id>0 " . $search_query . " ORDER BY id ASC"); // Remove Limit
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
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content py-2 px-3">
            <div class="d-flex">
                <div class="breadcrumb py-2">
                    <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                    <a href="index.php" class="breadcrumb-item">Home</a>
                    <a href="listing_invoices.php" class="breadcrumb-item">Manage Stock </a>
                    <span class="breadcrumb-item active">Generate Report</span>
                </div>

                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            </div>

            <!-- <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
				<div class="d-lg-flex mb-2 mb-lg-0">
					<button type="button" onclick="window.location.href='index.php';"" class=" btn btn-outline-primary my-1 me-2">Exit</button>
				</div>
			</div> -->

        </div>
    </div>
    <!-- /page header -->


    <div class="content">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="row">
            <div class="col-lg-12">
                <div class="card bg-success bg-opacity-10">
                    <!-- <div class="card-header">
                            <h6 class="mb-0">Generate Invoice For:</h6>
                        </div> -->

                    <form class="steps-basic clearfix" method="request" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_shipping_stocks.php">
                        <input type="hidden" name="action" id="action" value="generate_report" />

                        <div class="card-body">

                            <div class="row">

                                <div class="col-lg-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Date From: </label>

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
                                        <label class="form-label fw-semibold">Date To: </label>

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
                                        <label class="form-label fw-semibold">Consignee: </label>

                                        <div class="form-control-feedback form-control-feedback-start">
                                            <select name="consignee_id" id="consignee_id" class="form-select">
                                                <option value='0'></option>
                                                <?php
                                                $result = $mysqli->query("SELECT * FROM `" . DB::CONSIGNEES  . "` WHERE is_active=1 ORDER BY consignee_name ASC");
                                                while ($rows = $result->fetch_array()) {
                                                    $consignee_name = $rows["consignee_name"];
                                                ?>
                                                    <option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $consignee_id) { ?>selected <?php } else if ($rows['id'] == $consignee_id) { ?>selected <?php } ?>>
                                                        <?php echo $consignee_name; ?>
                                                    </option>
                                                <?php } ?>

                                            </select>
                                        </div>

                                    </div>
                                </div>


                                <div class="col-lg-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Incoterms: </label>
                                        <div>
                                            <select name="incoterm" id="incoterm" class="form-select">
                                                <option value='0'>&nbsp;</option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result = $mysqli->query("SELECT * FROM `" . DB::INCOTERMS  . "` ORDER BY incoterm ASC");
                                                while ($rows = $result->fetch_array()) {
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $incoterm) { ?> selected <?php } else if ($rows['id'] == $incoterm) { ?> selected <?php } ?>>
                                                        <?php echo $rows["incoterm"]; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>


                                <div class="col-lg-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">HS Code: </label>
                                        <div>
                                            <input type="text" class="form-control" name="hs_code" id="hs_code" value="<?php echo $hs_code; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-2">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Description: </label>
                                        <div>
                                            <input type="text" class="form-control" name="description" id="description" value="<?php echo $description; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Total QTY: </label>
                                        <div>
                                            <input type="text" class="form-control" name="total_qty" id="total_qty" value="<?php echo $total_qty; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Remaining QTY: </label>
                                        <div>
                                            <input type="text" class="form-control" name="remaining_qty" id="remaining_qty" value="<?php echo $remaining_qty; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Origin: </label>
                                        <div>
                                            <input type="text" class="form-control" name="origin" id="origin" value="<?php echo $origin; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Value: </label>
                                        <div>
                                            <input type="text" class="form-control" name="value" id="value" value="<?php echo $value; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Weight: </label>
                                        <div>
                                            <input type="text" class="form-control" name="weight" id="weight" value="<?php echo $weight; ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-1">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Invoice #: </label>
                                        <div>
                                            <input type="text" class="form-control" name="invoice_no" id="invoice_no" value="<?php echo $invoice_no; ?>">
                                        </div>
                                    </div>
                                </div>




                                <div class="col-lg-3">
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success my-1 me-2">Generate Report</button>
                                        <button type="button" onclick="resetForm();" class="btn btn-light my-1 me-2">Reset</button>
                                    </div>
                                </div>


                            </div>

                        </div>

                    </form>

                </div>
            </div>

        </div>

        <script>
            function resetForm() {
                document.getElementById('date_from').value = '';
                document.getElementById('date_to').value = '';
                document.getElementById('hs_code').value = '';
                document.getElementById('description').value = '';
                document.getElementById('origin').value = '0';
                document.getElementById('origin').text = 'Please select';
                document.getElementById('total_qty').value = '';
                document.getElementById('remaining_qty').value = '';
                document.getElementById('value').value = '';
                document.getElementById('weight').value = '';
                document.getElementById('invoice_no').value = '';
            }
        </script>


        <?php
        if ($action == 'generate_report') {
        ?>

            <div class="card pb-3">
                <div class="card-header d-flex align-items-center">
                    <h5 class="mb-0"><?php echo $total_pages; ?> Found.</h5>

                    <div class="ms-auto">

                        <!-- <a href="export_pdf.php?<?php echo str_replace('listing_reports.php?', '', $targetpage); ?>&export=pdf" target="_blank">
							<button type="button" class="btn btn-info btn-labeled btn-labeled-start">
								<span class="btn-labeled-icon bg-black bg-opacity-20">
									<i class="ph-file-pdf"></i>
								</span>Export PDF
							</button>
						</a> -->

                        <!-- <a href="<?php //echo $targetpage; ?>&export=excel"> -->
                            <!-- <button type="button" class="btn btn-info btn-labeled btn-labeled-start">
                                <span class="btn-labeled-icon bg-black bg-opacity-20">
                                    <i class="ph-file-csv"></i>
                                </span>Export Excel
                            </button> -->
                        <!-- </a> -->

                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table">

                        <thead>
                            <tr>
                                <!-- <th width="100">&nbsp;</th> -->
                                <!-- <th width="150">QUOTATION #</th>
                                <th width="150">REQUESTED DATE</th>
                                <th>COMPANY NAME</th>
                                <th width="120">VAT (5%)</th>
                                <th width="120">GRAND TOTAL</th>
                                <th width="100">STATUS</th>
                                <th width="150"></th> -->

                                <th>HS CODE</th>
                                <th>DESCRIPTION</th>
                                <th>TOTAL QTY</th>
                                <th>REMAINING QTY</th>
                                <th>ORIGIN</th>
                                <th></th>
                            </tr>
                        </thead>


                        <tbody>

                            <?php

                            // ---------------------------------------------------------------------------------------
                            while ($row_shipping_advice_item = $result_shipping_advice_items->fetch_array(MYSQLI_ASSOC)) {

                                $id             = $row_shipping_advice_item["id"];
                                $advice_id      = $row_shipping_advice_item["advice_id"];
                                $invoice_date   = getTableAttr('invoice_no', DB::SHIPPING_ADVICES, $advice_id);
                                $hs_code        = $row_shipping_advice_item["hs_code"];
                                $description    = $row_shipping_advice_item["description"];
                                $qty            = $row_shipping_advice_item["qty"];
                                $origin         = $row_shipping_advice_item["origin"];
                                $value          = $row_shipping_advice_item["value"];
                                $weight         = $row_shipping_advice_item["weight"];
                                $created_at     = $row_shipping_advice_item["created_at"];

                                // Calculate Remaining QTY (same formula as shipping_stocks.php)
                                $remaining_qty = 0;
                                $rs         = $mysqli->query("SELECT sum(out_qty) FROM `" . DB::SHIPPING_STOCK_ITEMS . "` WHERE shipping_advice_item_id=$id");
                                $rw         = $rs->fetch_array();
                                $total_out_qty = (($rw[0] > 0) ? $rw[0] : 0);
                                $remaining_qty = $qty - $total_out_qty;

                                $invoice_no = getTableAttr('invoice_no', DB::SHIPPING_ADVICES, $advice_id);

                                // ---------------------------------------------------------------------------------------
                            ?>

                                <tr>
                                    <!-- <td><?php //echo $id;
                                                ?></a></td> -->
                                    <td><?php echo $hs_code; ?> </td>
                                    <td><?php echo $description; ?> </td>
                                    <td><?php echo $qty; ?> </td>
                                    <td><?php echo $remaining_qty; ?> </td>
                                    <td><?php echo $origin; ?> </td>
                                    <td></td>

                                </tr>


                            <?php
                            } //while
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
        } // endif
        ?></div>

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>