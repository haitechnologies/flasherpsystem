<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module                 = 'leads';
$module_caption         = 'Lead';
$tbl_name = DB::LEADS;

// $photo_upload_path          = '../uploads/' . $module . '/';
// $allowed_file_size          = $GLOBALS['PHOTO']['MAX_UPLOAD_SIZE']; //MB Bytes
// $allowed_file_formats       = $GLOBALS['PHOTO']['FORMATS']; //MB Bytes

// $image_width                    = '500';
// $image_height                   = '500';

// $thumb_width                    = '200';
// $thumb_height                   = '200';

// $display_thumb_width            = '100';
// $display_thumb_height           = '100';

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



// CHECK IF NOT SUPER ADMIN
// FOR - LEAD OWNER - ASSGINED TO - CREATED BY
if ($session_role_id > 2 && !empty($id)) {
    $rs_verify = $mysqli->query("SELECT id FROM `" . DB::LEADS  . "` WHERE id=$id AND (lead_owner = Session::userId() OR assigned_to = Session::userId() OR created_by = Session::userId())");
    if ($rs_verify->num_rows == 0) {
        flash_error('Leads Permissions not Valid.');
        header("Location:listing_leads.php");
    }
}
/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

if (isset($_POST['publish']))                                 $publish     = 1;
else $publish = 0;


$lead_type     = 'business';

if (isset($_POST['lead_type'])) {
    $lead_type     = e_s__($_POST['lead_type']);
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
}

// print_r($posted_tags_arr);


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" || $action == "add_$module") {

    $lead_owner                 = e_s__($_POST['lead_owner'] ?? '');

    $lead_status                = e_s__($_POST['lead_status'] ?? '');
    $lead_source                = e_s__($_POST['lead_source'] ?? '');
    $assigned_to                = e_s__($_POST['assigned_to'] ?? '');

    $salutation                 = e_s__($_POST['salutation'] ?? '');
    $first_name                 = e_s__($_POST['first_name'] ?? '');
    $last_name                  = e_s__($_POST['last_name'] ?? '');
    // $company_name               = e_s__($_POST['company_name'] ?? '');
    $display_name               = e_s__($_POST['display_name'] ?? '');
    $address                    = e_s__($_POST['address'] ?? '');
    $email                      = e_s__($_POST['email'] ?? '');
    $phone                      = e_s__($_POST['phone'] ?? '');
    $mobile                     = e_s__($_POST['mobile'] ?? '');
    $trn                        = e_s__($_POST['trn'] ?? '');
    $contacted_date             = e_s__($_POST['contacted_date'] ?? '');
    $description                = e_s__($_POST['description'] ?? '');

    $street1                    = e_s__($_POST['street1'] ?? '');
    $street2                    = e_s__($_POST['street2'] ?? '');
    $city                       = e_s__($_POST['city'] ?? '');
    $state                      = e_s__($_POST['state'] ?? '');
    $pobox                      = e_s__($_POST['pobox'] ?? '');
    $country                    = e_s__($_POST['country'] ?? '');

    $service                    = e_s__($_POST['service'] ?? '');

    $website                    = e_s__($_POST['website'] ?? '');
    $department                 = e_s__($_POST['department'] ?? '');
    $designation                = e_s__($_POST['designation'] ?? '');
    $x                          = e_s__($_POST['x'] ?? '');
    $facebook                   = e_s__($_POST['facebook'] ?? '');
    $instagram                  = e_s__($_POST['instagram'] ?? '');
} else {

    $lead_owner                 = '';

    $lead_status                = '';
    $lead_source                = '';
    $assigned_to                = '';

    $salutation                 = '';
    $first_name                 = '';
    $last_name                  = '';
    // $company_name               = '';
    $display_name               = '';
    $address                    = '';
    $email                      = '';
    $phone                      = '';
    $mobile                     = '';
    $trn                        = '';
    $contacted_date             = '';
    $description                = '';

    $street1                    = '';
    $street2                    = '';
    $city                       = '';
    $state                      = '';
    $pobox                      = '';
    $country                    = '';

    $service                    = '';

    $website                    = '';
    $department                 = '';
    $designation                = '';
    $x                          = '';
    $facebook                   = '';
    $instagram                  = '';
}

$is_converted = 0;

/*
|--------------------------------------------------------------------------
| 	UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" && !empty($id) && granted('edit', $module_id)) {

    if (empty($display_name)) {
        $error_message = 'Display name is mandatory.';
    } else if (empty($address)) {
        $error_message = 'Address is mandatory.';
    } else {

        $lead_owner     = (empty($lead_owner) ? '0' : $lead_owner);
        $lead_status    = (empty($lead_status) ? '0' : $lead_status);
        $lead_source    = (empty($lead_source) ? '0' : $lead_source);
        $contacted_date = (empty($contacted_date) ? '1970-01-01 00:00:00' : date('Y-m-d h:i:s', strtotime($contacted_date)));
        $assigned_to    = (empty($assigned_to) ? '0' : $assigned_to);
        $service        = (empty($service) ? '0' : $service);
        $state          = (empty($state) ? '0' : $state);
        $country        = (empty($country) ? '0' : $country);

        /* ---------------------- QUERY ---------------------- */
        $update_row = $mysqli->query("
										UPDATE `$tbl_name` SET
											
                                            lead_owner			    = '" . $lead_owner . "',
                                            
                                            lead_status			    = '" . $lead_status . "',
											lead_source			    = '" . $lead_source . "',
											assigned_to			    = '" . $assigned_to . "',
											
                                            lead_type			    = '" . $lead_type . "',
											salutation			    = '" . $salutation . "',
											first_name			    = '" . $first_name . "',
											last_name			    = '" . $last_name . "',
											display_name			= '" . $display_name . "',
											address			        = '" . $address . "',
											email			        = '" . $email . "',
											phone			        = '" . $phone . "',
											mobile			        = '" . $mobile . "',
											trn			            = '" . $trn . "',
											contacted_date			= '" . $contacted_date . "',
											description			    = '" . $description . "',
											tags			        = '" . $tags_string . ",',
                                            
                                            street1			        = '" . $street1 . "',
											street2			        = '" . $street2 . "',
											city			        = '" . $city . "',
											state			        = '" . $state . "',
											pobox			        = '" . $pobox . "',
											country			        = '" . $country . "',
											
                                            service			        = '" . $service . "',

											website			        = '" . $website . "',
											department			    = '" . $department . "',
											designation			    = '" . $designation . "',
											x			            = '" . $x . "',
											facebook			    = '" . $facebook . "',
											instagram			    = '" . $instagram . "',
                                            is_active 			    = '" . $publish . "'
										WHERE id=$id");
        if ($update_row) {
            $success_message = "The $module_caption has been updated successfully.";
            fp__($tbl_name, $id);
            // Lead Logs
            updateLeadLogs($id, 'lead', $id, 'updated');
            flash_success($success_message);
            header("Location:listing_$module.php");
        } else {
            $error_message = "The $module_caption could not be updated. Please try again.";
            //header("Location:$module.php?action=edit_$module&id=$id&error_message=$error_message");
        }
    }

    /*
|--------------------------------------------------------------------------
| 	ADD
|--------------------------------------------------------------------------
|
*/
} else if ($action == "add_$module" && granted('create', $module_id)) {


    if (empty($display_name)) {
        $error_message = 'Display name is mandatory.';
    } else if (empty($address)) {
        $error_message = 'Address is mandatory.';
    } else {

        $lead_owner     = (empty($lead_owner) ? '0' : $lead_owner);
        $lead_status    = (empty($lead_status) ? '0' : $lead_status);
        $lead_source    = (empty($lead_source) ? '0' : $lead_source);

        $contacted_date = (empty($contacted_date) ? '1970-01-01 00:00:00' : processDateDtoY($contacted_date));
        $assigned_to    = (empty($assigned_to) ? '0' : $assigned_to);
        $service        = (empty($service) ? '0' : $service);
        $state          = (empty($state) ? '0' : $state);
        $country        = (empty($country) ? '0' : $country);

        $insert_row = $mysqli->query("INSERT INTO `$tbl_name`(lead_type, lead_owner, lead_status, lead_source, assigned_to, salutation, first_name, last_name, display_name, address, email, phone, mobile, trn, contacted_date, description, tags, street1, street2, city, state, pobox, country, service, website, department, designation, x, facebook, instagram, is_active) VALUES ('" . $lead_type . "', '" . $lead_owner . "', '" . $lead_status . "', '" . $lead_source . "', '" . $assigned_to . "', '" . $salutation . "', '" . $first_name . "', '" . $last_name . "', '" . $display_name . "', '" . $address . "', '" . $email . "', '" . $phone . "', '" . $mobile . "', '" . $trn . "', '" . $contacted_date . "', '" . $description . "', '" . $tags_string . ",', '" . $street1 . "', '" . $street2 . "', '" . $city . "', '" . $state . "',   '" . $pobox . "',  '" . $country . "',  '" . $service . "',  '" . $website . "', '" . $department . "', '" . $designation . "', '" . $x . "', '" . $facebook . "', '" . $instagram . "',  '" . $publish . "'); ");


        if ($insert_row) {
            $id = $mysqli->insert_id;
            $success_message = "The $module_caption has been saved successfully.";
            fp__($tbl_name, $id);
            // Lead Logs
            updateLeadLogs($id, 'lead',  $id, 'created');
            ////////////////////////////////////////////////////////////////////////
            flash_success($success_message);
            header("Location:listing_$module.php");
        } else {
            $error_message = "The $module_caption could not be saved. Please try again.";
            // header("Location:$module.php?error_message=$error_message");
        }
    }
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
*/
if (!empty($id)) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    $is_converted          = (int)$row['is_converted'];

    $lead_owner             = s__($row['lead_owner']);

    $lead_status            = s__($row['lead_status']);
    $lead_source            = s__($row['lead_source']);
    $assigned_to            = s__($row['assigned_to']);

    $lead_type              = s__($row['lead_type']);
    $salutation             = s__($row['salutation']);
    $first_name             = s__($row['first_name']);
    $last_name              = s__($row['last_name']);
    $display_name           = s__($row['display_name']);
    $address                = s__($row['address']);
    $email                  = s__($row['email']);
    $phone                  = s__($row['phone']);
    $mobile                 = s__($row['mobile']);
    $trn                    = s__($row['trn']);

    $contacted_date         = s__($row['contacted_date']);
    $contacted_date         = ($contacted_date == '1970-01-01 00:00:00' ? '' : date('d-m-Y h:i:s', strtotime($contacted_date)));
    // $contacted_date         = (empty($contacted_date) ? date('d-m-Y h:i:s', time()) : date('d-m-Y h:i:s', strtotime($contacted_date)));

    $description            = s__($row['description']);

    // -- Tags
    $tags                   = s__($row['tags']);
    $tags_arr               = array();
    if ($tags != NULL) {
        $tags_arr               = explode(',', $tags);
    }

    $street1                = s__($row['street1']);
    $street2                = s__($row['street2']);
    $city                   = s__($row['city']);
    $state                  = s__($row['state']);
    $pobox                  = s__($row['pobox']);
    $country                = s__($row['country']);

    $service                = s__($row['service']);

    $website                = s__($row['website']);
    $department             = s__($row['department']);
    $designation            = s__($row['designation']);
    $x                      = s__($row['x']);
    $facebook               = s__($row['facebook']);
    $instagram              = s__($row['instagram']);
    $publish                = s__($row['is_active']);
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
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>Edit<?php } else { ?>New<?php } ?> <?php echo $module_caption; ?></h5>
                <span class="text-muted small">(<?php if ($publish == '1') { ?>Active<?php } else { ?>InActive<?php } ?>)</span>
            </div>

            <div class="my-1">
                <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm me-2">Save</button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off" enctype="multipart/form-data">
                <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php } else { ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php } ?>

                <div class="row">

                    <div class="col-lg-6">
                        <div class="card">

                            <div class="card-body">

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">&nbsp;</label>
                                    <div class="col-lg-9">
                                        <div class="mt-2">
                                            <!-- <p class="fw-semibold">Type</p> -->
                                            <div class="form-check form-check-inline">
                                                <input type="radio" class="form-check-input" name="lead_type" id="lead_type" value="business" <?php if ($lead_type == 'business') { ?>checked <?php } ?>>
                                                <label class="form-check-label">Business</label>
                                            </div>

                                            <div class="form-check form-check-inline">
                                                <input type="radio" class="form-check-input" name="lead_type" id="lead_type" value="individual" <?php if ($lead_type == 'individual') { ?>checked <?php } ?>>
                                                <label class="form-check-label">Individual</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>


                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Primary Contact: <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-salutation="The name you enter here will be for your primary contact. You can continue to add multiple contact persons from the details page"></i> </label>

                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <select class="form-select" name="salutation" id="salutation">
                                                <option value="0"></option>
                                                <option value="mr." <?php if ($action == "edit_$module" && $salutation == 'mr.') { ?>selected <?php } else if ($salutation == 'mr.') { ?>selected <?php } ?>>Mr.</option>
                                                <option value="ms." <?php if ($action == "edit_$module" && $salutation == 'ms.') { ?>selected <?php } else if ($salutation == 'ms.') { ?>selected <?php } ?>>Ms.</option>
                                                <option value="mrs." <?php if ($action == "edit_$module" && $salutation == 'mrs.') { ?>selected <?php } else if ($salutation == 'mrs.') { ?>selected <?php } ?>>Mrs.</option>
                                                <option value="miss." <?php if ($action == "edit_$module" && $salutation == 'miss.') { ?>selected <?php } else if ($salutation == 'miss.') { ?>selected <?php } ?>>Miss.</option>
                                                <option value="dr." <?php if ($action == "edit_$module" && $salutation == 'dr.') { ?>selected <?php } else if ($salutation == 'dr.') { ?>selected <?php } ?>>Dr.</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <!-- <label class="form-label fw-semibold">Care of:</label> -->
                                            <input type="text" class="form-control" name="first_name" id="first_name" value="<?php echo $first_name; ?>" placeholder="First Name">
                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <input type="text" class="form-control" name="last_name" id="last_name" value="<?php echo $last_name; ?>" placeholder="Last Name">
                                        </div>
                                    </div>

                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Company Name:*</span> <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="This name will be displayed on the transactions you create for this lead"></i> </label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="display_name" id="display_name" value="<?php echo $display_name; ?>" class="form-control" <?php echo ($is_converted === 1 ? 'readonly' : ''); ?>>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Address:*</span> </label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="address" id="address" value="<?php echo $address; ?>" class="form-control" <?php echo ($is_converted === 1 ? 'readonly' : ''); ?>>
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Email Address: <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="Privacy Info: This data will be stored without encryption and will be visible only to your organisation users who have the required permission."></i> </label>
                                    <div class="col-lg-9">
                                        <input type="email" name="email" id="email" value="<?php echo $email; ?>" class="form-control">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Phone: <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="Privacy Info: This data will be stored without encryption and will be visible only to your organisation users who have the required permission."></i> </label>

                                    <div class="col-lg-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-phone"></i></span>
                                            <input type="text" class="form-control" name="phone" id="phone" value="<?php echo $phone; ?>" placeholder="Work Phone">
                                        </div>
                                    </div>

                                    <div class="col-lg-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-device-mobile"></i></span>
                                            <input type="text" class="form-control" name="mobile" id="mobile" value="<?php echo $mobile; ?>" placeholder="Mobile">
                                        </div>
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Contacted: </label>
                                    <div class="col-lg-4">
                                        <input type="text" name="contacted_date" id="contacted_date" value="<?php echo $contacted_date; ?>" class="form-control">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Description: </label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="description" id="description"><?php echo $description; ?></textarea>
                                    </div>
                                </div>


                                <div class="mb-2 row">
                                    <label class="col-lg-3 col-form-label">Tags: </label>
                                    <div class="col-lg-9">

                                        <select name="tags[]" id="tags[]" class="form-control select" multiple="multiple" data-tags="true">
                                            <?php
                                            // -------------------------------------------------------------------------------------------------
                                            $result_tags = $mysqli->query("SELECT * FROM `" . DB::TAXONOMIES  . "` WHERE is_active=1 AND type='lead_tag' ORDER BY value");
                                            while ($rows_tags = $result_tags->fetch_array()) {
                                                // -------------------------------------------------------------------------------------------------
                                            ?>

                                                <option value="<?php echo $rows_tags['id']; ?>" <?php if ($action == "edit_$module" && in_array($rows_tags['id'], $tags_arr)) { ?>selected <?php } else if (in_array($rows_tags['id'], $posted_tags_arr)) { ?>selected <?php } ?>>
                                                    <?php echo $rows_tags['value']; ?>
                                                </option>

                                            <?php
                                            }  // while
                                            ?>
                                        </select>

                                    </div>
                                </div>


                                <div class="row">

                                    <div class="col-lg-4">
                                        <div class="mt-2">

                                            <label class="form-label">Status: </label>
                                            <select class="form-select" name="lead_status" id="lead_status">
                                                <option value='0'></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result_statuses = $mysqli->query("SELECT * FROM `" . DB::TAXONOMIES  . "` WHERE is_active=1 AND type='lead_status' ORDER BY value");
                                                while ($rows_statuses = $result_statuses->fetch_array()) {
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows_statuses['id']; ?>" <?php if ($action == "edit_$module" && $rows_statuses['id'] == $lead_status) { ?>selected <?php } else if ($rows_statuses['id'] == $lead_status) { ?>selected <?php } ?>>
                                                        <?php echo $rows_statuses['value']; ?>
                                                    </option>

                                                <?php
                                                }  // while
                                                ?>
                                            </select>

                                        </div>
                                    </div>


                                    <div class="col-lg-4">
                                        <div class="mt-2">

                                            <label class="form-label">Source: </label>
                                            <select class="form-select" name="lead_source" id="lead_source">
                                                <option value='0'></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result_sources = $mysqli->query("SELECT * FROM `" . DB::TAXONOMIES  . "` WHERE is_active=1 AND type='lead_source' ORDER BY value");
                                                while ($rows_sources = $result_sources->fetch_array()) {
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>

                                                    <option value="<?php echo $rows_sources['id']; ?>" <?php if ($action == "edit_$module" && $rows_sources['id'] == $lead_source) { ?>selected <?php } else if ($rows_sources['id'] == $lead_source) { ?>selected <?php } ?>>
                                                        <?php echo $rows_sources['value']; ?>
                                                    </option>

                                                <?php
                                                }  // while
                                                ?>
                                            </select>

                                        </div>
                                    </div>


                                    <div class="col-lg-4">
                                        <div class="mt-2">

                                            <label class="form-label">Assigned To: </label>
                                            <select class="form-select" name="assigned_to" id="assigned_to">
                                                <option value='0'></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result_users = $mysqli->query("SELECT * FROM `" . DB::USERS  . "` WHERE is_active=1 ORDER BY full_name");
                                                while ($rows_users = $result_users->fetch_array()) {
                                                    // $assigned_to        = s__($rows_users['full_name']);
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows_users['id']; ?>" <?php if ($action == "edit_$module" && $rows_users['id'] == $assigned_to) { ?>selected <?php } else if ($rows_users['id'] == $assigned_to) { ?>selected <?php } ?>>
                                                        <?php echo $rows_users['full_name']; ?>
                                                    </option>

                                                <?php
                                                }  // while
                                                ?>
                                            </select>

                                        </div>
                                    </div>


                                </div>

                                <!-- <div class="border-bottom-black border-bottom-lg">&nbsp;</div> -->


                            </div>




                        </div>
                    </div>


                    <div class="col-lg-3">
                        <div class="card">

                            <div class="card-header">
                                <span class="mb-0 fw-semibold">Lead Owner </span>
                            </div>

                            <div class="content clearfix">
                                <div class="row mb-2">
                                    <!-- <label class="col-lg-4 col-form-label">Lead Type:</label> -->
                                    <div class="col-lg-12">
                                        <div class="">
                                            <select class="form-select" name="lead_owner" id="lead_owner">
                                                <option value='0'></option>
                                                <?php
                                                // -------------------------------------------------------------------------------------------------
                                                $result_users = $mysqli->query("SELECT * FROM `" . DB::USERS  . "` WHERE is_active=1 ORDER BY full_name");
                                                // $result_users = $mysqli->query("SELECT * FROM `" . DB::USERS  . "` WHERE is_active=1 ORDER BY full_name");
                                                while ($rows_users = $result_users->fetch_array()) {
                                                    // $assigned_to        = s__($rows_users['full_name']);
                                                    // -------------------------------------------------------------------------------------------------
                                                ?>
                                                    <option value="<?php echo $rows_users['id']; ?>" <?php if ($action == "edit_$module" && $rows_users['id'] == $lead_owner) { ?>selected <?php } else if ($rows_users['id'] == $lead_owner) { ?>selected <?php } ?>>
                                                        <?php echo $rows_users['full_name']; ?>
                                                    </option>

                                                <?php
                                                }  // while
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="card">

                            <div class="card-header">
                                <span class="mb-0 fw-semibold">Address Details</span>
                            </div>


                            <div class="content clearfix">


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Country: </label>
                                    <div class="col-lg-9">
                                        <select required class="form-select select" name="country" id="country" onchange="ajax_populate_states(this.value);">
                                            <option value="0">&nbsp;</option>
                                            <?php
                                            // -------------------------------------------------------------------------------------------------
                                            $result = $mysqli->query("SELECT * FROM `" . $tbl_prefix . "geo_countries` WHERE is_active=1 ORDER BY country");
                                            while ($rows = $result->fetch_array()) {
                                                // -------------------------------------------------------------------------------------------------
                                            ?>
                                                <option value="<?php echo $rows['id']; ?>" <?php if ($action == "edit_$module" && $rows['id'] == $country) { ?>selected <?php } else if ($rows['id'] == $country) { ?>selected <?php } ?>>
                                                    <?php echo $rows['country']; ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Street1:</label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="street1" id="street1" value="<?php echo $street1; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Street2:</label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="street2" id="street2" value="<?php echo $street2; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">City:</label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="city" id="city" value="<?php echo $city; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">State: </label>
                                    <div class="col-lg-9">

                                        <select class="form-select" name="state" id="state">
                                            <option value='0'></option>
                                            <?php
                                            // -------------------------------------------------------------------------------------------------
                                            if (!empty($country)) {
                                                $result_states = $mysqli->query("SELECT * FROM `" . DB::GEO_STATES  . "` WHERE is_active=1 AND country_id=$country");
                                            } else {
                                                $result_states = $mysqli->query("SELECT * FROM `" . DB::GEO_STATES  . "` WHERE id=0");
                                            }

                                            while ($rows_states = $result_states->fetch_array()) {
                                                $state_name        = s__($rows_states['state']);
                                                // -------------------------------------------------------------------------------------------------
                                            ?>
                                                <option value="<?php echo $rows_states['id']; ?>" <?php if ($action == "edit_$module" && $rows_states['id'] == $state) { ?>selected <?php } else if ($rows_states['id'] == $state) { ?>selected <?php } ?>>
                                                    <?php echo $state_name; ?>
                                                </option>

                                            <?php
                                            }  // while
                                            ?>
                                        </select>

                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">P.O Box:</label>
                                    <div class="col-lg-9">
                                        <input type="text" class="form-control" name="pobox" id="pobox" value="<?php echo $pobox; ?>" placeholder="P.O. Box / Zip Code">
                                    </div>
                                </div>


                            </div>

                        </div>
                    </div>



                    <div class="col-lg-3">

                        <div class="card">

                            <div class="card-header">
                                <span class="mb-0 fw-semibold">Service</span>
                            </div>

                            <div class="content clearfix">

                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Service: </label>
                                    <div class="col-lg-8">

                                        <select class="form-select" name="service" id="service">
                                            <option value='0'></option>
                                            <?php
                                            // -------------------------------------------------------------------------------------------------
                                            $result_items = $mysqli->query("SELECT * FROM `" . DB::ITEMS  . "` WHERE is_active=1 AND item_type = 'services' ORDER BY item_name");
                                            while ($rows_items = $result_items->fetch_array()) {
                                                $item_name        = s__($rows_items['item_name']);
                                                // -------------------------------------------------------------------------------------------------
                                            ?>
                                                <option value="<?php echo $rows_items['id']; ?>" <?php if ($action == "edit_$module" && $rows_items['id'] == $service) { ?>selected <?php } else if ($rows_items['id'] == $service) { ?>selected <?php } ?>>
                                                    <?php echo $item_name; ?>
                                                </option>

                                            <?php
                                            }  // while
                                            ?>
                                        </select>

                                    </div>
                                </div>

                            </div>


                        </div>

                        <div class="card">

                            <div class="card-header">
                                <span class="mb-0 fw-semibold">Other Details</span>
                            </div>


                            <div class="content clearfix">


                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Website:</label>
                                    <div class="col-lg-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-globe"></i></span>
                                            <input type="text" class="form-control" name="website" id="website" value="<?php echo $website; ?>" placeholder="https://www.example.com">
                                        </div>
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Department:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="department" id="department" value="<?php echo $department; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Designation:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="designation" id="designation" value="<?php echo $designation; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">X(Twitter):</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="x" id="x" value="<?php echo $x; ?>">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Facebook:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="facebook" id="facebook" value="<?php echo $facebook; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Instagram:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="instagram" id="instagram" value="<?php echo $instagram; ?>">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">TRN #: </label>
                                    <div class="col-lg-8">
                                        <input type="text" name="trn" id="trn" value="<?php echo $trn; ?>" class="form-control">
                                    </div>
                                </div>



                            </div>

                        </div>
                    </div>




                    <?php if (false) { ?>
                    <!-- <div class="col-lg-2">

                        <div class="card card-body">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="row mb-2">
                                        <label class="form-label fw-semibold">Photo:</label>
                                        <input type="file" name="photo" id="photo" class="form-control">
                                    </div>
                                    <div class="form-text text-muted">Size <?php echo $image_width; ?>px x <?php echo $image_height; ?>px -> <?php echo $allowed_file_formats; ?>. Max file size <?php echo $allowed_file_size; ?> Mb</div>

                                    <?php if (!empty($photo) && file_exists('../uploads/leads/thumbs/' . $photo)) { ?>
                                        <div class="form-group">
                                            <a data-lightbox="driver" href="<?php echo $photo_upload_path .  $photo ?>" target="_blank">
                                                <img src="<?php echo $photo_upload_path . '/thumbs/' . $photo ?>" alt="" width="<?php echo $dispaly_thumb_width; ?>" height="<?php echo $display_thumb_height; ?>" />
                                            </a><br /><br />
                                            <a href="<?php echo $module; ?>.php?action=<?php echo $action; ?>&id=<?php echo $id; ?>&delete_photo=1">
                                                <button type="button" class="btn btn-danger btn-sm" name="delete_photo" id="delete_photo">Delete</button>
                                            </a>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                    </div> -->
                    <?php } ?>

                </div>
            </div>


            </form>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>


<!-- 
    // ---------------------------------------------------------
    // ENABLE VIEW ONLY MODE FOR FORM ELEMENTS
    // ---------------------------------------------------------
-->
<?php if (isset($module_id) && granted('view', $module_id) && !granted('create', $module_id) && !granted('edit', $module_id)) { ?>
    <script>
        $(function() {
            toggleFormElements('true');
        });
    </script>
<?php } ?>

<?php include('admin_elements/admin_footer.php'); ?>