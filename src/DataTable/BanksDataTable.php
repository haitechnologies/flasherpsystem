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
        0 => 'id', 1 => 'is_primary', 2 => 'account_name',
        3 => 'currency', 4 => 'account_code', 5 => 'bank_name',
        6 => 'branch', 7 => 'iban', 8 => 'routing_number',
        9 => 'created_at', 10 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id       = (int)($row['id'] ?? 0);
        $primary  = (int)($row['is_primary'] ?? 0) ? BadgeHelper::success('Primary') : '';
        $name     = (string)($row['account_name'] ?? '');
        $currency = (string)($row['currency_name'] ?? $row['currency'] ?? '');
        $code     = (string)($row['account_code'] ?? '');
        $bankName = (string)($row['bank_name'] ?? '');
        $bankNameCell = $bankName !== ''
            ? '<a href="banks.php?action=edit_banks&id=' . $id . '" class="text-body text-decoration-none" title="Open">'
                . htmlspecialchars($bankName)
                . '</a>'
            : '';
        $branch   = (string)($row['branch'] ?? '');
        $iban     = (string)($row['iban'] ?? '');
        $routing  = (string)($row['routing_number'] ?? '');
        $created  = (string)($row['created_at'] ?? '');
        return [
            $this->rowNumber, $primary,
            htmlspecialchars($name),
            htmlspecialchars($currency),
            htmlspecialchars($code),
            $bankNameCell,
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
