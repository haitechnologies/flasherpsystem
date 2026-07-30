<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\JobItem;

class JobItemRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByJobId(int $jobId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::JOB_ITEMS}` WHERE job_id = :job_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['job_id' => $jobId, 'org_id' => $orgId]);
        return array_map($this->mapRowToDto(...), $rows);
    }

    public function deleteByJobId(int $jobId, int $orgId): void
    {
        $this->db->execute(
            "DELETE FROM `{DB::JOB_ITEMS}` WHERE job_id = :job_id AND organization_id = :org_id",
            ['job_id' => $jobId, 'org_id' => $orgId]
        );
    }

    public function insert(JobItem $item): int
    {
        $sql = "INSERT INTO `{DB::JOB_ITEMS}` (organization_id, job_id, dim_length, dim_width, dim_height, dim_pcs, dim_volume, dim_cbm)
                VALUES (:organization_id, :job_id, :dim_length, :dim_width, :dim_height, :dim_pcs, :dim_volume, :dim_cbm)";
        return (int)$this->db->insert($sql, [
            'organization_id' => $item->organizationId,
            'job_id' => $item->jobId,
            'dim_length' => $item->dimLength,
            'dim_width' => $item->dimWidth,
            'dim_height' => $item->dimHeight,
            'dim_pcs' => $item->dimPcs,
            'dim_volume' => $item->dimVolume,
            'dim_cbm' => $item->dimCbm,
        ]);
    }

    public function replaceForJob(int $jobId, int $orgId, array $items): void
    {
        $this->db->beginTransaction();
        try {
            $this->deleteByJobId($jobId, $orgId);
            foreach ($items as $item) {
                $this->insert($item);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function mapRowToDto(array $row): JobItem
    {
        return new JobItem(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            jobId: (int)$row['job_id'],
            dimLength: (float)($row['dim_length'] ?? 0),
            dimWidth: (float)($row['dim_width'] ?? 0),
            dimHeight: (float)($row['dim_height'] ?? 0),
            dimPcs: (int)($row['dim_pcs'] ?? 0),
            dimVolume: (float)($row['dim_volume'] ?? 0),
            dimCbm: (float)($row['dim_cbm'] ?? 0),
        );
    }
}
