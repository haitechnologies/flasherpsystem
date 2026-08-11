<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class PurchasesDataTable extends BaseDataTable
{
    protected $table = DB::PURCHASES;
    protected $searchFields = ['purchase_no', 'reference_no'];
    protected $sortableColumns = [0 => 'purchase_date', 1 => 'purchase_no', 2 => 'purchase_order_id', 3 => 'vendor_id', 4 => 'purchase_status', 5 => 'expiry_date', 6 => 'grand_total', 7 => 'id'];

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
                ErrorCapture::record("PurchasesDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }

        $purchaseIds = array_filter(array_unique(array_map(fn($r) => (int)($r['id'] ?? 0), $rows)));
        $this->relatedDataCache['balance_due'] = [];
        if ($purchaseIds) {
            try {
                $inClause = implode(',', $purchaseIds);
                $paymentRows = $this->db->fetchAll(
                    "SELECT mi.purchase_id, COALESCE(SUM(mi.amount_paid), 0) AS total_paid
                     FROM `" . DB::PAYMENT_MADE_ITEMS . "` mi
                     INNER JOIN `" . DB::PAYMENTS_MADE . "` pm ON pm.id = mi.payment_id
                     WHERE mi.purchase_id IN (" . $inClause . ")
                     AND pm.payment_status <> 'void'
                     GROUP BY mi.purchase_id"
                );
                foreach ($paymentRows as $row) {
                    $this->relatedDataCache['balance_due'][(int)$row['purchase_id']] = (float)$row['total_paid'];
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("PurchasesDataTable::prepareRelatedData balance_due error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id         = (int)($row['id'] ?? 0);
        $date       = (string)($row['purchase_date'] ?? '');
        $no         = (string)($row['purchase_no'] ?? '');
        $orderNo    = (string)($row['purchase_order_id'] ?? '');
        $vendorId   = (int)($row['vendor_id'] ?? 0);
        $status     = (string)($row['purchase_status'] ?? '');
        $dueDate    = (string)($row['expiry_date'] ?? '');
        $total      = (float)($row['grand_total'] ?? 0);
        $vendorName = $this->relatedDataCache['vendors'][$vendorId] ?? '';
        $badge      = BadgeHelper::info(htmlspecialchars($status));
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';

        $paid = $this->relatedDataCache['balance_due'][$id] ?? 0.0;
        $balanceDue = $total - $paid;

        return [
            $this->formatDate($date) ?: '-',
            '<a href="purchase_overview.php?id=' . $id . '" class="text-decoration-none">' . htmlspecialchars($no) . '</a>',
            htmlspecialchars($orderNo),
            htmlspecialchars($vendorName),
            $badge,
            $this->formatDate($dueDate) ?: '-',
            $currencyCode . ' ' . number_format($total, 2),
            $currencyCode . ' ' . number_format($balanceDue, 2),
        ];
    }
}
