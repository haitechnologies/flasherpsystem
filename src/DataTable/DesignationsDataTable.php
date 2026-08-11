<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;
use App\Core\ErrorCapture;

class DesignationsDataTable extends BaseDataTable
{
    protected $table = DB::DESIGNATIONS;
    protected $searchFields = ['designation'];
    protected $sortableColumns = [0 => 'id', 1 => 'designation', 3 => 'created_at'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $desigIds = array_filter(array_map(fn($r) => (int)($r['id'] ?? 0), $rows));
        if (empty($desigIds)) {
            return;
        }

        $idList = implode(',', $desigIds);

        $this->relatedDataCache['employees'] = [];
        try {
            $sql = "SELECT designation_id, COUNT(*) as cnt 
                    FROM " . DB::USERS . " 
                    WHERE designation_id IN ({$idList}) AND is_active = 1
                    GROUP BY designation_id";
            $empRows = $this->db->fetchAll($sql);
            foreach ($empRows as $empRow) {
                $this->relatedDataCache['employees'][(int)$empRow['designation_id']] = (int)$empRow['cnt'];
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("DesignationsDataTable::prepareRelatedData error: " . $e->getMessage());
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id      = (int)($row['id'] ?? 0);
        $name    = (string)($row['designation'] ?? '');
        $created = (string)($row['created_at'] ?? '');
        $empCount = $this->relatedDataCache['employees'][$id] ?? 0;
        return [
            $this->rowNumber,
            htmlspecialchars($name),
            $empCount,
            $this->formatTimeAgo($created),
            $this->getActionButtons($id, 'designations'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $a = '';
        if ($this->isGranted('edit', $module)) {
            $a .= ActionButtonHelper::editButton((int)$id, 'designations.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $a .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return $a;
    }
}
