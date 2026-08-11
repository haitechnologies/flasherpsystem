<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class DepartmentsDataTable extends BaseDataTable
{
    protected $table = DB::DEPARTMENT;
    protected $searchFields = ['department'];
    protected $sortableColumns = [0 => 'id', 1 => 'department', 3 => 'created_at'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $deptIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));
        if (empty($deptIds)) {
            return;
        }

        $idList = implode(',', $deptIds);

        $this->relatedDataCache['employees'] = [];
        try {
            $sql = "SELECT department_id, COUNT(*) as cnt 
                    FROM " . DB::USERS . " 
                    WHERE department_id IN ({$idList}) AND is_active = 1
                    GROUP BY department_id";
            $empRows = $this->db->fetchAll($sql);
            foreach ($empRows as $empRow) {
                $this->relatedDataCache['employees'][(int)$empRow['department_id']] = (int)$empRow['cnt'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("DepartmentsDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $dept    = (string)($row['department'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $empCount = $this->relatedDataCache['employees'][$id] ?? 0;
        return [
            $this->rowNumber,
            htmlspecialchars($dept),
            $row['email'] ?? '',
            $empCount,
            $this->formatTimeAgo($created),
            $this->getActionButtons($id, 'departments'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'departments.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
