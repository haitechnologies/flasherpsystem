<?php
use App\Core\DB;
include('admin_elements/admin_header.php');

$module             = 'shipping_advices';
$GLOBALS['module']  = $module;
$module_caption     = 'Shipping Advice';
$tbl_name           = DB::SHIPPING_ADVICES;
$error_message      = '';
$success_message    = '';

require_once '../vendor/autoload.php';
require_once 'helpers/shipping_customer_helper.php';

include('admin_elements/permissions.php');

$customer_id = 0;
if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id = e_s__($_REQUEST['customer_id']);
}

// ---------------------- Shipping Advice Items -----------------------------
$advice_hs_code_arr     = [];
$advice_description_arr = [];
$advice_qty_arr         = [];
$advice_origin_arr      = [];
$advice_value_arr       = [];
$advice_weight_arr      = [];

$total_advice_rows = 1;
if (isset($_POST['total_advice_rows']) && !empty($_POST['total_advice_rows'])) {
    $total_advice_rows = e_s__($_POST['total_advice_rows']);
}

// ---------------------- Shipping Invoice Items -----------------------------
$invoice_serial_no_arr      = [];
$invoice_description_arr    = [];
$invoice_origin_arr         = [];
$invoice_declaration_no_arr = [];
$invoice_hs_code_arr        = [];
$invoice_qty_arr            = [];
$invoice_unit_price_arr     = [];
$invoice_total_amount_arr   = [];

$total_invoice_rows = 1;
if (isset($_POST['total_invoice_rows']) && !empty($_POST['total_invoice_rows'])) {
    $total_invoice_rows = e_s__($_POST['total_invoice_rows']);
}

if ($action == "add_$module") {

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
    } elseif (!granted('create', $module_id)) {
        $error_message = 'You do not have permission to create Shipping Advice.';
    } else {

        // ---------------------- Shipping Advice Items -----------------------------
        for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) {

            $index = $shipping_advice_item - 1;

            $advice_hs_code      = (isset($_POST['advice_hs_code'][$index]) && !empty($_POST['advice_hs_code'][$index]) ? $_POST['advice_hs_code'][$index] : '');
            $advice_description  = (isset($_POST['advice_description'][$index]) && !empty($_POST['advice_description'][$index]) ? $_POST['advice_description'][$index] : '');
            $advice_qty          = (isset($_POST['advice_qty'][$index]) && !empty($_POST['advice_qty'][$index]) ? $_POST['advice_qty'][$index] : 1);
            $advice_origin       = (isset($_POST['advice_origin'][$index]) && !empty($_POST['advice_origin'][$index]) ? $_POST['advice_origin'][$index] : '');
            $advice_value        = (isset($_POST['advice_value'][$index]) && !empty($_POST['advice_value'][$index]) ? $_POST['advice_value'][$index] : 0);
            $advice_weight       = (isset($_POST['advice_weight'][$index]) && !empty($_POST['advice_weight'][$index]) ? $_POST['advice_weight'][$index] : 0);

            array_push($advice_hs_code_arr,     e_s__($advice_hs_code));
            array_push($advice_description_arr, e_s__($advice_description));
            array_push($advice_qty_arr,         e_s__($advice_qty));
            array_push($advice_origin_arr,      e_s__($advice_origin));
            array_push($advice_value_arr,       e_s__($advice_value));
            array_push($advice_weight_arr,      e_s__($advice_weight));
        }

        // ---------------------- Shipping Invoice Items -----------------------------
        for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_invoice_rows; $shipping_invoice_item++) {

            $index = $shipping_invoice_item - 1;

            $invoice_serial_no      = (isset($_POST['invoice_serial_no'][$index]) && !empty($_POST['invoice_serial_no'][$index]) ? $_POST['invoice_serial_no'][$index] : '');
            $invoice_description    = (isset($_POST['invoice_description'][$index]) && !empty($_POST['invoice_description'][$index]) ? $_POST['invoice_description'][$index] : '');
            $invoice_origin         = (isset($_POST['invoice_origin'][$index]) && !empty($_POST['invoice_origin'][$index]) ? $_POST['invoice_origin'][$index] : '');
            $invoice_declaration_no = (isset($_POST['invoice_declaration_no'][$index]) && !empty($_POST['invoice_declaration_no'][$index]) ? $_POST['invoice_declaration_no'][$index] : '');
            $invoice_hs_code        = (isset($_POST['invoice_hs_code'][$index]) && !empty($_POST['invoice_hs_code'][$index]) ? $_POST['invoice_hs_code'][$index] : '');
            $invoice_qty            = (isset($_POST['invoice_qty'][$index]) && !empty($_POST['invoice_qty'][$index]) ? $_POST['invoice_qty'][$index] : 1);
            $invoice_unit_price     = (isset($_POST['invoice_unit_price'][$index]) && !empty($_POST['invoice_unit_price'][$index]) ? $_POST['invoice_unit_price'][$index] : 0);
            $invoice_total_amount   = (isset($_POST['invoice_total_amount'][$index]) && !empty($_POST['invoice_total_amount'][$index]) ? $_POST['invoice_total_amount'][$index] : 0);

            array_push($invoice_serial_no_arr,      e_s__($invoice_serial_no));
            array_push($invoice_description_arr,    e_s__($invoice_description));
            array_push($invoice_origin_arr,         e_s__($invoice_origin));
            array_push($invoice_declaration_no_arr, e_s__($invoice_declaration_no));
            array_push($invoice_hs_code_arr,        e_s__($invoice_hs_code));
            array_push($invoice_qty_arr,            e_s__($invoice_qty));
            array_push($invoice_unit_price_arr,     e_s__($invoice_unit_price));
            array_push($invoice_total_amount_arr,   e_s__($invoice_total_amount));
        }

        $shipment_type        = e_s__($_POST['shipment_type'] ?? '');
        $destination_port     = e_s__($_POST['destination_port'] ?? '');
        $exit_point           = e_s__($_POST['exit_point'] ?? '');
        $transport_mode       = e_s__($_POST['transport_mode'] ?? '');
        $incoterm             = e_s__($_POST['incoterm'] ?? '');

        $invoice_date         = e_s__($_POST['invoice_date'] ?? '');
        $invoice_no           = e_s__($_POST['invoice_no'] ?? '');
        $awb_no               = e_s__($_POST['awb_no'] ?? '');
        $license_no           = e_s__($_POST['license_no'] ?? '');
        $mirsal_II_code       = e_s__($_POST['mirsal_II_code'] ?? '');

        $customer_id          = (int)($_POST['customer_id'] ?? 0);

        $country_of_origin    = e_s__($_POST['country_of_origin'] ?? '');
        $grand_advice_qty     = e_s__($_POST['grand_advice_qty'] ?? '');
        $grand_advice_weight  = e_s__($_POST['grand_advice_weight'] ?? '');
        $currency             = e_s__($_POST['currency'] ?? '');
        $grand_advice_value   = e_s__($_POST['grand_advice_value'] ?? '');
        $payment_method       = e_s__($_POST['payment_method'] ?? '');

        $invoice_pkgs               = e_s__($_POST['invoice_pkgs'] ?? '');
        $invoice_pkgs_unit          = e_s__($_POST['invoice_pkgs_unit'] ?? '');
        $invoice_weight             = e_s__($_POST['invoice_weight'] ?? '');
        $invoice_weight_unit        = e_s__($_POST['invoice_weight_unit'] ?? '');
        $invoice_grand_qty          = e_s__($_POST['invoice_grand_qty'] ?? '');
        $invoice_grand_total_amount = e_s__($_POST['invoice_grand_total_amount'] ?? '');

        // CHECK FOR DUPLICATE INVOICE NO
        $rs_invoice_no = $mysqli->query("SELECT id FROM `" . DB::SHIPPING_ADVICES . "` WHERE invoice_no = '" . $invoice_no . "' ");

        if ($rs_invoice_no->num_rows > 0) {
            $duplicate_id = getTableAttrV('id', DB::SHIPPING_ADVICES, " invoice_no = '" . $invoice_no . "' ");
            $error_message = 'Duplicate Invoice. The Same Invoice # <a href="view_shipping_advice.php?id=' . $duplicate_id . '">' . $invoice_no . '</a> already exists in the System.';
        } else if (empty($invoice_date)) {
            $error_message = 'Please select Invoice Date.';
        } else if (empty($shipment_type) || $shipment_type == 'Please select') {
            $error_message = 'Please select Shipment Type.';
        } else if (empty($destination_port) || $destination_port == 'Please select') {
            $error_message = 'Please select Destination Port.';
        } else if (empty($exit_point) || $exit_point == 'Please select') {
            $error_message = 'Please select Exit Point.';
        } else if (empty($transport_mode) || $transport_mode == 'Please select') {
            $error_message = 'Please select Transport Mode.';
        } else if (empty($incoterm) || $incoterm == 'Please select') {
            $error_message = 'Please select Incoterm.';
        } else if (empty($invoice_no)) {
            $error_message = 'Please enter Invoice Number.';
        } else if (empty($awb_no)) {
            $error_message = 'Please enter AWB Number.';
        } else if (empty($license_no)) {
            $error_message = 'Please enter License Number.';
        } else if (empty($mirsal_II_code)) {
            $error_message = 'Please enter Mirsal II Code.';
        } else if (empty($country_of_origin) || $country_of_origin == 'Please select') {
            $error_message = 'Please select Country of Origin.';
        } else if (empty($grand_advice_qty)) {
            $error_message = 'Please enter Grand Advice Quantity.';
        } else if (empty($grand_advice_weight)) {
            $error_message = 'Please enter Grand Advice Weight.';
        } else if (empty($currency) || $currency == 'Please select') {
            $error_message = 'Please select Currency.';
        } else if (empty($grand_advice_value)) {
            $error_message = 'Please enter Grand Advice Value.';
        } else if (empty($payment_method) || $payment_method == 'Please select') {
            $error_message = 'Please select Payment Method.';
        } else {

            // -- PROCESS ITEMS
            try {
            if ($total_advice_rows > 0) {

                $inserted_row = 0;

                for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) {

                    $index = $shipping_advice_item - 1;

                    $item_advice_hs_code        = $advice_hs_code_arr[$index];
                    $item_advice_description    = $advice_description_arr[$index];
                    $item_advice_qty            = $advice_qty_arr[$index];
                    $item_advice_origin         = $advice_origin_arr[$index];
                    $item_advice_value          = $advice_value_arr[$index];
                    $item_advice_weight         = $advice_weight_arr[$index];

                    if (!empty($item_advice_hs_code) && !empty($item_advice_description) && !empty($item_advice_qty) && !empty($item_advice_origin) && !empty($item_advice_value) && !empty($item_advice_weight)) {

                        // SAVE SHIPPING ADVICE
                        if ($inserted_row == 0) {

                            $invoice_date = processDateDtoY($invoice_date);

                            $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_ADVICES . "`(shipment_type, destination_port, exit_point, transport_mode, incoterm, invoice_date, invoice_no, customer_id, awb_no, license_no, mirsal_II_code, country_of_origin, grand_advice_qty, grand_advice_weight, currency, grand_advice_value, payment_method, invoice_pkgs, invoice_pkgs_unit, invoice_weight, invoice_weight_unit, invoice_grand_qty, invoice_grand_total_amount, is_active)
                            VALUES ('" . $shipment_type . "', '" . $destination_port . "', '" . $exit_point . "', '" . $transport_mode . "', '" . $incoterm . "', '" . $invoice_date . "', '" . $invoice_no . "', " . ($customer_id > 0 ? $customer_id : 'NULL') . ", '" . $awb_no . "', '" . $license_no . "', '" . $mirsal_II_code . "', '" . $country_of_origin . "', '" . $grand_advice_qty . "', '" . $grand_advice_weight . "', '" . $currency . "', '" . $grand_advice_value . "', '" . $payment_method . "', '" . $invoice_pkgs . "', '" . $invoice_pkgs_unit . "', '" . $invoice_weight . "', '" . $invoice_weight_unit . "', '" . $invoice_grand_qty . "', '" . $invoice_grand_total_amount . "', 1); ");

                            $id = $mysqli->insert_id;
                            fp__($tbl_name, $id);
                            $success_message = "The $module_caption has been saved successfully.";
                            $advice_id = $id;
                        }

                        // SAVE ADVICE ITEMS
                        $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_ADVICE_ITEMS . "`(advice_id, hs_code, description, qty, origin, value, weight) VALUES ('" . $advice_id . "', '" . $item_advice_hs_code . "', '" . $item_advice_description . "', '" . $item_advice_qty . "', '" . $item_advice_origin . "', '" . $item_advice_value . "', '" . $item_advice_weight . "'); ");

                        if ($insert_row) {
                            $inserted_row++;
                        }
                        fp__(DB::SHIPPING_ADVICE_ITEMS, $mysqli->insert_id);
                    }
                }

                // CHECK IF AT LEAST ONE SHIPPING INVOICE ITEM IS ADDED
                if ($inserted_row == 0) {
                    $error_message = "Please add at least one Invoice Item.";
                } else {

                    // IF ADVICE SAVE THEN ---------------> SAVE INVOICE ITEMS
                    for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_invoice_rows; $shipping_invoice_item++) {

                        $index = $shipping_invoice_item - 1;

                        $item_invoice_serial_no      = $invoice_serial_no_arr[$index];
                        $item_invoice_description    = $invoice_description_arr[$index];
                        $item_invoice_origin         = $invoice_origin_arr[$index];
                        $item_invoice_declaration_no = $invoice_declaration_no_arr[$index];
                        $item_invoice_hs_code        = $invoice_hs_code_arr[$index];
                        $item_invoice_qty            = $invoice_qty_arr[$index];
                        $item_invoice_unit_price     = $invoice_unit_price_arr[$index];
                        $item_invoice_total_amount   = $invoice_total_amount_arr[$index];

                        if (
                            !empty($item_invoice_serial_no) &&
                            !empty($item_invoice_description) &&
                            !empty($item_invoice_origin) &&
                            !empty($item_invoice_declaration_no) &&
                            !empty($item_invoice_hs_code) &&
                            !empty($item_invoice_qty) &&
                            !empty($item_invoice_unit_price) &&
                            !empty($item_invoice_total_amount)
                        ) {

                            $insert_row = $mysqli->query(" INSERT INTO `" . DB::SHIPPING_INVOICE_ITEMS . "` (advice_id, serial_no, description, origin, declaration_no, hs_code, qty, unit_price, total_amount)
                                                    VALUES (
                                                        '" . $advice_id . "',
                                                        '" . $item_invoice_serial_no . "',
                                                        '" . $item_invoice_description . "',
                                                        '" . $item_invoice_origin . "',
                                                        '" . $item_invoice_declaration_no . "',
                                                        '" . $item_invoice_hs_code . "',
                                                        '" . $item_invoice_qty . "',
                                                        '" . $item_invoice_unit_price . "',
                                                        '" . $item_invoice_total_amount . "'
                                                    );
                                                ");

                            fp__(DB::SHIPPING_INVOICE_ITEMS, $mysqli->insert_id);
                        }
                    }

                    flash_success($success_message);
                    header("Location:listing_$module.php");
                    exit;
                }
            }
            } catch (\Throwable $e) {
                if (function_exists('log_error')) {
                    log_error('Shipping Advice save failed: ' . $e->getMessage(), 'ERROR', __FILE__, __LINE__, ['module' => 'shipping_advices']);
                }
                $error_message = 'An error occurred while saving the Shipping Advice. Please try again.';
            }
        }
    }
}

// Refresh the posted arrays so the form re-renders what the user entered on validation error
$shippingCustomers = getAllShippingCustomers($mysqli);

?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-3">
                <h5 class="mb-0">Create <?php echo $module_caption; ?></h5>
            </div>

            <div class="my-1">
                <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm me-2">Save <?php echo $module_caption; ?></button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Exit</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php if (!empty($error_message)) { ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php } ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="shipping_advices.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="action" value="add_shipping_advices" />
                <?php echo csrf_field(); ?>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Customs Bill Type: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="shipment_type" id="shipment_type" value="<?php echo $shipment_type ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Destination: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="destination_port" id="destination_port" value="<?php echo $destination_port ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Exit Point: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="exit_point" id="exit_point" value="<?php echo $exit_point ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Mode: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="transport_mode" id="transport_mode" value="<?php echo $transport_mode ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Shipment Terms: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="incoterm" id="incoterm" value="<?php echo $incoterm ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Customer: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <select class="form-select" name="customer_id" id="customer_id">
                                            <option value="0">Please select</option>
                                            <?php foreach ($shippingCustomers as $sc) { ?>
                                                <option value="<?php echo $sc['id']; ?>" <?php echo ((int)$customer_id === (int)$sc['id'] ? 'selected' : ''); ?>><?php echo $sc['customer_name']; ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Invoice Date: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date ?? date('d-m-Y', time()); ?>" placeholder="dd-mm-yyyy">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Invoice no: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="invoice_no" id="invoice_no" value="<?php echo $invoice_no ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">AWB No: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="awb_no" id="awb_no" value="<?php echo $awb_no ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">License No: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="license_no" id="license_no" value="<?php echo $license_no ?? ''; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Mirsal II Code: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="mirsal_II_code" id="mirsal_II_code" value="<?php echo $mirsal_II_code ?? ''; ?>">
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
                                <div class="col-lg-1"><label class="form-label ms-3 fw-semibold">HS Code: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-3"><label class="form-label ms-3 fw-semibold">Description of Goods: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1"><label class="form-label fw-semibold">QTY: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1"><label class="form-label fw-semibold">Origin: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1 text-end"><label class="form-label fw-semibold">VALUE: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-2"><label class="form-label ms-4 fw-semibold">Weight (Kg): </label></div>
                                <div class="col-lg-1"></div>
                            </div>

                            <div class="card">
                                <div class="row card-body">
                                    <div class="col-lg-12">

                                        <?php for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) { $index = $shipping_advice_item - 1; ?>
                                            <div class="row mb-1" id="advice_row_<?php echo $shipping_advice_item; ?>">
                                                <div class="col-lg-1">
                                                    <input type="text" class="form-control" name="advice_hs_code[]" id="advice_hs_code<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_hs_code_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-3">
                                                    <input type="text" class="form-control" name="advice_description[]" id="advice_description<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_description_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-1">
                                                    <input type="number" step="1" min="0" class="form-control" name="advice_qty[]" id="advice_qty<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_qty_arr[$index] ?? '0'; ?>" onkeyup="calculateAdviceGrand();" onchange="calculateAdviceGrand();">
                                                </div>
                                                <div class="col-lg-1">
                                                    <input type="text" class="form-control" name="advice_origin[]" id="advice_origin<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_origin_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-1 text-end">
                                                    <input type="number" step="1" min="0" class="form-control text-end" name="advice_value[]" id="advice_value<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_value_arr[$index] ?? '0'; ?>" onkeyup="calculateAdviceGrand();" onchange="calculateAdviceGrand();">
                                                </div>
                                                <div class="col-lg-2">
                                                    <input type="number" step="1" min="0" class="form-control" name="advice_weight[]" id="advice_weight<?php echo $shipping_advice_item; ?>" value="<?php echo $advice_weight_arr[$index] ?? '0'; ?>" onkeyup="calculateAdviceGrand();" onchange="calculateAdviceGrand();">
                                                </div>
                                                <div class="col-lg-1 mt-1">
                                                    <?php if ($shipping_advice_item > 1) { ?>
                                                        <a href="#" onclick="clearAdviceRow(<?php echo $shipping_advice_item; ?>);return false;"><span class="badge bg-warning"><i class="ph-x"></i></span></a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div id="add_advice_row_here"></div>

                                        <div class="mb-2">
                                            <span><a href="#" onclick="addAdviceRow();return false;"><span class="badge bg-primary"> Add New Row </span></a></span>
                                        </div>

                                        <input type="hidden" name="total_advice_rows" id="total_advice_rows" value="<?php echo $total_advice_rows; ?>">

                                        <div class="row">
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-2 fw-semibold">GRAND TOTAL</div>
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-1">
                                                <input type="number" min="0" class="form-control text-center" name="grand_advice_qty" id="grand_advice_qty" value="<?php echo $grand_advice_qty ?? '0'; ?>">
                                            </div>
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-1 text-end">
                                                <input type="number" min="0" class="form-control text-end" name="grand_advice_value" id="grand_advice_value" value="<?php echo $grand_advice_value ?? '0'; ?>">
                                            </div>
                                            <div class="col-lg-1 text-end">
                                                <input type="number" min="0" class="form-control text-end" name="grand_advice_weight" id="grand_advice_weight" value="<?php echo $grand_advice_weight ?? '0'; ?>">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-3 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Country of Origin: <span class="text-danger">*</span></label>
                                        <div class="col-lg-6">
                                            <input type="text" class="form-control text-center" name="country_of_origin" id="country_of_origin" value="<?php echo $country_of_origin ?? ''; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-2">&nbsp;</div>

                        <div class="col-lg-3">
                            <div class="card h-100">
                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Gross Weight No. <span class="text-danger">*</span></label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control text-end" name="grand_advice_weight" id="grand_advice_weight_box" value="<?php echo $grand_advice_weight ?? '0'; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Currency <span class="text-danger">*</span></label>
                                        <div class="col-lg-6">
                                            <input type="text" class="form-control text-end" name="currency" id="currency" value="<?php echo $currency ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Total Value <span class="text-danger">*</span></label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control text-end" name="grand_advice_value" id="grand_advice_value_box" value="<?php echo $grand_advice_value ?? '0'; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-6 col-form-label">Payment Method <span class="text-danger">*</span></label>
                                        <div class="col-lg-6">
                                            <input type="text" class="form-control text-end" name="payment_method" id="payment_method" value="<?php echo $payment_method ?? ''; ?>">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-xl-12">

                            <div class="row mb-2">
                                <div class="col-lg-1"><label class="form-label ms-3 fw-semibold">Serial: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-3"><label class="form-label ms-3 fw-semibold">Description: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1 text-center"><label class="form-label ms-3 fw-semibold">Origin: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-2"><label class="form-label fw-semibold">Declaration No: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1"><label class="form-label fw-semibold">HS Code: <span class="text-danger">*</span></label></div>
                                <div class="col-lg-1"><label class="form-label ms-4 fw-semibold">Qty: </label></div>
                                <div class="col-lg-1"><label class="form-label fw-semibold">Unit Price: </label></div>
                                <div class="col-lg-2"><label class="form-label ms-2 fw-semibold">Total: </label></div>
                            </div>

                            <div class="card">
                                <div class="row card-body">
                                    <div class="col-lg-12">

                                        <?php for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_invoice_rows; $shipping_invoice_item++) { $index = $shipping_invoice_item - 1; ?>
                                            <div class="row mb-1" id="invoice_row_<?php echo $shipping_invoice_item; ?>">
                                                <div class="col-lg-1 text-center">
                                                    <input type="text" class="form-control text-center" name="invoice_serial_no[]" id="invoice_serial_no<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_serial_no_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-3">
                                                    <input type="text" class="form-control" name="invoice_description[]" id="invoice_description<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_description_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-1 text-center">
                                                    <input type="text" class="form-control text-center" name="invoice_origin[]" id="invoice_origin<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_origin_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-2">
                                                    <input type="text" class="form-control" name="invoice_declaration_no[]" id="invoice_declaration_no<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_declaration_no_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-1">
                                                    <input type="text" class="form-control" name="invoice_hs_code[]" id="invoice_hs_code<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_hs_code_arr[$index] ?? ''; ?>">
                                                </div>
                                                <div class="col-lg-1 text-center">
                                                    <input type="number" step="1" min="0" class="form-control text-center" name="invoice_qty[]" id="invoice_qty<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_qty_arr[$index] ?? '1'; ?>" onkeyup="calculateInvoiceItemAmount(<?php echo $shipping_invoice_item; ?>);" onchange="calculateInvoiceItemAmount(<?php echo $shipping_invoice_item; ?>);">
                                                </div>
                                                <div class="col-lg-1 text-center">
                                                    <input type="number" step="1" min="0" class="form-control text-center" name="invoice_unit_price[]" id="invoice_unit_price<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_unit_price_arr[$index] ?? '0'; ?>" onkeyup="calculateInvoiceItemAmount(<?php echo $shipping_invoice_item; ?>);" onchange="calculateInvoiceItemAmount(<?php echo $shipping_invoice_item; ?>);">
                                                </div>
                                                <div class="col-lg-1 text-center">
                                                    <input type="number" min="0" class="form-control text-end" name="invoice_total_amount[]" id="invoice_total_amount<?php echo $shipping_invoice_item; ?>" value="<?php echo $invoice_total_amount_arr[$index] ?? '0'; ?>" onkeyup="calculateInvoiceGrand();" onchange="calculateInvoiceGrand();">
                                                </div>
                                                <div class="col-lg-1 mt-1">
                                                    <?php if ($shipping_invoice_item > 1) { ?>
                                                        <a href="#" onclick="clearInvoiceRow(<?php echo $shipping_invoice_item; ?>);return false;"><span class="badge bg-warning"><i class="ph-x"></i></span></a>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        <?php } ?>

                                        <div id="add_invoice_row_here"></div>

                                        <div class="mb-2">
                                            <span><a href="#" onclick="addInvoiceRow();return false;"><span class="badge bg-primary"> Add New Row </span></a></span>
                                        </div>

                                        <input type="hidden" name="total_invoice_rows" id="total_invoice_rows" value="<?php echo $total_invoice_rows; ?>">

                                        <div class="row mb-3 pb-3">
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-2 fw-semibold">GRAND TOTAL</div>
                                            <div class="col-lg-2"></div>
                                            <div class="col-lg-2"></div>
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-1 text-center">
                                                <input type="number" min="0" class="form-control text-center" name="invoice_grand_qty" id="invoice_grand_qty" value="<?php echo $invoice_grand_qty ?? '0'; ?>">
                                            </div>
                                            <div class="col-lg-1"></div>
                                            <div class="col-lg-1 text-center">
                                                <input type="number" min="0" class="form-control text-end" name="invoice_grand_total_amount" id="invoice_grand_total_amount" value="<?php echo $invoice_grand_total_amount ?? '0'; ?>">
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-4 col-form-label">PLT/BOX/PKG's: </label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control text-center" name="invoice_pkgs" id="invoice_pkgs" value="<?php echo $invoice_pkgs ?? ''; ?>">
                                        </div>
                                        <div class="col-lg-2">
                                            <input type="text" class="form-control" name="invoice_pkgs_unit" id="invoice_pkgs_unit" value="<?php echo $invoice_pkgs_unit ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-4 col-form-label">WEIGHT: </label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control text-center" name="invoice_weight" id="invoice_weight" value="<?php echo $invoice_weight ?? ''; ?>">
                                        </div>
                                        <div class="col-lg-2">
                                            <input type="text" class="form-control" name="invoice_weight_unit" id="invoice_weight_unit" value="<?php echo $invoice_weight_unit ?? ''; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-4 col-form-label">AWB: <span class="text-danger">*</span></label>
                                        <div class="col-lg-6 mt-2 text-center fw-semibold">
                                            <?php echo $awb_no ?? ''; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<script>
    var adviceRowCounter = <?php echo $total_advice_rows; ?>;
    var invoiceRowCounter = <?php echo $total_invoice_rows; ?>;

    function addAdviceRow() {
        adviceRowCounter++;
        var row = "";
        row += "<div class=\"row mb-1\" id=\"advice_row_" + adviceRowCounter + "\">";
        row += "<div class=\"col-lg-1\"><input type=\"text\" class=\"form-control\" name=\"advice_hs_code[]\" id=\"advice_hs_code" + adviceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-3\"><input type=\"text\" class=\"form-control\" name=\"advice_description[]\" id=\"advice_description" + adviceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-1\"><input type=\"number\" step=\"1\" min=\"0\" class=\"form-control\" name=\"advice_qty[]\" id=\"advice_qty" + adviceRowCounter + "\" value=\"0\" onkeyup=\"calculateAdviceGrand();\" onchange=\"calculateAdviceGrand();\"></div>";
        row += "<div class=\"col-lg-1\"><input type=\"text\" class=\"form-control\" name=\"advice_origin[]\" id=\"advice_origin" + adviceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-1 text-end\"><input type=\"number\" step=\"1\" min=\"0\" class=\"form-control text-end\" name=\"advice_value[]\" id=\"advice_value" + adviceRowCounter + "\" value=\"0\" onkeyup=\"calculateAdviceGrand();\" onchange=\"calculateAdviceGrand();\"></div>";
        row += "<div class=\"col-lg-2\"><input type=\"number\" step=\"1\" min=\"0\" class=\"form-control\" name=\"advice_weight[]\" id=\"advice_weight" + adviceRowCounter + "\" value=\"0\" onkeyup=\"calculateAdviceGrand();\" onchange=\"calculateAdviceGrand();\"></div>";
        row += "<div class=\"col-lg-1 mt-1\"><span><a href=\"#\" onclick=\"clearAdviceRow(" + adviceRowCounter + ");return false;\"><span class=\"badge bg-warning\"><i class=\"ph-x\"></i></span></a></span></div>";
        row += "</div>";
        document.getElementById('add_advice_row_here').insertAdjacentHTML("beforebegin", row);
        document.getElementById('total_advice_rows').value = adviceRowCounter;
        calculateAdviceGrand();
    }

    function clearAdviceRow(row_no) {
        document.getElementById('advice_row_' + row_no).style.display = 'none';
        document.getElementById('advice_qty' + row_no).value = 0;
        document.getElementById('advice_value' + row_no).value = 0;
        document.getElementById('advice_weight' + row_no).value = 0;
        calculateAdviceGrand();
    }

    function calculateAdviceGrand() {
        var total_rows = document.getElementById('total_advice_rows').value;
        var grand_qty = 0, grand_value = 0, grand_weight = 0;
        for (var i = 1; i <= total_rows; i++) {
            var qtyEl = document.getElementById('advice_qty' + i);
            var valueEl = document.getElementById('advice_value' + i);
            var weightEl = document.getElementById('advice_weight' + i);
            if (qtyEl && qtyEl.style.display !== 'none') grand_qty += Number(qtyEl.value) || 0;
            if (valueEl && valueEl.style.display !== 'none') grand_value += Number(valueEl.value) || 0;
            if (weightEl && weightEl.style.display !== 'none') grand_weight += Number(weightEl.value) || 0;
        }
        document.getElementById('grand_advice_qty').value = parseFloat(grand_qty.toFixed(2));
        document.getElementById('grand_advice_value').value = parseFloat(grand_value.toFixed(2));
        document.getElementById('grand_advice_weight').value = parseFloat(grand_weight.toFixed(2));
        var wBox = document.getElementById('grand_advice_weight_box');
        var vBox = document.getElementById('grand_advice_value_box');
        if (wBox) wBox.value = parseFloat(grand_weight.toFixed(2));
        if (vBox) vBox.value = parseFloat(grand_value.toFixed(2));
    }

    function addInvoiceRow() {
        invoiceRowCounter++;
        var row = "";
        row += "<div class=\"row mb-1\" id=\"invoice_row_" + invoiceRowCounter + "\">";
        row += "<div class=\"col-lg-1 text-center\"><input type=\"text\" class=\"form-control text-center\" name=\"invoice_serial_no[]\" id=\"invoice_serial_no" + invoiceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-3\"><input type=\"text\" class=\"form-control\" name=\"invoice_description[]\" id=\"invoice_description" + invoiceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-1 text-center\"><input type=\"text\" class=\"form-control text-center\" name=\"invoice_origin[]\" id=\"invoice_origin" + invoiceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-2\"><input type=\"text\" class=\"form-control\" name=\"invoice_declaration_no[]\" id=\"invoice_declaration_no" + invoiceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-1\"><input type=\"text\" class=\"form-control\" name=\"invoice_hs_code[]\" id=\"invoice_hs_code" + invoiceRowCounter + "\"></div>";
        row += "<div class=\"col-lg-1 text-center\"><input type=\"number\" step=\"1\" min=\"0\" class=\"form-control text-center\" name=\"invoice_qty[]\" id=\"invoice_qty" + invoiceRowCounter + "\" value=\"1\" onkeyup=\"calculateInvoiceItemAmount(" + invoiceRowCounter + ");\" onchange=\"calculateInvoiceItemAmount(" + invoiceRowCounter + ");\"></div>";
        row += "<div class=\"col-lg-1 text-center\"><input type=\"number\" step=\"1\" min=\"0\" class=\"form-control text-center\" name=\"invoice_unit_price[]\" id=\"invoice_unit_price" + invoiceRowCounter + "\" value=\"0\" onkeyup=\"calculateInvoiceItemAmount(" + invoiceRowCounter + ");\" onchange=\"calculateInvoiceItemAmount(" + invoiceRowCounter + ");\"></div>";
        row += "<div class=\"col-lg-1 text-center\"><input type=\"number\" min=\"0\" class=\"form-control text-end\" name=\"invoice_total_amount[]\" id=\"invoice_total_amount" + invoiceRowCounter + "\" value=\"0\" onkeyup=\"calculateInvoiceGrand();\" onchange=\"calculateInvoiceGrand();\"></div>";
        row += "<div class=\"col-lg-1 mt-1\"><span><a href=\"#\" onclick=\"clearInvoiceRow(" + invoiceRowCounter + ");return false;\"><span class=\"badge bg-warning\"><i class=\"ph-x\"></i></span></a></span></div>";
        row += "</div>";
        document.getElementById('add_invoice_row_here').insertAdjacentHTML("beforebegin", row);
        document.getElementById('total_invoice_rows').value = invoiceRowCounter;
        calculateInvoiceGrand();
    }

    function clearInvoiceRow(row_no) {
        document.getElementById('invoice_row_' + row_no).style.display = 'none';
        document.getElementById('invoice_total_amount' + row_no).value = 0;
        calculateInvoiceGrand();
    }

    function calculateInvoiceItemAmount(row_no) {
        var qtyEl = document.getElementById('invoice_qty' + row_no);
        var priceEl = document.getElementById('invoice_unit_price' + row_no);
        var totalEl = document.getElementById('invoice_total_amount' + row_no);
        if (qtyEl && priceEl && totalEl) {
            var amount = (Number(qtyEl.value) || 0) * (Number(priceEl.value) || 0);
            totalEl.value = parseFloat(amount.toFixed(2));
        }
        calculateInvoiceGrand();
    }

    function calculateInvoiceGrand() {
        var total_rows = document.getElementById('total_invoice_rows').value;
        var grand_qty = 0, grand_amount = 0;
        for (var i = 1; i <= total_rows; i++) {
            var qtyEl = document.getElementById('invoice_qty' + i);
            var amountEl = document.getElementById('invoice_total_amount' + i);
            if (qtyEl && qtyEl.style.display !== 'none') grand_qty += Number(qtyEl.value) || 0;
            if (amountEl && amountEl.style.display !== 'none') grand_amount += Number(amountEl.value) || 0;
        }
        document.getElementById('invoice_grand_qty').value = parseFloat(grand_qty.toFixed(2));
        document.getElementById('invoice_grand_total_amount').value = parseFloat(grand_amount.toFixed(2));
    }
</script>

<?php include('admin_elements/admin_footer.php'); ?>