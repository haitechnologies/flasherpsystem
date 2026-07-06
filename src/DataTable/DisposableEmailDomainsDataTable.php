<?php

/**
 * DisposableEmailDomainsDataTable Handler
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class DisposableEmailDomainsDataTable extends BaseDataTable
{
    protected $table = DB::DISPOSABLE_EMAIL_DOMAINS;
    protected $searchFields = ['domain', 'source'];
    protected $sortableColumns = [
        0 => 'id',
        1 => 'domain',
        2 => 'source',
        3 => 'is_disposable',
        4 => 'is_allowlisted',
        5 => 'updated_at',
        6 => 'id', // action – not sortable
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id           = (int)$row['id'];
        $domain       = htmlspecialchars($row['domain'] ?? '', ENT_QUOTES, 'UTF-8');
        $source       = htmlspecialchars($row['source'] ?? '', ENT_QUOTES, 'UTF-8');
        $isDisposable = (int)$row['is_disposable'];
        $isAllowlisted = (int)$row['is_allowlisted'];
        $updatedAt    = $row['updated_at'] ?? '';

        $disposableBadge  = $isDisposable  ? BadgeHelper::danger('Blocked')    : BadgeHelper::success('Clean');
        $allowlistedBadge = $isAllowlisted ? BadgeHelper::success('Allowlisted') : BadgeHelper::secondary('No');

        return [
            $this->rowNumber,
            '<code>' . $domain . '</code>',
            !empty($source) ? '<span class="text-muted small">' . $source . '</span>' : '—',
            $disposableBadge,
            $allowlistedBadge,
            !empty($updatedAt) ? $this->formatTimeAgo($updatedAt) : '—',
            $this->getActionButtons($id, 'disposable_email_domains'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $buttons = [];
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
