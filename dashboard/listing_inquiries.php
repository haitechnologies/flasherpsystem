<?php


use App\Core\DB;
use App\Core\Session;
/*
|--------------------------------------------------------------------------
| BOOTSTRAP CORE DEPENDENCIES (before any output)
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/bootstrap.php';

$module = 'inquiries';
$module_caption = 'Inquiry';
$tbl_name = DB::INQUIRIES;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| AJAX HANDLERS - MUST RUN BEFORE admin_header.php TO AVOID HTML POLLUTION
|--------------------------------------------------------------------------
| These handlers check $_POST['action'] and exit early with JSON response
| before any HTML output occurs
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| UPDATE STATUS (AJAX)
|--------------------------------------------------------------------------
*/
if (($_POST['action'] ?? null) == 'update_inquiries' && !empty($_POST['id'])) {
	
	$id = (int)$_POST['id'];
	$status = !empty($_POST['status']) ? (int)$_POST['status'] : 0;
	
	$stmt = $mysqli->prepare("UPDATE " . DB::INQUIRIES . " SET status = ?, updated_at = NOW() WHERE id = ?");
	$stmt->bind_param("ii", $status, $id);
	
	if ($stmt->execute()) {
		echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
	} else {
		echo json_encode(['success' => false, 'message' => 'Failed to update status.']);
	}
	exit;

/*
|--------------------------------------------------------------------------
| BULK DELETE (AJAX)
|--------------------------------------------------------------------------
*/
} else if (($_POST['action'] ?? null) == 'bulk_delete_inquiries' && !empty($_POST['ids'])) {
	
	// Parse the JSON array of IDs
	$ids_json = $_POST['ids'];
	$ids = json_decode($ids_json, true);
	
	if (!is_array($ids) || empty($ids)) {
		echo json_encode(['success' => false, 'message' => 'Invalid IDs provided.']);
		exit;
	}
	
	// Sanitize and validate IDs (must be integers)
	$ids = array_map('intval', $ids);
	$ids_string = implode(',', $ids);
	
	// Get module ID for permission check
	$module_id = getModuleIdBySlug($module, $mysqli);
	if (!granted('delete', $module_id)) {
		echo json_encode(['success' => false, 'message' => 'Permission denied.']);
		exit;
	}
	
	// Delete the records
	$sql = "DELETE FROM " . DB::INQUIRIES . " WHERE id IN ($ids_string)";
	
	if ($mysqli->query($sql)) {
		$affected = $mysqli->affected_rows;
		echo json_encode(['success' => true, 'message' => "$affected inquiry(ies) deleted successfully.", 'deleted_count' => $affected]);
	} else {
		echo json_encode(['success' => false, 'message' => 'Failed to delete inquiries.']);
	}
	exit;

/*
|--------------------------------------------------------------------------
| GET INQUIRY THREAD (AJAX)
|--------------------------------------------------------------------------
*/
/*
|--------------------------------------------------------------------------
| MARK / UNMARK SPAM (AJAX)
|--------------------------------------------------------------------------
*/
} else if (($_POST['action'] ?? null) == 'mark_spam_inquiry' && !empty($_POST['id'])) {
	header('Content-Type: application/json; charset=utf-8');

	if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
		echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
		exit;
	}

	if (!granted_('edit', 'inquiries')) {
		echo json_encode(['success' => false, 'message' => 'Permission denied.']);
		exit;
	}

	$id    = (int)$_POST['id'];
	$spam  = (int)$_POST['spam'] === 1 ? 1 : 0;
	$stmt  = $mysqli->prepare("UPDATE " . DB::INQUIRIES . " SET is_spam = ?, updated_at = NOW() WHERE id = ?");
	$stmt->bind_param("ii", $spam, $id);

	if ($stmt->execute()) {
		$msg = $spam ? 'Marked as spam.' : 'Unmarked as spam.';
		echo json_encode(['success' => true, 'message' => $msg]);
	} else {
		echo json_encode(['success' => false, 'message' => 'Failed to update spam status.']);
	}
	exit;

/*
|--------------------------------------------------------------------------
| ARCHIVE / UNARCHIVE (AJAX)
|--------------------------------------------------------------------------
*/
} else if (($_POST['action'] ?? null) == 'mark_archive_inquiry' && !empty($_POST['id'])) {
	header('Content-Type: application/json; charset=utf-8');

	if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
		echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
		exit;
	}

	if (!granted_('edit', 'inquiries')) {
		echo json_encode(['success' => false, 'message' => 'Permission denied.']);
		exit;
	}

	$id = (int)$_POST['id'];
	$archive = (int)$_POST['archive'] === 1 ? 1 : 0;
	$newStatus = $archive === 1 ? 4 : 1;

	$stmt = $mysqli->prepare("UPDATE " . DB::INQUIRIES . " SET status = ?, updated_at = NOW() WHERE id = ?");
	$stmt->bind_param("ii", $newStatus, $id);

	if ($stmt->execute()) {
		$msg = $archive === 1 ? 'Moved to archive.' : 'Moved back to inbox.';
		echo json_encode(['success' => true, 'message' => $msg]);
	} else {
		echo json_encode(['success' => false, 'message' => 'Failed to update archive status.']);
	}
	exit;

/*
|--------------------------------------------------------------------------
| GET INQUIRY THREAD (AJAX)
|--------------------------------------------------------------------------
*/
} else if (($_POST['action'] ?? null) == 'get_inquiry_thread' && !empty($_POST['inquiry_id'])) {

	$inquiryId = (int)$_POST['inquiry_id'];

	$stmt = $mysqli->prepare(
		"SELECT id, admin_name, direction, recipient_email, subject, body, is_email_sent, created_at
		 FROM `" . DB::INQUIRY_REPLIES . "`
		 WHERE inquiry_id = ?
		 ORDER BY created_at ASC"
	);

	if (!$stmt) {
		echo json_encode(['success' => false, 'message' => 'Query failed.']);
		exit;
	}

	$stmt->bind_param('i', $inquiryId);
	$stmt->execute();
	$result = $stmt->get_result();
	$replies = [];
	while ($row = $result->fetch_assoc()) {
		$replies[] = [
			'id'              => (int)$row['id'],
			'admin_name'      => $row['admin_name'],
			'direction'       => $row['direction'],
			'recipient_email' => $row['recipient_email'],
			'subject'         => $row['subject'],
			'body'            => $row['body'],
			'is_email_sent'   => (int)$row['is_email_sent'],
			'created_at'      => $row['created_at'],
		];
	}
	$stmt->close();
	echo json_encode(['success' => true, 'replies' => $replies]);
	exit;

/*
|--------------------------------------------------------------------------
| SEND INQUIRY REPLY (AJAX)
|--------------------------------------------------------------------------
*/
} else if (($_POST['action'] ?? null) == 'send_inquiry_reply' && !empty($_POST['inquiry_id'])) {

	if (!granted_('edit', 'inquiries')) {
		echo json_encode(['success' => false, 'message' => 'Permission denied.']);
		exit;
	}

	$inquiryId    = (int)$_POST['inquiry_id'];
	$replyBody    = trim($_POST['reply_body'] ?? '');
	$replySubject = trim($_POST['reply_subject'] ?? '');
	$direction    = ($_POST['direction'] ?? 'outbound') === 'note' ? 'note' : 'outbound';
	$adminUserId  = (int)(Session::userId() ?? 0);
	$adminName    = $session_full_name ?? 'Admin';

	if ($replyBody === '') {
		echo json_encode(['success' => false, 'message' => 'Reply body cannot be empty.']);
		exit;
	}

	// Fetch the original inquiry to get recipient email
	$inqStmt = $mysqli->prepare("SELECT email, full_name, subject FROM `" . DB::INQUIRIES . "` WHERE id = ? AND is_active = 1 LIMIT 1");
	if (!$inqStmt) {
		echo json_encode(['success' => false, 'message' => 'Inquiry not found.']);
		exit;
	}
	$inqStmt->bind_param('i', $inquiryId);
	$inqStmt->execute();
	$inqResult = $inqStmt->get_result();
	$inquiry = $inqResult->fetch_assoc();
	$inqStmt->close();

	if (!$inquiry) {
		echo json_encode(['success' => false, 'message' => 'Inquiry not found.']);
		exit;
	}

	$recipientEmail = $inquiry['email'];
	$recipientName  = $inquiry['full_name'];
	$originalSubject = $inquiry['subject'];

	if ($replySubject === '') {
		$replySubject = 'Re: ' . $originalSubject;
	}

	$isEmailSent = 0;
	$emailQueueId = 0;

	// Send email if direction is outbound
	if ($direction === 'outbound' && filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
		// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/EmailQueue.php';
		$emailQueue = new EmailQueue();

		$emailBody = '<div style="font-family:Arial,sans-serif;font-size:15px;color:#333;line-height:1.6;">'
			. '<p>Dear ' . htmlspecialchars($recipientName) . ',</p>'
			. '<div style="background:#f9f9f9;border-left:4px solid #0d6efd;padding:12px 16px;margin:16px 0;">'
			. nl2br(htmlspecialchars($replyBody))
			. '</div>'
			. '<p style="color:#888;font-size:13px;margin-top:24px;">This is a reply to your inquiry submitted on Flash Logistics.</p>'
			. '</div>';

		$supportEmail  = $_ENV['MAIL_FROM_ADDRESS'] ?? 'support@flasherpsystem.com';
		$originalMsgId = '<inquiry-' . $inquiryId . '@flasherpsystem.com>';
		$replyMsgId    = '<inquiry-' . $inquiryId . '-r-' . time() . '@flasherpsystem.com>';

		$headers = [
			'Reply-To' => $supportEmail,
			'Message-ID' => $replyMsgId,
			'In-Reply-To' => $originalMsgId,
			'References' => $originalMsgId,
		];
		$queueId = $emailQueue->enqueue($recipientEmail, $replySubject, $emailBody, $headers, 1);
		if ($queueId) {
			$emailQueueId = (int)$queueId;
			$sent = $emailQueue->processPending(1);
			$isEmailSent = ($sent > 0) ? 1 : 0;
		}
	}

	// Log the reply
	$insertStmt = $mysqli->prepare(
		"INSERT INTO `" . DB::INQUIRY_REPLIES . "`
		 (inquiry_id, admin_user_id, admin_name, direction, recipient_email, subject, body, is_email_sent, email_queue_id, created_at)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
	);

	if (!$insertStmt) {
		echo json_encode(['success' => false, 'message' => 'Failed to log reply.']);
		exit;
	}

	$insertStmt->bind_param('iisssssii',
		$inquiryId, $adminUserId, $adminName, $direction,
		$recipientEmail, $replySubject, $replyBody, $isEmailSent, $emailQueueId
	);

	if (!$insertStmt->execute()) {
		echo json_encode(['success' => false, 'message' => 'Failed to save reply.']);
		exit;
	}
	$newReplyId = $mysqli->insert_id;
	$insertStmt->close();

	// Update inquiry status to Replied (2)
	$mysqli->query("UPDATE `" . DB::INQUIRIES . "` SET status = 2, updated_at = NOW() WHERE id = $inquiryId");

	echo json_encode([
		'success'       => true,
		'message'       => $direction === 'note' ? 'Note saved.' : ($isEmailSent ? 'Reply sent.' : 'Reply saved (email queued).'),
		'reply_id'      => $newReplyId,
		'is_email_sent' => $isEmailSent,
	]);
	exit;
}

/*
|--------------------------------------------------------------------------
| PAGE RENDERING - Now include admin_header.php (HTML output is safe here)
|--------------------------------------------------------------------------
*/
include('admin_elements/admin_header.php');

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');
$activeOrganizationId = dashboardRequireActiveOrganization();
$hide_add_button = true;

/*
|--------------------------------------------------------------------------
| CSRF VALIDATION FOR FORM SUBMISSIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in ' . __FILE__, 'WARNING', __FILE__, __LINE__);
    }
}

$module_id = getModuleIdBySlug($module, $mysqli);
include('admin_elements/listing_handler.php');
?>

<div class="content-wrapper">

	<!-- Page header -->
	<!-- /page header -->


	<div class="content datatable-enhanced">

		<?php include('admin_elements/breadcrumb.php'); ?>

		<?php
		$statuses = [
			0 => ['New', 'primary'],
			1 => ['Read', 'info'],
			2 => ['Replied', 'success']
		];

		$statusCounts = [];
		foreach ($statuses as $status_id => $status_info) {
			$result = $mysqli->query("SELECT COUNT(*) as count FROM " . DB::INQUIRIES . " WHERE status = $status_id AND is_active=1");
			$row = $result ? $result->fetch_assoc() : ['count' => 0];
			$statusCounts[$status_id] = (int)($row['count'] ?? 0);
		}

		$spamCount = 0;
		$spamResult = $mysqli->query("SELECT COUNT(*) as count FROM " . DB::INQUIRIES . " WHERE is_spam = 1 AND is_active = 1");
		if ($spamResult) {
			$spamRow = $spamResult->fetch_assoc();
			$spamCount = (int)($spamRow['count'] ?? 0);
		}

		$archiveCount = 0;
		$archiveResult = $mysqli->query("SELECT COUNT(*) as count FROM " . DB::INQUIRIES . " WHERE status = 4 AND is_active = 1");
		if ($archiveResult) {
			$archiveRow = $archiveResult->fetch_assoc();
			$archiveCount = (int)($archiveRow['count'] ?? 0);
		}
		?>

		<div class="card">
			<div class="card-header d-flex flex-wrap align-items-start justify-content-between gap-3">
				<div>
					<h5 class="mb-0">
						<i class="ph-envelope me-2"></i>
						Contact Form Inquiries
					</h5>
					<span class="text-muted">Manage customer inquiries and support requests</span>
				</div>
				<div class="d-flex flex-wrap justify-content-lg-end gap-2">
					<?php foreach ($statuses as $status_id => $status_info) { ?>
						<span class="badge bg-<?php echo $status_info[1]; ?>" style="font-size: 14px; padding: 8px 12px;">
							<?php echo $status_info[0]; ?>: <?php echo (int)($statusCounts[$status_id] ?? 0); ?>
						</span>
					<?php } ?>
					<button id="bulk-delete-btn" class="btn btn-sm btn-danger" style="display: none;">
						<i class="ph-trash me-2"></i><span id="delete-count">Delete Selected</span>
					</button>
				</div>
			</div>

			<style>
				#grid-inquiries td {
					vertical-align: top;
				}

				.inquiry-sender-cell,
				.inquiry-preview-cell,
				.inquiry-status-cell {
					min-width: 0;
				}

				.inquiry-sender-name {
					font-weight: 600;
					color: #132238;
					margin-bottom: 0.2rem;
				}

				.inquiry-sender-meta,
				.inquiry-message-snippet {
					font-size: 0.875rem;
					line-height: 1.45;
					color: #54657d;
				}

				.inquiry-sender-link {
					color: #0d6efd;
					text-decoration: none;
				}

				.inquiry-sender-link:hover {
					text-decoration: underline;
				}

				.inquiry-meta-sep {
					margin: 0 0.35rem;
					color: #98a5b6;
				}

				.inquiry-subject-line {
					font-weight: 600;
					color: #132238;
					margin-bottom: 0.45rem;
				}

				.inquiry-message-snippet {
					white-space: normal;
					display: -webkit-box;
					line-clamp: 6;
					-webkit-line-clamp: 6;
					-webkit-box-orient: vertical;
					overflow: hidden;
				}

				.inquiry-actions-cell .btn {
					white-space: nowrap;
				}

				@media (min-width: 1200px) {
					#grid-inquiries colgroup col:nth-child(4) {
						width: 43%;
					}
				}
			</style>

			<div class="card-body">
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
					<div class="btn-group" role="group" aria-label="Inquiry filter tabs">
						<button type="button" id="inquiry-tab-inbox" class="btn btn-sm btn-primary inquiry-filter-tab active" data-filter-spam="0">
							<i class="ph-envelope me-1"></i>Inbox
						</button>
						<button type="button" id="inquiry-tab-spam" class="btn btn-sm btn-outline-danger inquiry-filter-tab" data-filter-spam="1">
							<i class="ph-prohibit me-1"></i>Spam (<?php echo (int)$spamCount; ?>)
						</button>
						<button type="button" id="inquiry-tab-archive" class="btn btn-sm btn-outline-dark inquiry-filter-tab" data-filter-spam="0" data-filter-archive="1">
							<i class="ph-archive me-1"></i>Archive (<?php echo (int)$archiveCount; ?>)
						</button>
					</div>
					<small class="text-muted">Switch tabs to view inbox, spam, or archived records.</small>
				</div>
			<div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="custom_datatables datatable-professional display responsive table-hover" width="100%">
				<colgroup>
					<col style="width:40px;">
					<col style="width:110px;">
					<col style="width:20%;">
					<col style="width:43%;">
					<col style="width:90px;">
					<col style="width:14%;">
				</colgroup>
					<thead>
						<tr>
							<th width="40"><input type="checkbox" id="select-all" title="Select all"></th>
							<th width="110">DATE</th>
							<th>SENDER</th>
							<th>INQUIRY PREVIEW</th>
							<th>STATUS</th>
							<th>ACTIONS</th>
						</tr>
					</thead>
				</table>
</div>
			</div>
		</div>

	</div>

	<?php include('admin_elements/copyright.php'); ?>
</div>

<!-- Hidden CSRF Token for Form Submissions -->
<?php echo csrf_field(); ?>

<!-- Inquiry Thread Modal -->
<div class="modal fade" id="inquiryDetailsModal" tabindex="-1" aria-labelledby="inquiryDetailsModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl modal-dialog-scrollable">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="inquiryDetailsModalLabel">
					<i class="ph-envelope me-2"></i>Inquiry Thread
				</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0">
				<div class="p-3 border-bottom bg-light">
					<div class="row g-2 align-items-start">
						<div class="col-6 col-md-1"><div class="text-muted small">ID</div><div id="modal-inquiry-id" class="fw-semibold">-</div></div>
						<div class="col-6 col-md-2"><div class="text-muted small">Date</div><div id="modal-inquiry-date" class="fw-semibold small">-</div></div>
						<div class="col-6 col-md-2"><div class="text-muted small">Name</div><div id="modal-inquiry-name" class="fw-semibold">-</div></div>
						<div class="col-6 col-md-3"><div class="text-muted small">Email</div><div><a id="modal-inquiry-email-link" href="#" class="text-break">-</a></div></div>
						<div class="col-6 col-md-2"><div class="text-muted small">Phone</div><div id="modal-inquiry-phone">-</div></div>
						<div class="col-6 col-md-2"><div class="text-muted small">Status</div><div id="modal-inquiry-status">-</div></div>
					</div>
					<div class="row g-2 mt-1">
						<div class="col-md-4"><div class="text-muted small">IP</div><div id="modal-inquiry-ip" class="small text-muted">-</div></div>
						<div class="col-md-8"><div class="text-muted small">Subject</div><div id="modal-inquiry-subject" class="fw-semibold">-</div></div>
					</div>
					<div id="modal-claim-context-wrap" class="mt-2" style="display:none;">
						<div class="text-muted small">Claim Context</div>
						<div id="modal-claim-context" class="border rounded p-2 bg-warning-subtle small">-</div>
					</div>
				</div>

				<div class="p-3" id="inquiry-thread-wrapper">
					<div class="d-flex mb-3">
						<div class="flex-shrink-0 me-2">
							<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" style="width:34px;height:34px;font-size:14px;">
								<i class="ph-user"></i>
							</div>
						</div>
						<div class="flex-grow-1">
							<div class="d-flex justify-content-between align-items-center mb-1">
								<span class="fw-semibold small" id="modal-sender-name">Customer</span>
								<small class="text-muted" id="modal-original-date">-</small>
							</div>
							<div class="border rounded p-3 bg-light" id="modal-inquiry-message" style="white-space:pre-wrap;word-wrap:break-word;max-height:200px;overflow-y:auto;">-</div>
						</div>
					</div>

					<div id="inquiry-reply-thread"></div>
					<div id="thread-loading" class="text-center py-3" style="display:none;">
						<div class="spinner-border spinner-border-sm text-primary"></div>
						<span class="ms-2 text-muted small">Loading thread...</span>
					</div>
				</div>

				<?php if (granted_('edit', 'inquiries')): ?>
				<div class="border-top p-3 bg-white" id="reply-composer">
					<h6 class="mb-3 text-primary"><i class="ph-paper-plane-tilt me-2"></i>Compose Reply</h6>
					<div class="mb-2 d-flex gap-4">
						<div class="form-check">
							<input class="form-check-input" type="radio" name="reply_direction" id="dir-outbound" value="outbound" checked>
							<label class="form-check-label small" for="dir-outbound"><i class="ph-envelope me-1 text-primary"></i>Email to Customer</label>
						</div>
						<div class="form-check">
							<input class="form-check-input" type="radio" name="reply_direction" id="dir-note" value="note">
							<label class="form-check-label small" for="dir-note"><i class="ph-notepad me-1 text-warning"></i>Internal Note Only</label>
						</div>
					</div>
					<div class="mb-2" id="reply-subject-wrap">
						<label class="form-label small mb-1">Subject</label>
						<input type="text" id="reply-subject" class="form-control form-control-sm" placeholder="Re: ..." maxlength="500">
					</div>
					<div class="mb-2">
						<label class="form-label small mb-1">Message</label>
						<textarea id="reply-body" class="form-control form-control-sm" rows="4" placeholder="Type your reply here..."></textarea>
					</div>
					<div class="d-flex gap-2 align-items-center flex-wrap">
						<button id="send-reply-btn" class="btn btn-primary btn-sm"><i class="ph-paper-plane-tilt me-1"></i>Send Reply</button>
						<span id="reply-status-msg" class="small"></span>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="modal-footer py-2"></div>
		</div>
	</div>
</div>

<script>
window.inquirySpamFilter = 0;
window.inquiryArchiveFilter = 0;

$(document).ready(function() {
	// Initialize DataTable
	// ========================================

	window.HAIDatatableInitializer.init('#grid-inquiries', 'inquiries', {
		stateSave: false,
		deferRender: true,
		retrieve: false,
		autoWidth: false,
		dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>" +
			"rt" +
			"<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
		ajax: {
			data: function(d) {
				d.module = 'inquiries';
				d.filter_spam = window.inquirySpamFilter;
				d.filter_archive = window.inquiryArchiveFilter;
				d.edit_permission = <?php echo granted_('edit', 'inquiries') ? 1 : 0; ?>;
				d.delete_permission = <?php echo granted_('delete', 'inquiries') ? 1 : 0; ?>;
				d.session_user_id = '<?php echo Session::userId() ?? ''; ?>';
				d.dt_session_role_id = '<?php echo Session::roleId() ?? ''; ?>';
				return d;
			},
			error: function(xhr, status, error) {
				console.error('[Inquiries] DataTable AJAX Error');
				console.error('Status:', xhr.status, '|', status);
				console.error('Response:', xhr.responseText);
			}
		},
		columns: [
			{ data: 0, orderable: false },  // Checkbox
			{ data: 1 },                    // Date
			{ data: 2 },                    // Sender
			{ data: 3 },                    // Inquiry preview
			{ data: 4, orderable: false },  // Status
			{ data: 5, orderable: false }   // Actions
		],
		columnDefs: [
			{ targets: [2, 3, 4, 5], className: 'text-wrap' }
		],
		order: [[1, 'desc']],
		pageLength: 10,
		lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
	});

	$(document).on('click', '.inquiry-filter-tab', function() {
		const nextSpamFilter = parseInt($(this).attr('data-filter-spam') || '0', 10);
		const nextArchiveFilter = parseInt($(this).attr('data-filter-archive') || '0', 10);
		window.inquirySpamFilter = nextSpamFilter === 1 ? 1 : 0;
		window.inquiryArchiveFilter = nextArchiveFilter === 1 ? 1 : 0;

		$('.inquiry-filter-tab').removeClass('active');
		$('#inquiry-tab-inbox').removeClass('btn-primary').addClass('btn-outline-primary');
		$('#inquiry-tab-spam').removeClass('btn-danger').addClass('btn-outline-danger');
		$('#inquiry-tab-archive').removeClass('btn-dark').addClass('btn-outline-dark');

		if (window.inquiryArchiveFilter === 1) {
			$('#inquiry-tab-archive').addClass('btn-dark active').removeClass('btn-outline-dark');
		} else if (window.inquirySpamFilter === 1) {
			$('#inquiry-tab-spam').addClass('btn-danger active').removeClass('btn-outline-danger');
		} else {
			$('#inquiry-tab-inbox').addClass('btn-primary active').removeClass('btn-outline-primary');
		}

		$('#grid-inquiries').DataTable().ajax.reload(null, false);
	});

	$(document).on('click', '.mark-spam-btn', function(e) {
		e.stopPropagation();
		const id   = $(this).data('id');
		const spam = $(this).data('spam');
		const csrfToken = $('input[name="csrf_token"]').val();
		$.post('listing_inquiries.php', {
			action: 'mark_spam_inquiry',
			id: id,
			spam: spam,
			csrf_token: csrfToken
		}, function(resp) {
			if (resp && resp.success) {
				$('#grid-inquiries').DataTable().ajax.reload(null, false);
			} else {
				alert((resp && resp.message) ? resp.message : 'Error updating spam status.');
			}
		}, 'json').fail(function() {
			const details = (arguments[0] && arguments[0].responseText)
				? String(arguments[0].responseText).substring(0, 220)
				: 'No server response body.';
			alert('Failed to update spam status. ' + details);
		});
	});

	$(document).on('click', '.mark-archive-btn', function(e) {
		e.stopPropagation();
		const id = $(this).data('id');
		const archive = $(this).data('archive');
		const csrfToken = $('input[name="csrf_token"]').val();
		$.post('listing_inquiries.php', {
			action: 'mark_archive_inquiry',
			id: id,
			archive: archive,
			csrf_token: csrfToken
		}, function(resp) {
			if (resp && resp.success) {
				$('#grid-inquiries').DataTable().ajax.reload(null, false);
			} else {
				alert((resp && resp.message) ? resp.message : 'Error updating archive status.');
			}
		}, 'json').fail(function() {
			const details = (arguments[0] && arguments[0].responseText)
				? String(arguments[0].responseText).substring(0, 220)
				: 'No server response body.';
			alert('Failed to update archive status. ' + details);
		});
	});
});

document.addEventListener('DOMContentLoaded', function() {

	const inquiryDetailsModal = new bootstrap.Modal(document.getElementById('inquiryDetailsModal'));
	const statusLabels = {
		0: '<span class="badge bg-primary">New</span>',
		1: '<span class="badge bg-info">Read</span>',
		2: '<span class="badge bg-success">Replied</span>',
		3: '<span class="badge bg-secondary">Closed</span>',
		4: '<span class="badge bg-dark">Archived</span>'
	};

	// -------------------------------------------------------
	// Shared: Open modal, fill meta, load thread
	// -------------------------------------------------------
	function openInquiryModal(btn) {
		const $btn = $(btn);
		const inquiryId  = $btn.data('id');
		const statusCode = parseInt($btn.data('status'), 10);
		const subject    = $btn.data('subject') || '';
		const subjectDisplay = $btn.attr('data-subject-display') || subject;

		// Fill meta
		$('#modal-inquiry-id').text(inquiryId || '-');
		$('#modal-inquiry-date').text($btn.data('date') || '-');
		$('#modal-inquiry-name').text($btn.data('name') || '-');
		$('#modal-sender-name').text($btn.data('name') || 'Customer');
		$('#modal-inquiry-email-link').text($btn.data('email') || '-')
			.attr('href', 'mailto:' + ($btn.data('email') || '#'));
		$('#modal-inquiry-phone').text($btn.data('phone') || '-');
		$('#modal-inquiry-status').html(statusLabels[statusCode] || '-');
		$('#modal-inquiry-ip').text($btn.data('ip') || '-');
		$('#modal-inquiry-subject').html(subjectDisplay);
		$('#modal-inquiry-message').text($btn.data('message') || '-');
		$('#modal-original-date').text($btn.data('date') || '-');

		// Claim context
		const isClaim        = String($btn.attr('data-claim-request') || '0') === '1';
		const claimCompanyId = parseInt($btn.attr('data-claim-company-id') || '0', 10);
		const claimCompanySlug = ($btn.attr('data-claim-company-slug') || '').trim();
		if (isClaim) {
			let claimHtml = '<strong>Business claim request</strong>';
			if (claimCompanyId > 0) claimHtml += '<br>Company ID: ' + claimCompanyId;
			if (claimCompanySlug !== '') {
				claimHtml += '<br>Slug: ' + claimCompanySlug;
				claimHtml += ' <a href="../company/' + encodeURIComponent(claimCompanySlug) + '" target="_blank" rel="noopener">View listing</a>';
			}
			$('#modal-claim-context').html(claimHtml);
			$('#modal-claim-context-wrap').show();
		} else {
			$('#modal-claim-context-wrap').hide();
		}

		// Pre-fill reply subject
		$('#reply-subject').val('Re: ' + subject);
		$('#reply-body').val('');
		$('#reply-status-msg').text('').removeClass('text-success text-danger');
		$('#dir-outbound').prop('checked', true).trigger('change');
		$('#inquiry-reply-thread').html('');

		// Load thread
		loadInquiryThread(inquiryId);

		// Mark as read if New
		if (statusCode === 0) {
			const csrfToken = $('input[name="csrf_token"]').val();
			fetch('listing_inquiries.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=update_inquiries&id=' + inquiryId + '&status=1&csrf_token=' + encodeURIComponent(csrfToken)
			}).then(() => {
				$btn.data('status', 1);
				$('#modal-inquiry-status').html(statusLabels[1]);
			}).catch(() => {});
		}

		inquiryDetailsModal.show();
		// Attach event to reload DataTable after modal is closed
		const modalEl = document.getElementById('inquiryDetailsModal');
		if (modalEl) {
			modalEl.addEventListener('hidden.bs.modal', function handler() {
				$('#grid-inquiries').DataTable().ajax.reload(null, false);
				modalEl.removeEventListener('hidden.bs.modal', handler);
			});
		}
	}

	// -------------------------------------------------------
	// Load thread replies via AJAX
	// -------------------------------------------------------
	function loadInquiryThread(inquiryId) {
		$('#thread-loading').show();
		$('#inquiry-reply-thread').html('');
		const csrfToken = $('input[name="csrf_token"]').val();

		fetch('listing_inquiries.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: 'action=get_inquiry_thread&inquiry_id=' + encodeURIComponent(inquiryId) + '&csrf_token=' + encodeURIComponent(csrfToken)
		})
		.then(r => r.json())
		.then(data => {
			$('#thread-loading').hide();
			if (!data.success || !data.replies.length) return;
			renderThread(data.replies);
			// Scroll to bottom of thread
			const wrapper = document.getElementById('inquiry-thread-wrapper');
			if (wrapper) wrapper.scrollTop = wrapper.scrollHeight;
		})
		.catch(() => $('#thread-loading').hide());
	}

	// -------------------------------------------------------
	// Render thread replies
	// -------------------------------------------------------
	function renderThread(replies) {
		const $thread = $('#inquiry-reply-thread');
		replies.forEach(function(r) {
			const isNote      = r.direction === 'note';
			const isOutbound  = r.direction === 'outbound';
			const avatarBg    = isNote ? 'bg-warning' : 'bg-primary';
			const avatarIcon  = isNote ? 'ph-notepad' : 'ph-arrow-bend-up-right';
			const bubbleBg    = isNote ? 'bg-warning-subtle border-warning' : 'bg-primary-subtle border-primary';
			const typeBadge   = isNote
				? '<span class="badge bg-warning text-dark ms-1">Internal Note</span>'
				: '<span class="badge bg-primary ms-1">' + (r.is_email_sent ? 'Email Sent' : 'Email Queued') + '</span>';
			const emailInfo   = isOutbound
				? '<small class="text-muted"> â†’ ' + escHtml(r.recipient_email) + '</small>'
				: '';

			const html = `
			<div class="d-flex mb-3">
				<div class="flex-shrink-0 me-2">
					<div class="rounded-circle text-white d-flex align-items-center justify-content-center ${avatarBg}"
						style="width:34px;height:34px;font-size:14px;">
						<i class="${avatarIcon}"></i>
					</div>
				</div>
				<div class="flex-grow-1">
					<div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-1">
						<span class="fw-semibold small">${escHtml(r.admin_name)} ${typeBadge} ${emailInfo}</span>
						<small class="text-muted">${escHtml(r.created_at)}</small>
					</div>
					${r.subject ? '<div class="small text-muted mb-1"><strong>Subject:</strong> ' + escHtml(r.subject) + '</div>' : ''}
					<div class="border rounded p-2 ${bubbleBg}" style="white-space:pre-wrap;word-wrap:break-word;">${escHtml(r.body)}</div>
				</div>
			</div>`;
			$thread.append(html);
		});
	}

	function escHtml(str) {
		return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	// -------------------------------------------------------
	// Reply direction toggle: hide subject for note
	// -------------------------------------------------------
	$(document).on('change', 'input[name="reply_direction"]', function() {
		const isNote = $(this).val() === 'note';
		$('#reply-subject-wrap').toggle(!isNote);
		$('#send-reply-btn').html(isNote
			? '<i class="ph-notepad me-1"></i>Save Note'
			: '<i class="ph-paper-plane-tilt me-1"></i>Send Reply');
	});

	// -------------------------------------------------------
	// Send reply / save note
	// -------------------------------------------------------
	$(document).on('click', '#send-reply-btn', function() {
		const inquiryId = $('#modal-inquiry-id').text();
		const body      = $('#reply-body').val().trim();
		const subject   = $('#reply-subject').val().trim();
		const direction = $('input[name="reply_direction"]:checked').val() || 'outbound';
		const csrfToken = $('input[name="csrf_token"]').val();

		if (!body) {
			$('#reply-status-msg').text('Message cannot be empty.').removeClass('text-success').addClass('text-danger');
			return;
		}

		const $btn = $(this).prop('disabled', true).text('Sending...');
		$('#reply-status-msg').text('').removeClass('text-success text-danger');

		fetch('listing_inquiries.php', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: [
				'action=send_inquiry_reply',
				'inquiry_id=' + encodeURIComponent(inquiryId),
				'reply_body=' + encodeURIComponent(body),
				'reply_subject=' + encodeURIComponent(subject),
				'direction=' + encodeURIComponent(direction),
				'csrf_token=' + encodeURIComponent(csrfToken)
			].join('&')
		})
		.then(r => r.json())
		.then(data => {
			$btn.prop('disabled', false).html(
				direction === 'note'
					? '<i class="ph-notepad me-1"></i>Save Note'
					: '<i class="ph-paper-plane-tilt me-1"></i>Send Reply'
			);
			if (data.success) {
				$('#reply-status-msg').text('âœ“ ' + data.message).addClass('text-success');
				$('#reply-body').val('');
				// Reload thread
				loadInquiryThread(inquiryId);
				// Refresh status badge
				$('#modal-inquiry-status').html(statusLabels[2]);
				// Reload datatable row
				$('#grid-inquiries').DataTable().ajax.reload(null, false);
			} else {
				$('#reply-status-msg').text(data.message || 'Error sending.').addClass('text-danger');
			}
		})
		.catch(() => {
			$btn.prop('disabled', false);
			$('#reply-status-msg').text('Network error. Try again.').addClass('text-danger');
		});
	});

	// -------------------------------------------------------
	// Attach view-inquiry-btn listeners
	// -------------------------------------------------------
	function attachViewListeners() {
		$(document).off('click.viewInquiry', '.view-inquiry-btn');
		$(document).on('click.viewInquiry', '.view-inquiry-btn', function(e) {
			e.preventDefault();
			openInquiryModal(this);
		});
	}
	attachViewListeners();

	// -------------------------------------------------------
	// Bulk selection tracking
	// -------------------------------------------------------
	let selectedIds = new Set();
	const selectAllCheckbox = document.getElementById('select-all');
	const bulkDeleteBtn     = document.getElementById('bulk-delete-btn');
	const deleteCountSpan   = document.getElementById('delete-count');

	function updateBulkDeleteButton() {
		if (selectedIds.size > 0) {
			bulkDeleteBtn.style.display = 'inline-block';
			deleteCountSpan.textContent = 'Delete Selected (' + selectedIds.size + ')';
		} else {
			bulkDeleteBtn.style.display = 'none';
			deleteCountSpan.textContent = 'Delete Selected';
		}
	}

	if (selectAllCheckbox) {
		selectAllCheckbox.addEventListener('change', function() {
			document.querySelectorAll('.inquiry-checkbox').forEach(function(cb) {
				cb.checked = selectAllCheckbox.checked;
				if (selectAllCheckbox.checked) selectedIds.add(cb.getAttribute('data-id'));
				else selectedIds.delete(cb.getAttribute('data-id'));
			});
			updateBulkDeleteButton();
		});
	}

	function attachCheckboxListeners() {
		document.querySelectorAll('.inquiry-checkbox').forEach(function(cb) {
			cb.addEventListener('change', function() {
				if (this.checked) selectedIds.add(this.getAttribute('data-id'));
				else {
					selectedIds.delete(this.getAttribute('data-id'));
					if (selectAllCheckbox) selectAllCheckbox.checked = false;
				}
				updateBulkDeleteButton();
			});
		});
	}

	if (bulkDeleteBtn) {
		bulkDeleteBtn.addEventListener('click', function() {
			if (selectedIds.size === 0) { alert('Select at least one inquiry.'); return; }
			if (!confirm('Delete ' + selectedIds.size + ' inquiry(ies)? This cannot be undone.')) return;
			const csrfToken = document.querySelector('input[name="csrf_token"]').value;
			fetch('listing_inquiries.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=bulk_delete_inquiries&ids=' + JSON.stringify(Array.from(selectedIds)) + '&csrf_token=' + encodeURIComponent(csrfToken)
			})
			.then(() => {
				selectedIds.clear();
				if (selectAllCheckbox) selectAllCheckbox.checked = false;
				updateBulkDeleteButton();
				$('#grid-inquiries').DataTable().ajax.reload();
			})
			.catch(err => { console.error(err); alert('Error deleting.'); });
		});
	}

	// -------------------------------------------------------
	// Read/unread toggle
	// -------------------------------------------------------
	function attachToggleListeners() {
		$(document).off('click.toggleRead', '.toggle-read-status');
		$(document).on('click.toggleRead', '.toggle-read-status', function(e) {
			e.preventDefault();
			const inquiryId = this.getAttribute('data-id');
			const newStatus = this.getAttribute('data-status');
			const csrfToken = document.querySelector('input[name="csrf_token"]').value;
			fetch('listing_inquiries.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: 'action=update_inquiries&id=' + inquiryId + '&status=' + newStatus + '&csrf_token=' + encodeURIComponent(csrfToken)
			}).then(() => $('#grid-inquiries').DataTable().ajax.reload(null, false))
			.catch(err => console.error(err));
		});
	}

	// Initial attach
	attachCheckboxListeners();
	attachToggleListeners();

	// Re-attach after DataTable redraws
	$('#grid-inquiries').on('draw.dt', function() {
		attachCheckboxListeners();
		attachToggleListeners();
	});

	// Click any cell in row to open modal
	$(document).on('click', '#grid-inquiries tbody td', function(e) {
		if ($(e.target).closest('a, button, input, select, textarea').length) return;
		const $viewBtn = $(this).closest('tr').find('.view-inquiry-btn').first();
		if ($viewBtn.length) $viewBtn.trigger('click');
	});
});
// ========================================
// Single Delete Handler (Trash Icon)
// ========================================
$(document).on('click', '[data-action="delete_record"]', function(e) {
	e.preventDefault();
	var id = $(this).data('id');
	var module = $(this).data('module');
	if (!id || !module) return;
	if (confirm('Are you sure you want to delete this inquiry? This cannot be undone.')) {
		// Create and submit a form
		var form = $('<form>', {
			'method': 'POST',
			'action': 'listing_' + module + '.php'
		})
		.append($('<input>', {
			'type': 'hidden',
			'name': 'action',
			'value': 'delete_' + module
		}))
		.append($('<input>', {
			'type': 'hidden',
			'name': 'id',
			'value': id
		}))
		.append($('input[name="csrf_token"]').first().clone());
		$('body').append(form);
		form.submit();
	}
});
</script>

<?php include('admin_elements/admin_footer.php'); ?>





