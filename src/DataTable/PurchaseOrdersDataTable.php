<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class PurchaseOrdersDataTable extends BaseDataTable
{
    protected $table = DB::PURCHASE_ORDERS;
    protected $searchFields = ['purchase_order_no', 'reference_no'];
    protected $sortableColumns = [0 => 'purchase_order_date', 1 => 'purchase_order_no', 2 => 'reference_no', 3 => 'vendor_id', 4 => 'purchase_order_status', 5 => 'grand_total'];

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
                ErrorCapture::record("PurchaseOrdersDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id         = (int)($row['id'] ?? 0);
        $date       = (string)($row['purchase_order_date'] ?? '');
        $no         = (string)($row['purchase_order_no'] ?? '');
        $ref        = (string)($row['reference_no'] ?? '');
        $vendorId   = (int)($row['vendor_id'] ?? 0);
        $status     = (string)($row['purchase_order_status'] ?? '');
        $total      = (float)($row['grand_total'] ?? 0);
        $vendorName = $this->relatedDataCache['vendors'][$vendorId] ?? '';
        $badge      = BadgeHelper::info(htmlspecialchars($status));
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';
        return [
            $this->formatDate($date) ?: '-',
            '<a href="purchase_order_overview.php?id=' . $id . '" class="text-decoration-none">' . htmlspecialchars($no) . '</a>',
            htmlspecialchars($ref),
            htmlspecialchars($vendorName),
            $badge,
            $currencyCode . ' ' . number_format($total, 2),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'purchase_orders.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
