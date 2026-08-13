<?php


use App\Security\Roles;
include('admin_elements/admin_header.php');

$module             = 'users';
$module_caption     = 'Employee';

$tbl_name = $tbl_prefix . $module;
$photo_upload_path        = '../uploads/' . $module . '/';


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


// ------------------ CHECK IF ID IS VALID ----------------
$userService = \App\Core\Container::getInstance()->get(\App\Service\UserService::class);
try {
    $user = $userService->getById((int)$id);
} catch (\App\Exception\NotFoundException $e) {
    log_error('User not found: ' . $e->getMessage(), 'WARNING', __FILE__, __LINE__, ['module' => 'users', 'id' => (int)($id ?? 0)]);
    flash_error('User Information is not accessible');
    header("Location:listing_users.php");
    exit;
}

// -- System Admin
if (!Roles::isSystemAdmin(Session::roleId() ?? null) && $id == 1) {
    flash_error('Only System Admin has the rights to access this User.');
    header("Location:listing_users.php");
    exit;
}



/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$selected_tab = 'personal';

if (isset($_REQUEST['selected_tab']) && !empty($_REQUEST['selected_tab']))
    $selected_tab     = e_s__($_REQUEST['selected_tab']);


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
*/
if (!empty($id)) {
    $role_id                    = $user->roleId;
    $full_name                  = htmlspecialchars($user->fullName);
    $email                      = htmlspecialchars($user->email);
    $contact1                   = htmlspecialchars($user->contact1 ?? '');
    $contact2                   = htmlspecialchars($user->contact2 ?? '');
    $address                    = htmlspecialchars($user->address ?? '');

    $dob                        = $user->dob ? processDateYtoD($user->dob) : '';

    $can_access_system          = $user->canAccessSystem ? 1 : 0;
    $is_active                  = $user->isActive ? 1 : 0;
    $photo                      = $user->photo;
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
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content">
            <div class="row mt-2">
                <div class="col-lg-12">
                    <h5 class="ms-2">Employee</h5>
                </div>

                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            </div>

        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">


                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-body text-center">

                            <!-- Light Box -->
                            <?php if (!empty($photo) && file_exists('../uploads/users/thumbs/' . $photo)) { ?>
                                <div class="card-img-actions d-inline-block mb-3">
                                    <a data-lightbox="photo" href="<?php echo $photo_upload_path .  $photo ?>" target="_blank">
                                        <img class="img-fluid rounded-circle" src="<?php echo $photo_upload_path . '/thumbs/' . $photo; ?>" alt="User photo" width="170" height="170" />
                                    </a>
                                    <!-- <img class="img-fluid rounded-circle" src="assets/images/demo/users/face2.jpg" width="170" height="170" alt=""> -->
                                </div>
                            <?php } else { ?>
                                <img class="img-fluid rounded-circle" src="../assets/custom_images/no-image.png" alt="" />

                            <?php } ?>

                            <h6 class="mb-0"><?php echo $full_name; ?></h6>
                            <small class="mb-0"><?php echo (($can_access_system == 1) ? '<span class="badge bg-info">Access System</span>' : '<span class="badge bg-danger">Disabled System Access</span>'); ?></small><br /><br />

                            <span class="text-muted"><?php echo (($is_active == 1) ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-warning">Not Active</span>'); ?></span>
                        </div>
                        <div class="card-footer d-flex justify-content-around text-center p-0">

                            <?php if (granted_('edit', 'users') && $role_id > 2) { ?>
                                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $id; ?>" class="text-body flex-fill p-2" data-bs-popup="tooltip" aria-label="Edit" data-bs-original-title="Edit">
                                    <i class="ph-pencil"></i>
                                </a>
                            <?php } ?>

                            <!-- <a href="#" class="text-body flex-fill p-2" data-bs-popup="tooltip" aria-label="Delete" data-bs-original-title="Delete">
                                <i class="ph-trash"></i>
                            </a> -->
                        </div>
                    </div>
                </div>


                <div class="col-lg-9">

                    <!-- <ul class="nav nav-tabs nav-tabs-solid nav-tabs-solid-dark bg-dark nav-justified mb-3">
                        <li class="nav-item">
                            <a href="user.php?id=<?php echo $id; ?>" class="nav-link <?php echo (($selected_tab == 'personal') ? 'active' : ''); ?>">Personal Information</a>
                        </li>
                        <li class="nav-item">
                            <a href="user.php?id=<?php echo $id; ?>&selected_tab=booking" class="nav-link <?php echo (($selected_tab == 'booking') ? 'active' : ''); ?>">Payroll</a>
                        </li>
                        <li class="nav-item">
                            <a href="user.php?id=<?php echo $id; ?>&selected_tab=vehicle" class="nav-link <?php echo (($selected_tab == 'vehicle') ? 'active' : ''); ?>">Leave History</a>
                        </li>
                        <li class="nav-item">
                            <a href="user.php?id=<?php echo $id; ?>&selected_tab=cash" class="nav-link <?php echo (($selected_tab == 'cash') ? 'active' : ''); ?>">Documents</a>
                        </li>
                    </ul> -->

                    <div class="card">

                        <div class="tab-content card-body">

                            <!-- -------------------------------------------------------------------------------------------------------------------- -->
                            <!-- --------------------------------- TAB: PERSONAL INFORMATION -------------------------------------------------------- -->
                            <!-- -------------------------------------------------------------------------------------------------------------------- -->
                            <div class="tab-pane fade <?php echo (($selected_tab == 'personal') ? 'active show' : ''); ?>" id="tab-personal" role="tabpanel">


                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Full Name:</label>
                                    <div class="col-lg-9 fw-semibold">
                                        <?php echo $full_name; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Date of Birth:</label>
                                    <div class="col-lg-9 fw-semibold">
                                        <?php echo (($dob == '01-01-1970') ? '' : $dob); ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Email:</label>
                                    <div class="col-lg-9 fw-semibold">
                                        <?php echo $email; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Phone:</label>
                                    <div class="col-lg-9 fw-semibold">
                                        <?php echo $contact1; ?>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Address:</label>
                                    <div class="col-lg-9 fw-semibold">
                                        <?php echo $address; ?>
                                    </div>
                                </div>

                            </div>




                            <!-- -------------------------------------------------------------------------------------------------------------------- -->
                            <!-- ------------------------------------------- TAB: BOOKING HISTORY --------------------------------------------------- -->
                            <!-- -------------------------------------------------------------------------------------------------------------------- -->

                            <div class="tab-pane fade <?php echo (($selected_tab == 'booking') ? 'active show' : ''); ?>" id="booking-history" role="tabpanel">


                                <div class="table-responsive">
                                    <table class="table">

                                        <thead>
                                            <tr>
                                                <th>ID #</th>
                                                <th width="250">REQUESTED DATE TIME</th>
                                                <th class="text-center">FULL NAME</th>
                                                <th>VEHICLE</th>
                                                <th>BOOKING INFORMATION</th>
                                                <!-- <th>CASH</th> -->
                                                <!-- <th>BOOKING STATUS</th> -->
                                                <th></th>
                                            </tr>
                                        </thead>


                                    </table>
                                </div>


                            </div>





                        </div>
                    </div>
                </div>

            </div>

        </div>


        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>