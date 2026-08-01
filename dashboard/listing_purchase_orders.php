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

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id))) {
    if (Session::roleId() == '1') {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id ");
    } else {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ORDER_ITEMS . "` WHERE purchase_order_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by='" . Session::userId() . "'");
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
        <th width="40">SR.</th>
        <th width="100">DATE</th>
        <th>PURCHASE ORDER #</th>
        <th>REFERENCE #</th>
        <th>VENDOR NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5, 'className' => 'col-center'],
        ['data' => 6, 'className' => 'text-end'],
        ['data' => 7, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[1, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search purchase orders...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
