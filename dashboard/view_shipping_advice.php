<?php

use App\Core\DB;
use App\Core\Session;
include('admin_elements/admin_header.php');

$module             = 'shipping_advices';
$module_caption     = 'Shipping Advice';
$tbl_name = DB::SHIPPING_ADVICES;
$error_message      = '';
$success_message    = '';

require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

// Validate ID parameter
if (empty($id) || !is_numeric($id)) {
    $_SESSION[$project_pre]['DASHBOARD']['error_message'] = 'Invalid or missing shipping advice ID.';
    header("Location: listing_$module.php");
    exit();
}

// print_r($_REQUEST);

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


$customer_id = 0;
if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id     = e_s__($_REQUEST['customer_id']);
}


// ---------------------- Shipping Advice Items -----------------------------


$advice_hs_code_arr     = [];
$advice_description_arr = [];
$advice_qty_arr         = [];
$advice_origin_arr      = [];
$advice_value_arr       = [];
$advice_weight_arr      = [];


$total_advice_rows      = 1;
if (isset($_POST['total_advice_rows']) && !empty($_POST['total_advice_rows'])) {
    $total_advice_rows      = e_s__($_POST['total_advice_rows']);
}

if ($action == "add_$module") {

    for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) {

        $index = $shipping_advice_item;
        $index = $index - 1;

        // Assuming you're looping through posted items using $index
        $advice_hs_code      = (isset($_POST['advice_hs_code'][$index]) && !empty($_POST['advice_hs_code'][$index]) ? $_POST['advice_hs_code'][$index] : '');
        $advice_description  = (isset($_POST['advice_description'][$index]) && !empty($_POST['advice_description'][$index]) ? $_POST['advice_description'][$index] : '');
        $advice_qty          = (isset($_POST['advice_qty'][$index]) && !empty($_POST['advice_qty'][$index]) ? $_POST['advice_qty'][$index] : 1);
        $advice_origin       = (isset($_POST['advice_origin'][$index]) && !empty($_POST['advice_origin'][$index]) ? $_POST['advice_origin'][$index] : '');
        $advice_value        = (isset($_POST['advice_value'][$index]) && !empty($_POST['advice_value'][$index]) ? $_POST['advice_value'][$index] : 0);
        $advice_weight       = (isset($_POST['advice_weight'][$index]) && !empty($_POST['advice_weight'][$index]) ? $_POST['advice_weight'][$index] : 0);

        // Push sanitized values into arrays
        array_push($advice_hs_code_arr,     e_s__($advice_hs_code));
        array_push($advice_description_arr, e_s__($advice_description));
        array_push($advice_qty_arr,         e_s__($advice_qty));
        array_push($advice_origin_arr,      e_s__($advice_origin));
        array_push($advice_value_arr,       e_s__($advice_value));
        array_push($advice_weight_arr,      e_s__($advice_weight));
    } //for 
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
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0"><?php if (($action == "edit_$module" || $action == "update_$module" || $action == "change_password") && !empty($id)) { ?>Edit<?php } else { ?>New<?php } ?> <?php echo $module_caption; ?></h5>
            </div>

            <div class="my-1">
                
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="import_<?php echo $module; ?>.php" enctype="multipart/form-data">

        <input type="hidden" name="action" id="action" value="" />

        <!-- Page header -->


                <?php
                /*
                |--------------------------------------------------------------------------
                | EDIT - ONLY SUPERADMIN or RELEVANT USER
                |--------------------------------------------------------------------------
                |
                */
                $created_by = getTableAttr('created_by', DB::SHIPPING_ADVICES, $id);

                $customer_name = $customer_address = $incoterm = $transport_mode = '';
                $exit_point = $destination_port = $shipment_type = '';
                $invoice_date = $invoice_no = $awb_no = $license_no = $mirsal_II_code = '';
                $country_of_origin = $currency = $payment_method = '';
                $grand_advice_value = $grand_advice_weight = $grand_advice_qty = '';
                $total_invoice_rows = 0;
                $invoice_pkgs = $invoice_pkgs_unit = $invoice_weight = $invoice_weight_unit = '';
                $invoice_grand_qty = $invoice_grand_total_amount = '';

                if (
                    (!empty($id) && Session::roleId() == '1')
                    ||
                    (!empty($id) && $_SESSION[$project_pre]['DASHBOARD']['admin_id'] == $created_by)
                ) {

                    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
                    $row = $result->fetch_array();

                    // Redirect if record not found
                    if (!$row) {
                        $_SESSION[$project_pre]['DASHBOARD']['error_message'] = 'Shipping advice not found or has been deleted.';
                        header("Location: listing_$module.php");
                        exit();
                    }

                    if ($row) {
                        $shipment_type        = s__($row['shipment_type']);
                        $destination_port     = s__($row['destination_port']);
                        $exit_point           = s__($row['exit_point']);
                        $transport_mode       = s__($row['transport_mode']);
                        $incoterm             = s__($row['incoterm']);

                        $invoice_date         = s__($row['invoice_date']);
                        $invoice_no           = s__($row['invoice_no']);
                        $awb_no               = s__($row['awb_no']);
                        $license_no           = s__($row['license_no']);
                        $mirsal_II_code       = s__($row['mirsal_II_code']);

                        // $customer_id        = s__($row['customer_id']);
                        $customer_id          = '';
                        $customer_name        = '';
                        $customer_address     = '';

                        $country_of_origin    = s__($row['country_of_origin']);
                        $grand_advice_qty     = s__($row['grand_advice_qty']);
                        $grand_advice_weight  = s__($row['grand_advice_weight']);
                        $currency             = s__($row['currency']);
                        $grand_advice_value   = s__($row['grand_advice_value']);
                        $payment_method       = s__($row['payment_method']);

                        $invoice_pkgs               = s__($row['invoice_pkgs']);
                        $invoice_pkgs_unit          = s__($row['invoice_pkgs_unit']);
                        $invoice_weight             = s__($row['invoice_weight']);
                        $invoice_weight_unit        = s__($row['invoice_weight_unit']);
                        $invoice_grand_qty          = s__($row['invoice_grand_qty']);
                        $invoice_grand_total_amount = s__($row['invoice_grand_total_amount']);
                        $publish                    = s__($row['is_active'] ?? 0);
                    } else {
                        // Initialize with default values if no row found
                        $shipment_type        = '';
                        $destination_port     = '';
                        $exit_point           = '';
                        $transport_mode       = '';
                        $incoterm             = '';

                        $invoice_date         = '';
                        $invoice_no           = '';
                        $awb_no               = '';
                        $license_no           = '';
                        $mirsal_II_code       = '';

                        $customer_id          = '';
                        $customer_name        = '';
                        $customer_address     = '';

                        $country_of_origin    = '';
                        $grand_advice_qty     = 0;
                        $grand_advice_weight  = 0;
                        $currency             = '';
                        $grand_advice_value   = 0;
                        $payment_method       = '';

                        $invoice_pkgs               = 0;
                        $invoice_pkgs_unit          = '';
                        $invoice_weight             = 0;
                        $invoice_weight_unit        = '';
                        $invoice_grand_qty          = 0;
                        $invoice_grand_total_amount = 0;
                        $publish                    = '';
                    }

                    $invoice_date = processDateYtoD($invoice_date);

                    // ------------------ SHIPPING ADVICE ITEMS ------------------
                    $result_shipping_advice_items   = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE advice_id=$id");
                    $total_advice_rows              = $result_shipping_advice_items->num_rows;

                    $advice_item_id_arr        = [];
                    $advice_hs_code_arr        = [];
                    $advice_description_arr    = [];
                    $advice_qty_arr            = [];
                    $advice_origin_arr         = [];
                    $advice_value_arr          = [];
                    $advice_weight_arr         = [];

                    if ($total_advice_rows > 0) {
                        while ($row_shipping_advice_items = $result_shipping_advice_items->fetch_array()) {

                            array_push($advice_item_id_arr,             $row_shipping_advice_items['id']);
                            array_push($advice_hs_code_arr,             $row_shipping_advice_items['hs_code']);
                            array_push($advice_description_arr,         $row_shipping_advice_items['description']);
                            array_push($advice_qty_arr,                 $row_shipping_advice_items['qty']);
                            array_push($advice_origin_arr,              $row_shipping_advice_items['origin']);
                            array_push($advice_value_arr,               $row_shipping_advice_items['value']);
                            array_push($advice_weight_arr,              $row_shipping_advice_items['weight']);
                        } // while
                    } // if
                } //if


                /*
                |--------------------------------------------------------------------------
                | DELIVERY ADVICE - SUMMARY
                |--------------------------------------------------------------------------
                |
                */

                ?>

                <div class="card card-body bg-success">
                    <div class="text-center text-lg-start">
                        <span class="mb-0 text-white">Delivery Advice</span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">

                            <div class="card-body">

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Customs Bill Type: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $shipment_type; ?>

                                        <input type="hidden" class="form-control" name="shipment_type" id="shipment_type" value="<?php echo $shipment_type; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Destination: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $destination_port; ?>

                                        <input type="hidden" class="form-control" name="destination_port" id="destination_port" value="<?php echo $destination_port; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Exit Point: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $exit_point; ?>

                                        <input type="hidden" class="form-control" name="exit_point" id="exit_point" value="<?php echo $exit_point; ?>">
                                    </div>
                                </div>


                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Mode: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $transport_mode; ?>

                                        <input type="hidden" class="form-control" name="transport_mode" id="transport_mode" value="<?php echo $transport_mode; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Shipment Terms: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $incoterm; ?>

                                        <input type="hidden" class="form-control" name="incoterm" id="incoterm" value="<?php echo $incoterm; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Customer Name: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $customer_name; ?>

                                        <input type="hidden" class="form-control" name="customer_name" id="customer_name" value="<?php echo $customer_name; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Address: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $customer_address; ?>

                                        <input type="hidden" class="form-control" name="customer_address" id="customer_address" value="<?php echo $customer_address; ?>">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>



                    <div class="col-lg-6">
                        <div class="card">

                            <div class="card-body">

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Invoice Date: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $invoice_date; ?>

                                        <input type="hidden" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Invoice no: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $invoice_no; ?>

                                        <input type="hidden" class="form-control" name="invoice_no" id="invoice_no" value="<?php echo $invoice_no; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">AWB No: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $awb_no; ?>

                                        <input type="hidden" class="form-control" name="awb_no" id="awb_no" value="<?php echo $awb_no; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">License No: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $license_no; ?>

                                        <input type="hidden" class="form-control" name="license_no" id="license_no" value="<?php echo $license_no; ?>">
                                    </div>
                                </div>

                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Mirsal II Code: </label>
                                    <div class="col-lg-9 mt-2">
                                        <?php echo $mirsal_II_code; ?>

                                        <input type="hidden" class="form-control" name="mirsal_II_code" id="mirsal_II_code" value="<?php echo $mirsal_II_code; ?>">
                                    </div>
                                </div>

                            </div>


                        </div>
                    </div>
                </div>




                <div class="col-xl-12">

                    <div class="row">

                        <div class="col-xl-12">

                            <div class="row mb-2">

                                <div class="col-lg-1">
                                    <label class="form-label ms-3 fw-semibold">HS Code: </label>
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label ms-3 fw-semibold">Description of Goods: </label>
                                </div>

                                <div class="col-lg-1">
                                    <label class="form-label fw-semibold">QTY: </label>
                                </div>

                                <div class="col-lg-1">
                                    <label class="form-label fw-semibold">Origin: </label>
                                </div>

                                <div class="col-lg-1 text-end">
                                    <label class="form-label fw-semibold">VALUE: </label>
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label ms-4 fw-semibold">Weigth (Kg): </label>
                                </div>

                            </div>

                            <div class="card">

                                <div class="row card-body">

                                    <div class="col-lg-12">

                                        <?php

                                        $sr = 1;
                                        // ----------------------------------------------------------------------------
                                        for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) {
                                            $index = $shipping_advice_item;
                                            $index = $index - 1;


                                            if (!empty($advice_hs_code_arr[$index]) && !empty($advice_description_arr[$index]) && !empty($advice_qty_arr[$index]) && !empty($advice_origin_arr[$index]) && !empty($advice_value_arr[$index]) && !empty($advice_weight_arr[$index])) {


                                                // $total_advice_rows++;

                                                // array_push($advice_hs_code_arr,            $advice_hs_code);
                                                // array_push($advice_description_arr,        $advice_description);
                                                // array_push($advice_qty_arr,                $advice_qty);
                                                // array_push($advice_origin_arr,             $advice_origin);
                                                // array_push($advice_value_arr,              $advice_value);
                                                // array_push($advice_weight_arr,             $advice_weight);

                                                // ----------------------------------------------------------------------------
                                        ?>

                                                <div class="mb-2">
                                                    <div class="row mb-1" id="row_<?php echo $shipping_advice_item; ?>">


                                                        <div class="col-lg-12">
                                                            <div class="row">

                                                                <input type="hidden" name="advice_item_id[]" id="advice_item_id<?php echo $shipping_advice_item; ?>" value="<?php echo (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''); ?>">

                                                                <div class="col-lg-1">
                                                                    <?php echo (!empty($advice_hs_code_arr[$index]) ? $advice_hs_code_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control" name="advice_hs_code[]" id="advice_hs_code<?php echo $shipping_advice_item; ?>" value="<?php echo (!empty($advice_hs_code_arr[$index]) ? $advice_hs_code_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-3">
                                                                    <?php echo (!empty($advice_description_arr[$index]) ? $advice_description_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control" name="advice_description[]" id="advice_description<?php echo $shipping_advice_item; ?>" class="form-control" value="<?php echo (!empty($advice_description_arr[$index]) ? $advice_description_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-1">
                                                                    <?php echo (!empty($advice_qty_arr[$index]) ? $advice_qty_arr[$index] : '0'); ?>

                                                                    <input type="hidden" step="1" name="advice_qty[]" id="advice_qty<?php echo $shipping_advice_item; ?>" min="0" class="form-control" value="<?php echo (!empty($advice_qty_arr[$index]) ? $advice_qty_arr[$index] : '0'); ?>"> <!--  step="0.1" value="0.0" -->
                                                                </div>


                                                                <div class="col-lg-1">
                                                                    <?php
                                                                    $alpha2_code = (!empty($advice_origin_arr[$index]) ? $advice_origin_arr[$index] : '');
                                                                    echo $alpha2_code;
                                                                    ?>

                                                                    <input type="hidden" class="form-control" name="advice_origin[]" id="advice_origin<?php echo $shipping_advice_item; ?>" class="form-control" value="<?php echo (!empty($advice_origin_arr[$index]) ? $advice_origin_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-1 text-end">
                                                                    <?php echo (!empty($advice_value_arr[$index]) ? $advice_value_arr[$index] : '0'); ?>

                                                                    <input type="hidden" step="1" name="advice_value[]" id="advice_value<?php echo $shipping_advice_item; ?>" min="0" class="form-control" value="<?php echo (!empty($advice_value_arr[$index]) ? $advice_value_arr[$index] : '0'); ?>"> <!--  step="0.1" value="0.0" -->
                                                                </div>

                                                                <div class="col-lg-1 text-end">
                                                                    <?php echo (!empty($advice_weight_arr[$index]) ? $advice_weight_arr[$index] : '0'); ?>

                                                                    <input type="hidden" step="1" name="advice_weight[]" id="advice_weight<?php echo $shipping_advice_item; ?>" min="0" class="form-control" value="<?php echo (!empty($advice_weight_arr[$index]) ? $advice_weight_arr[$index] : '0'); ?>"> <!--  step="0.1" value="0.0" -->
                                                                </div>


                                                            </div>
                                                        </div>


                                                    </div>

                                                </div>

                                        <?php
                                            } // if 
                                            // -------------------------------------------------- 
                                        } // for 
                                        // -------------------------------------------------- 
                                        ?>
                                        <input type="hidden" name="total_advice_rows" id="total_advice_rows" value="<?php echo $total_advice_rows; ?>">


                                        <div class="mb-2">
                                            <div class="row">


                                                <div class="col-lg-12">
                                                    <div class="row">


                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-2 fw-semibold"> GRAND TOTAL </div>

                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-1 fw-semibold">
                                                            <?php echo $grand_advice_qty; ?>

                                                            <input type="hidden" name="grand_advice_qty" id="grand_advice_qty" min="0" class="form-control text-center" value="<?php echo $grand_advice_qty; ?>">
                                                        </div>

                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-1 text-end fw-semibold">
                                                            <?php echo $grand_advice_value; ?>

                                                            <input type="hidden" name="grand_advice_value" id="grand_advice_value" min="0" class="form-control" value="<?php echo $grand_advice_value; ?>">
                                                        </div>


                                                        <div class="col-lg-1 text-end fw-semibold">
                                                            <?php echo $grand_advice_weight; ?>

                                                            <input type="hidden" name="grand_advice_weight" id="grand_advice_weight" min="0" class="form-control" value="<?php echo $grand_advice_weight; ?>">
                                                        </div>


                                                    </div>
                                                </div>


                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>




                    </div>

                    <div class="row">
                        <!-- Box 1 -->
                        <div class="col-lg-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Country of Origin: </label>
                                        <div class="col-lg-6 mt-2 text-center fw-semibold">
                                            <?php echo $country_of_origin; ?>

                                            <input type="hidden" class="form-control text-center" name="country_of_origin" id="country_of_origin" value="<?php echo $country_of_origin; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-2">&nbsp;</div>

                        <div class="col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-6 col-form-label">Gross Weight No. </label>
                                        <div class="col-lg-6 mt-2 text-end fw-semibold">
                                            <?php echo $grand_advice_weight; ?>

                                            <input type="hidden" class="form-control" name="grand_advice_weight" id="grand_advice_weight" value="<?php echo $grand_advice_weight; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-6 col-form-label">Currency </label>
                                        <div class="col-lg-6 mt-2 text-end fw-semibold">
                                            <?php echo $currency; ?>

                                            <input type="hidden" class="form-control" name="currency" id="currency" value="<?php echo $currency; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-6 col-form-label">Total Value </label>
                                        <div class="col-lg-6 mt-2 text-end fw-semibold">
                                            <?php echo $grand_advice_value; ?>

                                            <input type="hidden" class="form-control" name="grand_advice_value" id="grand_advice_value" value="<?php echo $grand_advice_value; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-6 col-form-label">Payment Method </label>
                                        <div class="col-lg-6 mt-2 text-end fw-semibold">
                                            <?php echo $payment_method; ?>

                                            <input type="hidden" class="form-control" name="payment_method" id="payment_method" value="<?php echo $payment_method; ?>">
                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>
                    </div>


                </div>









                <?php
                /*
                |--------------------------------------------------------------------------
                | EDIT - ONLY SUPERADMIN or RELEVANT USER
                |--------------------------------------------------------------------------
                |
                */
                $created_by = getTableAttr('created_by', DB::SHIPPING_ADVICES, $id);

                if (
                    (!empty($id) && Session::roleId() == '1')
                    ||
                    (!empty($id) && $_SESSION[$project_pre]['DASHBOARD']['admin_id'] == $created_by)
                ) {

                    $result = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE advice_id=$id");
                    $row = $result->fetch_array();


                    // ------------------ SHIPPING INVOICE ITEMS ------------------
                    $result_shipping_invoice_items      = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE advice_id=$id");
                    $total_invoice_rows                 = $result_shipping_invoice_items->num_rows;

                    $serial_no_arr      = [];
                    $description_arr    = [];
                    $origin_arr         = [];
                    $declaration_no_arr = [];
                    $hs_code_arr        = [];
                    $qty_arr            = [];
                    $unit_price_arr     = [];
                    $total_amount_arr   = [];


                    if ($total_invoice_rows > 0) {
                        while ($row_shipping_invoice_items = $result_shipping_invoice_items->fetch_array()) {

                            array_push($serial_no_arr,      $row_shipping_invoice_items['serial_no']);
                            array_push($description_arr,    $row_shipping_invoice_items['description']);
                            array_push($origin_arr,         $row_shipping_invoice_items['origin']);
                            array_push($declaration_no_arr, $row_shipping_invoice_items['declaration_no']);
                            array_push($hs_code_arr,        $row_shipping_invoice_items['hs_code']);
                            array_push($qty_arr,            $row_shipping_invoice_items['qty']);
                            array_push($unit_price_arr,     $row_shipping_invoice_items['unit_price']);
                            array_push($total_amount_arr,   $row_shipping_invoice_items['total_amount']);
                        } // while
                    } // if
                } //if


                /*
                |--------------------------------------------------------------------------
                | PROCESS DELIVERY INVOICE - DETAILS
                |--------------------------------------------------------------------------
                |
                */
                ?>


                <div class="card card-body bg-success mt-4">
                    <div class="text-center text-lg-start">
                        <span class="mb-0 text-white">Invoice Details</span>
                    </div>
                </div>


                <div class="col-xl-12">

                    <div class="row">

                        <div class="col-xl-12">

                            <div class="row mb-2">

                                <div class="col-lg-1">
                                    <label class="form-label ms-3 fw-semibold">Serial: </label>
                                </div>

                                <div class="col-lg-3">
                                    <label class="form-label ms-3 fw-semibold">Description: </label>
                                </div>

                                <div class="col-lg-1 text-center">
                                    <label class="form-label ms-3 fw-semibold">Origin: </label>
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label fw-semibold">Declaration No: </label>
                                </div>

                                <div class="col-lg-1">
                                    <label class="form-label fw-semibold">HS Code: </label>
                                </div>

                                <div class="col-lg-1">
                                    <label class="form-label ms-4 fw-semibold">Qty: </label>
                                </div>

                                <div class="col-lg-1">
                                    <label class="form-label fw-semibold">Unit Price: </label>
                                </div>

                                <div class="col-lg-2">
                                    <label class="form-label ms-2 fw-semibold">Total: </label>
                                </div>

                            </div>

                            <div class="card">

                                <div class="row card-body">

                                    <div class="col-lg-12">

                                        <?php

                                        $sr = 1;
                                        // ----------------------------------------------------------------------------
                                        // for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_rows; $shipping_invoice_item++) {
                                        for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_invoice_rows; $shipping_invoice_item++) {
                                            $index = $shipping_invoice_item;
                                            $index = $index - 1;


                                            if (!empty($description_arr[$index]) && !empty($origin_arr[$index]) && !empty($declaration_no_arr[$index]) && !empty($hs_code_arr[$index]) && !empty($qty_arr[$index]) && !empty($unit_price_arr[$index]) && !empty($total_amount_arr[$index])) {


                                                // $total_invoice_rows++;
                                                // array_push($serial_no_arr,          $serial_no);
                                                // array_push($description_arr,        $description);
                                                // array_push($origin_arr,             $origin);
                                                // array_push($declaration_no_arr,     $declaration_no);
                                                // array_push($hs_code_arr,            $hs_code);
                                                // array_push($qty_arr,                $qty);
                                                // array_push($unit_price_arr,         $unit_price);
                                                // array_push($total_amount_arr,       $total_amount);

                                                // ----------------------------------------------------------------------------
                                        ?>

                                                <div class="mb-2">
                                                    <div class="row mb-1" id="row_<?php echo $shipping_invoice_item; ?>">


                                                        <div class="col-lg-12">
                                                            <div class="row">

                                                                <input type="hidden" name="item_id[]" id="item_id<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''); ?>">

                                                                <div class="col-lg-1 text-center">
                                                                    <?php echo (!empty($serial_no_arr[$index]) ? $serial_no_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control text-center" name="serial_no[]" id="serial_no<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($serial_no_arr[$index]) ? $serial_no_arr[$index] : ''); ?>">
                                                                </div>


                                                                <div class="col-lg-3">
                                                                    <?php echo (!empty($description_arr[$index]) ? $description_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control" name="description[]" id="description<?php echo $shipping_invoice_item; ?>" class="form-control" value="<?php echo (!empty($description_arr[$index]) ? $description_arr[$index] : ''); ?>">
                                                                </div>



                                                                <div class="col-lg-1 text-center">
                                                                    <?php
                                                                    $alpha2_code = (!empty($origin_arr[$index]) ? $origin_arr[$index] : '');
                                                                    echo $alpha2_code;
                                                                    ?>

                                                                    <input type="hidden" class="form-control" name="origin[]" id="origin<?php echo $shipping_invoice_item; ?>" class="form-control" value="<?php echo (!empty($origin_arr[$index]) ? $origin_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-2">
                                                                    <?php echo (!empty($declaration_no_arr[$index]) ? $declaration_no_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control" name="declaration_no[]" id="declaration_no<?php echo $shipping_invoice_item; ?>" class="form-control" placeholder="Declaration no" value="<?php echo (!empty($declaration_no_arr[$index]) ? $declaration_no_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-1">
                                                                    <?php echo (!empty($hs_code_arr[$index]) ? $hs_code_arr[$index] : ''); ?>

                                                                    <input type="hidden" class="form-control" name="hs_code[]" id="hs_code<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($hs_code_arr[$index]) ? $hs_code_arr[$index] : ''); ?>">
                                                                </div>

                                                                <div class="col-lg-1 text-center">
                                                                    <?php echo (!empty($qty_arr[$index]) ? $qty_arr[$index] : '0'); ?>

                                                                    <input type="hidden" step="1" name="qty[]" id="qty<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($qty_arr[$index]) ? $qty_arr[$index] : '0'); ?>"> <!--  step="0.1" value="0.0" -->
                                                                </div>


                                                                <div class="col-lg-1 text-center">
                                                                    <?php echo (!empty($unit_price_arr[$index]) ? $unit_price_arr[$index] : '0'); ?>

                                                                    <input type="hidden" step="1" name="unit_price[]" id="unit_price<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($unit_price_arr[$index]) ? $unit_price_arr[$index] : '0'); ?>" onkeyup="calculateItemAmount('<?php echo $shipping_invoice_item; ?>');" onchange=" calculateItemAmount('<?php echo $shipping_invoice_item; ?>');"> <!--  step="0.1" value="0.0" -->
                                                                </div>

                                                                <div class="col-lg-1 text-center">
                                                                    <?php echo (!empty($total_amount_arr[$index]) ? $total_amount_arr[$index] : ''); ?>

                                                                    <input type="hidden" name="total_amount[]" id="total_amount<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-end" placeholder="0" value="<?php echo (!empty($total_amount_arr[$index]) ? $total_amount_arr[$index] : ''); ?>" onchange="calculateGrand(<?php echo $shipping_invoice_item; ?>);" onkeyup="calculateGrand(<?php echo $shipping_invoice_item; ?>);"> <!--  oninput="this.value = Math.abs(this.value)" -->
                                                                </div>

                                                            </div>
                                                        </div>


                                                    </div>

                                                </div>

                                        <?php
                                            } // if 
                                            // -------------------------------------------------- 
                                        } // for 
                                        // -------------------------------------------------- 
                                        ?>
                                        <input type="hidden" name="total_invoice_rows" id="total_invoice_rows" value="<?php echo $total_invoice_rows; ?>">


                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3">


                                                <div class="col-lg-12">
                                                    <div class="row">


                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-2 fw-semibold"> GRAND TOTAL </div>

                                                        <div class="col-lg-2"></div>

                                                        <div class="col-lg-2"></div>

                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-1 text-center fw-semibold">
                                                            <?php echo $invoice_grand_qty; ?>
                                                        </div>

                                                        <div class="col-lg-1"></div>

                                                        <div class="col-lg-1 text-center fw-semibold">
                                                            <?php echo $invoice_grand_total_amount; ?>
                                                        </div>


                                                    </div>
                                                </div>


                                            </div>

                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>


                        <div class="row">

                            <div class="col-lg-4">

                                <div class="card">

                                    <div class="card-body">

                                        <div class="row mb-2">
                                            <label class="col-lg-4 col-form-label">PLT/BOX/PKG's: </label>

                                            <div class="col-lg-6 mt-2 text-center fw-semibold">
                                                <?php echo $invoice_pkgs; ?>
                                            </div>

                                            <div class="col-lg-2 mt-2">
                                                <span class="form-text text-muted"><?php echo $invoice_pkgs_unit; ?></span>
                                            </div>

                                        </div>

                                        <div class="row mb-2">
                                            <label class="col-lg-4 col-form-label">WEIGHT: </label>
                                            <div class="col-lg-6 mt-2 text-center fw-semibold">
                                                <?php echo $invoice_weight; ?>
                                            </div>

                                            <div class="col-lg-2 mt-2">
                                                <span class="form-text text-muted"><?php echo $invoice_weight_unit; ?></span>
                                            </div>
                                        </div>


                                        <div class="row">
                                            <label class="col-lg-4 col-form-label">AWB: </label>
                                            <div class="col-lg-6 mt-2 text-center fw-semibold">
                                                <?php echo $awb_no; ?>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>

                        </div>


                    </div>
                </div>


            </div>


            <?php include('admin_elements/copyright.php'); ?>
        </div>
    </form>
</div>


<?php include('admin_elements/admin_footer.php'); ?>