<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class VendorAddressesDataTable extends BaseDataTable
{
    protected $table = DB::VENDOR_ADDRESSES;

    protected $searchFields = [
        'va.attention',
        'va.address_line1',
        'va.address_line2',
        'va.city',
        'va.state',
        'va.phone',
        'v.display_name',
        'v.email'
    ];

    protected $sortableColumns = [
        0 => 'va.id',
        1 => 'v.display_name',
        2 => 'va.type',
        3 => 'va.attention',
        4 => 'va.city',
        5 => 'va.state',
        6 => 'va.country',
        7 => 'va.phone',
        8 => 'va.created_at',
        9 => 'va.id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT va.*, va.addressable_id AS vendor_id, v.display_name, v.email "
            . "FROM `" . DB::VENDOR_ADDRESSES . "` va "
            . "LEFT JOIN `" . DB::VENDORS . "` v ON v.id = va.addressable_id "
            . "WHERE va.addressable_type = 'Vendor' AND va.id > 0";
    }

    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue)) {
            return '';
        }

        $conditions = [];
        foreach ($this->searchFields as $index => $field) {
            $paramKey = 'search_' . $index;
            $conditions[] = "{$field} LIKE :{$paramKey}";
            $this->params[$paramKey] = '%' . $searchValue . '%';
        }

        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $countryIds = array_unique(array_filter(array_map(fn($r) => (int)($r['country'] ?? 0), $rows)));
        if (empty($countryIds)) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($countryIds as $index => $id) {
            $key = 'country_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $placeholdersStr = implode(',', $placeholders);

        $sql = "SELECT id, country_name FROM `" . DB::GEO_COUNTRIES . "` WHERE id IN ({$placeholdersStr})";

        $this->relatedDataCache['countries'] = [];
        try {
            $countryRows = $this->db->fetchAll($sql, $params);
            foreach ($countryRows as $cRow) {
                $this->relatedDataCache['countries'][(int)$cRow['id']] = $cRow['country_name'] ?? '-';
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("VendorAddressesDataTable::prepareRelatedData countries error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $vendorId = (int)($row['vendor_id'] ?? 0);
        $displayName = ($row['display_name'] ?? '') ?: 'Unknown';
        $type = ($row['type'] ?? '');
        $attention = ($row['attention'] ?? '') ?: '-';
        $city = ($row['city'] ?? '') ?: '-';
        $state = ($row['state'] ?? '') ?: '-';
        $countryId = (int)($row['country'] ?? 0);
        $phone = ($row['phone'] ?? '') ?: '-';
        $createdAt = $row['created_at'] ?? '';

        $typeBadge = $type === 'billing'
            ? BadgeHelper::info('Billing')
            : ($type === 'shipping' ? BadgeHelper::primary('Shipping') : BadgeHelper::secondary('Other'));

        $countryName = $this->relatedDataCache['countries'][$countryId] ?? '-';

        $createdDisplay = !empty($createdAt) ? date('d M Y', strtotime($createdAt)) : '-';

        return [
            $this->rowNumber,
            '<a href="vendor_overview.php?vendor_id=' . $vendorId . '" class="text-primary">' . htmlspecialchars($displayName) . '</a>',
            $typeBadge,
            htmlspecialchars($attention),
            htmlspecialchars($city),
            htmlspecialchars($state),
            htmlspecialchars($countryName),
            htmlspecialchars($phone),
            $createdDisplay,
            $this->getActionButtons($id, 'vendor_addresses', $vendorId)
        ];
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? count($this->sortableColumns) - 2);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'DESC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'DESC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    protected function getActionButtons($id, $module, $vendorId)
    {
        $actions = '';
        if ($this->isGranted('edit', 'vendors')) {
            $actions .= '<a href="vendor_overview.php?vendor_id=' . $vendorId . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
