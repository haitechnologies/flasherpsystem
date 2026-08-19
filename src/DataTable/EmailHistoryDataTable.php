<?php

/**
 * EmailHistoryDataTable Handler
 *
 * Email send history tracking with module/subject/cc/bcc/body/attachment
 * details and a view-details popup.
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class EmailHistoryDataTable extends BaseDataTable
{
    protected $table = DB::EMAIL_HISTORY;
    protected $searchFields = ['recipient_email', 'status', 'from_name', 'from_email', 'subject', 'module', 'cc', 'bcc'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'module', 2 => 'recipient_email', 3 => 'subject',
        4 => 'cc', 5 => 'attachment', 6 => 'status', 7 => 'sent_at', 8 => 'created_at'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT eh.*
                FROM `" . $this->table . "` eh
                WHERE eh.id > 0";
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        // Decommissioned campaign prefetching
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $module = $this->sanitize($row['module'] ?? '');
        $recipientEmail = $this->sanitize($row['recipient_email'] ?? '');
        $subject = $this->sanitize($row['subject'] ?? '');
        $cc = $this->sanitize($row['cc'] ?? '');
        $attachment = $this->sanitize($row['attachment'] ?? '');
        $status = $this->sanitize($row['status'] ?? '');
        $sentAt = $row['sent_at'] ?? '';
        $createdAt = $row['created_at'] ?? '';

        $statusBadge = match ($status) {
            'queued' => '<span class="badge bg-secondary bg-opacity-20 text-secondary">Queued</span>',
            'sent' => '<span class="badge bg-success bg-opacity-20 text-success">Sent</span>',
            'failed' => '<span class="badge bg-danger bg-opacity-20 text-danger">Failed</span>',
            'bounced' => '<span class="badge bg-warning bg-opacity-20 text-warning">Bounced</span>',
            'unsubscribed' => '<span class="badge bg-info bg-opacity-20 text-info">Unsubscribed</span>',
            default => '<span class="badge bg-secondary bg-opacity-20 text-secondary">' . ucfirst($status) . '</span>'
        };

        $moduleBadge = $module !== '' ? '<span class="badge bg-light text-dark border">' . htmlspecialchars(ucwords(str_replace('_', ' ', $module))) . '</span>' : '-';

        $subjectShort = $subject !== '' ? htmlspecialchars(mb_strimwidth($subject, 0, 60, '...')) : '-';

        $viewBtn = '<a href="javascript:void(0);" class="btn btn-sm btn-light" data-action="view-email-details" data-id="' . $id . '" title="View Details"><i class="ph-eye"></i></a>';

        return [
            $this->rowNumber,
            $moduleBadge,
            '<a href="mailto:' . htmlspecialchars($recipientEmail) . '">' . htmlspecialchars($recipientEmail) . '</a>',
            $subjectShort,
            $cc !== '' ? htmlspecialchars(mb_strimwidth($cc, 0, 40, '...')) : '-',
            $attachment !== '' ? '<i class="ph-paperclip me-1"></i>' . htmlspecialchars($attachment) : '-',
            $statusBadge,
            !empty($sentAt) ? $this->formatTimeAgo($sentAt) : '-',
            $this->formatTimeAgo($createdAt),
            $viewBtn,
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? count($this->sortableColumns) - 2);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }
}
