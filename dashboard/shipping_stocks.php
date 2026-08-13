<?php


use App\Core\DB;
use App\Core\Session;
include('admin_elements/admin_header.php');
require '../vendor/autoload.php';


$module = 'shipping_stocks';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Stock';
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
// print_r($_REQUEST);

$id_list = $_REQUEST['id_list'] ?? '';


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

// if (empty($id_list)) {
//     echo $error_message = 'No items found. Please select at least 1 Shipping Advice Item to Proceed for Stock Management.';
//     exit;
// }

/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

$invoice_date               = ((isset($_POST['invoice_date']) && !empty($_POST['invoice_date'])) ? e_s__($_POST['invoice_date']) : '');
$consignee_id               = ((isset($_POST['consignee_id']) && !empty($_POST['consignee_id'])) ? e_s__($_POST['consignee_id']) : '');
$destination_port           = ((isset($_POST['destination_port']) && !empty($_POST['destination_port'])) ? e_s__($_POST['destination_port']) : '');
$destination_country        = ((isset($_POST['destination_country']) && !empty($_POST['destination_country'])) ? e_s__($_POST['destination_country']) : '');
$incoterm                   = ((isset($_POST['incoterm']) && !empty($_POST['incoterm'])) ? e_s__($_POST['incoterm']) : '');


// ---------------------- Items -----------------------------
$item_id_arr                = array();
$out_qty_arr                = array();



/*
|--------------------------------------------------------------------------
| 	UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" && !empty($id) && granted('edit', $module_id)) {


    if (empty($invoice_date)) {
        $error_message = 'Invoice Date is mandatory.';
    } else if (empty($consignee_id)) {
        $error_message = 'Please select Consignee.';
    } else if (empty($destination_port)) {
        $error_message = 'Please select Destination Port.';
    } else if (empty($destination_country)) {
        $error_message = 'Please select Destination Country.';
    } else if (empty($incoterm)) {
        $error_message = 'Please select Incoterm.';
    } else {

        $invoice_date = processDateDtoY($invoice_date);

        /* ---------------------- QUERY ---------------------- */
        $update_row = $mysqli->query("
        									UPDATE `$tbl_name` SET
                                                invoice_date            = '" . $invoice_date . "',
                                                consignee_id            = '" . $consignee_id . "',
                                                destination_port        = '" . $destination_port . "',
                                                destination_country     = '" . $destination_country . "',
                                                incoterm                = '" . $incoterm . "'
        									WHERE id=$id");
        $stock_id = $id;

        if ($update_row) {
            fp__($tbl_name, $stock_id);
            $success_message = "Shipping Stock has been updated successfully.";

            $total_rows = count(explode(',', $id_list));

            for ($quotation_item = 1; $quotation_item <= $total_rows; $quotation_item++) {

                $index = $quotation_item;
                $index = $index - 1;

                $item_id           = e_s__($_POST['item_id'][$index]);
                $item_out_qty      = e_s__($_POST['out_qty'][$index]);


                if (!empty($item_out_qty)) {

                    // -- SAVE STOCK
                    $item_out_qty       = (($item_out_qty == '') ? 0 : $item_out_qty);

                    /* ---------------------- QUERY ---------------------- */
                    $mysqli->query("UPDATE `" . DB::SHIPPING_STOCK_ITEMS . "` SET out_qty = '" . $item_out_qty . "' WHERE stock_id=$stock_id AND shipping_advice_item_id=$item_id");
                    // ---------------------------------------------
                }
            } //for 

            flash_success($success_message);
            header("Location:listing_shipping_stocks.php");
        } else {
            $error_message = "Failed to save the item. Please try again";
            //header("Location:$module.php?error_message=$error_message");
        }
    }



    /*
|--------------------------------------------------------------------------
| 	ADD
|--------------------------------------------------------------------------
|
*/
} else if ($action == "add_$module" && granted('create', $module_id)) {


    if (empty($invoice_date)) {
        $error_message = 'Invoice Date is mandatory.';
    } else if (empty($consignee_id)) {
        $error_message = 'Please select Consignee.';
    } else if (empty($destination_port)) {
        $error_message = 'Please select Destination Port.';
    } else if (empty($destination_country)) {
        $error_message = 'Please select Destination Country.';
    } else if (empty($incoterm)) {
        $error_message = 'Please select Incoterm.';
    } else {

        $invoice_date = processDateDtoY($invoice_date);

        $insert_row = $mysqli->query("INSERT INTO `$tbl_name`(invoice_date, consignee_id, destination_port, destination_country, incoterm) VALUES ('" . $invoice_date . "', '" . $consignee_id . "', '" . $destination_port . "', '" . $destination_country . "', '" . $incoterm . "'); ");

        if ($insert_row) {
            $stock_id = $mysqli->insert_id;
            fp__($tbl_name, $stock_id);
            $success_message = "Shipping Stock has been saved successfully.";

            $total_rows = count(explode(',', $id_list));

            for ($quotation_item = 1; $quotation_item <= $total_rows; $quotation_item++) {

                $index = $quotation_item;
                $index = $index - 1;

                $item_id           = e_s__($_POST['item_id'][$index]);
                $item_out_qty      = e_s__($_POST['out_qty'][$index]);

                if (!empty($item_out_qty)) {

                    // -- SAVE STOCK
                    $item_out_qty       = (($item_out_qty == '') ? 0 : $item_out_qty);

                    $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_STOCK_ITEMS . "`(stock_id, shipping_advice_item_id, out_qty) VALUES ('" . $stock_id . "', '" . $item_id . "', '" . $item_out_qty . "'); ");
                    // ---------------------------------------------
                }
            } //for 

            flash_success($success_message);
            header("Location:listing_shipping_stocks.php");
        } else {
            $error_message = "Failed to save the item. Please try again";
            //header("Location:$module.php?error_message=$error_message");
        }
    }
}



/*
|--------------------------------------------------------------------------
| EDIT - ONLY SUPERADMIN or RELEVANT USER
|--------------------------------------------------------------------------
|
*/
$created_by = getTableAttr('created_by', DB::QUOTATIONS, $id);

if (
    (!empty($id) && Session::roleId() == '1')
    ||
    (!empty($id) && $_SESSION[$project_pre]['DASHBOARD']['admin_id'] == $created_by)
) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    $invoice_date               = s__($row['invoice_date']);
    $invoice_date               = processDateYtoD($invoice_date);

    $consignee_id               = s__($row['consignee_id']);
    $destination_port           = s__($row['destination_port']);
    $destination_country        = s__($row['destination_country']);
    $incoterm                   = s__($row['incoterm']);

    // id_list from DB
    $result = $mysqli->query("SELECT shipping_advice_item_id FROM `" . DB::SHIPPING_STOCK_ITEMS . "` WHERE stock_id=$id");
    while ($row    = $result->fetch_array()) {
        $id_list    .= $row['shipping_advice_item_id'] . ',';
    }

    $id_list = rtrim($id_list, ',');
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
                <h5 class="mb-0"><?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>Update<?php } else { ?>Create<?php } ?> <?php echo $module_caption; ?></h5>
                <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
                    <span class="badge bg-light text-dark border fw-semibold">Stock Invoice #: <?php echo $id; ?></span>
                <?php } ?>
                <?php if (!empty($quotation_status)) { ?>
                    <span class="badge bg-secondary-subtle text-secondary fw-semibold"><strong><?php echo ucwords($quotation_status); ?></strong></span>
                <?php } ?>
            </div>

            <div class="my-1">
                <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm me-2"><?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>Update<?php } else { ?>Save<?php } ?></button>
                <?php } ?>
                <a href="listing_shipping_advice_items.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="id_list" id="id_list" value="<?php echo $id_list; ?>" />
                <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
                    <input type="hidden" name="action" id="action" value="update_shipping_stocks" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php } else { ?>
                    <input type="hidden" name="action" id="action" value="add_shipping_stocks" />
                <?php } ?>

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                        Record -> Stock OUT
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Invoice Date:*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <!--
                                    |--------------------------------------------------------------------------------------- 
                                    | CONSIGNEE DROPDOWN / ADD NEW CONSIGNEE 
                                    |--------------------------------------------------------------------------------------- 
                                     -->


                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Consignee:*</span> <i class="ph-plus-circle" id="openConsigneePopup" style="cursor:pointer;"></i> </label>
                                        <div class="col-lg-9">
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



                                    <!-- Popup Modal -->
                                    <div class="modal fade" id="consigneeModal" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form id="popupForm">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Consignee Info</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <!-- <input type="hidden" id="consignee_id" name="consignee_id"> -->

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label"></label>
                                                            <div class="col-lg-9">
                                                                <span id="ajax_consignee_error_message" class="text-danger"></span>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label"><span class="text-danger">Name:*</span></label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_name" name="consignee_name" required>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label"><span class="text-danger">Street Address1:*</span></label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_address_line1" name="consignee_address_line1" required>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Street Address2:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_address_line2" name="consignee_address_line2">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">City:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_city" name="consignee_city">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Zip/Postal Code:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_zipcode" name="consignee_zipcode">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Province:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_province" name="consignee_province">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Country:</label>
                                                            <div class="col-lg-9">
                                                                <select required class="form-select" name="consignee_country" id="consignee_country">
                                                                    <option value="0"></option>
                                                                    <?php
                                                                    // -------------------------------------------------------------------------------------------------
                                                                    $result = $mysqli->query("SELECT * FROM `" . $tbl_prefix . "geo_countries` WHERE is_active=1 ORDER BY country");
                                                                    while ($rows = $result->fetch_array()) {
                                                                        // -------------------------------------------------------------------------------------------------
                                                                    ?>
                                                                        <option value="<?php echo $rows['id']; ?>">
                                                                            <?php echo $rows['country']; ?>
                                                                        </option>
                                                                    <?php } ?>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Email:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_email" name="consignee_email">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Telephone:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_telephone" name="consignee_telephone">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Mobile:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_mobile" name="consignee_mobile">
                                                            </div>
                                                        </div>

                                                        <div class="row mb-2">
                                                            <label class="col-lg-3 col-form-label">Fax:</label>
                                                            <div class="col-lg-9">
                                                                <input type="text" class="form-control" id="consignee_fax" name="consignee_fax">
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" onclick="create_consignee();" class="btn btn-success">Save</button>
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <script>
                                        function create_consignee() {
                                            // $("#consigneeModal").modal("hide");

                                            let consignee_name = $("#consignee_name").val();
                                            let consignee_address_line1 = $("#consignee_address_line1").val();
                                            let consignee_address_line2 = $("#consignee_address_line2").val();
                                            let consignee_city = $("#consignee_city").val();
                                            let consignee_zipcode = $("#consignee_zipcode").val();
                                            let consignee_province = $("#consignee_province").val();
                                            let consignee_country = $("#consignee_country").val();
                                            let consignee_email = $("#consignee_email").val();
                                            let consignee_telephone = $("#consignee_telephone").val();
                                            let consignee_mobile = $("#consignee_mobile").val();
                                            let consignee_fax = $("#consignee_fax").val();

                                            if (consignee_name == '') {
                                                document.getElementById('ajax_consignee_error_message').innerHTML = 'Name is mandatory';
                                            } else if (consignee_address_line1 == '') {
                                                document.getElementById('ajax_consignee_error_message').innerHTML = 'Street Address1 is mandatory';
                                            } else {

                                                // Call function
                                                ajax_add_consignee(
                                                    consignee_name, consignee_address_line1, consignee_address_line2,
                                                    consignee_city, consignee_zipcode, consignee_province, consignee_country,
                                                    consignee_email, consignee_telephone, consignee_mobile, consignee_fax
                                                );

                                            } // end if

                                        } // function


                                        $(document).ready(function() {
                                            // Open popup with existing data
                                            $("#openConsigneePopup").on("click", function() {
                                                $("#popupId").val($("#consigneeIdMaster").val());
                                                $("#popupName").val($("#consigneeName").val());
                                                // $("#popupEmail").val($("#consigneeEmail").val());
                                                $("#consigneeModal").modal("show");
                                            });
                                        });
                                    </script>


                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Destination Port:*</span> </label>
                                        <div class="col-lg-3">
                                            <select class="form-select" name="destination_port" id="destination_port" onchange="ajax_select_port_country('destination', this.value);">
                                                <option value="0"></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                if (!empty($destination_country)) {
                                                    $result = $mysqli->query("SELECT * FROM `" . $tbl_prefix . "ports` WHERE is_active=1 AND country_id=$destination_country ORDER BY port_name");
                                                } else {
                                                    $result = $mysqli->query("SELECT * FROM `" . $tbl_prefix . "ports` WHERE is_active=1 ORDER BY port_name");
                                                }
                                                while ($rows = $result->fetch_array()) {
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $destination_port) { ?>selected <?php } else if ($rows['id'] == $destination_port) { ?>selected <?php } ?>>
                                                        <?php echo $rows['port_code']; ?> - <?php echo $rows['port_name']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>

                                        <label class="col-lg-2 col-form-label"><span class="text-danger">Country:*</span> </label>
                                        <div class="col-lg-4">
                                            <select class="form-select <?php echo ((!empty($destination_port)) ? 'bg-light' : '') ?>" name="destination_country" id="destination_country" <?php echo ((!empty($destination_port)) ? 'style="pointer-events:none;"' : '') ?> onchange="ajax_select_country_ports('destination');"> <!--  style="pointer-events:none;"-->
                                                <option value="0"></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result = $mysqli->query("SELECT * FROM `" . $tbl_prefix . "geo_countries` WHERE is_active=1 ORDER BY country");
                                                while ($rows = $result->fetch_array()) {
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $destination_country) { ?>selected <?php } else if ($rows['id'] == $destination_country) { ?>selected <?php } ?>>
                                                        <?php echo $rows['country']; ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Incoterms:*</span> </label>
                                        <div class="col-lg-9">
                                            <select name="incoterm" id="incoterm" class="form-control select">
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
                            </div>
                        </div>

                    </div>


                    <div>


                        <?php
                        // ------------------- STOCK MANAGEMENT -------------------
                        if (!empty($id_list)) {

                            //NORMAL QUERY
                            $result_shipping_advice     = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE id IN ($id_list) ORDER BY id ASC");

                        ?>

                            <div class="card pb-3">

                                <div class="table-responsive">
                                    <table class="table">

                                        <thead>
                                            <tr>
                                                <!-- <th width="100">&nbsp;</th> -->
                                                <th>HS CODE</th>
                                                <th>DESCRIPTION</th>
                                                <th>TOTAL QTY</th>
                                                <th>REMAINING QTY</th>
                                                <th>ORIGIN</th>
                                                <th>VALUE</th>
                                                <th>WEIGHT(KG)</th>
                                                <th>INVOICE #</th>
                                                <th width="150"><span class="text-danger">OUT NOW</span></th>
                                            </tr>
                                        </thead>


                                        <tbody>

                                            <?php
                                            $sr = 1;
                                            // ---------------------------------------------------------------------------------------
                                            while ($row_shipping_advice_item = $result_shipping_advice->fetch_array(MYSQLI_ASSOC)) {

                                                $shipping_advice_item_id    = $row_shipping_advice_item["id"];
                                                $advice_id                  = $row_shipping_advice_item["advice_id"];
                                                $invoice_date               = getTableAttr('invoice_no', DB::SHIPPING_ADVICES, $advice_id);
                                                $hs_code                    = $row_shipping_advice_item["hs_code"];
                                                $description                = $row_shipping_advice_item["description"];
                                                $qty                        = $row_shipping_advice_item["qty"];
                                                $remaining_qty              = isset($row_shipping_advice_item["remaining_qty"]) ? $row_shipping_advice_item["remaining_qty"] : 0;
                                                $origin                     = $row_shipping_advice_item["origin"];
                                                $value                      = $row_shipping_advice_item["value"];
                                                $weight                     = $row_shipping_advice_item["weight"];
                                                $created_at                 = $row_shipping_advice_item["created_at"];

                                                $invoice_no = getTableAttr('invoice_no', DB::SHIPPING_ADVICES, $advice_id);

                                                $out_qty = '0';

                                                if ($action == "edit_$module") {
                                                    $rs         = $mysqli->query("SELECT out_qty FROM `" . DB::SHIPPING_STOCK_ITEMS . "` WHERE stock_id=$id AND shipping_advice_item_id=$shipping_advice_item_id");
                                                    $rw         = $rs->fetch_array();
                                                    $out_qty  = $rw['out_qty'];
                                                }
                                                // ---------------------------------------------------------------------------------------
                                            ?>

                                                <tr>
                                                    <td>
                                                        <input type="hidden" name="item_id[]" id="item_id<?php echo $shipping_advice_item_id; ?>" value="<?php echo $shipping_advice_item_id; ?>">
                                                        <?php echo $hs_code; ?>
                                                    </td>
                                                    <td><?php echo $description; ?> </td>
                                                    <td><?php echo $qty; ?> </td>
                                                    <td>
                                                        <?php
                                                        // Calculate Remaining QTY
                                                        $remaining_qty = 0;
                                                        $rs         = $mysqli->query("SELECT sum(out_qty) FROM `" . DB::SHIPPING_STOCK_ITEMS . "` WHERE shipping_advice_item_id=$shipping_advice_item_id");
                                                        $rw         = $rs->fetch_array();
                                                        // $out_qty    = $rw[0];
                                                        $remaining_qty = (($rw[0] > 0) ? $rw[0] : 0);
                                                        echo $remaining_qty = $qty - $remaining_qty;
                                                        ?>
                                                    </td>
                                                    <td><?php echo $origin; ?> </td>
                                                    <td><?php echo $value; ?> </td>
                                                    <td><?php echo $weight; ?> </td>
                                                    <td><a href="view_shipping_advice.php?id=<?php echo $advice_id; ?>" target="_blank"> <?php echo $invoice_no; ?> </a></td>
                                                    <td>
                                                        <input type="number" min="1" max="<?php echo $remaining_qty; ?>" class="form-control out-qty-input" name="out_qty[]" id="out_qty<?php echo $shipping_advice_item_id; ?>" value="<?php echo $out_qty; ?>" data-remaining-qty="<?php echo $remaining_qty; ?>" data-item-id="<?php echo $shipping_advice_item_id; ?>">
                                                        <small class="text-danger error-msg" id="error_out_qty<?php echo $shipping_advice_item_id; ?>" style="display:none;"></small>
                                                    </td>
                                                </tr>


                                            <?php
                                            } //while
                                            ?>

                                </div>


                                </tbody>
                                </table>
                            </div>
                    </div>
                <?php } // if
                ?>

                </div>
            </div>

        </div>


        <script>
        // Validate OUT QTY input - ensure it doesn't exceed REMAINING QTY
        $(document).ready(function() {

            // Validate on input change
            $('.out-qty-input').on('input change', function() {
                var inputValue = parseFloat($(this).val()) || 0;
                var remainingQty = parseFloat($(this).data('remaining-qty')) || 0;
                var itemId = $(this).data('item-id');
                var errorElement = $('#error_out_qty' + itemId);

                // Clear previous error styling
                $(this).removeClass('border-danger');
                errorElement.hide();

                if (inputValue > remainingQty) {
                    $(this).addClass('border-danger');
                    errorElement.text('Cannot exceed remaining quantity (' + remainingQty + ')').show();
                } else if (inputValue < 0) {
                    $(this).addClass('border-danger');
                    errorElement.text('Value cannot be negative').show();
                }
            });

            // Validate on form submission
            $('#frmshipping_stocks').on('submit', function(e) {
                var hasError = false;

                $('.out-qty-input').each(function() {
                    var inputValue = parseFloat($(this).val()) || 0;
                    var remainingQty = parseFloat($(this).data('remaining-qty')) || 0;
                    var itemId = $(this).data('item-id');
                    var errorElement = $('#error_out_qty' + itemId);

                    if (inputValue > remainingQty) {
                        hasError = true;
                        $(this).addClass('border-danger');
                        errorElement.text('Cannot exceed remaining quantity (' + remainingQty + ')').show();
                    } else if (inputValue < 0) {
                        hasError = true;
                        $(this).addClass('border-danger');
                        errorElement.text('Value cannot be negative').show();
                    }
                });

                if (hasError) {
                    e.preventDefault();
                    alert('Please correct the errors in OUT NOW quantities before submitting.');
                    return false;
                }

                return true;
            });
        });
        </script>

        </form>
        </div>


        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>