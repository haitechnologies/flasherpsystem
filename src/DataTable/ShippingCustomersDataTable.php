<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class ShippingCustomersDataTable extends BaseDataTable
{
    protected $table = DB::CUSTOMERS;
    protected $searchFields = ['display_name', 'phone', 'email', 'address'];
    protected $sortableColumns = [0 => 'id', 1 => 'display_name', 2 => 'phone', 3 => 'address', 4 => 'address', 5 => 'customer_type', 6 => 'is_active', 7 => 'id'];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE entity_type = 'shipping'" . $this->getOrgIdWhereClause();
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['display_name'] ?? '');
        $phone   = (string)($row['phone'] ?? '');
        $address = (string)($row['address'] ?? '');
        $type    = (string)($row['customer_type'] ?? '');
        $active  = (int)($row['is_active'] ?? 0);
        $badge   = $active ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            htmlspecialchars($phone),
            htmlspecialchars($address),
            htmlspecialchars($address),
            htmlspecialchars($type),
            $badge,
            $this->getActionButtons($id, 'shipping_customers'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'shipping_customers.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
