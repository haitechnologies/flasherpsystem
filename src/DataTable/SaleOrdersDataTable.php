<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class SaleOrdersDataTable extends BaseDataTable
{
    protected $table = DB::SALE_ORDERS;
    protected $searchFields = ['sale_order_no', 'reference_no'];
    protected $sortableColumns = [0 => 'sale_order_date', 1 => 'sale_order_no', 2 => 'reference_no', 3 => 'customer_id', 4 => 'sale_order_status', 5 => 'grand_total'];

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
                ErrorCapture::record("SaleOrdersDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $date     = (string)($row['sale_order_date'] ?? '');
        $no       = (string)($row['sale_order_no'] ?? '');
        $ref      = (string)($row['reference_no'] ?? '');
        $custId   = (int)($row['customer_id'] ?? 0);
        $status   = (string)($row['sale_order_status'] ?? '');
        $total    = (float)($row['grand_total'] ?? 0);
        $custName = $this->relatedDataCache['customers'][$custId] ?? '';
        $badge    = BadgeHelper::info(htmlspecialchars($status));
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        return [
            $this->formatDate($date) ?: '-',
            htmlspecialchars($no),
            htmlspecialchars($ref),
            htmlspecialchars($custName),
            $badge,
            $currencyCode . ' ' . number_format($total, 2),
            $id,
        ];
    }
}
