<?php

/**
 * EmailProvidersDataTable Handler
 * Displays email providers with encryption status
 * Passwords are encrypted with AES-256-CBC and never shown in the table
 */

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class EmailProvidersDataTable extends BaseDataTable
{
    private const DEFAULT_DAILY_LIMIT = 100;
    private const DEFAULT_HOURLY_LIMIT = 50;

    protected $table = DB::EMAIL_PROVIDERS;
    protected $searchFields = ['provider_name', 'email', 'smtp_host'];
    protected $sortableColumns = [
        0 => 'provider_name', 1 => 'email', 2 => 'created_at', 3 => 'id'
    ];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $providerIds = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $providerIds[] = $id;
            }
        }

        $providerIds = array_values(array_unique($providerIds));
        $this->relatedDataCache['daily_sent'] = [];

        if (empty($providerIds)) {
            return;
        }

        $idList = implode(',', $providerIds);
        $query = "SELECT provider_id, COUNT(*) AS cnt
                  FROM `" . DB::EMAIL_HISTORY . "`
                  WHERE provider_id IN (" . $idList . ")
                    AND status = 'sent'
                    AND DATE(COALESCE(sent_at, created_at)) = CURDATE()
                  GROUP BY provider_id";

        try {
            $items = $this->db->fetchAll($query);
            foreach ($items as $item) {
                $pid = (int)($item['provider_id'] ?? 0);
                $cnt = (int)($item['cnt'] ?? 0);
                if ($pid > 0) {
                    $this->relatedDataCache['daily_sent'][$pid] = $cnt;
                }
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("EmailProvidersDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {

        $id = (int)$row['id'];
        $providerName = $row['provider_name'] ?? '';
        $email = $row['email'] ?? '';
        $smtpHost = $row['smtp_host'] ?? '';
        $smtpPort = $row['smtp_port'] ?? '587';
        $encryption = strtoupper($row['email_encryption'] ?? 'TLS');
        $isActive = (int)$row['is_active'];
        $isPrimary = (int)($row['is_primary'] ?? 0);
        $isEncrypted = !empty($row['smtp_password_encrypted']);
        $dailyLimitRaw = (int)($row['daily_limit'] ?? 0);
        $perHourLimitRaw = (int)($row['per_hour_limit'] ?? 0);
        $dailyLimit = $dailyLimitRaw > 0 ? $dailyLimitRaw : self::DEFAULT_DAILY_LIMIT;
        $perHourLimit = $perHourLimitRaw > 0 ? $perHourLimitRaw : self::DEFAULT_HOURLY_LIMIT;
        $usesDefaultLimits = ($dailyLimitRaw <= 0 && $perHourLimitRaw <= 0);
        $createdAt = $row['created_at'] ?? '';
        $sentToday = (int)($this->relatedDataCache['daily_sent'][$id] ?? 0);
        $remainingToday = max(0, $dailyLimit - $sentToday);
        $usedPercent = (int)min(100, round(($sentToday / max(1, $dailyLimit)) * 100));
        $remainingPercent = max(0, 100 - $usedPercent);
        $usageClass = 'limit-healthy';
        $usageLabel = 'Healthy';
        if ($remainingPercent <= 10) {
            $usageClass = 'limit-critical';
            $usageLabel = 'Critical';
        } elseif ($remainingPercent <= 30) {
            $usageClass = 'limit-warning';
            $usageLabel = 'Warning';
        }

        // Provider name with badges
        $providerDisplay = '<div class="fw-bold">' . htmlspecialchars($providerName) . '</div>';
        if ($isPrimary) {
            $providerDisplay .= '<span class="badge provider-mini-badge bg-primary bg-opacity-10 text-primary border border-primary-subtle mt-1"><i class="ph-star me-1"></i>Primary</span> ';
        }
        if ($isEncrypted) {
            $providerDisplay .= '<span class="badge provider-mini-badge bg-info bg-opacity-10 text-info border border-info-subtle mt-1" title="Password encrypted"><i class="ph-lock me-1"></i>Encrypted</span>';
        }

        // Email address (clickable mailto)
        $emailDisplay = '<a href="mailto:' . htmlspecialchars($email) . '" class="text-decoration-none">' . htmlspecialchars($email) . '</a>';

        // SMTP Configuration details
        $smtpDetails = '<div class="text-muted small">';
        $smtpDetails .= '<i class="ph-envelope-simple me-1"></i><strong>Host:</strong> ' . htmlspecialchars($smtpHost) . '<br>';
        $smtpDetails .= '<i class="ph-plugs-connected me-1"></i><strong>Port:</strong> ' . htmlspecialchars($smtpPort) . ' | <strong>Encryption:</strong> ' . $encryption . '<br>';
        $smtpDetails .= '<i class="ph-gauge me-1"></i><strong>Limits:</strong> ';
        $smtpDetails .= $dailyLimit . '/day ' . $perHourLimit . '/hour';
        if ($usesDefaultLimits) {
            $smtpDetails .= ' <span class="text-secondary">(standard)</span>';
        }
        $smtpDetails .= '</div>';

        // Daily usage details with compact mini candle chart
        $usageDetails = '<div class="text-muted small email-usage-wrap ' . $usageClass . '">';
        $usageDetails .= '<div><i class="ph-calendar-check me-1"></i><strong>Sent today:</strong> ' . number_format($sentToday) . '</div>';
        $usageDetails .= '<div><i class="ph-gauge me-1"></i><strong>Daily limit:</strong> ' . number_format($dailyLimit);
        if ($usesDefaultLimits) {
            $usageDetails .= ' <span class="text-secondary">(standard)</span>';
        }
        $usageDetails .= '</div>';
        $usageDetails .= '<div><i class="ph-hourglass me-1"></i><strong>Remaining:</strong> ' . number_format((int)$remainingToday) . '</div>';
        $usageDetails .= '<div class="mini-candle-label">Used ' . $usedPercent . '% / Left ' . $remainingPercent . '% <span class="usage-state">' . $usageLabel . '</span></div>';
        $usageDetails .= '</div>';

        $usageGraph = '<div class="email-usage-wrap ' . $usageClass . '">';
        $usageGraph .= '<div class="mini-candle-graph" aria-label="Daily email usage chart">';
        $usageGraph .= '<span class="mini-candle used" style="height:' . max(8, $usedPercent) . '%" title="Used: ' . $usedPercent . '%"></span>';
        $usageGraph .= '<span class="mini-candle remaining" style="height:' . max(8, $remainingPercent) . '%" title="Remaining: ' . $remainingPercent . '%"></span>';
        $usageGraph .= '</div>';
        $usageGraph .= '</div>';

        // Status badge with icon
        $statusBadge = $isActive
            ? '<span class="badge bg-success"><i class="ph-check-circle me-1"></i>Active</span>'
            : '<span class="badge bg-secondary"><i class="ph-x-circle me-1"></i>Inactive</span>';

        // Get total provider count and check if this is the only primary
        $totalProviders = 0;
        try {
            $totalProvidersResult = $this->db->fetchOne("SELECT COUNT(*) as cnt FROM `" . DB::EMAIL_PROVIDERS . "`");
            $totalProviders = (int)($totalProvidersResult['cnt'] ?? 0);
        } catch (\Throwable $e) {
            ErrorCapture::record("EmailProvidersDataTable error getting count: " . $e->getMessage());
        }
        $canDelete = !$isPrimary && $totalProviders > 1;

        return [
            'provider_name' => $providerDisplay,
            'email' => $emailDisplay,
            'smtp_details' => $smtpDetails,
            'daily_usage' => $usageDetails,
            'daily_usage_graph' => $usageGraph,
            'created_at' => $this->formatTimeAgo($createdAt),
            'status' => $statusBadge,
            'actions' => $this->getActionButtons($id, 'email_providers', $isPrimary, $canDelete)
        ];
    }

    protected function getActionButtons($id, $module, $isPrimary, $canDelete)
    {
        $actions = '';
        // Only show the edit (pencil) icon
        if ($this->isGranted('edit', $module)) {
            $actions .= '<a href="email_providers.php?action=edit_email_providers&id=' . $id . '" title="Edit" class="action-btn action-edit"><i class="ph-pencil"></i></a>';
        }
        return $actions;
    }
}
