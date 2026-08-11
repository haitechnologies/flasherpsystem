<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'leads';
$module_caption = 'Lead';
$tbl_name = DB::LEADS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::ENTITY_NOTES . "` WHERE entity_type='lead' AND entity_id=$id");

        $result = $mysqli->query("SELECT * FROM `" . DB::LEAD_ATTACHMENTS . "` WHERE attachable_type = 'Lead' AND attachable_id=$id");
        while ($rows = $result->fetch_array()) {
            @unlink('../uploads/lead_attachments/' . $rows['filename']);
            $mysqli->query("DELETE FROM `" . DB::LEAD_ATTACHMENTS . "` WHERE id=" . $rows['id']);
        }

        $mysqli->query("DELETE FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='lead' AND entity_id=$id");
        $mysqli->query("UPDATE `" . DB::LEADS . "` SET is_active=0 WHERE id=$id");
    } else {
        $mysqli->query("DELETE FROM `" . DB::ENTITY_NOTES . "` WHERE entity_type='lead' AND entity_id=$id AND created_by ='" . Session::userId() . "'");

        $result = $mysqli->query("SELECT * FROM `" . DB::LEAD_ATTACHMENTS . "` WHERE attachable_type = 'Lead' AND attachable_id=$id AND created_by ='" . Session::userId() . "'");
        while ($rows = $result->fetch_array()) {
            @unlink('../uploads/lead_attachments/' . $rows['filename']);
            $mysqli->query("DELETE FROM `" . DB::LEAD_ATTACHMENTS . "` WHERE id=" . $rows['id']);
        }

        $mysqli->query("DELETE FROM `" . DB::ENTITY_LOGS . "` WHERE entity_type='lead' AND entity_id=$id AND created_by ='" . Session::userId() . "'");
        $mysqli->query("UPDATE `" . DB::LEADS . "` SET is_active=0 WHERE id=$id AND created_by ='" . Session::userId() . "'");
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
        <th width="40">SR.</th>
        <th>COMPANY NAME</th>
        <th>ADDRESS</th>
        <th>EMAIL</th>
        <th>PHONE</th>
        <th>TAGS</th>
        <th>ASSIGNED</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100">LAST CONTACT</th>
        <th width="90">CREATED</th>
        <th width="90" class="col-center">ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'orderable' => false, 'searchable' => false],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7, 'className' => 'col-center'],
        ['data' => 8],
        ['data' => 9],
        ['data' => 10, 'orderable' => false, 'searchable' => false, 'className' => 'col-center'],
    ],
    'order' => [[9, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search leads...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
