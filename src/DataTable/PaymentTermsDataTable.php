<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class PaymentTermsDataTable extends BaseDataTable
{
    protected $table = DB::PAYMENT_TERMS;
    protected $searchFields = ['payment_term'];
    protected $sortableColumns = [0 => 'id', 1 => 'payment_term', 2 => 'created_at', 3 => 'is_active', 4 => 'id'];

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['payment_term'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $publish = (int)($row['is_active'] ?? 0);
        $badge   = $publish ? BadgeHelper::success('Active') : BadgeHelper::danger('Inactive');
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            $this->formatTimeAgo($created),
            $badge,
            $this->getActionButtons($id, 'payment_terms'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'payment_terms.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
