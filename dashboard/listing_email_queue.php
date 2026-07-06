<?php


use App\Core\DB;
use App\Core\DeletionManager;
use App\Service\SMTPMailer;
include('admin_elements/admin_header.php');
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/EmailQueue.php';
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/EmailProviderManager.php';
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/SMTPMailer.php';

// SMTPMailer resolves DB-backed provider through $GLOBALS['conn'].
if (!isset($GLOBALS['conn']) || !($GLOBALS['conn'] instanceof mysqli)) {
    $GLOBALS['conn'] = $mysqli;
}

$module = 'email_queue';
$module_id = getModuleIdBySlug($module, $mysqli);
$module_caption = 'Email Queue';
$tbl_name = DB::EMAIL_QUEUE;
$error_message = '';
$success_message = '';
$hide_add_button = true;

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| PROCESS QUEUE NOW (MANUAL)
|--------------------------------------------------------------------------
*/
if (($action == "process_{$module}_now") && granted('edit', $module_id)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $batchLimit = (int)($_POST['batch_limit'] ?? 25);
        if ($batchLimit < 1) {
            $batchLimit = 1;
        }
        if ($batchLimit > 100) {
            $batchLimit = 100;
        }

        $beforePending = 0;
        $beforeSent = 0;

        $beforeResult = $mysqli->query(
            "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_cnt
             FROM `" . DB::EMAIL_QUEUE . "`"
        );
        if ($beforeResult && ($beforeRow = $beforeResult->fetch_assoc())) {
            $beforePending = (int)($beforeRow['pending_cnt'] ?? 0);
            $beforeSent = (int)($beforeRow['sent_cnt'] ?? 0);
        }

        // Move eligible retry/queued records into pending so manual processing can pick them up.
        $mysqli->query(
            "UPDATE `" . DB::EMAIL_QUEUE . "`
             SET status = 'pending'
             WHERE status IN ('queued', 'retry')
               AND (next_retry_at IS NULL OR next_retry_at <= NOW())"
        );

        $queue = new EmailQueue();
        $sentNow = (int)$queue->processPending($batchLimit);

        $afterPending = 0;
        $afterSent = 0;
        $afterResult = $mysqli->query(
            "SELECT 
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent_cnt
             FROM `" . DB::EMAIL_QUEUE . "`"
        );
        if ($afterResult && ($afterRow = $afterResult->fetch_assoc())) {
            $afterPending = (int)($afterRow['pending_cnt'] ?? 0);
            $afterSent = (int)($afterRow['sent_cnt'] ?? 0);
        }

        $success_message = sprintf(
            'Queue processed. Sent now: %d (pending: %d -> %d, sent: %d -> %d).',
            $sentNow,
            $beforePending,
            $afterPending,
            $beforeSent,
            $afterSent
        );
    }
}

/*
|--------------------------------------------------------------------------
| SEND SINGLE QUEUE ITEM NOW (MANUAL)
|--------------------------------------------------------------------------
*/
if (($action == "send_now_{$module}" && !empty($id)) && granted('edit', $module_id)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $queueId = (int)$id;
        $stmt = $mysqli->prepare(
            "SELECT id, status, recipient_email, recipient, subject, body, headers, retries, max_retries
             FROM `" . DB::EMAIL_QUEUE . "`
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            $error_message = 'Unable to prepare queue lookup.';
        } else {
            $stmt->bind_param('i', $queueId);
            $stmt->execute();
            $queueResult = $stmt->get_result();
            $queueRow = $queueResult ? $queueResult->fetch_assoc() : null;
            $stmt->close();

            if (!$queueRow) {
                $error_message = 'Queue item not found.';
            } else {
                $status = strtolower(trim((string)($queueRow['status'] ?? '')));
                if (!in_array($status, ['pending', 'retry', 'queued', 'failed'], true)) {
                    $error_message = 'Only pending/retry/queued/failed emails can be sent manually.';
                } else {
                    $to = trim((string)($queueRow['recipient_email'] ?? ''));
                    if ($to === '') {
                        $to = trim((string)($queueRow['recipient'] ?? ''));
                    }

                    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                        $error_message = 'Invalid recipient email for this queue record.';
                    } else {
                        $headers = [];
                        if (!empty($queueRow['headers'])) {
                            $decoded = json_decode((string)$queueRow['headers'], true);
                            if (is_array($decoded)) {
                                $headers = $decoded;
                            }
                        }

                        $subject = (string)($queueRow['subject'] ?? 'Email');
                        $body = (string)($queueRow['body'] ?? '');

                        try {
                            $mailer = new SMTPMailer();
                            $sent = (bool)$mailer->send($to, $subject, $body, $headers);

                            if ($sent) {
                                $updateStmt = $mysqli->prepare(
                                    "UPDATE `" . DB::EMAIL_QUEUE . "`
                                     SET status = 'sent', sent_at = NOW(), updated_at = NOW(), failed_reason = NULL
                                     WHERE id = ?"
                                );
                                if ($updateStmt) {
                                    $updateStmt->bind_param('i', $queueId);
                                    $updateStmt->execute();
                                    $updateStmt->close();
                                }
                                $success_message = 'Queue item #' . $queueId . ' sent successfully.';
                            } else {
                                $retries = (int)($queueRow['retries'] ?? 0) + 1;
                                $maxRetries = (int)($queueRow['max_retries'] ?? 3);
                                if ($maxRetries <= 0) {
                                    $maxRetries = 3;
                                }
                                $nextStatus = $retries >= $maxRetries ? 'failed' : 'retry';

                                $lastError = method_exists($mailer, 'getLastError')
                                    ? (string)$mailer->getLastError()
                                    : 'Manual send failed';

                                $updateStmt = $mysqli->prepare(
                                    "UPDATE `" . DB::EMAIL_QUEUE . "`
                                     SET status = ?, retries = ?, attempts = ?, failed_reason = ?, updated_at = NOW()
                                     WHERE id = ?"
                                );
                                if ($updateStmt) {
                                    $updateStmt->bind_param('siisi', $nextStatus, $retries, $retries, $lastError, $queueId);
                                    $updateStmt->execute();
                                    $updateStmt->close();
                                }

                                $error_message = 'Manual send failed for queue item #' . $queueId . '.';
                            }
                        } catch (Throwable $e) {
                            $retries = (int)($queueRow['retries'] ?? 0) + 1;
                            $maxRetries = (int)($queueRow['max_retries'] ?? 3);
                            if ($maxRetries <= 0) {
                                $maxRetries = 3;
                            }
                            $nextStatus = $retries >= $maxRetries ? 'failed' : 'retry';

                            $lastError = substr((string)$e->getMessage(), 0, 1000);
                            $updateStmt = $mysqli->prepare(
                                "UPDATE `" . DB::EMAIL_QUEUE . "`
                                 SET status = ?, retries = ?, attempts = ?, failed_reason = ?, updated_at = NOW()
                                 WHERE id = ?"
                            );
                            if ($updateStmt) {
                                $updateStmt->bind_param('siisi', $nextStatus, $retries, $retries, $lastError, $queueId);
                                $updateStmt->execute();
                                $updateStmt->close();
                            }

                            $error_message = 'Manual send exception for queue item #' . $queueId . '.';
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid CSRF token.';
    } else {
        $statusStmt = $mysqli->prepare("SELECT status FROM `" . DB::EMAIL_QUEUE . "` WHERE id = ? LIMIT 1");
        if (!$statusStmt) {
            $error_message = 'Unable to validate queue status right now.';
        } else {
            $statusStmt->bind_param('i', $id);
            $statusStmt->execute();
            $statusResult = $statusStmt->get_result();
            $statusRow = $statusResult ? $statusResult->fetch_assoc() : null;
            $statusStmt->close();

            $queueStatus = strtolower(trim((string)($statusRow['status'] ?? '')));
            if ($queueStatus !== 'pending') {
                $error_message = 'Only pending email queue records can be deleted.';
            } else {
                $result = DeletionManager::delete(
                    $tbl_name,
                    $id,
                    Session::userId(),
                    ['item_label' => 'Email Queue Entry', 'module_slug' => 'email_queue']
                );
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
            }
        }
    }
}

$queueStats = [
    'pending' => 0,
    'queued' => 0,
    'retry' => 0,
    'sent' => 0,
    'failed' => 0,
    'total' => 0
];

$statsResult = $mysqli->query("SELECT status, COUNT(*) AS cnt FROM `" . DB::EMAIL_QUEUE . "` GROUP BY status");
if ($statsResult) {
    while ($statsRow = $statsResult->fetch_assoc()) {
        $statusKey = strtolower((string)$statsRow['status']);
        $count = (int)$statsRow['cnt'];
        if (array_key_exists($statusKey, $queueStats)) {
            $queueStats[$statusKey] = $count;
        }
        $queueStats['total'] += $count;
    }
}

$lastSentAt = '-';
$lastSentResult = $mysqli->query("SELECT MAX(sent_at) AS last_sent_at FROM `" . DB::EMAIL_QUEUE . "` WHERE sent_at IS NOT NULL");
if ($lastSentResult && ($lastSentRow = $lastSentResult->fetch_assoc()) && !empty($lastSentRow['last_sent_at'])) {
    $lastSentAt = dd_($lastSentRow['last_sent_at']);
}

?>

<div class="content-wrapper">
	<?php if (!empty($visibleEmailLinks) && $isEmailRelatedPage && function_exists('renderEmailQuickbar')): ?>
		<?php renderEmailQuickbar($visibleEmailLinks, $current_page); ?>
	<?php endif; ?>

        <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content datatable-enhanced">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="alert alert-info mb-3">
                    <strong>Queue Health:</strong>
                    Total: <?php echo (int)$queueStats['total']; ?> |
                    Pending: <?php echo (int)$queueStats['pending']; ?> |
                    Retry: <?php echo (int)$queueStats['retry']; ?> |
                    Sent: <?php echo (int)$queueStats['sent']; ?> |
                    Failed: <?php echo (int)$queueStats['failed']; ?> |
                    Last Sent: <?php echo htmlspecialchars($lastSentAt); ?>
                    <br>
                    <small>If pending emails stay unchanged, run: <code>php dashboard/cron/CronJobScheduler.php --job=email:queue</code></small>
                    <?php if (granted('edit', $module_id)): ?>
                        <div class="mt-3">
                            <form method="POST" action="listing_<?php echo $module; ?>.php" class="d-flex align-items-center gap-2 flex-wrap">
                                <input type="hidden" name="action" value="process_<?php echo $module; ?>_now">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                <label for="batchLimit" class="mb-0 small text-muted">Batch:</label>
                                <select id="batchLimit" name="batch_limit" class="form-select form-select-sm" style="width:auto;">
                                    <option value="10">10</option>
                                    <option value="25" selected>25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('Process queued emails now?');">
                                    <i class="ph-paper-plane-tilt me-1"></i>Process Queue Now
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="custom_datatables datatable-professional display responsive no-wrap table-hover" width="100%">
                    <thead>
                        <tr>
                            <th width="60">ID</th>
                            <th>RECIPIENT</th>
                            <th>SUBJECT</th>
                            <th width="120">STATUS</th>
                            <th width="120">PROVIDER</th>
                            <th width="120">CREATED</th>
                            <th width="120">SENT AT</th>
                            <th width="70">ACTIONS</th>
                        </tr>
                    </thead>
                </table>
</div>
            </div>
        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<script>
$(document).ready(function() {
    var tableSelector = '#grid-<?php echo $module; ?>';

    window.HAIDatatableInitializer.init(tableSelector, '<?php echo $module; ?>', {
        stateSave: false,
        deferRender: true,
        retrieve: false,
        pageLength: 10,
        dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        ajax: {
            data: function(d) {
                d.edit_permission = <?php echo granted('edit', $module_id) ? '1' : '0'; ?>;
                d.delete_permission = <?php echo granted('delete', $module_id) ? '1' : '0'; ?>;
                return d;
            },
            error: function() {
                $('.grid-error').html('');
                $(tableSelector).append('<tbody class="grid-error"><tr><th colspan="8">No Results Found.</th></tr></tbody>');
                $(tableSelector + '_processing').hide();
            }
        },
        columns: [
            { data: 0 },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7, orderable: false }
        ],
        order: [[0, 'desc']]
    });

    $(tableSelector + ' tbody').on('click', 'tr', function(e) {
        if ($(e.target).closest('a, button, .dropdown, .dropdown-menu').length > 0) {
            return;
        }

        var dt = $(tableSelector).DataTable();
        var rowData = dt.row(this).data();
        if (!rowData || typeof rowData[0] === 'undefined') {
            return;
        }

        var id = parseInt($('<div>').html(String(rowData[0])).text(), 10);
        if (!id || Number.isNaN(id)) {
            return;
        }

        viewEmailDetails(id);
    });

    $(document).on('click', '[data-action="delete_record"]', function(e) {
        e.preventDefault();
        if (confirm('Delete this item?')) {
            var csrfToken = window.HAI_CSRF_TOKEN || $('input[name="csrf_token"]').first().val() || '';
            var form = $('<form>', { 'method': 'POST', 'action': 'listing_<?php echo $module; ?>.php' })
                .append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'delete_<?php echo $module; ?>' }))
                .append($('<input>', { 'type': 'hidden', 'name': 'id', 'value': $(this).data('id') }))
                .append($('<input>', { 'type': 'hidden', 'name': 'csrf_token', 'value': csrfToken }));
            $('body').append(form);
            form.submit();
        }
    });

    $(document).on('click', '[data-action="send_now_record"]', function(e) {
        e.preventDefault();
        var $btn = $(this);
        if ($btn.data('locked') === true) {
            return;
        }
        if (confirm('Send this queued email now?')) {
            $btn.data('locked', true);
            $btn.addClass('disabled').attr('aria-disabled', 'true');
            setTimeout(function() {
                $btn.data('locked', false);
                $btn.removeClass('disabled').removeAttr('aria-disabled');
            }, 3000);

            var csrfToken = window.HAI_CSRF_TOKEN || $('input[name="csrf_token"]').first().val() || '';
            var form = $('<form>', { 'method': 'POST', 'action': 'listing_<?php echo $module; ?>.php' })
                .append($('<input>', { 'type': 'hidden', 'name': 'action', 'value': 'send_now_<?php echo $module; ?>' }))
                .append($('<input>', { 'type': 'hidden', 'name': 'id', 'value': $(this).data('id') }))
                .append($('<input>', { 'type': 'hidden', 'name': 'csrf_token', 'value': csrfToken }));
            $('body').append(form);
            form.submit();
        }
    });
});

// View email details function
function viewEmailDetails(id) {
    var csrfToken = window.HAI_CSRF_TOKEN || $('input[name="csrf_token"]').first().val() || '';
    $('#emailQueueDetailsBody').html('<div class="text-center py-4"><i class="ph-spinner ph-spin"></i> Loading details...</div>');
    var modalEl = document.getElementById('emailQueueDetailsModal');
    if (modalEl) {
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }

    $.ajax({
        url: 'ajax_email_queue_details.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: id,
            csrf_token: csrfToken
        },
        success: function(response) {
            if (response && response.success) {
                $('#emailQueueDetailsBody').html(response.html || '<div class="alert alert-warning">No details available.</div>');
            } else {
                $('#emailQueueDetailsBody').html('<div class="alert alert-danger">' + (response.message || 'Unable to load details.') + '</div>');
            }
        },
        error: function(xhr) {
            var msg = 'Failed to load queue details.';
            if (xhr && xhr.responseText) {
                msg += '<br><small>' + $('<div/>').text(xhr.responseText).html().substring(0, 300) + '</small>';
            }
            $('#emailQueueDetailsBody').html('<div class="alert alert-danger">' + msg + '</div>');
        }
    });
}
</script>

<div class="modal fade" id="emailQueueDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ph-info me-2"></i>Email Queue Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="emailQueueDetailsBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>



