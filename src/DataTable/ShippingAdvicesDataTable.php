<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class ShippingAdvicesDataTable extends BaseDataTable
{
    protected $table = DB::SHIPPING_ADVICES;

    protected $searchFields = [
        'invoice_no',
        'awb_no',
        'license_no',
        'mirsal_II_code',
    ];

    protected $sortableColumns = [
        0 => 'id',
        1 => 'invoice_no',
        2 => 'invoice_date',
        3 => 'customer_id',
        4 => 'awb_no',
        5 => 'license_no',
        6 => 'mirsal_II_code',
        7 => 'is_active',
        8 => 'id',
    ];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $customerIds = array_filter(array_map(static function ($row) {
            return (int)($row['customer_id'] ?? 0);
        }, $rows));

        if (empty($customerIds)) {
            return;
        }

        $uniqueIds = array_unique($customerIds);
        $placeholders = [];
        $params = [];
        foreach ($uniqueIds as $index => $id) {
            $key = 'cust_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholdersStr = implode(',', $placeholders);

        $this->relatedDataCache['customers'] = [];
        $query = "SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE entity_type = 'shipping' AND id IN ({$placeholdersStr})";
        try {
            $custRows = $this->db->fetchAll($query, $params);
            foreach ($custRows as $cRow) {
                $this->relatedDataCache['customers'][(int)$cRow['id']] = (string)($cRow['display_name'] ?? '');
            }
        } catch (\Throwable $e) {
            error_log("ShippingAdvicesDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $invoiceNo = (string)($row['invoice_no'] ?? '');
        $invoiceDate = (string)($row['invoice_date'] ?? '');
        $customerId = (int)($row['customer_id'] ?? 0);
        $awbNo = (string)($row['awb_no'] ?? '');
        $licenseNo = (string)($row['license_no'] ?? '');
        $mirsalCode = (string)($row['mirsal_II_code'] ?? '');
        $publish = (int)($row['is_active'] ?? 0);

        $customerName = $this->relatedDataCache['customers'][$customerId] ?? ('Customer #' . $customerId);
        $statusBadge = ($publish === 1) ? BadgeHelper::success('Published') : BadgeHelper::warning('Draft');

        return [
            $this->rowNumber,
            '<a href="view_shipping_advice.php?id=' . $id . '" class="text-primary">' . htmlspecialchars($invoiceNo) . '</a>',
            htmlspecialchars($invoiceDate),
            htmlspecialchars($customerName),
            htmlspecialchars($awbNo),
            htmlspecialchars($licenseNo),
            htmlspecialchars($mirsalCode),
            $statusBadge,
            $this->getActionButtons($id, 'shipping_advices'),
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function getActionButtons($id, $module)
    {
        $actions = '';
        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $actions;
    }
}
