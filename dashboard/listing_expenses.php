<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

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

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        log_error('Invalid CSRF token on expense delete', 'SECURITY', __FILE__, __LINE__, backend_runtime_log_context([
            'module' => 'expenses',
            'module_slug' => 'expenses',
        ]));
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $expenseId = (int)$id;
    $uid = (int)Session::userId();
    if (is_SystemAdmin() || is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . tbl_expense_items . "` WHERE expense_id=$expenseId AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `" . tbl_expense_attachments . "` WHERE expense_id=$expenseId AND organization_id=$activeOrganizationId");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$expenseId AND organization_id=$activeOrganizationId");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='expense' AND reference_id=$expenseId AND organization_id=$activeOrganizationId ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='expense' AND reference_id=$expenseId AND organization_id=$activeOrganizationId ");
        }
    } else {
        $mysqli->query("DELETE FROM `" . tbl_expense_items . "` WHERE expense_id=$expenseId AND organization_id=$activeOrganizationId AND created_by ='$uid'");
        $mysqli->query("DELETE FROM `" . tbl_expense_attachments . "` WHERE expense_id=$expenseId AND organization_id=$activeOrganizationId AND created_by ='$uid'");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$expenseId AND organization_id=$activeOrganizationId AND created_by ='$uid'");

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='expense' AND reference_id=$expenseId AND organization_id=$activeOrganizationId ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='expense' AND reference_id=$expenseId AND organization_id=$activeOrganizationId AND created_by='$uid' ");
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
