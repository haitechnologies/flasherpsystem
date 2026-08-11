<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class PaymentsMadeDataTable extends BaseDataTable
{
    protected $table = DB::PAYMENTS_MADE;
    protected $searchFields = ['reference_no'];
    protected $sortableColumns = [0 => 'payment_date', 1 => 'id', 2 => 'reference_no', 3 => 'vendor_id', 4 => 'id', 5 => 'payment_method', 6 => 'total_amount_paid', 7 => 'payment_status'];

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
                ErrorCapture::record("PaymentsMadeDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }

        $paymentIds = array_filter(array_unique(array_map(fn($r) => (int)($r['id'] ?? 0), $rows)));
        $this->relatedDataCache['paymentPurchases'] = [];
        $this->relatedDataCache['paymentMethods'] = [];
        if ($paymentIds) {
            $inClause = implode(',', $paymentIds);
            try {
                $itemRows = $this->db->fetchAll("SELECT payment_id, purchase_id FROM `" . DB::PAYMENT_MADE_ITEMS . "` WHERE payment_id IN (" . $inClause . ")");
                $purchaseIds = array_filter(array_unique(array_map(fn($r) => (int)($r['purchase_id'] ?? 0), $itemRows)));
                $purchases = [];
                if ($purchaseIds) {
                    $purchaseRows = $this->db->fetchAll("SELECT id, purchase_no FROM `" . DB::PURCHASES . "` WHERE id IN (" . implode(',', $purchaseIds) . ")");
                    foreach ($purchaseRows as $row) {
                        $purchases[(int)$row['id']] = $row['purchase_no'];
                    }
                }
                foreach ($itemRows as $row) {
                    $pid = (int)$row['payment_id'];
                    $this->relatedDataCache['paymentPurchases'][$pid][] = $purchases[(int)$row['purchase_id']] ?? '';
                }

                $methodIds = array_filter(array_unique(array_map(fn($r) => (int)($r['payment_method'] ?? 0), $rows)));
                if ($methodIds) {
                    $methodRows = $this->db->fetchAll("SELECT id, payment_method FROM `" . DB::PAYMENT_METHODS . "` WHERE id IN (" . implode(',', $methodIds) . ")");
                    foreach ($methodRows as $row) {
                        $this->relatedDataCache['paymentMethods'][(int)$row['id']] = $row['payment_method'];
                    }
                }
            } catch (\Throwable $e) {
                ErrorCapture::record("PaymentsMadeDataTable::prepareRelatedData error: " . $e->getMessage());
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id         = (int)($row['id'] ?? 0);
        $date       = (string)($row['payment_date'] ?? '');
        $vendorId   = (int)($row['vendor_id'] ?? 0);
        $amount     = (float)($row['total_amount_paid'] ?? 0);
        $method     = (string)($row['payment_method'] ?? '');
        $ref        = (string)($row['reference_no'] ?? '');
        $status     = (string)($row['payment_status'] ?? '');
        $vendorName = $this->relatedDataCache['vendors'][$vendorId] ?? '';
        $badge      = BadgeHelper::info(htmlspecialchars($status));
        $currencyCode = defined('BASE_CURRENCY') ? BASE_CURRENCY['code'] : 'AED';

        $purchases = $this->relatedDataCache['paymentPurchases'][$id] ?? [];
        $purchaseLabel = '';
        if (!empty($purchases)) {
            $purchaseLabel = htmlspecialchars((string)($purchases[0] ?? ''));
            if (count($purchases) > 1) {
                $purchaseLabel .= ' (+' . (count($purchases) - 1) . ')';
            }
        }
        $methodName = $this->relatedDataCache['paymentMethods'][(int)$method] ?? $method;

        return [
            $this->formatDate($date) ?: '-',
            '<a href="payments_made_overview.php?payment_id=' . $id . '" class="text-decoration-none">PM_' . $id . '</a>',
            '<a href="payments_made_overview.php?payment_id=' . $id . '" class="text-decoration-none">' . htmlspecialchars($ref) . '</a>',
            htmlspecialchars($vendorName),
            $purchaseLabel,
            htmlspecialchars((string)$methodName),
            $currencyCode . ' ' . number_format($amount, 2),
            $badge,
        ];
    }
}
