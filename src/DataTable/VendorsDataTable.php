<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class VendorsDataTable extends BaseDataTable
{
    protected $table = DB::VENDORS;
    protected $searchFields = ['display_name', 'company_name', 'email'];
    protected $sortableColumns = [0 => 'display_name', 1 => 'company_name', 2 => 'email', 3 => 'phone', 4 => 'id'];

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['display_name'] ?? '');
        $company = (string)($row['company_name'] ?? '');
        $email   = (string)($row['email'] ?? '');
        $phone   = (string)($row['phone'] ?? '');
        return [
            '<a href="vendor_overview.php?vendor_id=' . $id . '" class="text-decoration-none">' . htmlspecialchars($name) . '</a>',
            htmlspecialchars($company),
            htmlspecialchars($email),
            htmlspecialchars($phone),
            $this->getActionButtons($id, 'vendors'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'vendors.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
