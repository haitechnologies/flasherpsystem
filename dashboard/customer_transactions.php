<?php

use App\Core\DB;
use App\Core\Session;
use App\Core\DeletionManager;
include('admin_elements/admin_header.php');

$module = 'customer_transactions';
$module_caption = 'Customer Transactions';
$tbl_name = (defined('DB::CUSTOMER_TRANSACTIONS') ? constant('DB::CUSTOMER_TRANSACTIONS') : 'erp_customer_transactions');
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

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
?>

<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <h1><?php echo e($module_caption); ?></h1>
    </div>

    <div class="table-responsive">
<table id="grid-<?php echo e($module); ?>" class="custom_datatables">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Date</th>
                <th>Status</th>
            </tr>
        </thead>
    </table>
</div>
    <?php include('admin_elements/copyright.php'); ?>
</div>
<?php include('admin_elements/admin_footer.php'); ?>

<script>
$(document).ready(function() {
    $('#grid-<?php echo e($module); ?>').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'datatables.php',
            type: 'POST',
            data: {
                ajax_action: 'listing_<?php echo e($module); ?>',
                csrf_token: $('input[name="csrf_token"]').val()
            }
        },
        columns: [
            {data: 'id'},
            {data: 'customer'},
            {data: 'amount'},
            {data: 'date'},
            {data: 'status'}
        ]
    });
});
</script>
