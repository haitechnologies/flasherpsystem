<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'purchase_orders';
$module_caption = 'Purchase Order';
$tbl_name = DB::PURCHASE_ORDERS;
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        log_error('Invalid CSRF token on purchase order delete', 'SECURITY', __FILE__, __LINE__, backend_runtime_log_context([
            'module' => 'purchase_orders',
            'module_slug' => 'purchase_orders',
        ]));
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $purchaseOrderId = (int)$id;
    $uid = (int)Session::userId();
    if (is_SystemAdmin() || is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id=$purchaseOrderId");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$purchaseOrderId AND organization_id=$activeOrganizationId");
    } else {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id=$purchaseOrderId AND created_by='$uid'");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$purchaseOrderId AND organization_id=$activeOrganizationId AND created_by='$uid'");
    }

    if ($mysqli->affected_rows > 0) {
        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted. Only Super Administrator can delete this record.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="100">DATE</th>
        <th>PURCHASE ORDER #</th>
        <th>REFERENCE #</th>
        <th>VENDOR NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'col-center'],
        ['data' => 5, 'className' => 'text-end'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search purchase orders...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
