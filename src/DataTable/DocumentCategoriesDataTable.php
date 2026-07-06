<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;
use App\Security\Roles;

class DocumentCategoriesDataTable extends BaseDataTable
{
    protected $table = DB::DOCUMENT_CATEGORIES;
    protected $searchFields = ['document_category'];
    protected $sortableColumns = [0 => 'id', 1 => 'document_category', 2 => 'document_category_type', 3 => 'created_at', 4 => 'id'];

    protected function buildBaseQuery($requestData)
    {
        $sql = "SELECT * FROM `" . $this->table . "` WHERE id > 0";
        if ($this->roleId !== null && !Roles::hasFullAccess($this->roleId)) {
            $sql .= " AND document_category_type = 'employees'";
        }
        return $sql;
    }

    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? -1);
        if ($orderColumn >= 0 && isset($this->sortableColumns[$orderColumn])) {
            $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'ASC');
            return 'ORDER BY ' . $this->sortableColumns[$orderColumn] . ' ' . $orderDir;
        }
        $fieldList = "'Emirates ID','Visa','Labor Card','Passport','Photo','Contract'";
        return "ORDER BY FIELD(document_category, {$fieldList}) = 0 ASC, FIELD(document_category, {$fieldList}) ASC, document_category_type DESC, document_category ASC";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['document_category'] ?? '');
        $type    = (string)($row['document_category_type'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $mandatory = (int)($row['is_mandatory'] ?? 0);
        $mandatoryBadge = $mandatory ? ' <span class="badge bg-danger py-0 px-1" style="font-size: .65rem;">Required</span>' : '';
        $nameHtml = htmlspecialchars($name) . $mandatoryBadge;

        return [
            $this->rowNumber,
            $nameHtml,
            htmlspecialchars($type),
            $this->formatTimeAgo($created),
            $this->getActionButtons($id, 'document_categories'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'document_categories.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
