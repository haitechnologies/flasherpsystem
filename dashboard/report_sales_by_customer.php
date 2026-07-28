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

$date_from = !empty($_GET['date_from']) ? e_s__($_GET['date_from']) : '';
$date_to = !empty($_GET['date_to']) ? e_s__($_GET['date_to']) : '';

$where = '';
if (!empty($date_from)) {
    $where .= " AND i.invoice_date >= '" . $date_from . "'";
}
if (!empty($date_to)) {
    $where .= " AND i.invoice_date <= '" . $date_to . "'";
}

$sales_data = [];
$result = $mysqli->query("SELECT c.display_name AS customer_name,
                                  COUNT(i.id) AS order_count,
                                  COALESCE(SUM(i.grand_total), 0) AS total_sales,
                                  CASE WHEN COUNT(i.id) > 0 THEN COALESCE(SUM(i.grand_total), 0) / COUNT(i.id) ELSE 0 END AS avg_order_value
                           FROM `" . $tbl_prefix . "invoices` i
                           LEFT JOIN `" . $tbl_prefix . "customers` c ON c.id = i.customer_id
                           WHERE i.customer_id > 0" . $where . "
                           GROUP BY i.customer_id, c.display_name
                           ORDER BY total_sales DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sales_data[] = $row;
    }
}
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
                                <input type="date" name="date_from" class="form-control" value="<?php echo e($date_from); ?>">
                            </div>
                            <div class="col-md-4">
                                <label>Date To</label>
                                <input type="date" name="date_to" class="form-control" value="<?php echo e($date_to); ?>">
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
        <tbody>
            <?php foreach ($sales_data as $row): ?>
            <tr>
                <td><?php echo e($row['customer_name']); ?></td>
                <td><?php echo number_format((float)$row['total_sales'], 2); ?></td>
                <td><?php echo (int)$row['order_count']; ?></td>
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
        order: [[1, 'desc']],
        dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
        language: {
            search: '',
            searchPlaceholder: 'Search customers...',
            lengthMenu: '_MENU_'
        }
    });
});
</script>
