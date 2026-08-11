<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class ItemsDataTable extends BaseDataTable
{
    protected $table = DB::ITEMS;
    protected $searchFields = ['item_name'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'item_name', 2 => 'selling_price',
        3 => 'cost_price', 4 => 'created_at', 5 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        $sql = "SELECT i.*
                FROM `" . $this->table . "` i
                WHERE i.id > 0";
        if ($this->organizationId !== null) {
            $this->params['active_org_id'] = (int)$this->organizationId;
            $sql .= " AND i.organization_id = :active_org_id";
        }
        return $sql;
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $itemName = $row['item_name'] ?? '';
        $sellingPrice = (float)($row['selling_price'] ?? 0);
        $costPrice = (float)($row['cost_price'] ?? 0);
        $createdAt = $row['created_at'] ?? '';

        return [
            $this->rowNumber,
            htmlspecialchars($itemName),
            number_format($sellingPrice, 2),
            number_format($costPrice, 2),
            date('d M Y', strtotime($createdAt)),
            $this->getActionButtons($id, 'items')
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $buttons = [];
        if ($this->isGranted('edit', $module)) {
            $buttons[] = ActionButtonHelper::editButton($id, 'items.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $buttons[] = ActionButtonHelper::deleteButton($id, $module);
        }
        return implode(' ', array_filter($buttons));
    }
}
