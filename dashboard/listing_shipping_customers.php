<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'shipping_customers';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Customer';
$tbl_name = DB::CUSTOMERS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND entity_type='shipping'");
    } else {
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND entity_type='shipping' AND created_by ='" . Session::userId() . "'");
    }

    if ($mysqli->affected_rows > 0) {
        $success_message = "Customer deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
    } else {
        $error_message = "Action denied. You are not authorized to delete this record.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => true,
    'thead' => '
        <th width="100">ID</th>
        <th>CUSTOMER NAME</th>
        <th>PHONE</th>
        <th>CITY</th>
        <th>COUNTRY</th>
        <th>TYPE</th>
        <th>STATUS</th>
        <th width="130">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6, 'className' => 'text-center'],
        ['data' => 7, 'className' => 'text-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search customers...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
