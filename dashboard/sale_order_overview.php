<?php

use App\Core\DB;
use App\Core\Session;
include('admin_elements/admin_header.php');

$module = 'sale_orders';
$module_caption = 'Sale order';
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


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$sale_order_id = '';
if (isset($_REQUEST['sale_order_id']))        $sale_order_id     = e_s__($_REQUEST['sale_order_id']);
if (isset($_POST['sale_order_id']))           $sale_order_id     = e_s__($_POST['sale_order_id']);
if (empty($sale_order_id) && isset($_REQUEST['id'])) $sale_order_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_sale_orders . "` WHERE id=$sale_order_id");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_sale_orders.php");
    exit;
}



// ------------------ CHECK IF SALE ORDER IS CONVERTED - INVOICED ----------------
$invoiced = 0;
$rs_converted     = $mysqli->query("SELECT sale_order_status FROM `" . tbl_sale_orders . "` WHERE id ='" . $sale_order_id . "' AND sale_order_status='invoiced' ");
if ($rs_converted->num_rows > 0) {
    $invoiced = 1;
    // $success_message = 'This Sale Order is Conveted into Invoice.';
}



/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$sale_order_status = 0;
if (isset($_REQUEST['sale_order_status']) && !empty($_REQUEST['sale_order_status'])) {
    $sale_order_status   = e_s__($_REQUEST['sale_order_status']);
}


$sale_order_item_id = 0;
if (isset($_REQUEST['sale_order_item_id']) && !empty($_REQUEST['sale_order_item_id'])) {
    $sale_order_item_id     = e_s__($_REQUEST['sale_order_item_id']);
}


/*
|--------------------------------------------------------------------------
| CONVERT TO INVOICE
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($sale_order_id))) {

    // ======================================================
    // INVOICE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-IN' . date('ym');

    // Get the last invoice number for this month
    $sql = "SELECT invoice_no  FROM `" . tbl_invoices . "`  WHERE invoice_no LIKE '{$prefix}-%'ORDER BY invoice_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['invoice_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First invoice of the month
        $new_serial = 1;
    }

    // Build new invoice number with zero padding
    $invoice_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Invoice
    $result = $mysqli->query("INSERT INTO `" . tbl_invoices . "` (customer_id, warehouse_id, subject, reference_no, invoice_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, invoice_status, is_active, created_at, updated_at)
    SELECT customer_id, warehouse_id, subject, reference_no, NOW(), NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_sale_orders . "` WHERE id = $sale_order_id;");

    $new_invoice_id = $mysqli->insert_id;
    fp__($tbl_name, $new_invoice_id);

    // Update Invoice no
    $mysqli->query("UPDATE `" . tbl_invoices . "` SET invoice_no = '" . $invoice_no . "', sale_order_id = $sale_order_id WHERE id=$new_invoice_id");

    // -- Invoice Items
    $result = $mysqli->query("INSERT INTO `" . tbl_invoice_items . "` ( invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_sale_order_items . "` WHERE sale_order_id = $sale_order_id");

    fp__(tbl_invoice_items, $mysqli->insert_id);


    $success_message = 'This Sale Order has been Converted to Invoice Successfully. Please click here to view. <a href="invoice_overview.php?invoice_id=' . $new_invoice_id . '"> ' . $invoice_no . '</a>';

    // CHANGE STATUS OF QUOATION TO INVOICED
    $mysqli->query("UPDATE `" . tbl_sale_orders . "` SET invoice_id = $new_invoice_id,  sale_order_status = 'invoiced' WHERE id=$sale_order_id");



    /*
|--------------------------------------------------------------------------
| CLONE SALE ORDER
|--------------------------------------------------------------------------
|
*/
} else if (($action == "clone_$module" && !empty($sale_order_id))) {

    // ======================================================
    // INVOICE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-SO' . date('ym');

    // Get the last invoice number for this month
    $sql = "SELECT sale_order_no  FROM `" . tbl_sale_orders . "`  WHERE sale_order_no LIKE '{$prefix}-%'ORDER BY sale_order_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['sale_order_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First invoice of the month
        $new_serial = 1;
    }

    // Build new invoice number with zero padding
    $sale_order_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Sale order
    $result = $mysqli->query("INSERT INTO `" . tbl_sale_orders . "` (customer_id, warehouse_id, sale_order_no, subject, reference_no, sale_order_date, expiry_date, expected_shipment_date, payment_term, shipment_type, sales_person, job_reference_no, master_awb_no, shipper_id, consignee_id, origin_port, destination_port, no_of_packs, gross_weight, chargeable_weight, volume, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, sale_order_status, is_active, created_at, updated_at)
    SELECT customer_id, warehouse_id, '" . $sale_order_no . "', subject, reference_no, NOW(), NOW(), expected_shipment_date, payment_term, shipment_type, sales_person, job_reference_no, master_awb_no, shipper_id, consignee_id, origin_port, destination_port, no_of_packs, gross_weight, chargeable_weight, volume, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_sale_orders . "` WHERE id = $sale_order_id;");

    $new_cloned_id = $mysqli->insert_id;
    fp__($tbl_name, $new_cloned_id);

    // -- Sale order Items
    $result = $mysqli->query("INSERT INTO `" . tbl_sale_order_items . "` ( sale_order_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_cloned_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_sale_order_items . "` WHERE sale_order_id = $sale_order_id");

    fp__(tbl_sale_order_items, $mysqli->insert_id);


    $success_message = 'Sale order has been cloned Successfully. Please click here to view. <a href="sale_order_overview.php?sale_order_id=' . $new_cloned_id . '"> ' . $sale_order_no . '</a>';





    /*
|--------------------------------------------------------------------------
| UPDATE SALE ORDER STATUS
|--------------------------------------------------------------------------
|
*/
} else if (($action == "update_$module" && !empty($sale_order_id) && !empty($sale_order_status))) {

    $result = $mysqli->query("UPDATE `$tbl_name` SET sale_order_status = '" . $sale_order_status . "' WHERE id=$sale_order_id");

    if ($result) {
        $success_message = "The $module_caption status has been updated successfully.";


        // ------------ Sale order Log -------------
        // if (isset($_POST['sale_order_log_comments']) && !empty($_POST['sale_order_log_comments'])) {
        //     $sale_order_log_comments     = e_s__($_POST['sale_order_log_comments']);

        //     $mysqli->query("INSERT INTO `" . tbl_sale_order_logs . "` (sale_order_id, sale_order_status, comments) VALUES ('" . $id . "', '" . $sale_order_status . "', '" . $sale_order_log_comments . "'); ");
        //     fp__(tbl_sale_order_logs, $mysqli->insert_id);
        // }

        /* ---------------------- NOTIFICATIONS QUERY ---------------------- */

        // --------------------------------------------------------------------------------
        flash_success($success_message);
        header("Location:sale_order_overview.php?sale_order_id=$sale_order_id");
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

$sale_order_item_id_arr      = array();
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
    <?php include('admin_elements/sidebar_sale_order.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_sale_order.php'); ?>
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
            if (!empty($sale_order_id)) {

                $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$sale_order_id");
                $row = $result->fetch_array();

                $customer_id            = s__($row['customer_id']);
                $sale_order_no          = s__($row['sale_order_no']);
                $sale_order_status      = s__($row['sale_order_status']);
                $sale_order_date        = s__($row['sale_order_date']);
                $expiry_date            = s__($row['expiry_date']);
                $reference_no           = s__($row['reference_no']);

                $expected_shipment_date = s__($row['expected_shipment_date']);
                $payment_term           = getTableAttr('payment_term', tbl_customers, $customer_id);

                $shipment_type          = s__($row['shipment_type']);
                $sales_person           = s__($row['sales_person']);
                $job_reference_no       = s__($row['job_reference_no']);
                $master_awb_no          = s__($row['master_awb_no']);
                $shipper                = (isset($row['shipper_id']) ? s__($row['shipper_id']) : '');
                $consignee              = (isset($row['consignee_id']) ? s__($row['consignee_id']) : '');
                $origin                 = (isset($row['origin_port']) ? s__($row['origin_port']) : '');
                $destination            = (isset($row['destination_port']) ? s__($row['destination_port']) : '');
                $no_of_packs            = s__($row['no_of_packs']);
                $gross_weight           = s__($row['gross_weight']);
                $chargeable_weight      = s__($row['chargeable_weight']);
                $volume                 = s__($row['volume']);

                $customer_notes         = s__($row['customer_notes']);
                $terms_and_conditions   = s__($row['terms_and_conditions']);
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



                // --- Customer Information
                $rs = $mysqli->query("SELECT * FROM `" . tbl_customers . "` WHERE id=$customer_id");
                $row_customer = $rs->fetch_array();
                $salutation             = s__($row_customer['salutation']);
                $first_name             = s__($row_customer['first_name']);
                $last_name              = s__($row_customer['last_name']);
                $company_name           = s__($row_customer['company_name']);
                $display_name           = s__($row_customer['display_name']);
                $email                  = s__($row_customer['email']);
                $phone                  = s__($row_customer['phone']);
                $mobile                 = s__($row_customer['mobile']);
                $trn                    = s__($row_customer['trn']);

                // Customer Billing Address 
                $rs_billing     = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='billing' ");
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


                $sale_order_date         = function_exists('ddm_') ? ddm_($sale_order_date) : date('d M Y', strtotime($sale_order_date));
                $expiry_date            = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : (function_exists('ddm_') ? ddm_($expiry_date) : date('d M Y', strtotime($expiry_date)));
                $expected_shipment_date = ($expected_shipment_date == '1970-01-01' || empty($expected_shipment_date)) ? '' : (function_exists('ddm_') ? ddm_($expected_shipment_date) : date('d M Y', strtotime($expected_shipment_date)));


                // ------------------ TOTAL SALE ORDER ITEMS ------------------
                // echo "SELECT * FROM `" . tbl_sale_order_items . "` WHERE sale_order_id=$id ORDER BY requested_date";
                $result_sale_order_items     = $mysqli->query("SELECT * FROM `" . tbl_sale_order_items . "` WHERE sale_order_id=$sale_order_id ORDER BY id");
                $total_rows                 = $result_sale_order_items->num_rows;


                if ($total_rows > 0) {
                    while ($row_sale_order_items = $result_sale_order_items->fetch_array()) {

                        array_push($sale_order_item_id_arr,      $row_sale_order_items['id']);
                        array_push($service_arr,                $row_sale_order_items['service']);
                        array_push($description_arr,            $row_sale_order_items['description']);
                        array_push($qty_arr,                    $row_sale_order_items['qty']);
                        array_push($rate_arr,                   $row_sale_order_items['rate']);
                        array_push($sub_total_arr,              $row_sale_order_items['sub_total']);
                        array_push($tax_arr,                    $row_sale_order_items['tax']);
                        array_push($tax_amount_arr,             $row_sale_order_items['tax_amount']);
                        array_push($total_arr,                  $row_sale_order_items['total']);
                    }
                }
            }


            if ($total_rows == 0)           $total_rows = 1;

            ?>


            <?php
            //COUNT QUERY
            $result         = $mysqli->query("SELECT id FROM `" . tbl_invoices . "` WHERE sale_order_id=$sale_order_id ORDER BY id DESC LIMIT 5 ");
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
                                    <h6 class="mb-0"><?php echo $total_pages; ?> Invoices Found.</h6>
                                </div> -->

                            <div class="card-body">

                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Invoice#</th>
                                                <th>Status</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                                <th>Balance Due</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php

                                            //NORMAL QUERY
                                            $result_invoices = $mysqli->query("SELECT * FROM `" . tbl_invoices . "` WHERE sale_order_id=$sale_order_id ORDER BY id DESC LIMIT 5");
                                            // ---------------------------------------------------------------------------------------
                                            while ($row_invoices = $result_invoices->fetch_array(MYSQLI_ASSOC)) {

                                                $id                     = $row_invoices["id"];

                                                $invoice_no             = s__($row_invoices['invoice_no']);

                                                $invoice_date           = s__($row_invoices['invoice_date']);
                                                $invoice_date           = ddm_($invoice_date);
                                                $expiry_date            = s__($row_invoices['expiry_date']);
                                                $expiry_date            = ddm_($expiry_date);

                                                $invoice_status         = s__($row_invoices['invoice_status']);
                                                $grand_total            = s__($row_invoices['grand_total']);
                                                // ---------------------------------------------------------------------------------------
                                            ?>

                                                <tr>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo $invoice_date; ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo $invoice_no; ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo $invoice_status; ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo $expiry_date; ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo $grand_total; ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"></a></td>
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
            <?php } // invoices
            ?>


            <div class="row">

                <div class="col-lg-1">
                </div>

                <div class="card col-lg-10">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-sm-6">
                                <div class="mb-4">

                                    <span class="text-muted">Sale Order To:</span>
                                    <ul class="list list-unstyled mb-0">
                                        <li>
                                            <h5 class="my-2"><a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>"><?php echo $display_name; ?></a></h5>
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
                            $rs_warehouse   = $mysqli->query("SELECT * FROM `" . tbl_warehouses . "` WHERE id=1");
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
                                    <h6 class="text-primary mb-2 mt-lg-2">Sale Order #<?php echo $sale_order_no; ?></h6>
                                    <ul class="list list-unstyled mb-0">
                                        <li>Date: <span class="fw-semibold"><?php echo $sale_order_date; ?></span></li>
                                        <li>Due date: <span class="fw-semibold"><?php echo $expiry_date; ?></span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="d-lg-flex flex-lg-wrap">

                            <div class="col-sm-6">
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Expected Shipment Date:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $expected_shipment_date; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Delivery Method:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $shipment_type; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Job Reference No:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $job_reference_no; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Shipper:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('shipper_name', tbl_shippers, $shipper); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Origin:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('alpha3_code', tbl_geo_countries, $origin); ?> - <?php echo getTableAttr('country_name', tbl_geo_countries, $origin); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">No of Packs:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $no_of_packs; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Chargeable Weight:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $chargeable_weight; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Payment Terms:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('payment_term', tbl_payment_terms, $payment_term); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Sales Person:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('warehouse_name', tbl_warehouses, $sales_person); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Master AWB No:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $master_awb_no; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Consignee:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('consignee_name', tbl_consignees, $consignee); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Destination:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo getTableAttr('alpha3_code', tbl_geo_countries, $destination); ?> - <?php echo getTableAttr('country_name', tbl_geo_countries, $destination); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Gross Weight:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $gross_weight; ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Volume (CBM):</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo $volume; ?>
                                    </div>
                                </div>
                            </div>

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
                                    |------------------------------------------------------ Sale order ITEMS  ----------------------------------------------------------|
                                    */
                                // echo $total_rows;

                                for ($sale_order_item = 1; $sale_order_item <= $total_rows; $sale_order_item++) {
                                    $index = $sale_order_item;
                                    $index = $index - 1;
                                    $sale_order_item_id                = $sale_order_item_id_arr[$index];
                                    //--------------------------------------------------------------------------------------------------------------------------------|
                                ?>

                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo getTableAttr('item_name', tbl_items, $service_arr[$index] ?? 0); ?></div>
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
                                        <td><?php echo $description_arr[$index] ?? ''; ?></td>
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
                                    <li class="mb-3">Customer Notes: <br /><?php echo $customer_notes; ?></li>
                                    <li class="mb-3"><span class="fw-semibold">Terms and Conditions: </span> <br /><?php echo $final_terms_and_conditions; ?></li>
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
                                            Send invoice
                                            <i class="ph-paper-plane-tilt ms-2"></i>
                                        </button>
                                    </div> -->

                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <span class="text-muted">Looking forward for your business..</span>
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