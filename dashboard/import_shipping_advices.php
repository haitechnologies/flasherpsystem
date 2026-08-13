<?php
use App\Core\DB;
include('admin_elements/admin_header.php');

$module             = 'shipping_advices';
$GLOBALS['module']  = $module;
$module_caption     = 'Shipping Advice';
$tbl_name             = DB::SHIPPING_ADVICES;
$error_message         = '';
$success_message     = '';

require_once '../vendor/autoload.php';
require_once 'helpers/shipping_customer_helper.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;



/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/

include('admin_elements/permissions.php');


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

// ---------------------- Shipping Invoice Items -----------------------------

$invoice_serial_no_arr      = [];
$invoice_description_arr    = [];
$invoice_origin_arr         = [];
$invoice_declaration_no_arr = [];
$invoice_hs_code_arr        = [];
$invoice_qty_arr            = [];
$invoice_unit_price_arr     = [];
$invoice_total_amount_arr   = [];


$total_invoice_rows      = 1;
if (isset($_POST['total_invoice_rows']) && !empty($_POST['total_invoice_rows'])) {
    $total_invoice_rows      = e_s__($_POST['total_invoice_rows']);
}





if ($action == "add_$module") {


    // ---------------------- Shipping Advice Items -----------------------------
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


    // ---------------------- Shipping Invoice Items -----------------------------
    for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_invoice_rows; $shipping_invoice_item++) {

        $index = $shipping_invoice_item;
        $index = $index - 1;

        // Assuming you're looping through posted items using $index
        $invoice_serial_no      = (isset($_POST['invoice_serial_no'][$index]) && !empty($_POST['invoice_serial_no'][$index]) ? $_POST['invoice_serial_no'][$index] : '');
        $invoice_description    = (isset($_POST['invoice_description'][$index]) && !empty($_POST['invoice_description'][$index]) ? $_POST['invoice_description'][$index] : '');
        $invoice_origin         = (isset($_POST['invoice_origin'][$index]) && !empty($_POST['invoice_origin'][$index]) ? $_POST['invoice_origin'][$index] : '');
        $invoice_declaration_no = (isset($_POST['invoice_declaration_no'][$index]) && !empty($_POST['invoice_declaration_no'][$index]) ? $_POST['invoice_declaration_no'][$index] : '');
        $invoice_hs_code        = (isset($_POST['invoice_hs_code'][$index]) && !empty($_POST['invoice_hs_code'][$index]) ? $_POST['invoice_hs_code'][$index] : '');
        $invoice_qty            = (isset($_POST['invoice_qty'][$index]) && !empty($_POST['invoice_qty'][$index]) ? $_POST['invoice_qty'][$index] : 1);
        $invoice_unit_price     = (isset($_POST['invoice_unit_price'][$index]) && !empty($_POST['invoice_unit_price'][$index]) ? $_POST['invoice_unit_price'][$index] : 0);
        $invoice_total_amount   = (isset($_POST['invoice_total_amount'][$index]) && !empty($_POST['invoice_total_amount'][$index]) ? $_POST['invoice_total_amount'][$index] : 0);

        // Push sanitized values into arrays
        array_push($invoice_serial_no_arr,      e_s__($invoice_serial_no));
        array_push($invoice_description_arr,    e_s__($invoice_description));
        array_push($invoice_origin_arr,         e_s__($invoice_origin));
        array_push($invoice_declaration_no_arr, e_s__($invoice_declaration_no));
        array_push($invoice_hs_code_arr,        e_s__($invoice_hs_code));
        array_push($invoice_qty_arr,            e_s__($invoice_qty));
        array_push($invoice_unit_price_arr,     e_s__($invoice_unit_price));
        array_push($invoice_total_amount_arr,   e_s__($invoice_total_amount));
    } //for 
}


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "add_$module") {


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

    // $customer_id                = e_s__($_POST['customer_id'] ?? '');
    $customer_id                = (int)($_POST['customer_id'] ?? 0);

    $country_of_origin      = e_s__($_POST['country_of_origin'] ?? '');
    $grand_advice_qty       = e_s__($_POST['grand_advice_qty'] ?? '');
    $grand_advice_weight    = e_s__($_POST['grand_advice_weight'] ?? '');
    $currency               = e_s__($_POST['currency'] ?? '');
    $grand_advice_value     = e_s__($_POST['grand_advice_value'] ?? '');
    $payment_method         = e_s__($_POST['payment_method'] ?? '');

    $invoice_pkgs               = e_s__($_POST['invoice_pkgs'] ?? '');
    $invoice_pkgs_unit          = e_s__($_POST['invoice_pkgs_unit'] ?? '');
    $invoice_weight             = e_s__($_POST['invoice_weight'] ?? '');
    $invoice_weight_unit        = e_s__($_POST['invoice_weight_unit'] ?? '');
    $invoice_grand_qty          = e_s__($_POST['invoice_grand_qty'] ?? '');
    $invoice_grand_total_amount = e_s__($_POST['invoice_grand_total_amount'] ?? '');
} else {

    $shipment_type          = '';
    $destination_port       = '';
    $exit_point             = '';
    $transport_mode         = '';
    $incoterm               = '';

    $invoice_date            = date('d-m-Y', time());
    $invoice_no             = '';
    $awb_no                 = '';
    $license_no             = '';
    $mirsal_II_code         = '';

    $customer_id            = '';

    $country_of_origin      = '';
    $grand_advice_qty       = '';
    $grand_advice_weight    = '';
    $currency               = '';
    $grand_advice_value     = '';
    $payment_method         = '';

    $invoice_pkgs               = '';
    $invoice_pkgs_unit          = '';
    $invoice_weight             = '';
    $invoice_weight_unit        = '';
    $invoice_grand_qty          = '';
    $invoice_grand_total_amount = '';
}


/*
|--------------------------------------------------------------------------
| 	ADD
|--------------------------------------------------------------------------
|
*/
if ($action == "add_$module") {


    // if (empty($customer_id) || $customer_id == 'Please select') {
    //     $error_message = 'Please select Customer.';

    // CHECK FOR DUPLICATE INVOICE NO
    $rs_invoice_no  = $mysqli->query("SELECT id FROM `" . DB::SHIPPING_ADVICES . "` WHERE invoice_no= '" . $invoice_no . "' ");

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

        ///////////////////////////////////////////////////////////

        // -- PROCESS ITEMS

        if ($total_advice_rows > 0) {

            $inserted_row = 0;

            for ($shipping_advice_item = 1; $shipping_advice_item <= $total_advice_rows; $shipping_advice_item++) {

                $index = $shipping_advice_item;
                $index = $index - 1;

                $item_advice_hs_code        = $advice_hs_code_arr[$index];
                $item_advice_description    = $advice_description_arr[$index];
                $item_advice_qty            = $advice_qty_arr[$index];
                $item_advice_origin         = $advice_origin_arr[$index];
                $item_advice_value          = $advice_value_arr[$index];
                $item_advice_weight         = $advice_weight_arr[$index];

                if (!empty($item_advice_hs_code) && !empty($item_advice_description)  && !empty($item_advice_qty) && !empty($item_advice_origin) && !empty($item_advice_value) && !empty($item_advice_weight)) {

                    // ---------------------------------------------
                    // SAVE SHIPPING INVOICE
                    // ---------------------------------------------
                    if ($inserted_row == 0) {

                        $invoice_date   = processDateDtoY($invoice_date);

                        $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_ADVICES . "`(shipment_type, destination_port, exit_point, transport_mode, incoterm, invoice_date, invoice_no, customer_id, awb_no, license_no, mirsal_II_code, country_of_origin, grand_advice_qty, grand_advice_weight, currency, grand_advice_value, payment_method, invoice_pkgs, invoice_pkgs_unit, invoice_weight, invoice_weight_unit, invoice_grand_qty, invoice_grand_total_amount, is_active)
                        VALUES ('" . $shipment_type . "', '" . $destination_port . "', '" . $exit_point . "', '" . $transport_mode . "', '" . $incoterm . "', '" . $invoice_date . "', '" . $invoice_no . "', " . ($customer_id > 0 ? $customer_id : 'NULL') . ", '" . $awb_no . "', '" . $license_no . "', '" . $mirsal_II_code . "', '" . $country_of_origin . "', '" . $grand_advice_qty . "', '" . $grand_advice_weight . "', '" . $currency . "', '" . $grand_advice_value . "', '" . $payment_method . "', '" . $invoice_pkgs . "', '" . $invoice_pkgs_unit . "', '" . $invoice_weight . "', '" . $invoice_weight_unit . "', '" . $invoice_grand_qty . "', '" . $invoice_grand_total_amount . "', 1); ");


                        $id = $mysqli->insert_id;
                        // if ($insert_row) {
                        fp__($tbl_name, $id);
                        $success_message = "The $module_caption has been saved successfully.";
                        $advice_id = $id;
                    }
                    // ---------------------------------------------


                    // ---------------------------------------------
                    // SAVE invoice ITEMS
                    // ---------------------------------------------

                    $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_ADVICE_ITEMS . "`(advice_id, hs_code, description, qty, origin, value, weight) VALUES ('" . $advice_id . "', '" . $item_advice_hs_code . "', '" . $item_advice_description . "', '" . $item_advice_qty . "', '" . $item_advice_origin . "', '" . $item_advice_value . "', '" . $item_advice_weight . "'); ");

                    if ($insert_row) $inserted_row++;
                    fp__(DB::SHIPPING_ADVICE_ITEMS, $mysqli->insert_id);
                    // ---------------------------------------------
                }
            } //for 


            // CHECK IF AT LEAST ONE SHIPPING INVOICE ITEM IS ADDED
            if ($inserted_row == 0) {
                $error_message = "Please add at least one Invoice Item.";
            } else {


                // IF ADVISE SAVE THEN ---------------> SAVE INOVICE ITEMS
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

                    // Ensure required fields are not empty before inserting
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

                        // SAVE ITEMS
                        $insert_row = $mysqli->query(" INSERT INTO `" . DB::SHIPPING_INVOICE_ITEMS . "` (advice_id, serial_no, description, origin, declaration_no, hs_code, qty,unit_price, total_amount)
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
                        // ---------------------------------------------

                    }
                } // for


                flash_success($success_message);
                header("Location:listing_$module.php");
            }
        } // if

        ///////////////////////////////////////////////////////////
        // header("Location:listing_$module.php?success_message=$success_message");
        // } else {
        //     $error_message = "The $module_caption could not be saved. Please try again.";
        //     //header("Location:$module.php?error_message=$error_message");
        // }

    }
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
            <div class="my-1 d-flex align-items-center gap-3">
                <h5 class="mb-0">Import <?php echo $module_caption; ?></h5>
                <div class="d-flex align-items-center gap-2 ms-3">
                    <label class="fw-semibold mb-0" style="white-space: nowrap;">Select File: <span class="text-danger">*</span></label>
                    <input type="file" name="shipping_invoice_file" form="frm<?php echo $module; ?>" accept=".xlsx" class="form-control form-control-sm" style="width: auto;">
                    <span class="text-muted small" style="white-space: nowrap;"><a href="sample.xlsx">Sample.xlsx</a></span>
                </div>
            </div>

            <div class="my-1">
                <?php if ($action != "import_$module") { ?>
                    <button type="button" onclick="document.getElementById('action').value='import_shipping_advices'; document.getElementById('frm<?php echo $module; ?>').submit();" class="btn btn-primary btn-sm me-2">IMPORT <?php echo $module_caption; ?></button>
                <?php } else { ?>
                    <button type="button" onclick="document.getElementById('action').value='add_shipping_advices'; document.getElementById('frm<?php echo $module; ?>').submit();" class="btn btn-primary btn-sm me-2">SAVE <?php echo $module_caption; ?></button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Exit</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="import_<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="action" value="<?php echo $action; ?>" />


                <!-- 
                |--------------------------------------------------------------------------
                | PROCESS DELIVERY ADVICE - SUMMARY
                |--------------------------------------------------------------------------
                |
                -->

                <?php

                if ($action == "import_$module") {

                    // Check if file is uploaded
                    if (isset($_FILES['shipping_invoice_file'])) {
                        $fileName = $_FILES['shipping_invoice_file']['name'];
                        $fileTmp  = $_FILES['shipping_invoice_file']['tmp_name'];
                        $fileSize = $_FILES['shipping_invoice_file']['size'];
                        $fileError = $_FILES['shipping_invoice_file']['error'];

                        // Validate file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if ($fileExt !== 'xlsx') {
                            $error_message = "Only .xlsx files are allowed!";
                            flash_error($error_message);
                            header("Location:import_shipping_advices.php");
                        }

                        // Handle upload error
                        if ($fileError !== UPLOAD_ERR_OK) {
                            $error_message = "Please select File to upload.";
                            flash_error($error_message);
                            header("Location:import_shipping_advices.php");
                        }

                        try {

                            // Input Excel file
                            // Create Reader
                            $reader = new Xlsx();
                            $reader->setReadDataOnly(true);

                            /*
                            |--------------------------------------------------------------------------
                            | PROCESS DELIVERY ADVICE - SUMMARY
                            |--------------------------------------------------------------------------
                            |
                            */

                            // Load the file
                            $spreadsheet = $reader->load($fileTmp);
                            $sheet = $spreadsheet->getSheet(1); // first sheet

                            $highestRow = $sheet->getHighestDataRow();
                            $highestColumn = $sheet->getHighestDataColumn();
                            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                            $start_advice_items = 0;
                            $total_advice_rows  = 0;

                            $advice_hs_code_arr        = [];
                            $advice_description_arr    = [];
                            $advice_qty_arr            = [];
                            $advice_origin_arr         = [];
                            $advice_value_arr          = [];
                            $advice_weight_arr         = [];

                            // ---------------------------------

                            $grand_advice_qty          =  '';
                            $grand_advice_value        =  '';
                            $grand_advice_weight       =  '';

                            // ---------------------------------
                            $country_of_origin = '';
                            $gross_weigth = '';
                            $currency = '';
                            $total_value = '';
                            $payment_method = '';

                            // ---------------------------------

                            // Customs Bill Type 	TRANSIT OUT
                            // Destination	AZERBAIJAN
                            // Exit Point 	AS PER BOE
                            // Mode Of Shipment	AIR
                            // Shipment Terms 	CIF

                            $shipment_type = '';
                            $destination_port = '';
                            $exit_point = '';
                            $transport_mode = '';
                            $incoterm = '';
                            $customer_name = '';
                            $customer_address = '';
                            $customer_id = 0;


                            // DATE:	21-10-2025	
                            // INVOICE NO:	TC-472	
                            // AWB No:	555-23153524	
                            // Licence No:	5508	
                            // Mirsal II Code:	AE-1167928	

                            $invoice_date = '';
                            $invoice_no = '';
                            $awb_no = '';
                            $license_no = '';
                            $mirsal_II_code = '';

                            // ---------------------------------


                            $counter = 0;
                            for ($row = 1; $row <= $highestRow; ++$row) {
                                $counter++;

                                // echo 'row no: ' . $row . ' ';

                                // Read cell values
                                $column_a       = trim((string)($sheet->getCell([1, $row])->getValue() ?? ''));
                                $column_b       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                $column_c       = trim((string)($sheet->getCell([3, $row])->getCalculatedValue() ?? ''));;
                                $column_d       = trim((string)($sheet->getCell([4, $row])->getValue() ?? ''));
                                $column_e       = trim((string)($sheet->getCell([5, $row])->getValue() ?? ''));
                                $column_f       = trim((string)($sheet->getCell([6, $row])->getValue() ?? ''));

                                // $hs_code        = trim((string)($sheet->getCell([1, $row])->getValue() ?? ''));
                                // $description    = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                // $qty            = trim((string)($sheet->getCell([3, $row])->getValue() ?? ''));
                                // $origin         = trim((string)($sheet->getCell([4, $row])->getValue() ?? ''));
                                // $value          = trim((string)($sheet->getCell([5, $row])->getValue() ?? ''));
                                // $weight         = trim((string)($sheet->getCell([6, $row])->getValue() ?? ''));

                                if (preg_match('/HS Code/i', $column_a) || preg_match('/Description of Goods/i', $column_b)) {
                                    $start_advice_items = 1;
                                    // echo 'im in number ' . $row;  
                                    continue; // skip header itself
                                }


                                if ($start_advice_items == 1) {

                                    $advice_hs_code        = $column_a;
                                    $advice_description    = $column_b;
                                    $advice_qty            = $column_c;
                                    $advice_origin         = $column_d;
                                    $advice_value          = $column_e;
                                    $advice_weight         = $column_f;


                                    $advice_value   = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                    if (!empty($advice_value) && is_numeric($advice_value)) {
                                        $advice_value = round((float)$advice_value, 3);
                                    }


                                    // ✅ Use getCalculatedValue() to get evaluated number
                                    $advice_weight   = trim((string)($sheet->getCell([6, $row])->getCalculatedValue() ?? ''));
                                    if (!empty($advice_weight) && is_numeric($advice_weight)) {
                                        $advice_weight = round((float)$advice_weight, 3);
                                    }


                                    $total_advice_rows++;

                                    array_push($advice_hs_code_arr,            $advice_hs_code);
                                    array_push($advice_description_arr,        $advice_description);
                                    array_push($advice_qty_arr,                $advice_qty);
                                    array_push($advice_origin_arr,             $advice_origin);
                                    array_push($advice_value_arr,              $advice_value);
                                    array_push($advice_weight_arr,             $advice_weight);
                                }



                                if (preg_match('/Total/i', $column_a)) {
                                    // echo 'grand total ' . $row;
                                    $start_advice_items = 0;
                                }


                                // Customs Bill Type 	TRANSIT OUT
                                // Destination	AZERBAIJAN
                                // Exit Point 	AS PER BOE
                                // Mode Of Shipment	AIR
                                // Shipment Terms 	CIF
                                // Importer's Name
                                // Address

                                // Customs Bill Type 	TRANSIT OUT
                                if (preg_match("/\bCustoms\s+Bill\s+Type\b/", $column_a)) {
                                    $shipment_type       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                }

                                // Destination	AZERBAIJAN
                                if (preg_match("/\bDestination\b/", $column_a)) {
                                    $destination_port       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                }

                                // Exit Point 	AS PER BOE
                                if (preg_match("/\bExit\s+Point\b/", $column_a)) {
                                    $exit_point       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                }

                                // Mode Of Shipment	AIR
                                if (preg_match("/\bMode\s+Of\s+Shipment\b/", $column_a)) {
                                    $transport_mode       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                }

                                // Shipment Terms 	CIF
                                if (preg_match("/\bShipment\s+Terms\b/", $column_a)) {
                                    $incoterm       = trim((string)($sheet->getCell([2, $row])->getValue() ?? ''));
                                }

                                // Importer's Name
                                if (preg_match("/\bImporter's\s+Name\b/i", $column_a)) {
                                    $customer_name = trim((string)($sheet->getCell([2, $row])->getCalculatedValue() ?? ''));
                                }

                                // Address
                                if (preg_match("/\bAddress\b/i", $column_a)) {
                                    $customer_address = trim((string)($sheet->getCell([2, $row])->getCalculatedValue() ?? ''));
                                }

                                // === PROCESS CUSTOMER: Find or Create ===
                                if (!empty($customer_name) && !empty($customer_address) && $customer_id == 0) {
                                    // Call helper function to find existing or create new customer
                                    $customer_id = findOrCreateShippingCustomer($mysqli, $customer_name, $customer_address);
                                }



                                // DATE:	21-10-2025	
                                // INVOICE NO:	TC-472	
                                // AWB No:	555-23153524	
                                // Licence No:	5508	
                                // Mirsal ll Code:	AE-1167928	


                                // DATE:	21-10-2025	
                                if (preg_match("/\bDATE:/i", $column_d)) {
                                    $invoice_date       = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                }

                                // INVOICE NO:	TC-472	
                                if (preg_match("/\bINVOICE\s+NO:/i", $column_d)) {
                                    $invoice_no       = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                }

                                // AWB No:	555-23153524	
                                if (preg_match("/\bAWB\s+No:/i", $column_d)) {
                                    $awb_no       = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                }

                                // Licence No:	5508	
                                if (preg_match("/\bLicence\s+No:/i", $column_d)) {
                                    $license_no       = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                }

                                // Mirsal ll Code:	AE-1167928	
                                if (preg_match("/\bMirsal\s+ll?\s+Code:/i", $column_d)) {
                                    $mirsal_II_code       = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                }


                                // Totals	
                                if (preg_match("/Total/i", $column_a)) {
                                    $grand_advice_qty       = trim((string)($sheet->getCell([3, $row])->getCalculatedValue() ?? ''));
                                    $grand_advice_value     = trim((string)($sheet->getCell([5, $row])->getCalculatedValue() ?? ''));
                                    $grand_advice_weight    = trim((string)($sheet->getCell([6, $row])->getCalculatedValue() ?? ''));
                                }

                                // Country of Origins	
                                if (preg_match("/Country of Origin/i", $column_a)) {
                                    $country_of_origin       = trim((string)($sheet->getCell([2, $row])->getCalculatedValue() ?? ''));
                                }


                                // Currency	
                                if (preg_match("/currency/i", $column_d) || preg_match("/currency/i", $column_e)) {
                                    $currency       = trim((string)($sheet->getCell([6, $row])->getCalculatedValue() ?? ''));
                                }

                                // Payment Method	
                                if (preg_match("/Payment Method/i", $column_d) || preg_match("/Payment Method/i", $column_e)) {
                                    $payment_method     = trim((string)($sheet->getCell([6, $row])->getCalculatedValue() ?? ''));
                                }
                            } // for



                        } catch (Exception $e) {
                            log_error('Excel import error: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'shipping_advices', 'action' => 'import']);
                            $error_message = "Error reading Excel file: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "No file uploaded.";
                    }


                    // Invalid Data in EXCEL Format File
                    if ($total_advice_rows <= 1) {
                        $error_message = "Invalid Excel File Format. Please selct Correct Format <a href=\"sample.xlsx\">Sample.xlsx</a> File to upload.";
                        flash_error($error_message);
                        header("Location:import_shipping_advices.php");
                    }

                ?>








                    <div class="card card-body bg-warning">
                        <div class="text-center text-lg-start">
                            <span class="mb-0 text-white">Please review -> Imported Delivery Advice</span>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Customs Bill Type: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $shipment_type; ?>

                                            <input type="hidden" class="form-control" name="shipment_type" id="shipment_type" value="<?php echo $shipment_type; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Destination: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $destination_port; ?>

                                            <input type="hidden" class="form-control" name="destination_port" id="destination_port" value="<?php echo $destination_port; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Exit Point: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $exit_point; ?>

                                            <input type="hidden" class="form-control" name="exit_point" id="exit_point" value="<?php echo $exit_point; ?>">
                                        </div>
                                    </div>


                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Mode: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $transport_mode; ?>

                                            <input type="hidden" class="form-control" name="transport_mode" id="transport_mode" value="<?php echo $transport_mode; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Shipment Terms: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $incoterm; ?>

                                            <input type="hidden" class="form-control" name="incoterm" id="incoterm" value="<?php echo $incoterm; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Customer Name: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $customer_name; ?>

                                            <input type="hidden" class="form-control" name="customer_name" id="customer_name" value="<?php echo $customer_name; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Address: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $customer_address; ?>

                                            <input type="hidden" class="form-control" name="customer_address" id="customer_address" value="<?php echo $customer_address; ?>">
                                        </div>
                                    </div>

                                    <!-- Hidden field for customer_id -->
                                    <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $customer_id; ?>">

                                </div>
                            </div>
                        </div>



                        <div class="col-lg-6">
                            <div class="card">

                                <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                    </h6>
                                </div> -->

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Invoice Date: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $invoice_date; ?>

                                            <input type="hidden" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Invoice no: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $invoice_no; ?>

                                            <input type="hidden" class="form-control" name="invoice_no" id="invoice_no" value="<?php echo $invoice_no; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">AWB No: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $awb_no; ?>

                                            <input type="hidden" class="form-control" name="awb_no" id="awb_no" value="<?php echo $awb_no; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">License No: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $license_no; ?>

                                            <input type="hidden" class="form-control" name="license_no" id="license_no" value="<?php echo $license_no; ?>">
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Mirsal II Code: <span class="text-danger">*</span></label>
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
                                        <label class="form-label ms-3 fw-semibold">HS Code: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-3">
                                        <label class="form-label ms-3 fw-semibold">Description of Goods: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-1">
                                        <label class="form-label fw-semibold">QTY: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-1">
                                        <label class="form-label fw-semibold">Origin: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-1 text-end">
                                        <label class="form-label fw-semibold">VALUE: <span class="text-danger">*</span></label>
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
                                            // for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_rows; $shipping_invoice_item++) {
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
                                            <label class="col-lg-6 col-form-label">Country of Origin: <span class="text-danger">*</span></label>
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
                                            <label class="col-lg-6 col-form-label">Gross Weight No. <span class="text-danger">*</span></label>
                                            <div class="col-lg-6 mt-2 text-end fw-semibold">
                                                <?php echo $grand_advice_weight; ?>

                                                <input type="hidden" class="form-control" name="grand_advice_weight" id="grand_advice_weight" value="<?php echo $grand_advice_weight; ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-6 col-form-label">Currency <span class="text-danger">*</span></label>
                                            <div class="col-lg-6 mt-2 text-end fw-semibold">
                                                <?php echo $currency; ?>

                                                <input type="hidden" class="form-control" name="currency" id="currency" value="<?php echo $currency; ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-6 col-form-label">Total Value <span class="text-danger">*</span></label>
                                            <div class="col-lg-6 mt-2 text-end fw-semibold">
                                                <?php echo $grand_advice_value; ?>

                                                <input type="hidden" class="form-control" name="grand_advice_value" id="grand_advice_value" value="<?php echo $grand_advice_value; ?>">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-6 col-form-label">Payment Method <span class="text-danger">*</span></label>
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

                <?php } // import File 
                ?>
















                <!-- 
                |--------------------------------------------------------------------------
                | PROCESS DELIVERY INVOICE - DETAILS
                |--------------------------------------------------------------------------
                |
                -->

                <?php

                if ($action == "import_$module") {

                    // Check if file is uploaded
                    if (isset($_FILES['shipping_invoice_file'])) {
                        $fileName = $_FILES['shipping_invoice_file']['name'];
                        $fileTmp  = $_FILES['shipping_invoice_file']['tmp_name'];
                        $fileSize = $_FILES['shipping_invoice_file']['size'];
                        $fileError = $_FILES['shipping_invoice_file']['error'];

                        // Validate file extension
                        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                        if ($fileExt !== 'xlsx') {
                            $error_message = "Error: Only .xlsx files are allowed!";
                            // exit;
                        }

                        // Handle upload error
                        if ($fileError !== UPLOAD_ERR_OK) {
                            $error_message = "Error: File upload failed. Please try again.";
                            // exit;
                        }

                        try {

                            // Input Excel file
                            // Create Reader
                            $reader = new Xlsx();
                            $reader->setReadDataOnly(true);

                            /*
                            |--------------------------------------------------------------------------
                            | PROCESS DELIVERY INVOICE - DETAILS
                            |--------------------------------------------------------------------------
                            |
                            */

                            // Load the file
                            $spreadsheet = $reader->load($fileTmp);
                            $sheet = $spreadsheet->getSheet(0); // first sheet

                            $highestRow = $sheet->getHighestDataRow();
                            $highestColumn = $sheet->getHighestDataColumn();
                            $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

                            $start_invoice_items = 0;
                            $total_invoice_rows  = 0;

                            $invoice_serial_no_arr      = [];
                            $invoice_description_arr    = [];
                            $invoice_origin_arr         = [];
                            $invoice_declaration_no_arr = [];
                            $invoice_hs_code_arr        = [];
                            $invoice_qty_arr            = [];
                            $invoice_unit_price_arr     = [];
                            $invoice_total_amount_arr   = [];

                            // ---------------------------------

                            $invoice_grand_qty          = '';
                            $invoice_grand_total_amount =  '';

                            // ---------------------------------
                            // PLT/BOX/PKG's:	15.00	PKG's
                            // WEIGHT:	480.00	KG's
                            // AWB:	141-7002 8840	
                            $invoice_pkgs = '';
                            $invoice_pkgs_unit = '';

                            $invoice_weight = '';
                            $invoice_weight_unit = '';

                            // ---------------------------------
                            $counter = 0;
                            for ($row = 1; $row <= $highestRow; ++$row) {
                                $counter++;

                                // echo 'row no: ' . $row . ' ';

                                // Read cell values
                                $invoice_serial_no      = trim((string)($sheet->getCell([2, $row])->getValue() ?? '')); //echo '<br />';
                                $invoice_description    = trim((string)($sheet->getCell([3, $row])->getValue() ?? ''));
                                $invoice_origin         = trim((string)($sheet->getCell([5, $row])->getValue() ?? ''));
                                $invoice_declaration_no = trim((string)($sheet->getCell([6, $row])->getValue() ?? ''));
                                $invoice_hs_code        = trim((string)($sheet->getCell([7, $row])->getValue() ?? ''));
                                $invoice_qty            = trim((string)($sheet->getCell([8, $row])->getValue() ?? ''));

                                $invoice_unit_price     = trim((string)($sheet->getCell([9, $row])->getValue() ?? ''));
                                if (!empty($invoice_unit_price) && is_numeric($invoice_unit_price)) {
                                    $invoice_unit_price = round((float)$invoice_unit_price, 3);
                                } else {
                                    $invoice_unit_price = 0; // or '' if you prefer empty instead of 0
                                }

                                // ✅ Use getCalculatedValue() to get evaluated number
                                // $total_amount   = trim((string)($sheet->getCell([10, $row])->getValue() ?? ''));
                                $invoice_total_amount   = trim((string)($sheet->getCell([10, $row])->getCalculatedValue() ?? ''));
                                if (!empty($invoice_total_amount) && is_numeric($invoice_total_amount)) {
                                    $invoice_total_amount = round((float)$invoice_total_amount, 3);
                                } else {
                                    $invoice_total_amount = 0; // or '' if you prefer empty instead of 0
                                }

                                if (preg_match('/S\.?\s*No\.?/i', $invoice_serial_no)) {
                                    $start_invoice_items = 1;
                                    // echo 'im in number ' . $row;  
                                    continue; // skip header itself
                                }


                                if ($start_invoice_items == 1) {

                                    $total_invoice_rows++;

                                    array_push($invoice_serial_no_arr,          $invoice_serial_no);
                                    array_push($invoice_description_arr,        $invoice_description);
                                    array_push($invoice_origin_arr,             $invoice_origin);
                                    array_push($invoice_declaration_no_arr,     $invoice_declaration_no);
                                    array_push($invoice_hs_code_arr,            $invoice_hs_code);
                                    array_push($invoice_qty_arr,                $invoice_qty);
                                    array_push($invoice_unit_price_arr,         $invoice_unit_price);
                                    array_push($invoice_total_amount_arr,       $invoice_total_amount);
                                }



                                if (preg_match('/GRAND total/i', $invoice_serial_no)) {
                                    // echo 'grand total ' . $row;
                                    $start_invoice_items = 0;
                                }



                                // PLT/BOX/PKG's:	15.00	PKG's
                                if (preg_match("/\b(PLT|BOX|PKG)(?:['’]?[sS])?\b/i", $invoice_description)) {
                                    $invoice_pkgs       = trim((string)($sheet->getCell([4, $row])->getValue() ?? ''));
                                    $invoice_pkgs_unit  = trim((string)($sheet->getCell([5, $row])->getValue() ?? ''));
                                }

                                // WEIGHT:	480.00	KG's
                                if (preg_match("/weight/i", $invoice_description)) {
                                    $invoice_weight         = trim((string)($sheet->getCell([4, $row])->getValue() ?? ''));
                                    $invoice_weight_unit    = trim((string)($sheet->getCell([5, $row])->getValue() ?? ''));
                                }

                                // AWB:	141-7002 8840	
                                // if (preg_match("/awb/i", $invoice_description)) {
                                //     $awb    = trim((string)($sheet->getCell([4, $row])->getValue() ?? ''));
                                // }


                                if ($invoice_serial_no > 2 && empty($invoice_description)) {
                                    $invoice_grand_qty  = trim((string)($sheet->getCell([8, $row])->getCalculatedValue() ?? ''));

                                    $invoice_grand_total_amount   = trim((string)($sheet->getCell([10, $row])->getCalculatedValue() ?? ''));
                                    if (!empty($invoice_grand_total_amount) && is_numeric($invoice_grand_total_amount)) {
                                        $invoice_grand_total_amount = round((float)$invoice_grand_total_amount, 3);
                                    } else {
                                        $invoice_grand_total_amount = 0; // or '' if you prefer empty instead of 0
                                    }
                                }
                            } // for



                        } catch (Exception $e) {
                            log_error('Excel import error: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'shipping_advices', 'action' => 'import']);
                            $error_message = "Error reading Excel file: " . $e->getMessage();
                        }
                    } else {
                        $error_message = "No file uploaded.";
                    }
                ?>


                    <div class="card card-body bg-warning mt-4">
                        <div class="text-center text-lg-start">
                            <span class="mb-0 text-white">Please review -> Imported Invoice Advice</span>
                        </div>
                    </div>


                    <div class="col-xl-12">

                        <div class="row">

                            <div class="col-xl-12">

                                <div class="row mb-2">

                                    <div class="col-lg-1">
                                        <label class="form-label ms-3 fw-semibold">Serial: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-3">
                                        <label class="form-label ms-3 fw-semibold">Description: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-1 text-center">
                                        <label class="form-label ms-3 fw-semibold">Origin: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-2">
                                        <label class="form-label fw-semibold">Declaration No: <span class="text-danger">*</span></label>
                                    </div>

                                    <div class="col-lg-1">
                                        <label class="form-label fw-semibold">HS Code: <span class="text-danger">*</span></label>
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


                                                if (!empty($invoice_description_arr[$index]) && !empty($invoice_origin_arr[$index]) && !empty($invoice_declaration_no_arr[$index]) && !empty($invoice_hs_code_arr[$index]) && !empty($invoice_qty_arr[$index]) && !empty($invoice_unit_price_arr[$index]) && !empty($invoice_total_amount_arr[$index])) {


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

                                                                    <input type="hidden" name="invoice_item_id[]" id="invoice_item_id<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($invoice_item_id_arr[$index]) ? $invoice_item_id_arr[$index] : ''); ?>">

                                                                    <div class="col-lg-1 text-center">
                                                                        <?php echo (!empty($invoice_serial_no_arr[$index]) ? $invoice_serial_no_arr[$index] : ''); ?>

                                                                        <input type="hidden" class="form-control text-center" name="invoice_serial_no[]" id="invoice_serial_no<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($invoice_serial_no_arr[$index]) ? $invoice_serial_no_arr[$index] : ''); ?>">
                                                                    </div>


                                                                    <div class="col-lg-3">
                                                                        <?php echo (!empty($invoice_description_arr[$index]) ? $invoice_description_arr[$index] : ''); ?>

                                                                        <input type="hidden" class="form-control" name="invoice_description[]" id="invoice_description<?php echo $shipping_invoice_item; ?>" class="form-control" value="<?php echo (!empty($invoice_description_arr[$index]) ? $invoice_description_arr[$index] : ''); ?>">
                                                                    </div>



                                                                    <div class="col-lg-1 text-center">
                                                                        <?php
                                                                        $alpha2_code = (!empty($invoice_origin_arr[$index]) ? $invoice_origin_arr[$index] : '');
                                                                        echo $alpha2_code;
                                                                        ?>

                                                                        <input type="hidden" class="form-control" name="invoice_origin[]" id="invoice_origin<?php echo $shipping_invoice_item; ?>" class="form-control" value="<?php echo (!empty($invoice_origin_arr[$index]) ? $invoice_origin_arr[$index] : ''); ?>">
                                                                    </div>

                                                                    <div class="col-lg-2">
                                                                        <?php echo (!empty($invoice_declaration_no_arr[$index]) ? $invoice_declaration_no_arr[$index] : ''); ?>

                                                                        <input type="hidden" class="form-control" name="invoice_declaration_no[]" id="invoice_declaration_no<?php echo $shipping_invoice_item; ?>" class="form-control" placeholder="Declaration no" value="<?php echo (!empty($invoice_declaration_no_arr[$index]) ? $invoice_declaration_no_arr[$index] : ''); ?>">
                                                                    </div>

                                                                    <div class="col-lg-1">
                                                                        <?php echo (!empty($invoice_hs_code_arr[$index]) ? $invoice_hs_code_arr[$index] : ''); ?>

                                                                        <input type="hidden" class="form-control" name="invoice_hs_code[]" id="invoice_hs_code<?php echo $shipping_invoice_item; ?>" value="<?php echo (!empty($invoice_hs_code_arr[$index]) ? $invoice_hs_code_arr[$index] : ''); ?>">
                                                                    </div>

                                                                    <div class="col-lg-1 text-center">
                                                                        <?php echo (!empty($invoice_qty_arr[$index]) ? $invoice_qty_arr[$index] : '0'); ?>

                                                                        <input type="hidden" step="1" name="invoice_qty[]" id="invoice_qty<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($invoice_qty_arr[$index]) ? $invoice_qty_arr[$index] : '0'); ?>"> <!--  step="0.1" value="0.0" -->
                                                                    </div>


                                                                    <div class="col-lg-1 text-center">
                                                                        <?php echo (!empty($invoice_unit_price_arr[$index]) ? $invoice_unit_price_arr[$index] : '0'); ?>

                                                                        <input type="hidden" step="1" name="invoice_unit_price[]" id="invoice_unit_price<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($invoice_unit_price_arr[$index]) ? $invoice_unit_price_arr[$index] : '0'); ?>" onkeyup="calculateItemAmount('<?php echo $shipping_invoice_item; ?>');" onchange=" calculateItemAmount('<?php echo $shipping_invoice_item; ?>');"> <!--  step="0.1" value="0.0" -->
                                                                    </div>

                                                                    <div class="col-lg-1 text-center">
                                                                        <?php echo (!empty($invoice_total_amount_arr[$index]) ? $invoice_total_amount_arr[$index] : ''); ?>

                                                                        <input type="hidden" name="invoice_total_amount[]" id="invoice_total_amount<?php echo $shipping_invoice_item; ?>" min="0" class="form-control text-end" placeholder="0" value="<?php echo (!empty($invoice_total_amount_arr[$index]) ? $invoice_total_amount_arr[$index] : ''); ?>" onchange="calculateGrand(<?php echo $shipping_invoice_item; ?>);" onkeyup="calculateGrand(<?php echo $shipping_invoice_item; ?>);"> <!--  oninput="this.value = Math.abs(this.value)" -->
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

                                                                <input type="hidden" name="invoice_grand_qty" id="invoice_grand_qty" min="0" class="form-control" value="<?php echo $invoice_grand_qty; ?>">
                                                            </div>

                                                            <div class="col-lg-1"></div>

                                                            <div class="col-lg-1 text-center fw-semibold">
                                                                <?php echo $invoice_grand_total_amount; ?>

                                                                <input type="hidden" name="invoice_grand_total_amount" id="invoice_grand_total_amount" min="0" class="form-control" value="<?php echo $invoice_grand_total_amount; ?>">
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
                                                <label class="col-lg-4 col-form-label">PLT/BOX/PKG's: <span class="text-danger">*</span></label>

                                                <div class="col-lg-6 mt-2 text-center fw-semibold">
                                                    <?php echo $invoice_pkgs; ?>

                                                    <input type="hidden" class="form-control text-center" name="invoice_pkgs" id="invoice_pkgs" value="<?php echo $invoice_pkgs; ?>">
                                                </div>

                                                <div class="col-lg-2 mt-2">
                                                    <span class="form-text text-muted"><?php echo $invoice_pkgs_unit; ?></span>

                                                    <input type="hidden" class="form-control text-center" name="invoice_pkgs_unit" id="invoice_pkgs_unit" value="<?php echo $invoice_pkgs_unit; ?>">
                                                </div>

                                            </div>

                                            <div class="row mb-2">
                                                <label class="col-lg-4 col-form-label">WEIGHT: <span class="text-danger">*</span></label>

                                                <div class="col-lg-6 mt-2 text-center fw-semibold">
                                                    <?php echo $invoice_weight; ?>

                                                    <input type="hidden" class="form-control text-center" name="invoice_weight" id="invoice_weight" value="<?php echo $invoice_weight; ?>">
                                                </div>

                                                <div class="col-lg-2 mt-2">
                                                    <span class="form-text text-muted"><?php echo $invoice_weight_unit; ?></span>

                                                    <input type="hidden" class="form-control text-center" name="invoice_weight_unit" id="invoice_weight_unit" value="<?php echo $invoice_weight_unit; ?>">
                                                </div>
                                            </div>


                                            <div class="row">
                                                <label class="col-lg-4 col-form-label">AWB: <span class="text-danger">*</span></label>
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


                <?php
                } // file import
                ?>

            </form>
        </div>


        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>


<?php include('admin_elements/admin_footer.php'); ?>