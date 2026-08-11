<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class CustomerAddressesDataTable extends BaseDataTable
{
    protected $table = DB::CUSTOMER_ADDRESSES;

    protected $searchFields = [
        'ca.attention',
        'ca.address_line1',
        'ca.address_line2',
        'ca.city',
        'ca.state',
        'ca.phone',
        'c.display_name',
        'c.email'
    ];

    protected $sortableColumns = [
        0 => 'ca.id',
        1 => 'c.display_name',
        2 => 'ca.type',
        3 => 'ca.attention',
        4 => 'ca.city',
        5 => 'ca.state',
        6 => 'ca.country',
        7 => 'ca.phone',
        8 => 'ca.created_at',
        9 => 'ca.id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT ca.*, ca.addressable_id AS customer_id, c.display_name, c.email "
            . "FROM `" . DB::CUSTOMER_ADDRESSES . "` ca "
            . "LEFT JOIN `" . DB::CUSTOMERS . "` c ON c.id = ca.addressable_id "
            . "WHERE ca.addressable_type = 'Customer' AND ca.id > 0";
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
            ErrorCapture::record("CustomerAddressesDataTable::prepareRelatedData countries error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $customerId = (int)($row['customer_id'] ?? 0);
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
            '<a href="customer_overview.php?customer_id=' . $customerId . '" class="text-primary">' . htmlspecialchars($displayName) . '</a>',
            $typeBadge,
            htmlspecialchars($attention),
            htmlspecialchars($city),
            htmlspecialchars($state),
            htmlspecialchars($countryName),
            htmlspecialchars($phone),
            $createdDisplay,
            $this->getActionButtons($id, 'customer_addresses', $customerId)
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

    protected function getActionButtons($id, $module, $customerId)
    {
        $actions = '';
        if ($this->isGranted('edit', 'customers')) {
            $actions .= '<a href="customer_overview.php?customer_id=' . $customerId . '" title="View"><span class="text-dark opacity-50"><i class="ph-eye"></i></span></a> ';
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
