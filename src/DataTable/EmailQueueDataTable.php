<?php

/**
 * EmailQueueDataTable Handler
 * Enhanced to show test emails and full email details
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class EmailQueueDataTable extends BaseDataTable
{
    protected $table = DB::EMAIL_QUEUE;
    protected $searchFields = ['recipient', 'recipient_email', 'subject', 'status', 'failed_reason', 'headers'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'recipient', 2 => 'subject', 3 => 'status',
        4 => 'provider_id', 5 => 'created_at', 6 => 'sent_at', 7 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT id, recipient, recipient_email, subject, status, provider_id, headers,
                       priority, retries, max_retries, failed_reason, created_at, sent_at, updated_at
                FROM `" . $this->table . "`";
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $providerIds = [];
        foreach ($rows as $row) {
            $pid = (int)($row['provider_id'] ?? 0);
            if ($pid > 0) {
                $providerIds[$pid] = true;
            }
        }

        if (empty($providerIds)) {
            return;
        }

        $ids = implode(',', array_map('intval', array_keys($providerIds)));
        try {
            $providerRows = $this->db->fetchAll(
                "SELECT id, provider_name FROM `" . DB::EMAIL_PROVIDERS . "` WHERE id IN (" . $ids . ")"
            );
            foreach ($providerRows as $row) {
                $this->relatedDataCache[(int)$row['id']] = $row['provider_name'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("EmailQueueDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {

        $id = (int)$row['id'];
        $recipientEmail = trim((string)($row['recipient_email'] ?? ''));
        $recipientFallback = trim((string)($row['recipient'] ?? ''));
        $recipientRaw = $recipientEmail !== '' ? $recipientEmail : $recipientFallback;
        $recipient = htmlspecialchars($recipientRaw);
        $subject = htmlspecialchars($row['subject'] ?? 'No Subject');
        $status = $row['status'] ?? 'queued';
        $providerId = (int)($row['provider_id'] ?? 0);
        $headersJson = $row['headers'] ?? '{}';
        $retries = (int)($row['retries'] ?? 0);
        $maxRetries = (int)($row['max_retries'] ?? 0);
        $failedReason = trim((string)($row['failed_reason'] ?? ''));
        $createdAt = $row['created_at'] ?? '';
        $sentAtRaw = $row['sent_at'] ?? '';

        // Parse headers to check if test email and extract sender/reply information.
        $headers = json_decode($headersJson, true) ?? [];
        $isTestEmail = isset($headers['test_email']) && $headers['test_email'] === true;
        $replyToEmail = trim((string)($headers['Reply-To'] ?? $headers['reply_to'] ?? $headers['from_email'] ?? ''));

        if ($replyToEmail !== '' && strcasecmp($replyToEmail, $recipientRaw) !== 0) {
            $recipient .= '<div class="small text-muted mt-1">Reply-To: ' . htmlspecialchars($replyToEmail) . '</div>';
        }

        // Status badge
        $statusBadge = match ($status) {
            'pending' => '<span class="badge bg-warning bg-opacity-20 text-warning">Pending</span>',
            'retry' => '<span class="badge bg-warning bg-opacity-20 text-warning">Retry</span>',
            'sent' => '<span class="badge bg-success bg-opacity-20 text-success">Sent</span>',
            'failed' => '<span class="badge bg-danger bg-opacity-20 text-danger">Failed</span>',
            'queued' => '<span class="badge bg-info bg-opacity-20 text-info">Queued</span>',
            default => '<span class="badge bg-secondary bg-opacity-20 text-secondary">' . htmlspecialchars(ucfirst($status)) . '</span>'
        };

        // Add test email badge
        if ($isTestEmail) {
            $statusBadge .= ' <span class="badge bg-primary" title="Test Email">TEST</span>';
        }

        if ($maxRetries > 0) {
            $statusBadge .= '<div class="small text-muted mt-1">Try: ' . $retries . '/' . $maxRetries . '</div>';
        }

        if ($failedReason !== '') {
            $statusBadge .= '<div class="small text-danger mt-1" title="' . htmlspecialchars($failedReason) . '">'
                . htmlspecialchars(strlen($failedReason) > 45 ? substr($failedReason, 0, 45) . '...' : $failedReason)
                . '</div>';
        }

        // Get provider name
        $providerName = $providerId > 0
            ? htmlspecialchars($this->relatedDataCache[$providerId] ?? ('#' . $providerId))
            : '-';

        // Truncate subject if too long
        $subjectDisplay = strlen($subject) > 50 ? substr($subject, 0, 50) . '...' : $subject;

        $sentAt = !empty($sentAtRaw) ? $this->formatTimeAgo($sentAtRaw) : '-';

        $sendBtn = '';
        if (in_array($status, ['pending', 'retry', 'queued', 'failed'], true) && $this->isGranted('edit', 'email_queue')) {
            $sendBtn = '<a href="#" data-action="send_now_record" data-id="' . $id . '" class="action-btn text-primary" title="Send Now"><i class="ph-paper-plane-tilt"></i></a>';
        }

        $deleteBtn = '';
        if ($status === 'pending' && $this->isGranted('delete', 'email_queue')) {
            $deleteBtn = '<a href="#" data-action="delete_record" data-id="' . $id . '" class="action-btn action-delete text-danger" title="Delete"><i class="ph-trash"></i></a>';
        }

        return [
            $this->rowNumber,
            $recipient,
            $subjectDisplay,
            $statusBadge,
            $providerName,
            $this->formatTimeAgo($createdAt),
            $sentAt,
            $sendBtn . $deleteBtn
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }
}
