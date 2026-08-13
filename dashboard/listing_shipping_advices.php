<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'shipping_advices';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Advice';
$tbl_name = DB::SHIPPING_ADVICES;
$error_message = '';
$success_message = '';

require_once '../vendor/autoload.php';
require_once 'helpers/shipping_customer_helper.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE advice_id=$id");
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE advice_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id");
    } else {
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE advice_id=$id AND created_by ='" . Session::userId() . "'");
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE advice_id=$id AND created_by ='" . Session::userId() . "'");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by ='" . Session::userId() . "'");
    }

    if ($mysqli->affected_rows > 0) {
        $success_message = "Item deleted successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php");
    } else {
        $error_message = "Action denied. You are not authorized to delete this record.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="100">SR.</th>
        <th>INVOICE #</th>
        <th>DATE</th>
        <th>CUSTOMER</th>
        <th>AWB</th>
        <th>LICENSE NO</th>
        <th>MIRSAL II CODE</th>
        <th></th>
        <th width="130">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'id', 'title' => 'SR.'],
        ['data' => 1, 'name' => 'invoice_no', 'title' => 'INVOICE #'],
        ['data' => 2, 'name' => 'invoice_date', 'title' => 'DATE'],
        ['data' => 3, 'name' => 'customer_name', 'title' => 'CUSTOMER'],
        ['data' => 4, 'name' => 'awb_no', 'title' => 'AWB'],
        ['data' => 5, 'name' => 'license_no', 'title' => 'LICENSE NO'],
        ['data' => 6, 'name' => 'mirsal_II_code', 'title' => 'MIRSAL II CODE'],
        ['data' => 7, 'name' => 'is_active', 'title' => '', 'visible' => false],
        ['data' => 8, 'title' => 'ACTION', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search shipping advices...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
