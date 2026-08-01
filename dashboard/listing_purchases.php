<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'purchases';
$module_caption = 'Purchase';
$tbl_name = DB::PURCHASES;
$error_message = '';
$success_message = '';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (($action == "delete_$module" && !empty($id))) {
    if (is_SystemAdmin() || is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ITEMS . "` WHERE purchase_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id ");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='purchase' AND reference_id=$id ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='purchase' AND reference_id=$id ");
        }
    } else {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ITEMS . "` WHERE purchase_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by='" . Session::userId() . "'");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='purchase' AND reference_id=$id ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='purchase' AND reference_id=$id AND created_by='" . Session::userId() . "' ");
        }
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
        <th>PURCHASE #</th>
        <th>ORDER NUMBER</th>
        <th>VENDOR NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th>DUE DATE</th>
        <th width="100" class="text-end">AMOUNT</th>
        <th width="100" class="text-end">BALANCE DUE</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'col-center'],
        ['data' => 5],
        ['data' => 6, 'className' => 'text-end'],
        ['data' => 7, 'className' => 'text-end'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search purchases...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
