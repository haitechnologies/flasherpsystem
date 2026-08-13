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
$id = $_REQUEST['id'] ?? '';


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

// Validate required params
if (empty($id) && empty($id_list)) {
    header("Location:listing_$module.php");
    exit;
}

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

$publish = '';

?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0"><?php if (($action == "edit_$module" || $action == "update_$module" || $action == "change_password") && !empty($id)) { ?>Edit<?php } else { ?>New<?php } ?> <?php echo $module_caption; ?></h5>
            </div>

            <div class="my-1 d-inline-flex align-items-center me-2">
                <div class="form-check form-check-inline form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" <?php if ($publish == '1') { ?>checked="checked" <?php } ?> form="frmshipping_stocks">
                    <label class="form-check-label" for="publish">Publish</label>
                </div>
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

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
        <input type="hidden" name="id_list" id="id_list" value="<?php echo $id_list; ?>" />

        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
            <input type="hidden" name="action" id="action" value="update_shipping_stocks" />
            <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
        <?php } else { ?>
            <input type="hidden" name="action" id="action" value="add_shipping_stocks" />
        <?php } ?>

        <!-- Page header -->


                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                        Stock OUT Details
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Invoice Date: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input disabled type="text" class="form-control" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date; ?>">
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
                                        <label class="col-lg-3 col-form-label">Consignee: <span class="text-danger">*</span> </label>
                                        <div class="col-lg-9">
                                            <select name="consignee_id" id="consignee_id" class="form-select" disabled>
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


                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Destination Port: <span class="text-danger">*</span> </label>
                                        <div class="col-lg-3">
                                            <select class="form-select" name="destination_port" id="destination_port" disabled onchange="ajax_select_port_country('destination', this.value);">
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

                                        <label class="col-lg-2 col-form-label">Country: <span class="text-danger">*</span> </label>
                                        <div class="col-lg-4">
                                            <select disabled class="form-select <?php echo ((!empty($destination_port)) ? 'bg-light' : '') ?>" name="destination_country" id="destination_country" <?php echo ((!empty($destination_port)) ? 'style="pointer-events:none;"' : '') ?> onchange="ajax_select_country_ports('destination');"> <!--  style="pointer-events:none;"-->
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
                                        <label class="col-lg-3 col-form-label">Incoterms: <span class="text-danger">*</span> </label>
                                        <div class="col-lg-9">
                                            <select disabled name="incoterm" id="incoterm" class="form-control select">
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
                                                <th width="150">OUT NOW</th>
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

                                                $rs         = $mysqli->query("SELECT out_qty FROM `" . DB::SHIPPING_STOCK_ITEMS . "` WHERE stock_id=$id AND shipping_advice_item_id=$shipping_advice_item_id");
                                                $rw         = $rs->fetch_array();
                                                $out_qty  = $rw['out_qty'];
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
                                                        <input disabled type="number" min="1" max="<?php echo $qty; ?>" class="form-control" name="out_qty[]" id="out_qty<?php echo $shipping_advice_item_id; ?>" value="<?php echo $out_qty; ?>">
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


        </form>
    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>