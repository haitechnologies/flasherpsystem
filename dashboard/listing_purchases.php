<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'purchases';
$module_caption = 'Purchase';
$tbl_name = DB::PURCHASES;
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please try again.';
        log_error('Invalid CSRF token on purchase delete', 'SECURITY', __FILE__, __LINE__, backend_runtime_log_context([
            'module' => 'purchases',
            'module_slug' => 'purchases',
        ]));
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $purchaseId = (int)$id;
    $uid = (int)Session::userId();
    $deleted = 0;
    if (is_SystemAdmin() || is_SuperAdmin()) {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ITEMS . "` WHERE purchase_id=$purchaseId");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$purchaseId AND organization_id=$activeOrganizationId");
        $deleted = $mysqli->affected_rows;

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='purchase' AND reference_id=$purchaseId AND organization_id=$activeOrganizationId ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='purchase' AND reference_id=$purchaseId AND organization_id=$activeOrganizationId ");
        }
    } else {
        $mysqli->query("DELETE FROM `" . DB::PURCHASE_ITEMS . "` WHERE purchase_id=$purchaseId AND created_by='$uid'");
        $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$purchaseId AND organization_id=$activeOrganizationId AND created_by='$uid'");
        $deleted = $mysqli->affected_rows;

        $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='purchase' AND reference_id=$purchaseId AND organization_id=$activeOrganizationId ");
        if (!empty($journal_id)) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$journal_id ");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE reference_type='purchase' AND reference_id=$purchaseId AND organization_id=$activeOrganizationId AND created_by='$uid' ");
        }
    }

    if ($deleted > 0) {
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
