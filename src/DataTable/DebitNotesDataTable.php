<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class DebitNotesDataTable extends BaseDataTable
{
    protected $table = DB::DEBIT_NOTES;
    protected $searchFields = ['debit_note_no', 'reference_no'];
    protected $sortableColumns = [0 => 'debit_note_date', 1 => 'debit_note_no', 2 => 'reference_no', 3 => 'vendor_id', 4 => 'debit_note_status', 5 => 'grand_total'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $ids = array_filter(array_unique(array_map(fn($r) => (int)($r['vendor_id'] ?? 0), $rows)));
        $this->relatedDataCache['vendors'] = [];
        if ($ids) {
            try {
                $lookupRows = $this->db->fetchAll("SELECT id, display_name FROM `" . DB::VENDORS . "` WHERE id IN (" . implode(',', $ids) . ")");
                foreach ($lookupRows as $row) {
                    $this->relatedDataCache['vendors'][(int)$row['id']] = $row['display_name'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("DebitNotesDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $date       = (string)($row['debit_note_date'] ?? '');
        $no         = (string)($row['debit_note_no'] ?? '');
        $ref        = (string)($row['reference_no'] ?? '');
        $vendorId   = (int)($row['vendor_id'] ?? 0);
        $status     = (string)($row['debit_note_status'] ?? '');
        $total      = (float)($row['grand_total'] ?? 0);
        $vendorName = $this->relatedDataCache['vendors'][$vendorId] ?? '';
        $badge      = BadgeHelper::info(htmlspecialchars($status));
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        return [
            $this->formatDate($date) ?: '-',
            '<a href="debit_note_overview.php?id=' . (int)($row['id'] ?? 0) . '" class="text-decoration-none">' . htmlspecialchars($no) . '</a>',
            htmlspecialchars($ref),
            htmlspecialchars($vendorName),
            $badge,
            $currencyCode . ' ' . number_format($total, 2),
        ];
    }
}
