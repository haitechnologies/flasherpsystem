<?php

use App\Core\Session;

include('admin_elements/admin_header.php');

$module                 = 'users';
$module_caption         = 'My Profile';
$tbl_name = $tbl_prefix . $module;

$photo_upload_path         = '../uploads/' . $module . '/';
$allowed_file_size         = 5; // MB
$allowed_file_formats     = 'jpg, jpeg, png, gif, webp'; // Allowed formats

// Template Sizes
// x x
// x x

$image_width             = '500';
$image_height             = '500';
$thumb_image_width         = '150';
$thumb_image_height     = '150';

$error_message = '';
$success_message = '';


$id = Session::userId();
/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

if (isset($_POST['is_active']))                     $is_active = 1;
else $is_active = 0;



/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module") {
    // CSRF validation
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in profile.php', 'WARNING', __FILE__, __LINE__);
        $full_name = '';
        $mobile = '';
    } else {
        $full_name            = e_s__($_POST['full_name']);
        $mobile                = e_s__($_POST['mobile']);
    }
} else {
    $full_name            = '';
    $mobile                = '';
}

/*
|--------------------------------------------------------------------------
| 	DELETE PHOTO ONLY
|--------------------------------------------------------------------------
|
*/
if (isset($_REQUEST['delete_photo']) && $_REQUEST['delete_photo'] == 1 && !empty($id)) {
    $photo = getTableAttr('photo', $tbl_name, $id);
    if (!empty($photo)) {
        delete_photo($photo, $photo_upload_path, '1');     // DELETE OLD THUMB
        delete_photo($photo, $photo_upload_path, '0');        // DELETE OLD PHOTO

        $result = $mysqli->query("UPDATE `$tbl_name` SET photo='' WHERE id=$id");
        $success_message = 'Image Deleted Successfully.';
    }
}

/*
|--------------------------------------------------------------------------
| 	UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" && !empty($id)) {


    if (empty($full_name)) {
        $error_message = 'Full Name is mandatory.';
    } else if (checkDuplicateRow($tbl_name, 'full_name', $full_name) && $full_name != getTableAttr('full_name', $tbl_name, $id)) {
        $error_message = 'Duplicate Full name. Please enter different.';
        // } else if (!empty($email)) {
        // 	$error_message = 'Full Name is mandatory.';

        // } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // 	$error_message = 'Please enter valid Email';
        // } else if (checkDuplicateRow($tbl_name, 'email', $email) && $email != getTableAttr('email', $tbl_name, $id)) {
        // 	$error_message = 'Duplicate Email. Please enter different.';
    } else {

        /* ---------------------- UPLOAD PHOTO ---------------------- */
        $old_photo = getTableAttr('photo', $tbl_name, $id);

        if (isset($_FILES["photo"]["name"])) {
            $photo = $_FILES["photo"]["name"];
            if (!empty($photo)) {
                // $renamed 				= full_rename($logo, 'haipulse-logo');
                // $renamed_photo 			= renamePhoto($photo, $complete=0);
                $renamed_photo                 = full_rename($photo, rand(111111111, 999999999));
                // $message 					= uploadPhoto('photo', $renamed_photo, $photo_upload_path, $allowed_file_size, $old_photo, $image_width, $image_height);
                $message                     = uploadNewPhoto('photo', $renamed_photo, $photo_upload_path, $allowed_file_size, $old_photo, $image_width, $image_height, $thumb_image_width, $thumb_image_height);

                if ($message)        $error_message     = $message;
                else                $result         = $mysqli->query("UPDATE `$tbl_name` SET photo='$renamed_photo' WHERE id=$id");
            } //endif
        }


        //////////////////////////////////////////////////
        $update_row = $mysqli->query("
																	UPDATE `$tbl_name` SET
																		full_name		= '" . $full_name . "',
																		mobile			= '" . $mobile . "'
																	WHERE id=$id");
        if ($update_row) {
            $_SESSION[$project_pre]['DASHBOARD']['full_name'] = $full_name;
            $success_message = "The $module_caption has been updated successfully.";
            fp__($tbl_name, $id);
            // header("Location:listing_$module.php?success_message=$success_message");
        } else {
            $error_message = "The $module_caption could not be updated. Please try again.";
            //header("Location:$module.php?action=edit_$module&id=$id&error_message=$error_message");
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

    $email             = s__($row['email']);
    $full_name         = s__($row['full_name']);
    $mobile         = s__($row['mobile']);
    $is_active         = s__($row['is_active']);
}

$photo = getTableAttr('photo', $tbl_name, $id);

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
            <div class="my-1">
                <h5 class="mb-0"><?php if (($action == "edit_$module" || $action == "update_$module" || $action == "change_password") && !empty($id)) { ?>Edit<?php } else { ?>New<?php } ?> <?php echo $module_caption; ?></h5>
            </div>

            <?php if (function_exists('has_full_access') && has_full_access()): ?>
            <div class="my-1 d-inline-flex align-items-center me-2">
                <div class="form-check form-check-inline form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success" name="is_active" id="is_active" <?php if ($is_active == '1') { ?>checked="checked" <?php } ?> form="frmusers">
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <?php endif; ?>
            <div class="my-1">
                <button type="submit" form="frmusers" class="btn btn-primary btn-sm me-2">Save</button>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="profile.php" autocomplete="off" enctype="multipart/form-data" novalidate>
                <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php echo csrf_field(); ?>

                <!-- Page header -->


                <div class="row">
                    <div class="col-lg-8">

                        <div class="card">

                            <div class="card-body">

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Email:</label>
                                            <input type="text" name="email" id="email" class="form-control" value="<?php echo Session::email(); ?>" readonly />
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <p class="mb-12"><label class="form-label fw-semibold">Full Name: <span class="text-danger">*</span></label></p>
                                            <div style="position: relative;">
                                                <input type="text" required class="form-control maxlength-badge-position pe-5" maxlength="50" name="full_name" id="full_name" value="<?php echo $full_name; ?>" aria-required="true">
                                                <div class="invalid-feedback">
                                                    Full name is required
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <p class="mb-12"><label class="form-label fw-semibold">Mobile</label></p>
                                            <div style="position: relative;">
                                                <input type="text" class="form-control maxlength-badge-position pe-5" maxlength="50" name="mobile" id="mobile" value="<?php echo $mobile; ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>



                    <div class="col-lg-4">

                        <div class="card card-body">

                            <div class="row">
                                <div class="col-md-12">
                                    <div class="row mb-3">
                                        <label class="form-label fw-semibold">Photo:</label>
                                        <input type="file" name="photo" id="photo" class="form-control">
                                    </div>
                                    <div class="form-text text-muted">Size <?php echo $image_width; ?>px x <?php echo $image_height; ?>px -> <?php echo $allowed_file_formats; ?>. Max file size <?php echo $allowed_file_size; ?> Mb</div>

                                    <?php if (!empty($photo) && file_exists('../uploads/users/thumbs/' . $photo)) { ?>
                                        <div class="form-group">
                                            <a data-lightbox="driver" href="<?php echo $photo_upload_path .  $photo ?>" target="_blank">
                                                <img src="<?php echo $photo_upload_path . '/thumbs/' . $photo; ?>" alt="" width="<?php echo $thumb_image_width; ?>" height="<?php echo $thumb_image_height; ?>" />
                                            </a><br /><br />

                                            <a href="profile.php?action=<?php echo $action; ?>&id=<?php echo $id; ?>&delete_photo=1">
                                                <button type="button" class="btn btn-danger btn-sm" name="delete_photo" id="delete_photo">Delete Photo</button>
                                            </a>
                                        </div>
                                    <?php } ?>
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
