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
        0 => 'id', 1 => 'item_name', 2 => 'selling_price', 3 => 'sale_account',
        4 => 'cost_price', 5 => 'purchase_account', 6 => 'preferred_vendor_id', 7 => 'created_at', 8 => 'id'
    ];

    protected function buildBaseQuery($requestData)
    {
        $sql = "SELECT i.*, sa.account_name AS sale_account_name, pa.account_name AS purchase_account_name,
                       tt.tax_treatment AS tax_treatment_name, v.display_name AS preferred_vendor_name
                FROM `" . $this->table . "` i
                LEFT JOIN `" . DB::ACCOUNTS . "` sa ON i.sale_account = sa.id
                LEFT JOIN `" . DB::ACCOUNTS . "` pa ON i.purchase_account = pa.id
                LEFT JOIN `" . DB::TAX_TREATMENTS . "` tt ON i.tax_treatment_id = tt.id
                LEFT JOIN `" . DB::VENDORS . "` v ON i.preferred_vendor_id = v.id
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
        $saleAccountName = (string)($row['sale_account_name'] ?? '');
        $purchaseAccountName = (string)($row['purchase_account_name'] ?? '');
        $taxTreatmentName = (string)($row['tax_treatment_name'] ?? '');
        $preferredVendorName = (string)($row['preferred_vendor_name'] ?? '');

        return [
            $this->rowNumber,
            htmlspecialchars($itemName),
            number_format($sellingPrice, 2),
            htmlspecialchars($saleAccountName),
            htmlspecialchars($taxTreatmentName),
            number_format($costPrice, 2),
            htmlspecialchars($purchaseAccountName),
            htmlspecialchars($preferredVendorName),
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
