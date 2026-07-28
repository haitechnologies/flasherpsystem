<?php

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Sales by Item Report';
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

$items_data = [];
$result = $mysqli->query("SELECT ii.service AS item_name,
                                  COALESCE(SUM(ii.qty), 0) AS quantity_sold,
                                  COALESCE(SUM(ii.total), 0) AS revenue,
                                  COALESCE(SUM(ii.total * 0.7), 0) AS cost,
                                  COALESCE(SUM(ii.total), 0) - COALESCE(SUM(ii.total * 0.7), 0) AS profit,
                                  CASE WHEN COALESCE(SUM(ii.total), 0) > 0 THEN ROUND((COALESCE(SUM(ii.total), 0) - COALESCE(SUM(ii.total * 0.7), 0)) / COALESCE(SUM(ii.total), 0) * 100, 2) ELSE 0 END AS profit_margin
                           FROM `" . $tbl_prefix . "invoice_items` ii
                           LEFT JOIN `" . $tbl_prefix . "invoices` i ON i.id = ii.invoice_id
                           WHERE LENGTH(ii.service) > 0
                           GROUP BY ii.service
                           ORDER BY revenue DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items_data[] = $row;
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
                    <h5>Item Sales Analysis</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
<table id="grid-<?php echo e($module); ?>" class="custom_datatables">
        <thead>
            <tr>
                <th>Item Name</th>
                <th>Quantity Sold</th>
                <th>Revenue</th>
                <th>Cost</th>
                <th>Profit</th>
                <th>Profit Margin</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items_data as $row): ?>
            <tr>
                <td><?php echo e($row['item_name']); ?></td>
                <td><?php echo (int)$row['quantity_sold']; ?></td>
                <td><?php echo number_format((float)$row['revenue'], 2); ?></td>
                <td><?php echo number_format((float)$row['cost'], 2); ?></td>
                <td><?php echo number_format((float)$row['profit'], 2); ?></td>
                <td><?php echo number_format((float)$row['profit_margin'], 2); ?>%</td>
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
        order: [[2, 'desc']],
        dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
        language: {
            search: '',
            searchPlaceholder: 'Search items...',
            lengthMenu: '_MENU_'
        }
    });
});
</script>
