<?php


use App\Core\DB;
include('admin_elements/admin_header.php');

$module                 = 'customer_logs';
$module_caption         = 'Customer Activity';
$tbl_name               = DB::ENTITY_LOGS; // erp_customer_logs merged into erp_entity_logs

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

if (!granted_('view', 'customers') && !granted_('create', 'customers') && !granted_('edit', 'customers') && !granted_('delete', 'customers')) {
    $error_message = 'You do not have permission to view this page.';
}

/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

if (isset($_POST['is_active']))                                 $is_active = 1;
else $is_active = 0;

$customer_id = '';
if (isset($_REQUEST['customer_id']))        $customer_id     = e_s__($_REQUEST['customer_id']);
if (isset($_POST['customer_id']))           $customer_id     = e_s__($_POST['customer_id']);


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



<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_customer.php'); ?>
    <!-- /sidebar content -->

</aside>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_customer.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <div class="col-lg-6 col-xl-12">

                    <div class="card">
                        <div class="card-header d-flex">
                            <h5 class="mb-0">
                                <i class="ph-folder me-2"></i>
                                Customer Activity
                            </h5>

                            <div class="ms-auto">
                                <span class="text-muted">
                                    <?php
                                    $result = $mysqli->query("SELECT id FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='customer' AND entity_id=$customer_id");
                                    echo '(' . $result->num_rows . ')';
                                    ?>
                                </span>
                            </div>
                        </div>


                        <div class="list-feed p-3">

                            <?php
                            $result = $mysqli->query("SELECT * FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='customer' AND entity_id=$customer_id ORDER BY id ASC");
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

                            <?php } ?>

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