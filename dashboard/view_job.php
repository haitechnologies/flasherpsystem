<?php


use App\Core\DB;
use App\Core\Database;
include('admin_elements/admin_header.php');

/** @var \App\Core\Database $db */
$db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);

$module             = 'jobs';
$module_caption     = 'Job';
$tbl_name = DB::JOBS;
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

if (empty($id) && isset($_REQUEST['job_id'])) {
    $id = (int)$_REQUEST['job_id'];
}

if (!empty($id)) {
    $rs_valid = $db->fetchOne("SELECT id FROM `" . $tbl_name . "` WHERE id = :id", ['id' => $id]);
    if (!$rs_valid) {
        flash_error('Invalid Record in the database.');
        header("Location:listing_jobs.php");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


if (isset($_REQUEST['customer_id']) && !empty($_REQUEST['customer_id'])) {
    $customer_id     = (int)$_REQUEST['customer_id'];
} else {
    $customer_id = 0;
}



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/







/*
|--------------------------------------------------------------------------
| CREATE PROJECT FOR THE JOB
|--------------------------------------------------------------------------
|
*/
if ($action == "create_project" && !empty($id)) {

    // CSRF check
    if (!isset($_POST['csrf_token']) || !\App\Core\Session::csrf_validate($_POST['csrf_token'])) {
        $error_message = 'Invalid security token.';
    }

    $job = $db->fetchOne("SELECT project_created, customer_id, organization_id FROM `" . DB::JOBS . "` WHERE id = :id", ['id' => $id]);
    if (!$job) {
        $error_message = 'Job not found.';
    }

    // IF ALREADY CREATED PROJECT - EXIT
    $jobProjectCreated = $job ? (int)$job['project_created'] : 0;
    $jobOrganizationId = $job ? (int)$job['organization_id'] : 0;

    if ($jobProjectCreated === 1) {
        $error_message = 'The Project is already Created for this Job.';
    } elseif ($jobOrganizationId !== $activeOrganizationId) {
        $error_message = 'You do not have permission to create a project for this job.';
    } else {
        $projectCustomerId = (int)$job['customer_id'];

        $newProjectId = $db->insert("INSERT INTO `" . DB::PROJECTS . "` (job_id, customer_id) VALUES (:job_id, :customer_id)", [
            'job_id' => $id,
            'customer_id' => $projectCustomerId,
        ]);

        $db->execute("UPDATE `" . DB::JOBS . "` SET project_created = 1, project_id = :project_id WHERE id = :id", [
            'project_id' => $newProjectId,
            'id' => $id,
        ]);

        flash_success('Project created successfully.');
        header("Location:view_job.php?id=$id");
        exit;
    }
}





/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


// $created_by                 = '';
$modified_by                = '';
$customer_type              = '';
$quote_id                   = '';
$books_customer_id          = '';
$approved_time              = '';
$project_id                 = '';
$approved_time_resubmission = '';






// ---------------------- Job Items -----------------------------
$item_id_arr                = array();
$service_arr                = array();
$description_arr            = array();
$qty_arr                    = array();
$rate_arr                   = array();
$sub_total_arr              = array();
// $discount_type_arr          = array();
// $discount_type_value_arr    = array();
// $discount_amount_arr        = array();
$tax_arr                    = array();
$tax_amount_arr             = array();
$total_arr                  = array();


if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows            = e_s__($_POST['total_rows']);
    // if ($total_rows == 0 || $total_rows == '') $total_rows = 1;
} else {
    $total_rows            = 1;
}

// Default variable initializations (prevent undefined variable warnings)
$job_no = $job_ref_no = $job_status = $job_seq = '';
$sales_person = $sales_person_from_lead = '';
$warehouse_id = '';
$display_name = $company_name = '';
$currency = $exchange_rate = $transport_mode = $shipment_type = $job_owner = '';
$tags_captions = $cs_agent = $services_captions = $incoterm = $email = '';
$supplier_rate = $estimated_net_profit = '';
$job_date = $estimated_invoice_amount = $etd = $eta = '';
$carrier = $vessel_name = $vessel_departure_date = '';
$flight_no = $flight_departure_date = '';
$job_completion_date = $payment_terms = $hawb = $mawb = '';
$estimated_cost_amount = $declaration_no = '';
$gross_weight = $volume_weight = $cbm = $chargeable_weight = $no_of_pieces = '';
$commodity_type = '';
$no_of_containers = $insurance_needed = $container_type = '';
$temperature_control_required = $container_number = $special_comments = '';
$landing_country = $landing_port = $loading_place = '';
$billing_city = $billing_state = $billing_code = $billing_country = '';
$destination_country = $destination_port = $fdp = '';
$shipping_city = $shipping_state = $shipping_code = $shipping_country = '';
$happy_customer = $unhappy_reason = $shipment_on_time = '';
$referral = $notes = '';
$project_created = '';
// Dimension arrays
if (!isset($item_dim_id_arr)) $item_dim_id_arr = [];
if (!isset($dim_length_arr)) $dim_length_arr = [];
if (!isset($dim_width_arr)) $dim_width_arr = [];
if (!isset($dim_height_arr)) $dim_height_arr = [];
if (!isset($dim_pcs_arr)) $dim_pcs_arr = [];
if (!isset($dim_volume_arr)) $dim_volume_arr = [];
if (!isset($dim_cbm_arr)) $dim_cbm_arr = [];

/*
|--------------------------------------------------------------------------
| EDIT - ONLY SUPERADMIN or RELEVANT USER
|--------------------------------------------------------------------------
|
*/
$created_by = getTableAttr('created_by', DB::JOBS, $id);

if (!empty($id) && (is_SystemAdmin() || is_SuperAdmin() || is_role() == 'accounts' || is_role() == 'operations' || Session::userId() == $created_by)) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    $project_created        = s__($row['project_created']);
    $job_date               = s__($row['job_date']);
    $job_date               = processDateYtoD($job_date);
    $job_status             = s__($row['job_status']);

    $warehouse_id           = s__($row['warehouse_id']);
    $customer_id            = s__($row['customer_id']);
    $sales_person_from_lead = s__($row['sales_person_from_lead']);
    $job_seq                = s__($row['job_seq']);
    $sales_person           = s__($row['sales_person']);

    $currency               = s__($row['currency']);
    $exchange_rate          = s__($row['exchange_rate']);
    $transport_mode         = s__($row['transport_mode']);
    $shipment_type          = s__($row['shipment_type']);
    $job_owner              = s__($row['job_owner']);

    // -- Tags
    $tags                   = s__($row['tags']);
    $tags_arr               = array();
    $tags_captions = '';

    if ($tags != NULL) {
        $tags_arr               = explode(',', $tags);
        foreach ($tags_arr as $tag_id) {
            $tags_captions .= '<span class="badge bg-light text-dark">' . htmlspecialchars(getTableAttr('value', DB::TAXONOMIES, $tag_id)) . '</span> &nbsp;';
        }
    }


    // -- Services
    $services                   = s__($row['services']);
    $services_arr               = array();
    $services_captions = '';

    if ($services != NULL) {
        $services_arr               = explode(',', $services);

        // $services_captions = '';

        foreach ($services_arr as $service_id) {
            $services_captions .= '<span class="badge bg-light text-dark">' . htmlspecialchars(getTableAttr('item_name', DB::ITEMS, $service_id)) . '</span> &nbsp;';
        }
    }


    $job_no                 = s__($row['job_no']);
    $job_ref_no             = s__($row['job_ref_no']);
    $cs_agent               = s__($row['cs_agent']);
    $incoterm               = s__($row['incoterm']);
    $email                  = s__($row['email']);
    $supplier_rate          = s__($row['supplier_rate']);
    $estimated_net_profit   = s__($row['estimated_net_profit']);

    $estimated_invoice_amount   = s__($row['estimated_invoice_amount']);
    $etd                        = s__($row['etd']);
    $etd                        = (($etd == '1970-01-01') ? '' : processDateYtoD($etd));

    $eta                        = s__($row['eta']);
    $eta                        = (($eta == '1970-01-01') ? '' : processDateYtoD($eta));

    $carrier                    = s__($row['carrier']);
    $vessel_name                = s__($row['vessel_name']);
    $vessel_departure_date      = s__($row['vessel_departure_date']);
    $vessel_departure_date      = (($vessel_departure_date == '1970-01-01') ? '' : processDateYtoD($vessel_departure_date));

    $flight_no                  = s__($row['flight_no']);
    $flight_departure_date      = s__($row['flight_departure_date']);
    $flight_departure_date      = (($flight_departure_date == '1970-01-01') ? '' : processDateYtoD($flight_departure_date));

    $job_completion_date        = s__($row['job_completion_date']);
    $job_completion_date        = (($job_completion_date == '1970-01-01') ? '' : processDateYtoD($job_completion_date));

    $payment_terms              = s__($row['payment_terms']);
    $hawb                       = s__($row['hawb']);
    $mawb                       = s__($row['mawb']);
    $estimated_cost_amount      = s__($row['estimated_cost_amount']);
    $declaration_no             = s__($row['declaration_no']);


    $gross_weight               = s__($row['gross_weight']);
    $volume_weight              = s__($row['volume_weight']);
    $cbm                        = s__($row['cbm'] ?? '');
    $chargeable_weight          = s__($row['chargeable_weight']);
    $no_of_pieces               = s__($row['no_of_pieces']);
    $commodity_type             = s__($row['commodity_type']);

    $no_of_containers           = s__($row['no_of_containers']);
    $insurance_needed           = s__($row['insurance_needed']);
    $container_type             = s__($row['container_type']);
    $temperature_control_required = s__($row['temperature_control_required']);
    $container_number           = s__($row['container_number']);
    $special_comments           = s__($row['special_comments']);

    $landing_country            = s__($row['landing_country']);
    $landing_port               = s__($row['landing_port']);
    $loading_place              = s__($row['loading_place']);
    $billing_city               = s__($row['billing_city']);
    $billing_state              = s__($row['billing_state']);
    $billing_code               = s__($row['billing_code']);
    $billing_country            = s__($row['billing_country']);

    $destination_country        = s__($row['destination_country']);
    $destination_port           = s__($row['destination_port']);
    $fdp                        = s__($row['fdp']);
    $shipping_city              = s__($row['shipping_city']);
    $shipping_state             = s__($row['shipping_state']);
    $shipping_code              = s__($row['shipping_code']);
    $shipping_country           = s__($row['shipping_country']);

    $happy_customer             = s__($row['happy_customer']);
    $unhappy_reason             = s__($row['unhappy_reason']);
    $shipment_on_time           = s__($row['shipment_on_time']);
    $referral                   = s__($row['referral']);
    $notes                      = s__($row['notes']);

    $modified_by                = s__($row['modified_by']);
    $modified_by_name           = getTableAttr('full_name', DB::USERS, $modified_by);
    $customer_type              = getTableAttr('customer_type', DB::CUSTOMERS, $customer_id);
    $quote_id                   = s__($row['quote_id']);
    $books_customer_id          = s__($row['books_customer_id']);
    $approved_time              = s__($row['created_at']);
    $project_id                 = s__($row['project_id']);
    $approved_time_resubmission = s__($row['approved_time_resubmission']);

    $publish                = s__($row['is_active']);

    // $expiry_date        = ($expiry_date == '1970-01-01' ? '' : processDateDtoY($expiry_date));


    // ------------------ TOTAL ITEMS ------------------
    // ------------------ TOTAL ITEMS ------------------
    $result_job_items = $db->fetchAll("SELECT job_id, dim_length, dim_width, dim_height, dim_pcs, dim_volume, dim_cbm FROM `" . tbl_job_items . "` WHERE job_id = :job_id AND organization_id = :org_id", ['job_id' => $id, 'org_id' => $activeOrganizationId]);

    // **CRITICAL FIX: INITIALIZE ARRAYS HERE**
    $item_dim_id_arr = [];
    $dim_length_arr  = [];
    $dim_width_arr   = [];
    $dim_height_arr  = [];
    $dim_pcs_arr     = [];
    $dim_volume_arr  = [];
    $dim_cbm_arr     = [];
    // --------------------------------------------------

    $total_rows = count($result_job_items);

    if ($total_rows > 0) {
        foreach ($result_job_items as $row_job_items) {
            $item_dim_id_arr[] = $row_job_items['job_id'];
            $dim_length_arr[]  = $row_job_items['dim_length'];
            $dim_width_arr[]   = $row_job_items['dim_width'];
            $dim_height_arr[]  = $row_job_items['dim_height'];
            $dim_pcs_arr[]     = $row_job_items['dim_pcs'];
            $dim_volume_arr[]  = $row_job_items['dim_volume'];
            $dim_cbm_arr[]     = $row_job_items['dim_cbm'];
        }
    }
}

if ($total_rows == 0) $total_rows = 1;


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>


<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content d-lg-flex border-top py-2 px-3 carriers-page-header-content align-items-center">
            <div class="my-1 d-flex align-items-center gap-2 flex-wrap">
                <h5 class="mb-0">JOB# <?php echo htmlspecialchars((string)$job_no); ?></h5>
                <?php if (!empty($job_ref_no)): ?><span class="text-muted small">(<?php echo htmlspecialchars((string)$job_ref_no); ?>)</span><?php endif; ?>
                <label class="form-check-label text-muted small mb-0"><?php echo (!empty($job_status) ? strtoupper((string)getTableAttr("job_status", DB::JOB_STATUSES, $job_status)) : ''); ?></label>
            </div>
            <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">

                <?php
                $draft_row = $db->fetchOne("SELECT id FROM `" . DB::JOB_STATUSES . "` WHERE LOWER(job_status) = 'draft' LIMIT 1");
                $draft_job_status_id = $draft_row ? (int)$draft_row['id'] : 0;

                $approved_row = $db->fetchOne("SELECT id FROM `" . DB::JOB_STATUSES . "` WHERE LOWER(job_status) = 'approved' LIMIT 1");
                $approved_job_status_id = $approved_row ? (int)$approved_row['id'] : 0;

                $pending_approval_row = $db->fetchOne("SELECT id FROM `" . DB::JOB_STATUSES . "` WHERE LOWER(job_status) = 'pending_approval' LIMIT 1");
                $pending_approval_status_id = $pending_approval_row ? (int)$pending_approval_row['id'] : 0;

                $current_role_id = \App\Core\Session::roleId();
                $is_accounts = ($current_role_id === \App\Security\Roles::ACCOUNTS) || \App\Security\Roles::hasFullAccess($current_role_id);
                ?>

                <?php if ($project_created == 0) { ?>

                    <?php if ($job_status == $draft_job_status_id) { ?>
                        <button type="button" onclick="window.location.href='<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $id; ?>'; " class="btn btn-primary btn-sm me-2">Edit</button>
                    <?php } ?>

                    <?php if ($job_status == $pending_approval_status_id) { ?>
                        <?php if ($is_accounts) { ?>
                            <button type="button" onclick="if(confirm('Approve this job?')){ document.getElementById('frmjobs').action='jobs.php?action=approve_job&id=<?php echo $id; ?>'; document.getElementById('frmjobs').submit(); }" class="btn btn-success btn-sm me-2"><i class="ph-check-circle me-1"></i> Approve</button>
                            <button type="button" onclick="if(confirm('Send back to draft? Operations will be able to edit again.')){ document.getElementById('frmjobs').action='jobs.php?action=reject_job&id=<?php echo $id; ?>'; document.getElementById('frmjobs').submit(); }" class="btn btn-danger btn-sm me-2"><i class="ph-x-circle me-1"></i> Reject</button>
                        <?php } else { ?>
                            <span class="badge bg-warning bg-opacity-10 text-warning px-3 py-2"><i class="ph-clock-countdown me-1"></i> Under Review — Awaiting Accounts Approval</span>
                        <?php } ?>
                    <?php } ?>

                    <?php if ($job_status == $approved_job_status_id) { ?>
                        <button type="button" onclick="window.location.href='view_job.php?action=create_project&id=<?php echo $id; ?>';" class="btn btn-primary btn-sm me-2">Create Project</button>
                    <?php } ?>

                <?php } else { ?>
                    <button type="button" class=" btn btn-light my-1 me-2" disabled>Project Created</button>
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
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="job_status" id="job_status" value="" />
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

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Warehouse:*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('warehouse_name', DB::WAREHOUSES, $warehouse_id); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Customer Name:*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('display_name', DB::CUSTOMERS, $customer_id); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Sale Person from Lead:</label>
                                        <div class="col-lg-9">
                                            <?php echo nz($sales_person_from_lead); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Job Seq:</label>
                                        <div class="col-lg-9">
                                            <?php echo nz($job_seq); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Sales Person: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('full_name', DB::USERS, $sales_person); ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>



                        <div class="col-lg-6">
                            <div class="card">

                                <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"></h6>
                                </div> -->

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Currency: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('currency', DB::CURRENCIES, $currency); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Exchange Rate:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($exchange_rate); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Transport Mode: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($transport_mode); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Type of Shipment:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php
                                            $types = !empty($shipment_type) ? explode(', ', $shipment_type) : [];
                                            foreach ($types as $t): ?>
                                                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($t)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Job Owner:*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('full_name', DB::USERS, $job_owner); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Tag:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $tags_captions; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>


                    </div>
                </div>



                <div class="row">
                    <div class="col-xl-12">

                        <div class="card">

                            <div class="card-header d-flex align-items-center">
                                <h6 class="mb-0">Job Status Details</h6>
                            </div>

                            <div class="card-body">

                                <div class="row">
                                    <label class="col-lg-2 col-form-label">Job Status: </label>
                                    <div class="col-lg-4 mt-2">
                                        <?php echo getTableAttr('job_status', DB::JOB_STATUSES, $job_status); ?>
                                    </div>

                                </div>

                            </div>
                        </div>

                    </div>
                </div>



                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">

                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                        Detailed Job Information
                                    </h6>
                                </div>

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Job No:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $job_no; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Job Ref No:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $job_ref_no; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">CS Agent: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('department', DB::DEPARTMENTS, $cs_agent); ?>

                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Type of Services: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $services_captions; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Incoterms: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('incoterm', DB::INCOTERMS, $incoterm); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Email:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $email; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Supplier Rates:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($supplier_rate); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Estimated Net Profit:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($estimated_net_profit); ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>



                        <div class="col-lg-6">
                            <div class="card">

                                <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"></h6>
                                </div> -->

                                <div class="card-body">

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Job Date:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $job_date; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Estimated Invoice Amount:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($estimated_invoice_amount); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">ETD: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $etd; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">ETA: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $eta; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Carrier Name: </label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo getTableAttr('carrier_name', DB::CARRIERS, $carrier); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Vessel Name:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $vessel_name; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Vessel Departure Date:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $vessel_departure_date; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Flight No:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $flight_no; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Flight Departure Date:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $flight_departure_date; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Job Completed Date:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $job_completion_date; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Payment Terms:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $payment_terms; ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">HAWB / HBL:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nl2br(htmlspecialchars($hawb)); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">MAWB / MBL:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nl2br(htmlspecialchars($mawb)); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label">Estimated Cost Amount:</label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo nz($estimated_cost_amount); ?>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Customs Declaration No:*</span></label>
                                        <div class="col-lg-9 mt-2">
                                            <?php echo $declaration_no; ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>


                    </div>
                </div>


                <!-- L × W × H ÷ 6000 ÷ 166.66 -->
                <div>

                    <div class="col-xl-12">

                        <div class="row">

                            <div class="col-lg-2">
                                <label class="form-label ms-3 fw-semibold">LENGTH </label>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label ms-4 fw-semibold">WIDTH </label>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label ms-3 fw-semibold">HEIGHT </label>
                            </div>

                            <div class="col-lg-1">
                                <label class="form-label ms-2 fw-semibold">NO OF PCS </label>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label ms-5 fw-semibold">VOLUME WEIGHT </label>
                            </div>

                            <div class="col-lg-2">
                                <label class="form-label ms-2 fw-semibold">CBM</label>
                            </div>

                        </div>

                        <div class="card">

                            <div class="row card-body">

                                <div class="col-lg-12">

                                    <?php
                                    $total_cbm = 0;
                                    $total_volume = 0;
                                    $total_pcs = 0;

                                    // ----------------------------------------------------------------------------
                                    for ($dim_item = 1; $dim_item <= $total_rows; $dim_item++) {
                                        $index = $dim_item;
                                        $index = $index - 1;

                                        $total_cbm += (isset($dim_cbm_arr[$index]) ? $dim_cbm_arr[$index] : 0);
                                        $total_volume += (isset($dim_volume_arr[$index]) ? $dim_volume_arr[$index] : 0);
                                        $total_pcs += (isset($dim_pcs_arr[$index]) ? $dim_pcs_arr[$index] : 0);

                                        // ----------------------------------------------------------------------------
                                    ?>

                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3">

                                                <div class="col-lg-12">
                                                    <div class="row">

                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-center" value="<?php echo (!empty($dim_length_arr[$index]) ? $dim_length_arr[$index] : '0'); ?>">
                                                        </div>


                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-center" value="<?php echo (!empty($dim_width_arr[$index]) ? $dim_width_arr[$index] : '0'); ?>">
                                                        </div>


                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-center" value="<?php echo (!empty($dim_height_arr[$index]) ? $dim_height_arr[$index] : '0'); ?>">
                                                        </div>

                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-center" value="<?php echo (!empty($dim_pcs_arr[$index]) ? $dim_pcs_arr[$index] : '0'); ?>">
                                                        </div>

                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo (!empty($dim_volume_arr[$index]) ? $dim_volume_arr[$index] : '0'); ?>">
                                                        </div>

                                                        <div class="col-lg-2">
                                                            <input readonly type="number" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo (!empty($dim_cbm_arr[$index]) ? $dim_cbm_arr[$index] : '0'); ?>">
                                                        </div>

                                                    </div>
                                                </div>

                                            </div>

                                        </div>

                                    <?php
                                        // -------------------------------------------------- 
                                    } // for 
                                    // -------------------------------------------------- 
                                    ?>

                                </div>

                            </div>
                        </div>
                    </div>


                    <div class="row">

                        <div class="col-lg-8"></div>

                        <div class="col-lg-4">
                            <div class="card ">

                                <div class="card-body"> <!--  bg-info bg-opacity-10 -->

                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">TOTAL CBM:</label>
                                        <div class="col-lg-6">
                                            <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="grand_subtotal" id="grand_subtotal" value="<?php echo $total_cbm; ?>" />
                                        </div>
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label">Total Volume Weight</label>
                                        <div class="col-lg-6">
                                            <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_tax" id="grand_tax" value="<?php echo $total_volume; ?>" placeholder="0">
                                        </div>
                                    </div>


                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Total Pieces</label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" name="grand_total" id="grand_total" value="<?php echo $total_pcs; ?>" readonly>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        </div>



                    </div>


                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">

                                    <div class="card-header d-flex align-items-center">
                                        <h6 class="mb-0">
                                            Commodity Details
                                        </h6>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Gross Weight: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($gross_weight); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Volume Weight: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($volume_weight); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">CBM: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($cbm); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Chargable Weight: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($chargeable_weight); ?>
                                            </div>
                                        </div>


                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">No. of Pieces: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($no_of_pieces); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Commodity Type: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo htmlspecialchars($commodity_type); ?>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>



                            <div class="col-lg-6">
                                <div class="card">

                                    <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"></h6>
                                </div> -->

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">No. of Contaiers:</label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo nz($no_of_containers); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Insurance Needed?:</label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo ($insurance_needed === '1' ? 'Yes' : 'No'); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Container Type: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('container_type', DB::CONTAINER_TYPES, $container_type); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Temperature Control Required: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo ($temperature_control_required === '1' ? 'Yes' : 'No'); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Container Number:</label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $container_number; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Special Comments:</label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $special_comments; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>


                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">

                                    <div class="card-header d-flex align-items-center">
                                        <h6 class="mb-0">
                                            Port Details
                                        </h6>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Loading Country: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('country', DB::GEO_COUNTRIES, $landing_country); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Port of Loading (POL): </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('port_name', DB::PORTS, $landing_port); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Place of Loading: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $loading_place; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Billing City: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $billing_city; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Billing State: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $billing_state; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Billing Code: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $billing_code; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Billing Country: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('country', DB::GEO_COUNTRIES, $billing_country); ?>
                                            </div>
                                        </div>


                                    </div>
                                </div>
                            </div>



                            <div class="col-lg-6">
                                <div class="card">

                                    <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"></h6>
                                </div> -->

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Destination Country: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('country', DB::GEO_COUNTRIES, $destination_country); ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Port of Destination (POD): </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('port_name', DB::PORTS, $destination_port); ?>
                                            </div>
                                        </div>


                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Final Destination (FDP): </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $fdp; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Shipping City: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $shipping_city; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Shipping State: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $shipping_state; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Shipping Code: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $shipping_code; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Shipping Country: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo getTableAttr('country', DB::GEO_COUNTRIES, $shipping_country); ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>



                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">

                                    <div class="card-header d-flex align-items-center">
                                        <h6 class="mb-0">
                                            After Service
                                        </h6>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Customer happy with service: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $happy_customer; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Reason for Customer Unsatisfaction: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $unhappy_reason; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>



                            <div class="col-lg-6">
                                <div class="card">

                                    <!-- <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"></h6>
                                </div> -->

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Shipment Delivered on Time: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $shipment_on_time; ?>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Referral: </label>
                                            <div class="col-lg-9 mt-2">
                                                <?php echo $referral; ?>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>



                    <div class="col-xl-12">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="card">

                                    <div class="card-header d-flex align-items-center">
                                        <h6 class="mb-0">
                                            System Fields
                                        </h6>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Created By: </label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo getTableAttr('full_name', DB::USERS, $created_by); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Modified By: </label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo $modified_by_name ?? $modified_by; ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Customer Type: </label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo ucwords($customer_type); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Quote: </label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo nz($quote_id); ?>" disabled>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>



                            <div class="col-lg-6">
                                <div class="card">

                                    <div class="card-header d-flex align-items-center">
                                        <h6 class="mb-0">&nbsp;</h6>
                                    </div>

                                    <div class="card-body">

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Books Customer ID:</label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo nz($books_customer_id); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Approved Time:</label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo $approved_time; ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Project ID:</label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo nz($project_id); ?>" disabled>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <label class="col-lg-3 col-form-label">Approved Time ReSubmission:</label>
                                            <div class="col-lg-9 mt-1">
                                                <input type="text" class="form-control" value="<?php echo $approved_time_resubmission; ?>" disabled>
                                            </div>
                                        </div>

                                    </div>
                                </div>
                            </div>


                        </div>
                    </div>



                    <div class="row">

                        <div class="col-lg-4">

                            <div class="row">
                                <div class="col-lg-12 mt-2">
                                    <div class="ms-sm-3 mb-3 mb-sm-0">
                                        <label class="col-lg-6 col-form-label">Notes:</label>
                                        <textarea class="form-control" name="notes" id="notes" style="field-sizing: content;" placeholder="" disabled><?php echo $notes; ?></textarea>
                                        <!-- Looking forward for your business -->
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

                <div class="mt-5 alert alert-info border-0 fade show small">
                    <!-- <i class="ph-info me-2"></i> -->
                    <!-- <span class="fw-semibold">Heads up!</span>  -->
                    *Job Edit option available for Draft Status. *Approved Project will show CREATE PROJECT option.
                </div>

            </div>


            <?php include('admin_elements/copyright.php'); ?>
        </div>
    </form>
</div>


<?php include('admin_elements/admin_footer.php'); ?>