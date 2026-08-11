<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module = 'payments_made';
$module_caption = 'Payment Made';
$tbl_name = DB::PAYMENTS_MADE;
$error_message = '';
$success_message = '';
$page = (int)($_GET['page'] ?? 1);

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (isset($_REQUEST['vendor_id']) && !empty($_REQUEST['vendor_id'])) {
    $vendor_id = e_s__($_REQUEST['vendor_id']);
} else {
    $vendor_id = '';
}

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Payment could not be deleted.';
        log_error('Invalid CSRF token on listing_payments_made delete');
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $paymentId = (int)$id;
    $uid = (int)Session::userId();

    $items_table = DB::PAYMENT_MADE_ITEMS;
    $journal_items_table = DB::JOURNAL_ITEMS;
    $journal_table = DB::JOURNALS;

    if (Session::roleId() == '1') {
        $mysqli->query("DELETE FROM `$items_table` WHERE payment_id=$paymentId");
        $deleted = 0;
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$paymentId AND organization_id=$activeOrganizationId");
        $deleted = $mysqli->affected_rows;

        $journal_ids = $mysqli->query("SELECT id FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId");
        if ($journal_ids && $journal_ids->num_rows > 0) {
            $mysqli->query("DELETE FROM `$journal_items_table` WHERE journal_id IN (SELECT id FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId)");
            $mysqli->query("DELETE FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId");
        }
    } else {
        $mysqli->query("DELETE FROM `$items_table` WHERE payment_id=$paymentId");
        $deleted = 0;
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$paymentId AND organization_id=$activeOrganizationId AND created_by='$uid'");
        $deleted = $mysqli->affected_rows;

        $journal_ids = $mysqli->query("SELECT id FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId AND created_by='$uid'");
        if ($journal_ids && $journal_ids->num_rows > 0) {
            $mysqli->query("DELETE FROM `$journal_items_table` WHERE journal_id IN (SELECT id FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId AND created_by='$uid')");
            $mysqli->query("DELETE FROM `$journal_table` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id=$paymentId AND created_by='$uid'");
        }
    }

    if ($deleted > 0) {
        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_payments_made.php?page=$page");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'table_classes' => 'custom_datatables display responsive no-wrap table-hover',
    'thead' => '
        <th width="100">DATE</th>
        <th width="100">PAYMENT#</th>
        <th width="100">REFERENCE NUMBER</th>
        <th width="100">VENDOR NAME</th>
        <th width="100">PURCHASE#</th>
        <th width="100">MODE</th>
        <th width="100">AMOUNT</th>
        <th width="100">STATUS</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4],
        ['data' => 5],
        ['data' => 6],
        ['data' => 7],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
