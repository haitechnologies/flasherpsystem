<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'vendors';
$module_caption = 'Vendor';
$tbl_name = DB::VENDORS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        log_error('Invalid CSRF token on vendor delete', 'SECURITY', __FILE__, __LINE__, backend_runtime_log_context([
            'module' => 'vendors',
            'module_slug' => 'vendors',
        ]));
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $vendorId = (int)$id;
    $uid = (int)Session::userId();
    $vendorExists = $mysqli->query("SELECT id FROM `" . DB::VENDORS . "` WHERE id=$vendorId AND organization_id=$activeOrganizationId");
    if (!$vendorExists || $vendorExists->num_rows === 0) {
        $error_message = 'Invalid Record in the database.';
    } elseif (is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendorId AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `" . DB::VENDOR_CONTACTS . "` WHERE contactable_type='Vendor' AND contactable_id=$vendorId AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `" . DB::ENTITY_NOTES . "` WHERE entity_type='vendor' AND entity_id=$vendorId AND organization_id=$activeOrganizationId");

        $result = $mysqli->query("SELECT * FROM `" . DB::VENDOR_ATTACHMENTS . "` WHERE attachable_type = 'Vendor' AND attachable_id=$vendorId AND organization_id=$activeOrganizationId");
        while ($rows = $result->fetch_array()) {
            @unlink('../uploads/vendor_attachments/' . $rows['filename']);
            $mysqli->query("DELETE FROM `" . DB::VENDOR_ATTACHMENTS . "` WHERE id=" . $rows['id'] . " AND organization_id=$activeOrganizationId");
        }

        $mysqli->query("DELETE FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='vendor' AND entity_id=$vendorId AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `" . DB::VENDORS . "` WHERE id=$vendorId AND organization_id=$activeOrganizationId");

        if ($mysqli->affected_rows > 0) {
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
        } else {
            $error_message = "Action denied. You are not authorized to delete this record.";
        }
    } else {
        $mysqli->query("DELETE FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");
        $mysqli->query("DELETE FROM `" . DB::VENDOR_CONTACTS . "` WHERE contactable_type='Vendor' AND contactable_id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");
        $mysqli->query("DELETE FROM `" . DB::ENTITY_NOTES . "` WHERE entity_type='vendor' AND entity_id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");

        $result = $mysqli->query("SELECT * FROM `" . DB::VENDOR_ATTACHMENTS . "` WHERE attachable_type = 'Vendor' AND attachable_id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");
        while ($rows = $result->fetch_array()) {
            @unlink('../uploads/vendor_attachments/' . $rows['filename']);
            $mysqli->query("DELETE FROM `" . DB::VENDOR_ATTACHMENTS . "` WHERE id=" . $rows['id'] . " AND organization_id=$activeOrganizationId AND created_by=$uid");
        }

        $mysqli->query("DELETE FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='vendor' AND entity_id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");
        $mysqli->query("DELETE FROM `" . DB::VENDORS . "` WHERE id=$vendorId AND organization_id=$activeOrganizationId AND created_by=$uid");

        if ($mysqli->affected_rows > 0) {
            $success_message = "Item deleted successfully.";
            flash_success($success_message);
            header("Location:listing_$module.php");
        } else {
            $error_message = "Action denied. You are not authorized to delete this record.";
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th>NAME</th>
        <th>COMPANY</th>
        <th>EMAIL</th>
        <th>WORK PHONE</th>
        <th>ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
    ],
    'order' => [[0, 'asc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
