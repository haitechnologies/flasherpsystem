<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class BanksDataTable extends BaseDataTable
{
    protected $table = DB::BANKS;
    protected $searchFields = ['account_name', 'bank_name', 'account_code'];

    protected function buildBaseQuery($requestData)
    {
        $sql = "SELECT b.*, c.currency AS currency_name FROM `" . $this->table . "` b
                LEFT JOIN `" . DB::CURRENCIES . "` c ON b.currency = c.id
                WHERE b.id > 0";
        if ($this->organizationId !== null) {
            $this->params['active_org_id'] = (int)$this->organizationId;
            $sql .= " AND (b.`organization_id` = :active_org_id OR b.`organization_id` IS NULL)";
        }
        return $sql;
    }
    protected $sortableColumns = [
        0 => 'id', 1 => 'is_primary', 2 => 'is_active', 3 => 'account_name',
        4 => 'currency', 5 => 'account_code', 6 => 'bank_name',
        7 => 'branch', 8 => 'iban', 9 => 'routing_number',
        10 => 'created_at', 11 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $primary  = (int)($row['is_primary'] ?? 0) ? BadgeHelper::success('Primary') : '';
        $publish  = (int)($row['is_active'] ?? 0);
        $badge    = $publish ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        $name     = (string)($row['account_name'] ?? '');
        $currency = (string)($row['currency_name'] ?? $row['currency'] ?? '');
        $code     = (string)($row['account_code'] ?? '');
        $bankName = (string)($row['bank_name'] ?? '');
        $branch   = (string)($row['branch'] ?? '');
        $iban     = (string)($row['iban'] ?? '');
        $routing  = (string)($row['routing_number'] ?? '');
        $created  = (string)($row['created_at'] ?? '');
        return [
            $this->rowNumber, $primary, $badge,
            htmlspecialchars($name),
            htmlspecialchars($currency),
            htmlspecialchars($code),
            htmlspecialchars($bankName),
            htmlspecialchars($branch),
            htmlspecialchars($iban),
            htmlspecialchars($routing),
            $this->formatTimeAgo($created),
            $this->getActionButtons($id, 'banks'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'banks.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
