<?php

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Sales Summary Report';
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

$sales_data = [];
$result = $mysqli->query("SELECT DATE_FORMAT(i.invoice_date, '%Y-%m') AS period,
                                  COUNT(DISTINCT i.id) AS orders,
                                  COUNT(DISTINCT i.customer_id) AS customers,
                                  COALESCE(SUM(i.grand_total), 0) AS revenue,
                                  CASE WHEN COUNT(DISTINCT i.id) > 0 THEN COALESCE(SUM(i.grand_total), 0) / COUNT(DISTINCT i.id) ELSE 0 END AS avg_order_value
                           FROM `" . $tbl_prefix . "invoices` i
                           WHERE i.customer_id > 0
                           GROUP BY DATE_FORMAT(i.invoice_date, '%Y-%m')
                           ORDER BY period DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = $row;
    }
}

$total_revenue = 0;
$total_orders = 0;
$total_customers = 0;
foreach ($sales_data as $row) {
    $total_revenue += (float)$row['revenue'];
    $total_orders += (int)$row['orders'];
    $total_customers += (int)$row['customers'];
}
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;
?>

<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <h1><?php echo e($module_caption); ?></h1>
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>Sales Overview</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Total Revenue</h6>
                                <p class="h4"><?php echo number_format($total_revenue, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Total Orders</h6>
                                <p class="h4"><?php echo $total_orders; ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Average Order Value</h6>
                                <p class="h4"><?php echo number_format($avg_order_value, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h6>Total Customers</h6>
                                <p class="h4"><?php echo $total_customers; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
<table id="grid-<?php echo e($module); ?>" class="custom_datatables">
        <thead>
            <tr>
                <th>Period</th>
                <th>Revenue</th>
                <th>Orders</th>
                <th>Customers</th>
                <th>Average Order Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sales_data as $row): ?>
            <tr>
                <td><?php echo e($row['period']); ?></td>
                <td><?php echo number_format((float)$row['revenue'], 2); ?></td>
                <td><?php echo (int)$row['orders']; ?></td>
                <td><?php echo (int)$row['customers']; ?></td>
                <td><?php echo number_format((float)$row['avg_order_value'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
    <?php include('admin_elements/copyright.php'); ?>
</div>
<?php include('admin_elements/admin_footer.php'); ?>

<script>
$(document).ready(function() {
    $('#grid-<?php echo e($module); ?>').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
        language: {
            search: '',
            searchPlaceholder: 'Search periods...',
            lengthMenu: '_MENU_'
        }
    });
});
</script>
