<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\ActionButtonHelper;

class SalaryStructuresDataTable extends BaseDataTable
{
    protected $table = DB::SALARY_STRUCTURES;
    protected $searchFields = [];
    protected $sortableColumns = [
        0 => 'ss.id',
        1 => 'u.full_name',
        2 => 'pc.component_name',
        3 => 'ss.amount',
        4 => 'ss.effective_from',
        5 => 'ss.effective_to',
        6 => 'ss.id'
    ];

    protected function buildBaseQuery($requestData)
    {
        return "SELECT ss.*, u.full_name, pc.component_name, pc.component_type
                FROM `" . DB::SALARY_STRUCTURES . "` ss
                LEFT JOIN `" . DB::USERS . "` u ON u.id = ss.employee_id
                LEFT JOIN `" . DB::PAYROLL_COMPONENTS . "` pc ON pc.id = ss.component_id
                WHERE ss.id > 0" . $this->getOrgIdWhereClause();
    }

    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }
        $this->params['ss_org_id'] = (int)$this->organizationId;
        return " AND ss.organization_id = :ss_org_id";
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)$row['id'];
        $empId = (int)$row['employee_id'];
        $effFrom = !empty($row['effective_from']) ? date('d M Y', strtotime($row['effective_from'])) : '-';
        $effTo = !empty($row['effective_to']) ? date('d M Y', strtotime($row['effective_to'])) : '-';

        return [
            $this->rowNumber,
            htmlspecialchars((string)($row['full_name'] ?? '')),
            htmlspecialchars((string)($row['component_name'] ?? '')),
            'AED ' . number_format((float)$row['amount'], 2),
            $effFrom,
            $effTo,
            $this->getActionButtons($empId, 'salary_structures'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'salary_structures.php', $module, 'Edit', false);
        }
        return $a;
    }
}
