<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Core\DB;

if (($_POST['action'] ?? null) === 'get_email_details' && !empty($_POST['id'])) {
    header('Content-Type: application/json; charset=utf-8');
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
        exit;
    }
    $detId = (int)$_POST['id'];
    $stmt = $mysqli->prepare("SELECT * FROM `" . DB::EMAIL_HISTORY . "` WHERE id = ?");
    $stmt->bind_param('i', $detId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row) {
        $attachment_url = '';
        if (!empty($row['module']) && !empty($row['reference_id'])) {
            $pdfPage = \App\Helper\PdfHelper::pageFor((string)$row['module']);
            $pdfIdParam = \App\Helper\PdfHelper::idParamFor((string)$row['module']);
            $pdfToken = hash('sha512', 'bushogai' . (int)$row['reference_id']);
            if ($pdfPage !== '') {
                $attachment_url = rtrim($admin_base_url, '/') . '/' . $pdfPage . '?' . $pdfIdParam . '=' . (int)$row['reference_id'] . '&token=' . $pdfToken;
            }
        }
        echo json_encode(['success' => true, 'data' => $row, 'attachment_url' => $attachment_url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Email history record not found.']);
    }
    exit;
}

include('admin_elements/admin_header.php');

$module = 'email_history';
$module_caption = 'Email History';
$tbl_name = DB::EMAIL_HISTORY;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$detailsModalHtml = '
<div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailsModalLabel">Email Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-loading" class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
                <div id="modal-content" class="d-none">
                    <table class="table table-sm">
                        <tbody>
                            <tr><th class="text-muted" style="width:180px;">Module</th><td id="det-module"></td></tr>
                            <tr><th class="text-muted">Status</th><td id="det-status"></td></tr>
                            <tr><th class="text-muted">From</th><td id="det-from"></td></tr>
                            <tr><th class="text-muted">To</th><td id="det-to"></td></tr>
                            <tr><th class="text-muted">CC</th><td id="det-cc"></td></tr>
                            <tr><th class="text-muted">BCC</th><td id="det-bcc"></td></tr>
                            <tr><th class="text-muted">Subject</th><td id="det-subject"></td></tr>
                            <tr><th class="text-muted">Attachment</th><td id="det-attachment"></td></tr>
                            <tr><th class="text-muted">Sent At</th><td id="det-sent-at"></td></tr>
                        </tbody>
                    </table>
                    <hr />
                    <div class="fw-semibold mb-1">Description / Body</div>
                    <div id="det-body" class="border rounded p-3 bg-light" style="white-space:pre-wrap;"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
';

$listingConfig = [
    'module' => $module,
    'module_caption' => $module_caption,
    'hide_add_button' => true,
    'thead' => '
        <th width="60">ID</th>
        <th>Module</th>
        <th>Recipient</th>
        <th>Subject</th>
        <th>CC</th>
        <th>Attachment</th>
        <th>Status</th>
        <th>Sent At</th>
        <th>Created</th>
        <th width="60" class="text-center">Actions</th>
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
        ['data' => 8],
        ['data' => 9, 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
    'extra_js' => "
        var emailDetailsModal = new bootstrap.Modal(document.getElementById('detailsModal'));

        function showEmailDetails(id) {
            $('#modal-loading').removeClass('d-none');
            $('#modal-content').addClass('d-none');
            emailDetailsModal.show();

            $.ajax({
                url: 'email_history.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'get_email_details',
                    id: id,
                    csrf_token: $('input[name=\"csrf_token\"]').val()
                },
                success: function(response) {
                    if (response.success) {
                        var d = response.data;
                        var module = d.module || '-';
                        module = module.replace(/_/g, ' ');
                        module = module.charAt(0).toUpperCase() + module.slice(1);
                        $('#det-module').text(module);
                        $('#det-status').html(d.status == 'sent' ? '<span class=\"badge bg-success\">Sent</span>' : (d.status || '-'));
                        $('#det-from').text((d.from_name ? d.from_name + ' <' + (d.from_email || '') + '>' : (d.from_email || '-')));
                        $('#det-to').text(d.recipient_email || '-');
                        $('#det-cc').text(d.cc || '-');
                        $('#det-bcc').text(d.bcc || '-');
                        $('#det-subject').text(d.subject || '-');
                        if (d.attachment && response.attachment_url) {
                            $('#det-attachment').html('<a href=\"' + response.attachment_url + '\" target=\"_blank\" class=\"fw-semibold\"><i class=\"ph-paperclip me-1\"></i>' + d.attachment + '</a>');
                        } else {
                            $('#det-attachment').html(d.attachment ? '<i class=\"ph-paperclip me-1\"></i>' + d.attachment : '-');
                        }
                        $('#det-sent-at').text(d.sent_at || '-');
                        var body = (d.body || '');
                        body = body.replace(/\\\\r\\\\n/g, '\\n').replace(/\\\\r/g, '\\n').replace(/\\\\n/g, '\\n').replace(/\\r\\n/g, '\\n').replace(/\\r/g, '\\n');
                        $('#det-body').text(body);
                        $('#modal-loading').addClass('d-none');
                        $('#modal-content').removeClass('d-none');
                    } else {
                        alert(response.message || 'Failed to fetch details.');
                        emailDetailsModal.hide();
                    }
                },
                error: function() {
                    alert('Error fetching details.');
                    emailDetailsModal.hide();
                }
            });
        }

        $(document).on('click', '[data-action=\"view-email-details\"]', function(e) {
            e.preventDefault();
            showEmailDetails($(this).data('id'));
        });

        // Open the details popup when a row is clicked (excluding the actions cell).
        $(document).on('click', '#grid-email_history tbody tr', function(e) {
            if ($(e.target).closest('a, button, .btn').length) {
                return;
            }
            var id = $(this).find('[data-action=\"view-email-details\"]').data('id');
            if (id) {
                showEmailDetails(id);
            }
        });
    ",
];

include('admin_elements/listing_template.php');

echo $detailsModalHtml;

include('admin_elements/admin_footer.php');
