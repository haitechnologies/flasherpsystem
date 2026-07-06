<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class LeadQuotationsDataTable extends BaseDataTable
{
    protected $table = DB::QUOTATIONS;
    protected $searchFields = ['quotation_no', 'job_reference_no'];
    protected $sortableColumns = [0 => 'id', 1 => 'quotation_date', 2 => 'quotation_no', 3 => 'job_reference_no', 4 => 'lead_id', 5 => 'quotation_status', 6 => 'grand_total'];

    protected function buildBaseQuery($requestData)
    {
        $leadId = (int)($requestData['lead_id'] ?? 0);
        $base = "SELECT * FROM `" . DB::QUOTATIONS . "` WHERE id > 0" . $this->getOrgIdWhereClause();
        if ($leadId > 0) {
            $base .= " AND lead_id = $leadId";
        }
        return $base;
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $leadIds = array_filter(array_unique(array_map(fn($r) => (int)($r['lead_id'] ?? 0), $rows)));
        $this->relatedDataCache['leads'] = [];
        if ($leadIds) {
            try {
                $lookupRows = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::LEADS . "` WHERE id IN (" . implode(',', $leadIds) . ")");
                foreach ($lookupRows as $row) {
                    $this->relatedDataCache['leads'][(int)$row['id']] = $row['display_name'];
                }
            } catch (\Throwable $e) {
                error_log("LeadQuotationsDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $date     = (string)($row['quotation_date'] ?? '');
        $no       = (string)($row['quotation_no'] ?? '');
        $jobRef   = (string)($row['job_reference_no'] ?? '');
        $leadId   = (int)($row['lead_id'] ?? 0);
        $status   = (string)($row['quotation_status'] ?? '');
        $total    = (string)($row['grand_total'] ?? '0');
        $leadName = $this->relatedDataCache['leads'][$leadId] ?? '';

        $rawStatus = trim($status);
        $normalized = strtolower($rawStatus);
        if (in_array($normalized, ['approved', 'confirmed', 'booked', '2'], true)) {
            $statusBadge = '<span class="badge bg-success">' . htmlspecialchars($status ?: 'N/A') . '</span>';
        } elseif (in_array($normalized, ['pending', 'draft', '1'], true)) {
            $statusBadge = '<span class="badge bg-warning">' . htmlspecialchars($status ?: 'N/A') . '</span>';
        } elseif (in_array($normalized, ['rejected', 'cancelled', 'void', '0'], true)) {
            $statusBadge = '<span class="badge bg-danger">' . htmlspecialchars($status ?: 'N/A') . '</span>';
        } else {
            $statusBadge = '<span class="badge bg-secondary">' . htmlspecialchars($status ?: 'N/A') . '</span>';
        }

        $actions = '';
        if ($this->isGranted('edit', 'quotations')) {
            $actions .= ActionButtonHelper::editButton($id, 'quotations.php' . '&lead_id=' . $leadId, 'quotations', 'Edit', false);
        }
        if ($this->isGranted('delete', 'quotations')) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, 'quotations');
        }

        return [
            $this->rowNumber,
            htmlspecialchars($date),
            htmlspecialchars($no),
            htmlspecialchars($jobRef),
            htmlspecialchars($leadName),
            $statusBadge,
            'AED' . htmlspecialchars(number_format((float)$total, 2)),
            trim($actions),
        ];
    }
}
