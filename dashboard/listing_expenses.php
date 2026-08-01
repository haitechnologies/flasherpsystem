<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'expenses';
$module_caption = 'Expense';
$tbl_name = DB::EXPENSES;
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
        $mysqli->query("DELETE FROM `" . tbl_expense_items . "` WHERE expense_id=$id");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='expense' AND reference_id=$id ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='expense' AND reference_id=$id ");
        }
    } else {
        $mysqli->query("DELETE FROM `" . tbl_expense_items . "` WHERE expense_id=$id AND created_by ='" . Session::userId() . "'");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$id AND created_by ='" . Session::userId() . "'");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='expense' AND reference_id=$id ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='expense' AND reference_id=$id AND created_by='" . Session::userId() . "' ");
        }
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
        <th width="100">DATE</th>
        <th>EXPENSE ACCOUNT</th>
        <th>REFERENCE#</th>
        <th>VENDOR NAME</th>
        <th>PAID THROUGH</th>
        <th>CUSTOMER NAME</th>
        <th width="80" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6, 'className' => 'col-center'],
        ['data' => 7, 'className' => 'text-end'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search expenses...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
