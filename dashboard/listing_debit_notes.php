<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;

include('admin_elements/admin_header.php');

$module             = 'debit_notes';
$module_caption     = 'Debit Note';
$tbl_name = DB::DEBIT_NOTES;
$error_message      = '';
$success_message    = '';
$page               = (int)($_GET['page'] ?? 1);

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!empty($action) && $action == "delete_$module") {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Debit Note could not be deleted.';
        log_error('Invalid CSRF token on debit_notes delete');
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {

    $debitNoteId = (int)$id;
    $uid = (int)Session::userId();

    if (Session::roleId() == '1') {

        $mysqli->query("DELETE FROM `" . DB::DEBIT_NOTE_ITEMS . "` WHERE debit_note_id=$debitNoteId");
        $deleted = 0;
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$debitNoteId AND organization_id=$activeOrganizationId");
        $deleted = $mysqli->affected_rows;

        // Delete associated journals (debit_note + debit_note_void)
        $journal_ids = array();
        $jresult = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE (reference_type='debit_note' OR reference_type='debit_note_void') AND reference_id=$debitNoteId");
        while ($jrow = $jresult->fetch_assoc()) {
            $journal_ids[] = (int)$jrow['id'];
        }
        foreach ($journal_ids as $jid) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$jid");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE id=$jid");
        }
    } else {

        $mysqli->query("DELETE FROM `" . DB::DEBIT_NOTE_ITEMS . "` WHERE debit_note_id=$debitNoteId");
        $deleted = 0;
        $result = $mysqli->query("DELETE FROM `$tbl_name` WHERE id=$debitNoteId AND organization_id=$activeOrganizationId AND created_by='$uid'");
        $deleted = $mysqli->affected_rows;

        // Delete associated journals (debit_note + debit_note_void)
        $journal_ids = array();
        $jresult = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE (reference_type='debit_note' OR reference_type='debit_note_void') AND reference_id=$debitNoteId AND created_by='$uid'");
        while ($jrow = $jresult->fetch_assoc()) {
            $journal_ids[] = (int)$jrow['id'];
        }
        foreach ($journal_ids as $jid) {
            $mysqli->query("DELETE FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$jid");
            $mysqli->query("DELETE FROM `" . DB::JOURNALS . "` WHERE id=$jid");
        }
    }

    if ($deleted > 0) {
        $success_message = "$module_caption Deleted Successfully.";
        flash_success($success_message);
        header("Location:listing_$module.php?page=$page");
    } else {
        $error_message = "Sorry! $module_caption Could Not Be Deleted. Only Super Administrator can delete this record.";
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'thead' => '
        <th width="100">DATE</th>
        <th width="150">DEBIT NOTE #</th>
        <th>REFERENCE #</th>
        <th>VENDOR NAME</th>
        <th width="100" class="col-center">STATUS</th>
        <th width="100" class="text-end">AMOUNT</th>
    ',
    'columns' => [
        ['data' => 0],
        ['data' => 1],
        ['data' => 2],
        ['data' => 3],
        ['data' => 4, 'className' => 'col-center'],
        ['data' => 5, 'className' => 'text-end'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'search_placeholder' => 'Search debit notes...',
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
