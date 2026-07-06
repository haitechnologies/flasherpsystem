<?php

/**
 * EmailHistoryDataTable Handler
 *
 * Email send history tracking with open/click monitoring
 * Complex: Campaign join, status badges, tracking icons
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class EmailHistoryDataTable extends BaseDataTable
{
    protected $table = DB::EMAIL_HISTORY;
    protected $searchFields = ['recipient_email', 'status', 'from_name', 'from_email', 'subject'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'recipient_email', 2 => 'status',
        3 => 'sent_at', 4 => 'created_at'
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
        $recipientEmail = $this->sanitize($row['recipient_email'] ?? '');
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

        return [
            $this->rowNumber,
            '<a href="mailto:' . htmlspecialchars($recipientEmail) . '">' . htmlspecialchars($recipientEmail) . '</a>',
            $statusBadge,
            !empty($sentAt) ? $this->formatTimeAgo($sentAt) : '-',
            $this->formatTimeAgo($createdAt)
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
