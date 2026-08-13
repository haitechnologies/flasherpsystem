<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'recurring_invoices';
$module_caption = 'Recurring Invoice';
$tbl_name = DB::INVOICES;
$error_message = '';
$success_message = '';
$page = $_GET['page'] ?? '';

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action == "delete_$module" && !empty($id)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid CSRF token.');
        header("Location:listing_$module.php?page=$page");
        exit;
    }

    $userId = Session::userId();

    if (is_SystemAdmin() || is_SuperAdmin() || getTableAttrV('created_by', $tbl_name, " id=$id ") == $userId) {
        $stmt = $mysqli->prepare("DELETE FROM `" . DB::INVOICE_ITEMS . "` WHERE invoice_id=? AND organization_id=?");
        $stmt->bind_param('ii', $id, $activeOrganizationId);
        $stmt->execute();
        $stmt->close();

        $stmt = $mysqli->prepare("DELETE FROM `$tbl_name` WHERE id=? AND organization_id=? AND recurring=1");
        $stmt->bind_param('ii', $id, $activeOrganizationId);
        $stmt->execute();
        $stmt->close();

        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted. Only the owner or a Super Administrator can delete this record.";
    }
    exit;
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'add_button_href' => "recurring_invoices.php",
    'thead' => '
        <th width="100">DATE</th>
        <th width="150">INVOICE #</th>
        <th>CUSTOMER NAME</th>
        <th>PROFILE NAME</th>
        <th width="100">FREQUENCY</th>
        <th width="110">LAST INVOICE</th>
        <th width="110">NEXT INVOICE</th>
        <th width="120" class="text-end">AMOUNT</th>
        <th width="90" class="col-center">STATUS</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7, 'className' => 'text-end'],
        ['data' => 8],
        ['data' => 9, 'visible' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search recurring invoices...',
    'extra_js' => "
        var table = $('#grid-{$module}').DataTable();
        table.on('draw', function() {
            table.column(1, { page: 'current' }).nodes().each(function(cell, i) {
                var row = table.row(cell).data();
                if (row && row[9]) {
                    var link = '<a href=\"recurring_invoice_overview.php?recurring_invoice_id=' + row[9] + '\" class=\"text-primary\">' + row[1] + '</a>';
                    $(cell).html(link);
                }
            });
        });
    ",
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
