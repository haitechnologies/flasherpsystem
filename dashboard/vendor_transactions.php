<?php

use App\Core\DB;
use App\Core\Session;
use App\Core\DeletionManager;
use App\Core\Container;
use App\Repository\VendorRepository;
include('admin_elements/admin_header.php');

$module = 'vendors';
$module_caption = 'Vendor Transactions';
$tbl_name = (defined('DB::VENDOR_TRANSACTIONS') ? constant('DB::VENDOR_TRANSACTIONS') : 'erp_vendor_transactions');
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : null;
$vendorData = null;
$payables = 0.0;

if ($vendorId) {
    try {
        $container = Container::getInstance();
        $vendorRepo = $container->get(VendorRepository::class);
        $vendorData = $vendorRepo->find($vendorId, $activeOrganizationId);
        
        if ($vendorData) {
            $payables = $vendorRepo->getPayables($vendorId, $activeOrganizationId);
            $module_caption = 'Transactions: ' . htmlspecialchars($vendorData->displayName);
        } else {
            $error_message = 'Vendor not found.';
            $vendorId = null;
        }
    } catch (\Exception $e) {
        $error_message = 'Error loading vendor details.';
        log_error('Error fetching vendor for transactions: ' . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
    }
}

/*
|--------------------------------------------------------------------------
| DELETE HANDLER
|--------------------------------------------------------------------------
*/
if ($action == "delete_$module" && !empty($id)) {
    $result = DeletionManager::delete(
        $tbl_name,
        $id,
        Session::userId(),
        [
            'verify_field' => 'transaction_id',
            'item_label' => 'Transaction',
            'module_slug' => $module
        ]
    );
    
    if ($result['success']) {
        $success_message = $result['message'];
        header("Location: " . $module . ".php?msg=deleted");
        exit;
    } else {
        $error_message = $result['message'];
    }
}

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed', 'WARNING', __FILE__, __LINE__);
    }
}

include('admin_elements/breadcrumb.php');

$vendor_id = $vendorId ? (int)$vendorId : 0;
?>

<?php if ($vendorId): ?>
<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_vendor.php'); ?>
    <!-- /sidebar content -->

</aside>
<?php endif; ?>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <?php if ($vendorId): ?>
            <!-- Page header -->
            <?php include('admin_elements/page_header_vendor.php'); ?>
            <!-- /page header -->
        <?php else: ?>
            <div class="page-header page-header-light shadow carriers-page-header">
                <h1><?php echo e($module_caption); ?></h1>
            </div>
        <?php endif; ?>

        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger mx-3 mt-3"><?php echo e($error_message); ?></div>
            <?php endif; ?>

            <?php if ($vendorData): ?>
            <div class="row">
                <!-- Vendor Details -->
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm h-100 border-0 rounded-lg">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center mr-3" style="width: 48px; height: 48px; font-size: 1.25rem;">
                                    <?php echo strtoupper(substr($vendorData->displayName, 0, 1)); ?>
                                </div>
                                <div>
                                    <h5 class="card-title text-primary mb-0 font-weight-bold"><?php echo e($vendorData->displayName); ?></h5>
                                    <?php if ($vendorData->companyName && $vendorData->companyName !== $vendorData->displayName): ?>
                                        <h6 class="card-subtitle mt-1 text-muted"><?php echo e($vendorData->companyName); ?></h6>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="card-text mt-3 text-muted" style="line-height: 1.8;">
                                <?php if ($vendorData->email): ?>
                                    <i class="icon-envelop3 mr-2 text-secondary"></i> <?php echo e($vendorData->email); ?><br>
                                <?php endif; ?>
                                <?php if ($vendorData->phone): ?>
                                    <i class="icon-phone2 mr-2 text-secondary"></i> <?php echo e($vendorData->phone); ?><br>
                                <?php endif; ?>
                                <?php if ($vendorData->address): ?>
                                    <i class="icon-location4 mr-2 text-secondary"></i> <?php echo nl2br(e($vendorData->address)); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Financial Highlights -->
                <div class="col-md-6 mb-3">
                    <div class="card shadow-sm h-100 border-0 rounded-lg" style="border-left: 4px solid #0056b3 !important;">
                        <div class="card-body d-flex flex-column justify-content-center bg-light">
                            <h6 class="text-uppercase text-muted font-weight-bold mb-4 text-center">Financial Summary</h6>
                            <div class="row text-center">
                                <div class="col-6 border-right">
                                    <div class="text-muted mb-2 font-weight-semibold">Outstanding Payables</div>
                                    <h2 class="text-danger mb-0 font-weight-bold"><small>AED</small> <?php echo e(number_format($payables, 2)); ?></h2>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted mb-2 font-weight-semibold">Credit Limit</div>
                                    <h3 class="text-success mb-0 font-weight-bold"><?php echo $vendorData->creditLimit > 0 ? e(number_format($vendorData->creditLimit, 2)) : '<span class="text-muted" style="font-size: 1rem;">N/A</span>'; ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 rounded-lg">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="vendor-transactions-grid" class="custom_datatables table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Vendor</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>
<?php include('admin_elements/admin_footer.php'); ?>

<script>
$(document).ready(function() {
    var vendorId = <?php echo $vendorId ? (int)$vendorId : 'null'; ?>;
    
    $('#vendor-transactions-grid').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'datatables.php',
            type: 'POST',
            data: function (d) {
                d.ajax_action = 'listing_vendor_transactions';
                d.csrf_token = $('input[name="csrf_token"]').val();
                if (vendorId) {
                    d.vendor_id = vendorId;
                }
            }
        },
        columns: [
            {data: 'id'},
            {data: 'vendor'},
            {data: 'amount'},
            {data: 'date'},
            {data: 'status'}
        ]
    });
});
</script>
