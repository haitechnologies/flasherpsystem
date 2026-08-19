<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\DB;
use App\Helper\BadgeHelper;
use App\Helper\ActionButtonHelper;

class JobsDataTable extends BaseDataTable
{
    protected $table = DB::JOBS;
    protected $searchFields = ['job_no', 'job_ref_no'];
    protected $sortableColumns = [0 => 'job_date', 1 => 'job_no', 2 => 'job_ref_no', 3 => 'customer_id', 4 => 'job_status', 5 => 'estimated_invoice_amount', 6 => 'id', 7 => 'id'];

    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        $customerIds = [];
        $statusIds = [];
        $jobIds = [];

        foreach ($rows as $row) {
            $customerIds[] = (int)($row['customer_id'] ?? 0);
            $statusIds[] = (int)($row['job_status'] ?? 0);
            $jobIds[] = (int)($row['id'] ?? 0);
        }

        $this->relatedDataCache['customers'] = $this->fetchLookupMap(DB::CUSTOMERS, $customerIds, 'display_name');
        $this->relatedDataCache['statuses'] = $this->fetchLookupMap(DB::JOB_STATUSES, $statusIds, 'job_status');
        $this->relatedDataCache['projects'] = [];

        $jobIds = array_values(array_filter(array_unique(array_map('intval', $jobIds))));
        if (!$jobIds) {
            return;
        }

        $projects = $this->db->fetchAll("SELECT id, job_id FROM `" . DB::PROJECTS . "` WHERE job_id IN (" . implode(',', $jobIds) . ") ORDER BY id ASC", []);
        foreach ($projects as $row) {
            $jobId = (int)($row['job_id'] ?? 0);
            if ($jobId > 0 && !isset($this->relatedDataCache['projects'][$jobId])) {
                $this->relatedDataCache['projects'][$jobId] = (int)($row['id'] ?? 0);
            }
        }
    }

    protected function formatRow($row, $requestData = [])
    {
        $id = (int)($row['id'] ?? 0);
        $customerId = (int)($row['customer_id'] ?? 0);
        $statusId = (int)($row['job_status'] ?? 0);
        $statusLabel = (string)($this->relatedDataCache['statuses'][$statusId] ?? '');

        return [
            $this->formatDate($row['job_date'] ?? ''),
            htmlspecialchars((string)($row['job_no'] ?? '')),
            htmlspecialchars((string)($row['job_ref_no'] ?? '')),
            htmlspecialchars((string)($this->relatedDataCache['customers'][$customerId] ?? '')),
            $statusLabel !== '' ? BadgeHelper::info(htmlspecialchars(ucwords(str_replace('_', ' ', $statusLabel)))) : '',
            htmlspecialchars(number_format((float)($row['estimated_invoice_amount'] ?? 0), 2)),
            htmlspecialchars((string)($this->relatedDataCache['projects'][$id] ?? '')),
            $this->getActionButtons($id, 'jobs'),
        ];
    }

    protected function getActionButtons($id, $module)
    {
        $actions = '';
        $actions .= '<a href="pdf_job.php?job_id=' . (int)$id . '" title="View PDF" class="action-btn action-view" target="_blank"><i class="ph-file-pdf"></i></a>';
        if ($this->isGranted('edit', $module)) {
            $actions .= ' ' . ActionButtonHelper::editButton((int)$id, 'jobs.php', $module, 'Edit', false);
        }
        if ($this->isGranted('delete', $module)) {
            $actions .= ' ' . ActionButtonHelper::deleteButton((int)$id, $module);
        }
        return trim($actions);
    }

}
