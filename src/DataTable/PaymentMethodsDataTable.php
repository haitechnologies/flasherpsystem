<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class PaymentMethodsDataTable extends BaseDataTable
{
    protected $table = DB::PAYMENT_METHODS;
    protected $searchFields = ['payment_method'];
    protected $sortableColumns = [
        0 => 'id', 1 => 'payment_method', 2 => 'created_at', 3 => 'is_active', 4 => 'id'
    ];

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $paymentMethod = htmlspecialchars($row['payment_method'] ?? '');
        $publish = (int)$row['is_active'];
        $createdAt = $row['created_at'] ?? '';

        $publishBadge = $publish == 0 ? BadgeHelper::danger('Inactive') : BadgeHelper::success('Active');

        $timeAgoStr = '';
        if (!empty($createdAt)) {
            $timeAgoStr = $this->formatTimeAgo($createdAt);
        }

        return [
            $this->rowNumber,
            $paymentMethod,
            $timeAgoStr,
            $publishBadge,
            $this->getActionButtons($id, 'payment_methods', $publish)
        ];
    }

    protected function getActionButtons($id, $module, $publish)
    {
        $actions = '';
        if ($this->isGranted('edit', $module)) {
            $actions .= ActionButtonHelper::editButton($id, 'payment_methods.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton($id, $module);
        }
        return $actions;
    }
}
