<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class CarriersDataTable extends BaseDataTable
{
    protected $table = DB::CARRIERS;

    protected $searchFields = [
        'carrier_name',
    ];

    protected $sortableColumns = [
        0 => 'id',
        1 => 'carrier_name',
        2 => 'id',
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $carrierName = (string)($row['carrier_name'] ?? '');

        $carrierLink = '<a href="#" class="view-carrier-details text-primary fw-semibold" data-id="' . $id . '">' . htmlspecialchars($carrierName) . '</a>';
        return [
            $this->rowNumber,
            $carrierLink,
            $this->getActionButtons($id, 'carriers'),
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
            $actions .= ActionButtonHelper::editButton((int)$id, 'carriers.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $actions;
    }
}
