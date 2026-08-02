<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;

class QuotationsDataTable extends BaseDataTable
{
    protected $table = DB::QUOTATIONS;
    protected $searchFields = ['quotation_no', 'job_reference_no'];
    protected $sortableColumns = [0 => 'quotation_date', 1 => 'quotation_no', 2 => 'job_reference_no', 3 => 'customer_id', 4 => 'quotation_status', 5 => 'grand_total'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $ids = array_filter(array_unique(array_map(fn($r) => (int)($r['customer_id'] ?? 0), $rows)));
        $this->relatedDataCache['customers'] = [];
        if ($ids) {
            try {
                $lookupRows = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE id IN (" . implode(',', $ids) . ")");
                foreach ($lookupRows as $row) {
                    $this->relatedDataCache['customers'][(int)$row['id']] = $row['display_name'];
                }
            } catch (\Throwable $e) {
                error_log("QuotationsDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id         = (int)($row['id'] ?? 0);
        $date       = (string)($row['quotation_date'] ?? '');
        $no         = (string)($row['quotation_no'] ?? '');
        $jobRef     = (string)($row['job_reference_no'] ?? '');
        $custId     = (int)($row['customer_id'] ?? 0);
        $status     = (string)($row['quotation_status'] ?? '');
        $total      = (string)($row['grand_total'] ?? '0');
        $custName   = $this->relatedDataCache['customers'][$custId] ?? '';
        $statusBadge = BadgeHelper::info(htmlspecialchars($status));
        return [
            htmlspecialchars($date),
            '<a href="quotation_overview.php?quotation_id=' . $id . '" class="fw-semibold text-primary text-decoration-none">' . htmlspecialchars($no) . '</a>',
            htmlspecialchars($jobRef),
            htmlspecialchars($custName),
            $statusBadge,
            htmlspecialchars(number_format((float)$total, 2)),
            $id,
        ];
    }
}
