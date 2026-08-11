<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'quotations';
$module_caption = 'Quotation';
$tbl_name = DB::QUOTATIONS;
$module_id = getModuleIdBySlug($module, $mysqli);
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$lead_id = (int)($_REQUEST['lead_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action == "delete_$module" && !empty($id) && granted('delete', $module_id)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
    } else {
        if (Session::roleId() == '1') {
            $mysqli->query("DELETE FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id=$id");
            $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$id AND organization_id=$activeOrganizationId");
            $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND organization_id=$activeOrganizationId");
        } else {
            $mysqli->query("DELETE FROM `" . DB::DIMENSION_ITEMS . "` WHERE module_type='quotations' AND record_id=$id");
            $mysqli->query("DELETE FROM `" . DB::QUOTATION_ITEMS . "` WHERE quotation_id=$id AND organization_id=$activeOrganizationId");
            $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by='" . Session::userId() . "' AND organization_id=$activeOrganizationId");
        }

        if ($mysqli->affected_rows > 0) {
            flash_success("$module_caption Deleted Successfully.");
            header("Location:listing_$module.php?page=$page");
            exit;
        } else {
            $error_message = "Sorry! $module Could Not Be Deleted. You may not have permission to delete this record.";
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th>DATE</th>
        <th>QUOTATION #</th>
        <th>JOB REFERENCE #</th>
        <th>CUSTOMER NAME</th>
        <th class="col-center">STATUS</th>
        <th class="text-end">AMOUNT</th>
        <th>ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'quotation_date', 'title' => 'DATE'],
        ['data' => 1, 'name' => 'quotation_no', 'title' => 'QUOTATION #'],
        ['data' => 2, 'name' => 'job_reference_no', 'title' => 'JOB REFERENCE #'],
        ['data' => 3, 'name' => 'customer_id', 'title' => 'CUSTOMER NAME'],
        ['data' => 4, 'name' => 'quotation_status', 'title' => 'STATUS', 'className' => 'col-center'],
        ['data' => 5, 'name' => 'grand_total', 'title' => 'AMOUNT', 'className' => 'text-end'],
        ['data' => 6, 'title' => 'ACTIONS', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'table_classes' => 'custom_datatables datatable-professional display responsive no-wrap table-hover',
    'search_placeholder' => 'Search quotations...',
    'dt_options' => [
        'ajax' => [
            'data' => ['lead_id' => $lead_id],
        ],
    ],
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
