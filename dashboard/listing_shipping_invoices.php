<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'shipping_invoices';
$module_caption = 'Shipping Invoice';
$tbl_name = DB::SHIPPING_INVOICES;
$error_message = '';
$success_message = '';

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE invoice_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id");
    } else {
        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE invoice_id=$id AND created_by ='" . Session::userId() . "'");
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
        <th width="150">INVOICE #</th>
        <th width="150">DATE</th>
        <th width="150">CUSTOMER</th>
        <th width="150">TOTAL</th>
        <th width="150">PKGS</th>
        <th width="150">WEIGHT</th>
        <th width="150">AWB</th>
        <th width="150">CREATED</th>
        <th width="100">STATUS</th>
        <th width="150"></th>
        <th width="130">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'id', 'title' => 'SR.'],
        ['data' => 1, 'name' => 'invoice_no', 'title' => 'INVOICE #'],
        ['data' => 2, 'name' => 'invoice_date', 'title' => 'DATE'],
        ['data' => 3, 'name' => 'customer_name', 'title' => 'CUSTOMER'],
        ['data' => 4, 'name' => 'grand_total', 'title' => 'TOTAL'],
        ['data' => 5, 'name' => 'no_of_packs', 'title' => 'PKGS'],
        ['data' => 6, 'name' => 'gross_weight', 'title' => 'WEIGHT'],
        ['data' => 7, 'name' => 'master_awb_no', 'title' => 'AWB'],
        ['data' => 8, 'name' => 'created_at', 'title' => 'CREATED'],
        ['data' => 9, 'name' => 'is_active', 'title' => 'STATUS'],
        ['data' => 10, 'title' => '', 'orderable' => false, 'searchable' => false],
        ['data' => 11, 'title' => 'ACTION', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
