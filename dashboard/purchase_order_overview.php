<?php

use App\Core\DB;
use App\Core\Session;
include('admin_elements/admin_header.php');

$module = 'purchase_orders';
$module_caption = 'Purchase order';
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

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$purchase_order_id = '';
if (isset($_REQUEST['purchase_order_id']))        $purchase_order_id     = e_s__($_REQUEST['purchase_order_id']);
if (isset($_POST['purchase_order_id']))           $purchase_order_id     = e_s__($_POST['purchase_order_id']);
if (empty($purchase_order_id) && isset($_REQUEST['id'])) $purchase_order_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_purchase_orders . "` WHERE id='" . $purchase_order_id . "' AND organization_id='" . (int)$activeOrganizationId . "'");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_purchase_orders.php");
    exit;
}



// ------------------ CHECK IF PURCHASE ORDER IS CONVERTED - INVOICED ----------------
$invoiced = 0;
$rs_converted     = $mysqli->query("SELECT purchase_order_status FROM `" . tbl_purchase_orders . "` WHERE id ='" . $purchase_order_id . "' AND purchase_order_status='invoiced' ");
if ($rs_converted->num_rows > 0) {
    $invoiced = 1;
    // $success_message = 'This Purchase Order is Conveted into Invoice.';
}



/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$purchase_order_status = 0;
if (isset($_REQUEST['purchase_order_status']) && !empty($_REQUEST['purchase_order_status'])) {
    $purchase_order_status   = e_s__($_REQUEST['purchase_order_status']);
}


$purchase_order_item_id = 0;
if (isset($_REQUEST['purchase_order_item_id']) && !empty($_REQUEST['purchase_order_item_id'])) {
    $purchase_order_item_id     = e_s__($_REQUEST['purchase_order_item_id']);
}


/*
|--------------------------------------------------------------------------
| CONVERT TO PURCHASE
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($purchase_order_id))) {

    // Guard against double conversion: a PO already converted to a purchase
    // must not be converted again (would create orphan duplicate purchases).
    $rs_po_status = $mysqli->query("SELECT purchase_order_status, purchase_id FROM `" . tbl_purchase_orders . "` WHERE id='" . (int)$purchase_order_id . "' AND organization_id='" . (int)$activeOrganizationId . "'");
    $po_status_row = $rs_po_status ? $rs_po_status->fetch_assoc() : null;
    if ($po_status_row && ($po_status_row['purchase_order_status'] === 'purchased' || !empty($po_status_row['purchase_id']))) {
        flash_error('This Purchase Order has already been converted to a Purchase.');
        header("Location:purchase_order_overview.php?purchase_order_id=" . (int)$purchase_order_id);
        exit;
    }

    // ======================================================
    // PURCHASE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-PR' . date('ym');

    // Get the last purchase number for this month
    $sql = "SELECT purchase_no  FROM `" . tbl_purchases . "`  WHERE purchase_no LIKE '{$prefix}-%'ORDER BY purchase_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['purchase_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First purchase of the month
        $new_serial = 1;
    }

    // Build new purchase number with zero padding
    $purchase_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- purchase
    $result = $mysqli->query("INSERT INTO `" . DB::PURCHASES . "` (vendor_id, warehouse_id, subject, reference_no, purchase_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, vendor_notes, terms_and_conditions, purchase_status, is_active, organization_id, created_at, updated_at)
     SELECT vendor_id, warehouse_id, subject, reference_no, NOW(), NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, vendor_notes, terms_and_conditions, 'draft', is_active, organization_id, NOW(), NOW() FROM `" . DB::PURCHASE_ORDERS . "` WHERE id = $purchase_order_id;");

    $new_purchase_id = $mysqli->insert_id;
    fp__($tbl_name, $new_purchase_id);

    // Update purchase no
    $mysqli->query("UPDATE `" . DB::PURCHASES . "` SET purchase_no = '" . $purchase_no . "', purchase_order_id = $purchase_order_id WHERE id=$new_purchase_id");

    // -- purchase Items
    $result = $mysqli->query("INSERT INTO `" . DB::PURCHASE_ITEMS . "` ( purchase_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_purchase_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id = $purchase_order_id");

    fp__(DB::PURCHASE_ITEMS, $mysqli->insert_id);


    $success_message = 'This Purchase Order has been Converted to Purchase Successfully. Please click here to view. <a href="purchase_overview.php?purchase_id=' . $new_purchase_id . '"> ' . $purchase_no . '</a>';

    // CHANGE STATUS OF QUOATION TO PURCHASED
    $mysqli->query("UPDATE `" . tbl_purchase_orders . "` SET purchase_id = $new_purchase_id,  purchase_order_status = 'purchased' WHERE id=$purchase_order_id");



    /*
|--------------------------------------------------------------------------
| CLONE PURCHAES ORDER
|--------------------------------------------------------------------------
|
*/
} else if (($action == "clone_$module" && !empty($purchase_order_id))) {

    // ======================================================
    // PURCHASE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-PO' . date('ym');

    // Get the last purchase number for this month
    $sql = "SELECT purchase_order_no  FROM `" . tbl_purchase_orders . "`  WHERE purchase_order_no LIKE '{$prefix}-%'ORDER BY purchase_order_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['purchase_order_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First purchase of the month
        $new_serial = 1;
    }

    // Build new purchase number with zero padding
    $purchase_order_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Purchase order
    $result = $mysqli->query("INSERT INTO `" . tbl_purchase_orders . "` (vendor_id, warehouse_id, purchase_order_no, subject, reference_no, purchase_order_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, vendor_notes, terms_and_conditions, purchase_order_status, is_active, organization_id, created_at, updated_at)
    SELECT vendor_id, warehouse_id, '" . $purchase_order_no . "', subject, reference_no, NOW(), NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, vendor_notes, terms_and_conditions, 'draft', is_active, organization_id, NOW(), NOW() FROM `" . tbl_purchase_orders . "` WHERE id = $purchase_order_id;");

    $new_cloned_id = $mysqli->insert_id;
    fp__($tbl_name, $new_cloned_id);

    // -- Purchase order Items
    $result = $mysqli->query("INSERT INTO `" . tbl_purchase_order_items . "` ( purchase_order_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_cloned_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_purchase_order_items . "` WHERE purchase_order_id = $purchase_order_id");

    fp__(tbl_purchase_order_items, $mysqli->insert_id);


    $success_message = 'Purchase order has been cloned Successfully. Please click here to view. <a href="purchase_order_overview.php?purchase_order_id=' . $new_cloned_id . '"> ' . $purchase_order_no . '</a>';





    /*
|--------------------------------------------------------------------------
| UPDATE PURCHASE ORDER STATUS
|--------------------------------------------------------------------------
|
*/
} else if (($action == "update_$module" && !empty($purchase_order_id) && !empty($purchase_order_status))) {

    $result = $mysqli->query("UPDATE `$tbl_name` SET purchase_order_status = '" . $purchase_order_status . "' WHERE id=$purchase_order_id");

    if ($result) {
        $success_message = "The $module_caption status has been updated successfully.";


        // ------------ Purchase order Log -------------
        // if (isset($_POST['purchase_order_log_comments']) && !empty($_POST['purchase_order_log_comments'])) {
        //     $purchase_order_log_comments     = e_s__($_POST['purchase_order_log_comments']);

        //     $mysqli->query("INSERT INTO `" . tbl_purchase_order_logs . "` (purchase_order_id, purchase_order_status, comments) VALUES ('" . $purchase_order_id . "', '" . $purchase_order_status . "', '" . $purchase_order_log_comments . "'); ");
        //     fp__(tbl_purchase_order_logs, $mysqli->insert_id);
        // }


        if ($purchase_order_status == 'not_confirmed') {


            // Delete PDF - Next Time System will Generate New with Confirmed Status 
            $pdf        = getTableAttr('pdf', tbl_purchase_orders, $purchase_order_id);
            if (!empty($pdf) && is_file("../pdfs_purchase_orders/" . $pdf . ".pdf")) {
                unlink("../pdfs_purchase_orders/" . $pdf . ".pdf");
            }
            $mysqli->query("UPDATE " . tbl_purchase_orders . "  SET pdf = '' WHERE id=$purchase_order_id");
        } else if ($purchase_order_status == 'confirmed') {


            // Delete PDF - Next Time System will Generate New with Confirmed Status 
            // $pdf        = getTableAttr('pdf', tbl_purchase_orders, $purchase_order_id);
            // unlink("../pdfs_purchase_orders/" . $pdf . ".pdf");
            // $mysqli->query("UPDATE " . tbl_purchase_orders . "  SET pdf = '' WHERE id=$purchase_order_id");


            // -----------------------------------------------------------------------------------------------------------------------------------------------
            // DELETE TRIPS - IF Purchase order STATUS IS CANCELLED
            // -----------------------------------------------------------------------------------------------------------------------------------------------
        } else if ($purchase_order_status == 'cancelled' || $purchase_order_status == 'on_hold') {

            // Delete PDF - Next Time System will Generate New 
            // $pdf        = getTableAttr('pdf', tbl_purchase_orders, $purchase_order_id);
            // unlink("../pdfs_purchase_orders/" . $pdf . ".pdf");
            // $mysqli->query("UPDATE " . tbl_purchase_orders . "  SET pdf = '' WHERE id=$purchase_order_id");


            /* ---------------------- NOTIFICATIONS QUERY ---------------------- */
        }


        // --------------------------------------------------------------------------------
        flash_success($success_message);
        header("Location:purchase_order_overview.php?purchase_order_id=$purchase_order_id");
        exit;
        // $error_message = "Sorry! $module Status Could Not Be Updated.";
    } else {
        $error_message = "Sorry! $module Status Could Not Be Updated.";
    }
}




/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/

$purchase_order_item_id_arr      = array();
$item_id_arr                = array();
$service_arr                = array();
$description_arr            = array();
$qty_arr                    = array();
$rate_arr                   = array();
$sub_total_arr              = array();
$tax_arr                    = array();
$tax_amount_arr             = array();
$total_arr                  = array();



if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows            = e_s__($_POST['total_rows']);
    // if ($total_rows == 0 || $total_rows == '') $total_rows = 1;
} else {
    $total_rows            = 1;
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_purchase_order.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_purchase_order.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>


            <?php

            /*
                |--------------------------------------------------------------------------
                | EDIT
                |--------------------------------------------------------------------------
                |
                */
            if (!empty($purchase_order_id)) {

                $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$purchase_order_id AND organization_id=" . (int)$activeOrganizationId);
                $row = $result->fetch_array();

                $vendor_id                  = s__($row['vendor_id']);
                $warehouse_id               = s__($row['warehouse_id']);

                $purchase_order_no          = s__($row['purchase_order_no']);
                $purchase_order_status      = s__($row['purchase_order_status']);
                $purchase_order_date        = s__($row['purchase_order_date']);
                $expiry_date                = s__($row['expiry_date']);
                $vendor_notes               = s__($row['vendor_notes']);

                $terms_and_conditions       = s__($row['terms_and_conditions']);
                // Seprate Line Number on base of Space new line
                $final_terms_and_conditions = '';

                if (!empty($terms_and_conditions)) {
                    $desc = explode("\r", $terms_and_conditions);
                    $d_counter = 1;
                    if (count($desc) > 0) {
                        foreach ($desc as $d) {
                            if (!empty($d)) {
                                // $final_terms_and_conditions .= $d_counter++ . '. ' . $d . '<br />';
                                $final_terms_and_conditions .= $d . '<br />';
                            }
                        }
                    }
                }



                $grand_subtotal             = s__($row['grand_subtotal']);
                $grand_discount_type        = s__($row['grand_discount_type']);
                $grand_discount_type_value  = s__($row['grand_discount_type_value']);
                $grand_discount_amount      = s__($row['grand_discount_amount']);
                $grand_after_discount       = s__($row['grand_after_discount']);
                $grand_tax                  = s__($row['grand_tax']);
                $grand_total                = s__($row['grand_total']);

                $publish                = s__($row['is_active']);



                // --- Vendor Information
                $rs = $mysqli->query("SELECT * FROM `" . tbl_vendors . "` WHERE id=$vendor_id");
                $row_vendor = $rs->fetch_array();
                $salutation             = s__($row_vendor['salutation']);
                $first_name             = s__($row_vendor['first_name']);
                $last_name              = s__($row_vendor['last_name']);
                $company_name           = s__($row_vendor['company_name']);
                $display_name           = s__($row_vendor['display_name']);
                $email                  = s__($row_vendor['email']);
                $phone                  = s__($row_vendor['phone']);
                $mobile                 = s__($row_vendor['mobile']);
                $trn                    = s__($row_vendor['trn']);

                // Vendor Billing Address 
                $rs_billing     = $mysqli->query("SELECT * FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendor_id AND type='billing' ");
                $row_billing    = $rs_billing->fetch_array();

                $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');
                $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
                $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
                $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
                $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
                $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
                $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
                $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
                $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');


                $purchase_order_date    = ddm_($purchase_order_date);
                $expiry_date            = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : ddm_($expiry_date);


                // ------------------ TOTAL PURCHASE ORDER ITEMS ------------------
                // echo "SELECT * FROM `" . tbl_purchase_order_items . "` WHERE purchase_order_id=$purchase_order_id ORDER BY requested_date";
                $result_purchase_order_items     = $mysqli->query("SELECT * FROM `" . tbl_purchase_order_items . "` WHERE purchase_order_id=$purchase_order_id ORDER BY id");
                $total_rows                 = $result_purchase_order_items->num_rows;


                if ($total_rows > 0) {
                    while ($row_purchase_order_items = $result_purchase_order_items->fetch_array()) {

                        array_push($purchase_order_item_id_arr,      $row_purchase_order_items['id']);
                        array_push($service_arr,                $row_purchase_order_items['service']);
                        array_push($description_arr,            $row_purchase_order_items['description']);
                        array_push($qty_arr,                    $row_purchase_order_items['qty']);
                        array_push($rate_arr,                   $row_purchase_order_items['rate']);
                        array_push($sub_total_arr,              $row_purchase_order_items['sub_total']);
                        array_push($tax_arr,                    $row_purchase_order_items['tax']);
                        array_push($tax_amount_arr,             $row_purchase_order_items['tax_amount']);
                        array_push($total_arr,                  $row_purchase_order_items['total']);
                    }
                }
            }


            if ($total_rows == 0)           $total_rows = 1;

            ?>


            <?php
            if (!empty($purchase_order_id)):
            //COUNT QUERY
            $result         = $mysqli->query("SELECT id FROM `" . tbl_purchases . "` WHERE purchase_order_id=$purchase_order_id ORDER BY id DESC LIMIT 5 ");
            $total_pages    = $result->num_rows;
            // -----------------------------------
            if ($total_pages > 0) {
                // -----------------------------------
            ?>
                <div class="row">
                    <div class="col-lg-1">
                    </div>
                    <div class="col-lg-10">
                        <div class="card">
                            <!-- <div class="card-header">
                                    <h6 class="mb-0"><?php echo $total_pages; ?> Purchases Found.</h6>
                                </div> -->

                            <div class="card-body">

                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Purchase#</th>
                                                <th>Status</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                                <th>Balance Due</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php

                                            //NORMAL QUERY
                                            $result_purchases = $mysqli->query("SELECT * FROM `" . tbl_purchases . "` WHERE purchase_order_id=$purchase_order_id ORDER BY id DESC LIMIT 5");
                                            // ---------------------------------------------------------------------------------------
                                            while ($row_purchases = $result_purchases->fetch_array(MYSQLI_ASSOC)) {

                                                $id                     = $row_purchases["id"];

                                                $purchase_no             = s__($row_purchases['purchase_no']);

                                                $purchase_date           = s__($row_purchases['purchase_date']);
                                                $purchase_date           = ddm_($purchase_date);
                                                $expiry_date            = s__($row_purchases['expiry_date']);
                                                $expiry_date            = ddm_($expiry_date);

                                                $purchase_status         = s__($row_purchases['purchase_status']);
                                                $grand_total            = s__($row_purchases['grand_total']);
                                                // ---------------------------------------------------------------------------------------
                                            ?>

                                                <tr>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"><?php echo $purchase_date; ?></a></td>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"><?php echo $purchase_no; ?></a></td>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"><?php echo $purchase_status; ?></a></td>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"><?php echo $expiry_date; ?></a></td>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"><?php echo $grand_total; ?></a></td>
                                                    <td><a href="purchase_overview.php?purchase_id=<?php echo $id; ?>"></a></td>
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
                </div>
            <?php } // Purchases
            endif; // !empty($purchase_order_id)
            ?>


            <div class="row">

                <div class="col-lg-1">
                </div>

                <div class="card col-lg-10">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="mb-4">

                                    <span class="text-muted">Purchase Order To:</span>
                                    <ul class="list list-unstyled mb-0">
                                        <li>
                                            <h5 class="my-2"><a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>"><?php echo $display_name; ?></a></h5>
                                        </li>
                                        <li><span class="fw-semibold"><?php echo $company_name; ?></span></li>
                                        <li><?php echo $billing_attention; ?></li>
                                        <li><?php echo $billing_country; ?></li>
                                        <li><?php echo $billing_address_line1; ?></li>
                                        <li><?php echo $billing_address_line2; ?></li>
                                        <li><?php echo $billing_city; ?></li>
                                        <li><?php echo $billing_state; ?></li>
                                        <li><?php echo $billing_zipcode; ?></li>
                                        <li><?php echo $billing_phone; ?></li>
                                        <li><?php echo $billing_fax; ?></li>
                                    </ul>

                                </div>
                            </div>

                            <?php
                            $warehouse_information = '';
                            $rs_warehouse   = $mysqli->query("SELECT * FROM `" . tbl_warehouses . "` WHERE id=$warehouse_id");
                            $row_warehouse  = $rs_warehouse->fetch_array();

                            $warehouse_no       = s__($row_warehouse['warehouse_no']);
                            $warehouse_name     = s__($row_warehouse['warehouse_name']);
                            $street1            = s__($row_warehouse['street1']);
                            $street2            = s__($row_warehouse['street2']);

                            $country            = s__($row_warehouse['country']);
                            $country            = getTableAttr('country_name', tbl_geo_countries, $country);

                            $state              = s__($row_warehouse['state']);
                            $state            = getTableAttr('state_name', tbl_geo_states, $state);

                            $phone              = s__($row_warehouse['phone']);
                            $email              = s__($row_warehouse['email']);
                            $trn                = s__($row_warehouse['trn']);

                            $warehouse_information .= (!empty($warehouse_name) ? '<strong>' . $warehouse_name . '</strong><br />' : '');
                            $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
                            $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
                            $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
                            $warehouse_information .= (!empty($state) ? $state . ', ' : '');
                            $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
                            $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
                            $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
                            $warehouse_information .= (!empty($trn) ? $trn : '');
                            ?>
                            <div class="col-sm-6">
                                <div class="text-sm-end mb-4">
                                    <?php echo $warehouse_information; ?>
                                    <h6 class="text-primary mb-2 mt-lg-2">Purchase Order #<?php echo $purchase_order_no; ?></h6>
                                    <ul class="list list-unstyled mb-0">
                                        <li>Date: <span class="fw-semibold"><?php echo $purchase_order_date; ?></span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="d-lg-flex flex-lg-wrap">
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-lg">
                            <thead>
                                <tr>
                                    <th>ITEM DETAILS</th>
                                    <th>DESCRIPTION</th>
                                    <th class="text-center">QUANTITY</th>
                                    <th class="text-center">RATE</th>
                                    <th class="text-center">SUBTOTAL</th>
                                    <th class="text-center">TAX</th>
                                    <th class="text-center">TOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                /*
                                    |------------------------------------------------------ Purchase order ITEMS  ----------------------------------------------------------|
                                    */
                                // echo $total_rows;

                                for ($purchase_order_item = 1; $purchase_order_item <= $total_rows; $purchase_order_item++) {
                                    $index = $purchase_order_item;
                                    $index = $index - 1;
                                    $purchase_order_item_id                = $purchase_order_item_id_arr[$index];
                                    //--------------------------------------------------------------------------------------------------------------------------------|
                                ?>

                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo getTableAttr('item_name', tbl_items, $service_arr[$index]); ?></div>
                                            <span class="text-muted">
                                                <?php
                                                // ----------------------------------------------
                                                // Seprate Line Number on base of Space new line
                                                // ----------------------------------------------
                                                $desc = explode("\r", (string)($description_arr[$index] ?? ''));
                                                // print_r($desc);
                                                $d_counter = 1;
                                                if (count($desc) > 0) {
                                                    foreach ($desc as $d) {
                                                        if (!empty($d)) {
                                                            echo $d_counter++ . '. ' . $d;
                                                            echo '<br />';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $description_arr[$index]; ?></td>
                                        <td class="text-center"><?php echo $qty_arr[$index]; ?></td>
                                        <td class="text-end"><?php echo $rate_arr[$index]; ?></td>
                                        <td class="text-end"><?php echo $sub_total_arr[$index]; ?></td>
                                        <td class="text-end"><?php echo $tax_arr[$index]; ?>% (<?php echo $tax_amount_arr[$index]; ?>)</td>
                                        <td class="text-end"><span class="fw-semibold"><?php echo $total_arr[$index]; ?></span></td>
                                    </tr>
                                <?php
                                } // for
                                /*
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    */
                                ?>


                            </tbody>
                        </table>
                    </div>

                    <div class="card-body border-top">
                        <div class="d-lg-flex flex-lg-wrap">

                            <div class="pt-2 mb-3">
                                <ul class="list-unstyled text-muted">
                                    <li class="mb-3">Vendor Notes: <br /><?php echo $vendor_notes; ?></li>
                                    <!-- <li class="mb-3"><span class="fw-semibold">Terms and Conditions: </span> <br /><?php //echo $final_terms_and_conditions; 
                                                                                                                        ?></li> -->
                                </ul>
                            </div>

                            <div class="pt-2 mb-3 wmin-lg-400 ms-auto">
                                <!-- <h6 class="mb-3">Total due</h6> -->
                                <div class="table-responsive">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>Grand Subtotal:</td>
                                                <td class="text-end"><?php echo $grand_subtotal; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Discount Type: <?php echo $grand_discount_type; ?></td>
                                                <td class="text-end"><?php echo $grand_discount_type_value; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Discount Amount: </td>
                                                <td class="text-end"><?php echo $grand_discount_amount; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Subtotal: (Discounted): </td>
                                                <td class="text-end"><?php echo $grand_after_discount; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Tax Amount:</td>
                                                <td class="text-end"><?php echo $grand_tax; ?></td>
                                            </tr>
                                            <tr>
                                                <td>Grand Total:</td>
                                                <td class="text-end text-primary">
                                                    <h5 class="fw-semibold"><?php echo $grand_total; ?></h5>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- <div class="text-end mt-3">
                                        <button type="button" class="btn btn-primary">
                                            Send Purchase
                                            <i class="ph-paper-plane-tilt ms-2"></i>
                                        </button>
                                    </div> -->

                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span class="text-muted">Thank you for your Business.</span>
                    </div>
                </div>

            </div>


            <div class="row">

                <div class="col-lg-1">
                </div>

                <div class="col-lg-10">
                    <div class="card">
                        <div class="card-header">
                            <h6>Terms & Conditions</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($final_terms_and_conditions)) {
                                echo $final_terms_and_conditions;
                            } else {
                                echo 'No Terms and Conditions';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>


        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>