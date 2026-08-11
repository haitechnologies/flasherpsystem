<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module                 = 'vendors';
$module_caption         = 'Vendor Activity';
$tbl_name               = DB::ENTITY_LOGS;

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

if (!granted_('view', 'vendors') && !granted_('create', 'vendors') && !granted_('edit', 'vendors') && !granted_('delete', 'vendors')) {
    $error_message = 'You do not have permission to view this page.';
}

/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

if (isset($_POST['is_active']))                                 $is_active = 1;
else $is_active = 0;

$vendor_id = '';
if (isset($_REQUEST['vendor_id']))        $vendor_id     = e_s__($_REQUEST['vendor_id']);
if (isset($_POST['vendor_id']))           $vendor_id     = e_s__($_POST['vendor_id']);


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>



<style>
    .timeline {
        position: relative;
        padding: 0;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #ddd;
        transform: translateX(-50%);
    }

    .timeline-item {
        display: flex;
        align-items: flex-start;
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-date {
        width: 45%;
        text-align: right;
        padding-right: 20px;
        font-weight: 600;
        color: #555;
    }

    .timeline-marker {
        position: relative;
        z-index: 1;
        background: #fff;
        border: 2px solid #0d6efd;
        border-radius: 50%;
        width: 14px;
        height: 14px;
        margin: 0 10px;
        flex-shrink: 0;
        top: 5px;
    }

    .timeline-content {
        width: 45%;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 6px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
</style>



<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content">
            <div class="d-flex">
                <div class="breadcrumb py-2">
                    <a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
                    <a href="index.php" class="breadcrumb-item">Home</a>
                    <a href="listing_vendors.php" class="breadcrumb-item">Vendors</a>
                    <span class="breadcrumb-item active"><?php if (($action == "edit_$module" || $action == "update_$module") && !empty($id)) { ?>Update<?php } else { ?>Activity<?php } ?></span>
                </div>

                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            </div>

            <div class="collapse d-lg-block ms-lg-auto mt-1" id="breadcrumb_elements">
                <div class="d-lg-flex mb-2 mb-lg-0">

                    <button type="button" class="btn btn-outline-dark my-1 me-2 nav-link" data-href="vendor.php?id=<?php echo $vendor_id; ?>">Exit</button>

                </div>
            </div>

        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <?php include('admin_elements/vendor_navbar.php'); ?>

                <div class="col-lg-6">

                    <div class="">

                        <div class="card">
                            <div class="card-header d-flex">
                                <h5 class="mb-0">
                                    <i class="ph-folder me-2"></i>
                                    Vendor Activity
                                </h5>

                                <div class="ms-auto">
                                    <span class="text-muted">
                                        <?php
                                        $result = $mysqli->query("SELECT id FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='vendor' AND entity_id=$vendor_id");
                                        echo '(' . $result->num_rows . ')';
                                        ?>
                                    </span>
                                </div>
                            </div>


                            <div class="list-feed p-3">

                                <?php
                                $result = $mysqli->query("SELECT * FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='vendor' AND entity_id=$vendor_id ORDER BY id ASC");
                                while ($rows = $result->fetch_array()) {
                                    $note_id = $rows['id'];
                                ?>

                                    <div class="timeline-item">
                                        <div class="timeline-date">
                                            <?php echo dd_($rows['created_at'], 'd M Y'); ?><br>
                                            <small class="text-muted"><?php echo dd_($rows['created_at'], 'g:ia'); ?></small>
                                        </div>
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <h6 class="mb-1"><?php echo ucwords($rows['module']); ?> has been <?php echo $rows['action']; ?>.</h6>
                                        </div>
                                    </div>

                                <?php } // while 
                                ?>

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
