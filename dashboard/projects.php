<?php


use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module             = 'projects';
$module_caption     = 'Project';
$tbl_name = DB::PROJECTS;
$error_message         = '';
$success_message     = '';


/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$id = $_REQUEST['id'] ?? '';

// print_r($_REQUEST);

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/



if (isset($_POST['publish']))                                 $publish     = 1;
else $publish = 0;


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


$created_by                 = '';
$modified_by                = '';
$customer_type              = '';
$quote_id                   = '';
$books_customer_id          = '';
$approved_time              = '';
$project_id                 = '';
$approved_time_resubmission = '';
$reference_no               = '';


// ---------------------- Items Rows -----------------------------
if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows = e_s__($_POST['total_rows']);
} else {
    $total_rows = 1;
}




// ---------------------- Tags Array -----------------------------
$tags_arr           = array();
$posted_tags_arr    = array();
$tags_string        = '';
$tag                = '';


if (isset($_POST['tags'])) {

    $posted_tags = $_POST['tags'];

    foreach ($posted_tags as $tag) {
        $tags_string .= $tag . ', ';
    }
    if (strlen($tags_string) > 2) {
        $tags_string = substr($tags_string, 0, -2);
    }
    // echo $tags_string;

    $posted_tags_arr = explode(',', $tags_string);
    // print_r($posted_tags_arr);
}


if ($action == "update_$module" || $action == "add_$module") {

    if (!isset($item_id_arr)) $item_id_arr = array();
    if (!isset($service_arr)) $service_arr = array();
    if (!isset($description_arr)) $description_arr = array();
    if (!isset($qty_arr)) $qty_arr = array();
    if (!isset($rate_arr)) $rate_arr = array();
    if (!isset($sub_total_arr)) $sub_total_arr = array();
    if (!isset($tax_arr)) $tax_arr = array();
    if (!isset($tax_amount_arr)) $tax_amount_arr = array();
    if (!isset($total_arr)) $total_arr = array();

    for ($quotation_item = 1; $quotation_item <= $total_rows; $quotation_item++) {

        $index = $quotation_item;
        $index = $index - 1;

        $post_item_id       = (isset($_POST['item_id'][$index]) && !empty($_POST['item_id'][$index]) ? $_POST['item_id'][$index] :  0);
        $post_service       = (isset($_POST['service'][$index]) && !empty($_POST['service'][$index]) ? $_POST['service'][$index] :  0);
        $post_description   = (isset($_POST['description'][$index]) && !empty($_POST['description'][$index]) ? $_POST['description'][$index] :  '');
        $post_qty           = (isset($_POST['qty'][$index]) && !empty($_POST['qty'][$index]) ? $_POST['qty'][$index] :  1);
        $post_rate          = (isset($_POST['rate'][$index]) && !empty($_POST['rate'][$index]) ? $_POST['rate'][$index] :  0);
        $post_sub_total     = (isset($_POST['sub_total'][$index]) && !empty($_POST['sub_total'][$index]) ? $_POST['sub_total'][$index] :  0);
        $post_tax           = (isset($_POST['tax'][$index]) && !empty($_POST['tax'][$index]) ? $_POST['tax'][$index] :  0);
        $post_tax_amount    = (isset($_POST['tax_amount'][$index]) && !empty($_POST['tax_amount'][$index]) ? $_POST['tax_amount'][$index] :  0);
        $post_total         = (isset($_POST['total'][$index]) && !empty($_POST['total'][$index]) ? $_POST['total'][$index] :  0);


        array_push($item_id_arr,                e_s__($post_item_id));
        array_push($service_arr,                e_s__($post_service));
        array_push($description_arr,            e_s__($post_description));
        array_push($qty_arr,                    e_s__($post_qty));
        array_push($rate_arr,                   e_s__($post_rate));
        array_push($sub_total_arr,              e_s__($post_sub_total));
        // array_push($discount_type_arr,          e_s__($post_discount_type));
        // array_push($discount_type_value_arr,    e_s__($post_discount_type_value));
        // array_push($discount_amount_arr,        e_s__($post_discount_amount));
        array_push($tax_arr,                    e_s__($post_tax));
        array_push($tax_amount_arr,             e_s__($post_tax_amount));
        array_push($total_arr,                  e_s__($post_total));
    } //for
}


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" || $action == "add_$module") {
    /*
    |--------------------------------------------------------------------------
    | 	UPDATE PROJECT NAME ONLY
    |--------------------------------------------------------------------------
    */
    if ($action == "update_$module" && !empty($id)) {
        $project_name = e_s__($_POST['project_name'] ?? '');

        if (empty($project_name)) {
            $error_message = 'Please enter Project Name.';
        } else {
            $update_row = $mysqli->query(
                "UPDATE `$tbl_name` SET project_name = '" . $project_name . "' WHERE id=$id"
            );

            if ($update_row) {
                $success_message = "The $module_caption has been updated successfully.";
                fp__($tbl_name, $id);
                flash_success($success_message);
                header("Location:listing_$module.php");
                exit;
            } else {
                $error_message = "The $module_caption could not be updated. Please try again.";
            }
        }
    } else if ($action == "add_$module") {
        $error_message = 'New projects are not created here. Only Project Name can be updated.';
    }
}

$created_by = getTableAttr('created_by', DB::PROJECTS, $id);

if (
    (!empty($id) && Session::roleId() == '1')
    ||
    (!empty($id) && ($_SESSION[$project_pre]['DASHBOARD']['admin_id'] ?? null) == $created_by)
) {

    // Ensure item arrays are initialized before use
    if (!isset($item_id_arr)) $item_id_arr = array();
    if (!isset($service_arr)) $service_arr = array();
    if (!isset($description_arr)) $description_arr = array();
    if (!isset($qty_arr)) $qty_arr = array();
    if (!isset($rate_arr)) $rate_arr = array();
    if (!isset($sub_total_arr)) $sub_total_arr = array();
    if (!isset($tax_arr)) $tax_arr = array();
    if (!isset($tax_amount_arr)) $tax_amount_arr = array();
    if (!isset($total_arr)) $total_arr = array();

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    $job_date               = s__($row['job_date'] ?? '');
    $job_date               = processDateYtoD($job_date);
    $job_status             = s__($row['job_status'] ?? '');

    $job_id                 = s__($row['job_id'] ?? '');
    $job_seq                = s__($row['job_seq'] ?? '');
    $project_name           = s__($row['project_name'] ?? '');

    if (!empty($job_id)) {
        if (empty($customer_id)) {
            $customer_id = s__(getTableAttr('customer_id', DB::JOBS, $job_id));
        }
        if (empty($job_status)) {
            $job_status = s__(getTableAttr('job_status', DB::JOBS, $job_id));
        }
        if (empty($job_seq)) {
            $job_seq = s__(getTableAttr('job_seq', DB::JOBS, $job_id));
        }
    }

    $currency               = s__($row['currency'] ?? '');
    $exchange_rate          = s__($row['exchange_rate'] ?? '');
    $transport_mode         = s__($row['transport_mode'] ?? '');
    $shipment_type          = s__($row['shipment_type'] ?? '');
    $job_owner              = s__($row['job_owner'] ?? '');

    // -- Tags
    $tags                   = s__($row['tags'] ?? '');
    $tags_arr               = array();
    if ($tags != NULL) {
        $tags_arr               = explode(',', $tags);
    }

    // -- Services
    $services                   = s__($row['services'] ?? '');
    $services_arr               = array();
    if ($services != NULL) {
        $services_arr               = explode(',', $services);
    }


    $job_no                 = s__($row['job_no'] ?? '');
    $job_ref_no             = s__($row['job_ref_no'] ?? '');
    $cs_agent               = s__($row['cs_agent'] ?? '');
    $incoterm               = s__($row['incoterm'] ?? '');
    $email                  = s__($row['email'] ?? '');
    $supplier_rate          = s__($row['supplier_rate'] ?? '');
    $estimated_net_profit   = s__($row['estimated_net_profit'] ?? '');

    $estimated_invoice_amount   = s__($row['estimated_invoice_amount'] ?? '');
    $etd                        = s__($row['etd'] ?? '');
    $etd                        = (($etd == '1970-01-01') ? '' : processDateYtoD($etd));

    $eta                        = s__($row['eta'] ?? '');
    $eta                        = (($eta == '1970-01-01') ? '' : processDateYtoD($eta));

    $carrier                    = s__($row['carrier'] ?? '');
    $vessel_name                = s__($row['vessel_name'] ?? '');
    $vessel_departure_date      = s__($row['vessel_departure_date'] ?? '');
    $vessel_departure_date      = (($vessel_departure_date == '1970-01-01') ? '' : processDateYtoD($vessel_departure_date));

    $flight_no                  = s__($row['flight_no'] ?? '');
    $flight_departure_date      = s__($row['flight_departure_date'] ?? '');
    $flight_departure_date      = (($flight_departure_date == '1970-01-01') ? '' : processDateYtoD($flight_departure_date));

    $job_completion_date        = s__($row['job_completion_date'] ?? '');
    $job_completion_date        = (($job_completion_date == '1970-01-01') ? '' : processDateYtoD($job_completion_date));

    $payment_terms              = s__($row['payment_terms'] ?? '');
    $hawb                       = s__($row['hawb'] ?? '');
    $mawb                       = s__($row['mawb'] ?? '');
    $estimated_cost_amount      = s__($row['estimated_cost_amount'] ?? '');
    $declaration_no             = s__($row['declaration_no'] ?? '');


    $gross_weight               = s__($row['gross_weight'] ?? '');
    $volume_weight              = s__($row['volume_weight'] ?? '');
    $chargeable_weight          = s__($row['chargeable_weight'] ?? '');
    $no_of_pieces               = s__($row['no_of_pieces'] ?? '');
    $commodity_type             = s__($row['commodity_type'] ?? '');

    $no_of_containers           = s__($row['no_of_containers'] ?? '');
    $insurance_needed           = s__($row['insurance_needed'] ?? '');
    $container_type             = s__($row['container_type'] ?? '');
    $temperature_control_required = s__($row['temperature_control_required'] ?? '');
    $container_number           = s__($row['container_number'] ?? '');
    $special_comments           = s__($row['special_comments'] ?? '');

    $landing_country            = s__($row['landing_country'] ?? '');
    $landing_port               = s__($row['landing_port'] ?? '');
    $loading_place              = s__($row['loading_place'] ?? '');
    $billing_city               = s__($row['billing_city'] ?? '');
    $billing_state              = s__($row['billing_state'] ?? '');
    $billing_code               = s__($row['billing_code'] ?? '');
    $billing_country            = s__($row['billing_country'] ?? '');

    $destination_country        = s__($row['destination_country'] ?? '');
    $destination_port           = s__($row['destination_port'] ?? '');
    $fdp                        = s__($row['fdp'] ?? '');
    $shipping_city              = s__($row['shipping_city'] ?? '');
    $shipping_state             = s__($row['shipping_state'] ?? '');
    $shipping_code              = s__($row['shipping_code'] ?? '');
    $shipping_country           = s__($row['shipping_country'] ?? '');

    $happy_customer             = s__($row['happy_customer'] ?? '');
    $unhappy_reason             = s__($row['unhappy_reason'] ?? '');
    $shipment_on_time           = s__($row['shipment_on_time'] ?? '');
    $referral                   = s__($row['referral'] ?? '');
    $notes                      = s__($row['notes'] ?? '');

    $created_by                 = s__($row['created_by'] ?? '');
    $modified_by                = '';
    $customer_type              = '';
    $quote_id                   = '';
    $books_customer_id          = $customer_id;
    $approved_time              = s__($row['created_at'] ?? '');
    $project_id                 = '';
    $approved_time_resubmission = '';


    $publish                = s__($row['is_active'] ?? '');

    // $expiry_date        = ($expiry_date == '1970-01-01' ? '' : processDateDtoY($expiry_date));


    // ------------------ TOTAL quotation ITEMS ------------------
    $result_quotation_items     = $mysqli->query("SELECT * FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$id");
    $total_rows                 = $result_quotation_items->num_rows;


    if ($total_rows > 0) {
        while ($row_quotation_items = $result_quotation_items->fetch_array()) {

            array_push($item_id_arr,                $row_quotation_items['id']);
            array_push($service_arr,                $row_quotation_items['service']);
            array_push($description_arr,            $row_quotation_items['description']);
            array_push($qty_arr,                    $row_quotation_items['qty']);
            array_push($rate_arr,                   $row_quotation_items['rate']);
            array_push($sub_total_arr,              $row_quotation_items['sub_total']);
            // array_push($discount_type_arr,          $row_quotation_items['discount_type']);
            // array_push($discount_type_value_arr,    $row_quotation_items['discount_type_value']);
            // array_push($discount_amount_arr,        $row_quotation_items['discount_amount']);
            array_push($tax_arr,                    $row_quotation_items['tax']);
            array_push($tax_amount_arr,             $row_quotation_items['tax_amount']);
            array_push($total_arr,                  $row_quotation_items['total']);
        }
    }
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$job_id = $customer_id = $project_name = $job_seq = $job_status = '';

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
                    <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" <?php if ($publish == '1') { ?>checked="checked" <?php } ?> form="frmprojects">
                    <label class="form-check-label" for="publish">Publish</label>
                </div>
            </div>
            <div class="my-1">
                <?php if (empty($id) || (isset($module_id) && granted('create', $module_id)) || (isset($module_id) && granted('edit', $module_id)) || $file === 'profile.php' || $file === 'change_password.php') { ?>
                    <button type="submit" form="frmprojects" class="btn btn-primary btn-sm me-2">Save</button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php } else { ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php } ?>

                <!-- Page header -->



                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>Update<?php } else { ?>New<?php } ?> <?php echo $module_caption; ?>
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <?php
                                    $job_display = '';
                                    if (!empty($job_id)) {
                                        $warehouse_id_for_job = getTableAttr('warehouse_id', DB::JOBS, $job_id);
                                        if (!empty($warehouse_id_for_job)) {
                                            $job_display = getTableAttr('warehouse_name', DB::WAREHOUSES, $warehouse_id_for_job);
                                        }
                                    }

                                    //$customer_display = '';
                                    if (!empty($customer_id)) {
                                        $customer_display = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
                                    }

                                    $job_status_display = '';
                                    if (!empty($job_status)) {
                                        $job_status_display = getTableAttr('job_status', DB::JOB_STATUSES, $job_status);
                                    }
                                    ?>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Warehouse: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-plaintext">
                                                <?php echo !empty($job_display) ? $job_display : '—'; ?>
                                            </div>
                                            <input type="hidden" name="job_id" id="job_id" value="<?php echo $job_id; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Customer Name: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-plaintext">
                                                <?php echo !empty($customer_display) ? $customer_display : '—'; ?>
                                            </div>
                                            <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $customer_id; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Project Name:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="project_name" id="project_name" value="<?php echo $project_name; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Job Seq:</label>
                                        <div class="col-lg-9">
                                            <div class="form-control-plaintext">
                                                <?php echo !empty($job_seq) ? $job_seq : '—'; ?>
                                            </div>
                                            <input type="hidden" name="job_seq" id="job_seq" value="<?php echo $job_seq; ?>">
                                        </div>
                                    </div>


                                </div>
                            </div>
                        </div>



                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Job Status Details</h6>
                                </div>

                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-2 col-form-label">Job Status: </label>
                                        <div class="col-lg-4">
                                            <div class="form-control-plaintext mt-1">
                                                <?php echo !empty($job_status_display) ? $job_status_display : '—'; ?>
                                            </div>
                                            <input type="hidden" name="job_status" id="job_status" value="<?php echo $job_status; ?>">
                                        </div>

                                        <!-- <label class="col-lg-2">
                                        <button type="button" class="btn btn-light">Create Sales Order</button></label> -->
                                        <!-- <div class="col-lg-4">
                                            <input type="text" class="form-control" placeholder="Reference no" name="reference_no" id="reference_no" value="<?php echo $reference_no; ?>">
                                        </div> -->
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
