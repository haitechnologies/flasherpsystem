<?php
include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Sales by Customer Report';
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

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Filter Report</h5>
                </div>
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-4">
                                <label>Date From</label>
                                <input type="date" name="date_from" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">Generate Report</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
<table id="grid-<?php echo e($module); ?>" class="custom_datatables">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Total Sales</th>
                <th>Number of Orders</th>
                <th>Average Order Value</th>
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
            data: function(d) {
                d.ajax_action = 'listing_<?php echo e($module); ?>';
                d.csrf_token = $('input[name="csrf_token"]').val();
                d.date_from = '<?php echo isset($_GET["date_from"]) ? e($_GET["date_from"]) : ""; ?>';
                d.date_to = '<?php echo isset($_GET["date_to"]) ? e($_GET["date_to"]) : ""; ?>';
            }
        },
        columns: [
            {data: 'customer'},
            {data: 'total_sales'},
            {data: 'order_count'},
            {data: 'avg_order_value'}
        ]
    });
});
</script>
