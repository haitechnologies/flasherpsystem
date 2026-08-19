<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class ShippersDataTable extends BaseDataTable
{
    protected $table = DB::SHIPPERS;

    protected $searchFields = [
        'shipper_name',
        'address_line1',
    ];

    protected $sortableColumns = [
        0 => 'id',
        1 => 'shipper_name',
        2 => 'address_line1',
        3 => 'created_at',
        4 => 'id',
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $name = (string)($row['shipper_name'] ?? '');
        $address1 = (string)($row['address_line1'] ?? '');
        $createdAt = (string)($row['created_at'] ?? '');

        $timeAgoStr = '';
        if (!empty($createdAt)) {
            $timeAgoStr = $this->formatTimeAgo($createdAt);
        }

        $shipperLink = '<a href="#" class="view-shipper-details text-primary fw-semibold" data-id="' . $id . '">' . htmlspecialchars($name) . '</a>';
        return [
            $this->rowNumber,
            $shipperLink,
            htmlspecialchars($address1),
            $timeAgoStr,
            $this->getActionButtons($id, 'shippers'),
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
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton((int)$id, 'shippers.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module, 'Are you sure you want to delete this shipper?');
        }
        return $actions;
    }
}
