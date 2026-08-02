<?php

use App\Core\DB;
use App\Core\Session;
include('admin_elements/admin_header.php');

$module = 'quotations';
$module_caption = 'Quotation';
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


$quotation_id = '';
if (isset($_REQUEST['quotation_id']))        $quotation_id     = e_s__($_REQUEST['quotation_id']);
if (isset($_POST['quotation_id']))           $quotation_id     = e_s__($_POST['quotation_id']);
if (empty($quotation_id) && isset($_REQUEST['id'])) $quotation_id = e_s__($_REQUEST['id']);



// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_quotations . "` WHERE id='". $quotation_id."' AND organization_id=$activeOrganizationId");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_quotations.php");
    exit;
}



$quotation_status = 0;
if (isset($_REQUEST['quotation_status']) && !empty($_REQUEST['quotation_status'])) {
    $quotation_status   = e_s__($_REQUEST['quotation_status']);
}

/*
|--------------------------------------------------------------------------
| ACTION SECURITY GATE
|--------------------------------------------------------------------------
*/
$post_actions = ["convert_$module", "clone_$module", "update_$module", "delete_$module"];
if (in_array($action, $post_actions)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        flash_error('Invalid request method.');
        header("Location: quotation_overview.php?quotation_id=$quotation_id");
        exit;
    }
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid security token. Please refresh the page and try again.');
        header("Location: quotation_overview.php?quotation_id=$quotation_id");
        exit;
    }
    if ($action !== "delete_$module" && !granted('edit', $module_id)) {
        flash_error('Access denied.');
        header("Location: quotation_overview.php?quotation_id=$quotation_id");
        exit;
    }
    if ($action === "delete_$module" && !granted('delete', $module_id)) {
        flash_error('Access denied.');
        header("Location: quotation_overview.php?quotation_id=$quotation_id");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CONVERT TO INVOICE
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($quotation_id))) {

    $result_meta = $mysqli->query("SELECT customer_id, lead_id FROM `" . tbl_quotations . "` WHERE id=$quotation_id");
    $row_meta = $result_meta->fetch_array();
    $customer_id_meta = (!empty($row_meta['customer_id']) ? $row_meta['customer_id'] : 0);
    $lead_id_meta = (!empty($row_meta['lead_id']) ? $row_meta['lead_id'] : 0);

    if (!empty($lead_id_meta) && (empty($customer_id_meta) || $customer_id_meta == '0')) {
        $error_message = 'Please convert the lead to a customer before converting this quotation to an invoice.';
    } else {

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
    $result = $mysqli->query("INSERT INTO `" . tbl_invoices . "` (organization_id, customer_id, warehouse_id, subject, job_reference_no, invoice_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, invoice_status, is_active, created_at, updated_at)
    SELECT organization_id, customer_id, warehouse_id, subject, job_reference_no, NOW(), NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_quotations . "` WHERE id = $quotation_id;");

    $new_invoice_id = $mysqli->insert_id;
    fp__($tbl_name, $new_invoice_id);

    // Update Invoice no
    $mysqli->query("UPDATE `" . tbl_invoices . "` SET invoice_no = '" . $invoice_no . "', quotation_id = $quotation_id WHERE id=$new_invoice_id");

    // -- Invoice Items
    $result = $mysqli->query("INSERT INTO `" . tbl_invoice_items . "` (organization_id, invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT organization_id, $new_invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_quotation_items . "` WHERE quotation_id = $quotation_id");

    fp__(tbl_invoice_items, $mysqli->insert_id);

    // -- Dimension Items (copy from quotation to invoice)
    $mysqli->query("INSERT INTO `" . DB::DIMENSION_ITEMS . "` (module_type, record_id, pcs, unit, length, width, height, formula, cbm, volume, created_at, updated_at, created_by)
    SELECT 'invoices', $new_invoice_id, pcs, unit, length, width, height, formula, cbm, volume, NOW(), NOW(), '" . Session::userId() . "'
    FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id = $quotation_id");


    $success_message = 'This Quotation has been Converted to Invoice Successfully. Please click here to view. <a href="invoice_overview.php?invoice_id=' . $new_invoice_id . '"> ' . $invoice_no . '</a>';

    // CHANGE STATUS OF QUOATION TO INVOICED
    $mysqli->query("UPDATE `" . tbl_quotations . "` SET invoice_id = $new_invoice_id,  quotation_status = 'invoiced' WHERE id=$quotation_id");
    }



    /*
|--------------------------------------------------------------------------
| CLONE Quotation
|--------------------------------------------------------------------------
|
*/
} else if (($action == "clone_$module" && !empty($quotation_id))) {

    // ======================================================
    // INVOICE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-QT' . date('ym');

    // Get the last invoice number for this month
    $sql = "SELECT quotation_no  FROM `" . tbl_quotations . "`  WHERE quotation_no LIKE '{$prefix}-%'ORDER BY quotation_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['quotation_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First invoice of the month
        $new_serial = 1;
    }

    // Build new invoice number with zero padding
    $quotation_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Quotation
    $result = $mysqli->query("INSERT INTO `" . tbl_quotations . "` (organization_id, customer_id, lead_id, warehouse_id, quotation_no, subject, job_reference_no, quotation_date, expiry_date, expected_shipment_date, payment_term, shipment_type, sales_person, mawb_bol, hwb_hbol, shipper_id, consignee_id, origin_port, origin_country, destination_port, no_of_packs, gross_weight, chargeable_weight, volume, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, quotation_status, is_active, created_at, updated_at)
    SELECT organization_id, customer_id, lead_id, warehouse_id, '" . $quotation_no . "', subject, FLOOR(111 + (RAND() * 889)), NOW(), NOW(), expected_shipment_date, payment_term, shipment_type, sales_person, mawb_bol, hwb_hbol, shipper_id, consignee_id, origin_port, origin_country, destination_port, no_of_packs, gross_weight, chargeable_weight, volume, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_quotations . "` WHERE id = $quotation_id;");

    $new_cloned_id = $mysqli->insert_id;
    fp__($tbl_name, $new_cloned_id);

    // -- Quotation Items
    $result = $mysqli->query("INSERT INTO `" . tbl_quotation_items . "` (organization_id, quotation_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT organization_id, $new_cloned_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_quotation_items . "` WHERE quotation_id = $quotation_id");

    fp__(tbl_quotation_items, $mysqli->insert_id);

    // -- Dimension Items
    $mysqli->query("INSERT INTO `" . DB::DIMENSION_ITEMS . "` (module_type, record_id, pcs, unit, length, width, height, formula, cbm, volume, created_at, updated_at, created_by)
    SELECT 'quotations', $new_cloned_id, pcs, unit, length, width, height, formula, cbm, volume, NOW(), NOW(), '" . Session::userId() . "'
    FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id = $quotation_id");


    $success_message = 'Quotation has been cloned Successfully. Please click here to view. <a href="quotation_overview.php?quotation_id=' . $new_cloned_id . '"> ' . $quotation_no . '</a>';





    /*
|--------------------------------------------------------------------------
| UPDATE Quotation STATUS
|--------------------------------------------------------------------------
|
*/
} else if (($action == "update_$module" && !empty($quotation_id) && !empty($quotation_status))) {

    $allowed_statuses = ['draft', 'sent', 'accepted', 'declined', 'expired', 'confirmed', 'rejected', 'pending', 'approved', 'invoiced', 'not_confirmed', 'on_hold', 'cancelled'];
    if (!in_array($quotation_status, $allowed_statuses, true)) {
        $error_message = "Invalid status value.";
    } else {
        $result = $mysqli->query("UPDATE `$tbl_name` SET quotation_status = '" . $quotation_status . "' WHERE id=$quotation_id AND organization_id=$activeOrganizationId");

        if ($result) {
            $success_message = "The $module_caption status has been updated successfully.";
            flash_success($success_message);
            header("Location:quotation_overview.php?quotation_id=$quotation_id");
            exit;
        } else {
            $error_message = "Sorry! $module Status Could Not Be Updated.";
        }
    }



    /*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
} else if (($action == "delete_$module" && !empty($quotation_id)) && granted('delete', $module_id)) {

    if (Session::roleId() == '1') {
        $mysqli->query("DELETE FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id=$quotation_id");
        $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$quotation_id AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$quotation_id AND organization_id=$activeOrganizationId");
    } else {
        $mysqli->query("DELETE FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id=$quotation_id");
        $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$quotation_id AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$quotation_id AND created_by='" . Session::userId() . "' AND organization_id=$activeOrganizationId");
    }

    if ($mysqli->affected_rows > 0) {
        $success_message = "Item deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
        exit;
    } else {
        $error_message = "Action denied. You are not authorized to delete this record.";
    }
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
    <?php include('admin_elements/sidebar_quotation.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_quotation.php'); ?>
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
                if (!empty($quotation_id)) {

                    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$quotation_id AND organization_id=$activeOrganizationId");
                    $row = $result->fetch_array();

                    $customer_id            = s__($row['customer_id']);
                    $lead_id                = (isset($row['lead_id']) ? s__($row['lead_id']) : 0);
                    $quotation_no           = s__($row['quotation_no']);
                    $quotation_status       = s__($row['quotation_status']);
                    $quotation_date         = s__($row['quotation_date']);
                    $expiry_date            = s__($row['expiry_date']);
                    $job_reference_no       = s__($row['job_reference_no']);

                    $expected_shipment_date = s__($row['expected_shipment_date']);
                    $payment_term           = '';
                    if (!empty($customer_id) && $customer_id != '0') {
                        $payment_term           = getTableAttr('payment_term', tbl_customers, $customer_id);
                    }

                    $shipment_type          = s__($row['shipment_type']);
                    $sales_person           = s__($row['sales_person']);
                    $mawb_bol               = s__($row['mawb_bol']);
                    $hwb_hbol               = s__($row['hwb_hbol']);
                    $shipper_id             = s__($row['shipper_id']);
                    $consignee_id           = s__($row['consignee_id']);
                    $origin_port            = s__($row['origin_port']);
                    $origin_country         = s__($row['origin_country']);
                    $destination_port       = s__($row['destination_port']);
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



                    // --- Customer/Lead Information
                    $salutation = '';
                    $first_name = '';
                    $last_name = '';
                    $company_name = '';
                    $display_name = '';
                    $email = '';
                    $phone = '';
                    $mobile = '';
                    $trn = '';

                    $billing_attention = '';
                    $billing_country = '';
                    $billing_address_line1 = '';
                    $billing_address_line2 = '';
                    $billing_city = '';
                    $billing_state = '';
                    $billing_zipcode = '';
                    $billing_phone = '';
                    $billing_fax = '';

                    if (!empty($customer_id) && $customer_id != '0') {
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
                        $billing_country        = (!empty($billing_country) ? getTableAttr('country', tbl_geo_countries, $billing_country) : '');

                        $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
                        $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
                        $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
                        $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
                        $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
                        $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
                        $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');
                    } else if (!empty($lead_id) && $lead_id != '0') {
                        $rs_lead = $mysqli->query("SELECT * FROM `" . tbl_leads . "` WHERE id=$lead_id");
                        $row_lead = $rs_lead->fetch_array();
                        $display_name = s__($row_lead['display_name']);
                        $email = (isset($row_lead['email']) ? s__($row_lead['email']) : '');
                        $phone = (isset($row_lead['phone']) ? s__($row_lead['phone']) : '');
                        $mobile = (isset($row_lead['mobile']) ? s__($row_lead['mobile']) : '');
                        $billing_phone = $phone;
                    }


                    $quotation_date         = processDateYtoD($quotation_date);
                    $expiry_date            = ($expiry_date == '1970-01-01') ? '' : processDateDtoY($expiry_date);
                    $expected_shipment_date = ($expected_shipment_date == '1970-01-01') ? '' : processDateDtoY($expected_shipment_date);


                    // ------------------ TOTAL Quotation ITEMS ------------------
                    $result_quotation_items     = $mysqli->query("SELECT * FROM `" . tbl_quotation_items . "` WHERE quotation_id=$quotation_id ORDER BY id");
                    $total_rows                 = $result_quotation_items->num_rows;


                    // Initialize all arrays to prevent undefined variable warnings
                    $quotation_item_id_arr = [];
                    $service_arr = [];
                    $description_arr = [];
                    $qty_arr = [];
                    $rate_arr = [];
                    $sub_total_arr = [];
                    $tax_arr = [];
                    $tax_amount_arr = [];
                    $total_arr = [];

                    if ($total_rows > 0) {

                        // Now your loop will work perfectly
                        while ($row_quotation_items = $result_quotation_items->fetch_array()) {

                            array_push($quotation_item_id_arr,      $row_quotation_items['id']);
                            array_push($service_arr,                $row_quotation_items['service']);
                            array_push($description_arr,            $row_quotation_items['description']);
                            array_push($qty_arr,                    $row_quotation_items['qty']);
                            array_push($rate_arr,                   $row_quotation_items['rate']);
                            array_push($sub_total_arr,              $row_quotation_items['sub_total']);
                            array_push($tax_arr,                    $row_quotation_items['tax']);
                            array_push($tax_amount_arr,             $row_quotation_items['tax_amount']);
                            array_push($total_arr,                  $row_quotation_items['total']);
                        }
                    }
                }


                if ($total_rows == 0)           $total_rows = 1;
            
            
            ?>


            <?php
            //COUNT QUERY
            $result         = $mysqli->query("SELECT id FROM `" . tbl_invoices . "` WHERE quotation_id=$quotation_id ORDER BY id DESC LIMIT 5 ");
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
                                            $result_invoices = $mysqli->query("SELECT * FROM `" . tbl_invoices . "` WHERE quotation_id=$quotation_id ORDER BY id DESC LIMIT 5");
                                            // ---------------------------------------------------------------------------------------
                                            while ($row_invoices = $result_invoices->fetch_array(MYSQLI_ASSOC)) {

                                                $id                     = $row_invoices["id"];

                                                $invoice_no             = s__($row_invoices['invoice_no']);

                                                $invoice_date           = s__($row_invoices['invoice_date']);
                                                $invoice_date           = processDateYtoD($invoice_date);
                                                $expiry_date            = s__($row_invoices['expiry_date']);
                                                $expiry_date            = processDateYtoD($expiry_date);

                                                $invoice_status         = s__($row_invoices['invoice_status']);
                                                $grand_total            = s__($row_invoices['grand_total']);
                                                // ---------------------------------------------------------------------------------------
                                            ?>

                                                <tr>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo e($invoice_date); ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo e($invoice_no); ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo e($invoice_status); ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo e($expiry_date); ?></a></td>
                                                    <td><a href="invoice_overview.php?invoice_id=<?php echo $id; ?>"><?php echo e($grand_total); ?></a></td>
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

                                    <span class="text-muted">Quotation To:</span>
                                    <ul class="list list-unstyled mb-0">
                                        <li>
                                            <?php
                                            $quotation_to_link = '#';
                                            if (!empty($customer_id) && $customer_id != '0') {
                                                $quotation_to_link = "customer_overview.php?customer_id=$customer_id";
                                            } else if (!empty($lead_id) && $lead_id != '0') {
                                                $quotation_to_link = "lead.php?id=$lead_id";
                                            }
                                            ?>
                                            <h5 class="my-2"><a href="<?php echo $quotation_to_link; ?>"><?php echo e($display_name); ?></a></h5>
                                        </li>
                                        <li><?php echo e($company_name); ?></li>
                                        <li><?php echo e($billing_attention); ?></li>
                                        <li><?php echo e($billing_country); ?></li>
                                        <li><?php echo e($billing_address_line1); ?></li>
                                        <li><?php echo e($billing_address_line2); ?></li>
                                        <li><?php echo e($billing_city); ?></li>
                                        <li><?php echo e($billing_state); ?></li>
                                        <li><?php echo e($billing_zipcode); ?></li>
                                        <li><?php echo e($billing_phone); ?></li>
                                        <li><?php echo e($billing_fax); ?></li>
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
                            $country            = getTableAttr('country', tbl_geo_countries, $country);

                            $state              = s__($row_warehouse['state']);
                            $state            = getTableAttr('state_name', tbl_geo_states, $state);

                            $phone              = s__($row_warehouse['phone']);
                            $email              = s__($row_warehouse['email']);
                            $trn                = s__($row_warehouse['trn']);

                            $warehouse_information .= (!empty($warehouse_name) ? '<strong>' . e($warehouse_name) . '</strong><br />' : '');
                            $warehouse_information .= (!empty($warehouse_no) ? e($warehouse_no) . '<br />' : '');
                            $warehouse_information .= (!empty($street1) ? e($street1) . '<br />' : '');
                            $warehouse_information .= (!empty($street2) ? e($street2) . '<br />' : '');
                            $warehouse_information .= (!empty($state) ? e($state) . ', ' : '');
                            $warehouse_information .= (!empty($country) ? e($country) . '<br />' : '');
                            $warehouse_information .= (!empty($phone) ? e($phone) . '<br />' : '');
                            $warehouse_information .= (!empty($email) ? e($email) . '<br />' : '');
                            $warehouse_information .= (!empty($trn) ? e($trn) : '');
                            ?>
                            <div class="col-sm-6">
                                <div class="text-sm-end mb-4">
                                    <?php echo $warehouse_information; ?>
                                    <h6 class="text-primary mb-2 mt-lg-2">Quotation #<?php echo e($quotation_no); ?></h6>
                                    <ul class="list list-unstyled mb-0">
                                        <li>Date: <span class="fw-semibold"><?php echo e($quotation_date); ?></span></li>
                                        <li>Expiry date: <span class="fw-semibold"><?php echo e($expiry_date); ?></span></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="d-lg-flex flex-lg-wrap">

                            <div class="col-sm-6">
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Expected Shipment Date:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($expected_shipment_date); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Delivery Method:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php if (!empty($shipment_type)) echo e(str_ireplace('_', '-', ucwords($shipment_type))); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Job Reference No:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($job_reference_no); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Shipper:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('shipper_name', tbl_shippers, $shipper_id)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Origin Port:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('alpha3_code', tbl_geo_countries, $origin_port)); ?> - <?php echo e(getTableAttr('country', tbl_geo_countries, $origin_port)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Origin Country:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('alpha3_code', tbl_geo_countries, $origin_country)); ?> - <?php echo e(getTableAttr('country', tbl_geo_countries, $origin_country)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">No of Packs:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($no_of_packs); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Chargeable Weight:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($chargeable_weight); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-sm-6">
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Payment Terms:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('payment_term', tbl_payment_terms, $payment_term)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Sales Person:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('warehouse_name', tbl_warehouses, $sales_person)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">MAWB/BOL:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($mawb_bol); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">HWB/HBOL:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($hwb_hbol); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Consignee:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('consignee_name', tbl_consignees, $consignee_id)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Destination Port:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e(getTableAttr('alpha3_code', tbl_geo_countries, $destination_port)); ?> - <?php echo e(getTableAttr('country', tbl_geo_countries, $destination_port)); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Gross Weight:</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($gross_weight); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-5 col-form-label">Volume (CBM):</label>
                                    <div class="col-lg-7 mt-2">
                                        <?php echo e($volume); ?>
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
                |------------------------------------------------------ Quotation ITEMS  ----------------------------------------------------------|
                */
                                // echo $total_rows;

                                for ($quotation_item = 1; $quotation_item <= $total_rows; $quotation_item++) {
                                    $index = $quotation_item;
                                    $index = $index - 1;
                                    $quotation_item_id                = ($quotation_item_id_arr[$index] ?? '');
                                    //--------------------------------------------------------------------------------------------------------------------------------|
                                ?>

                                    <tr>
                                        <td>
                                            <div class="fw-bold"><?php echo e(getTableAttr('item_name', tbl_items, ($service_arr[$index] ?? 0))); ?></div>
                                            <span class="text-muted">
                                                <?php
                                                // ----------------------------------------------
                                                // Seprate Line Number on base of Space new line
                                                // ----------------------------------------------
                                                $desc = explode("\r", ($description_arr[$index] ?? ''));
                                                // print_r($desc);
                                                $d_counter = 1;
                                                if (count($desc) > 0) {
                                                    foreach ($desc as $d) {
                                                        if (!empty($d)) {
                                                            echo e($d_counter++ . '. ' . $d);
                                                            echo '<br />';
                                                        }
                                                    }
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo e($description_arr[$index] ?? ''); ?></td>
                                        <td class="text-center"><?php echo e($qty_arr[$index] ?? ''); ?></td>
                                        <td class="text-end"><?php echo e($rate_arr[$index] ?? ''); ?></td>
                                        <td class="text-end"><?php echo e($sub_total_arr[$index] ?? ''); ?></td>
                                        <td class="text-end"><?php echo e($tax_arr[$index] ?? ''); ?>% (<?php echo e($tax_amount_arr[$index] ?? ''); ?>)</td>
                                        <td class="text-end"><span class="fw-semibold"><?php echo e($total_arr[$index] ?? ''); ?></span></td>
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
                                    <li class="mb-3">Customer Notes: <br /><?php echo e($customer_notes); ?></li>
                                </ul>
                            </div>

                            <div class="pt-2 mb-3 wmin-lg-400 ms-auto">
                                <!-- <h6 class="mb-3">Total due</h6> -->
                                <div class="table-responsive">
                                    <table class="table">
                                        <tbody>
                                            <tr>
                                                <td>Grand Subtotal:</td>
                                                <td class="text-end"><?php echo e($grand_subtotal); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Discount Type: <?php echo e($grand_discount_type); ?></td>
                                                <td class="text-end"><?php echo e($grand_discount_type_value); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Discount Amount: </td>
                                                <td class="text-end"><?php echo e($grand_discount_amount); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Subtotal: (Discounted): </td>
                                                <td class="text-end"><?php echo e($grand_after_discount); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Total Tax Amount:</td>
                                                <td class="text-end"><?php echo e($grand_tax); ?></td>
                                            </tr>
                                            <tr>
                                                <td>Grand Total:</td>
                                                <td class="text-end text-primary">
                                                    <h5 class="fw-semibold"><?php echo e($grand_total); ?></h5>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

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
                                echo e($final_terms_and_conditions);
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