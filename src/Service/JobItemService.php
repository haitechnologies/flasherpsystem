<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\JobItem;
use App\Repository\JobItemRepository;

class JobItemService
{
    private JobItemRepository $repo;

    public function __construct(JobItemRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getByJobId(int $jobId, int $orgId): array
    {
        return $this->repo->findByJobId($jobId, $orgId);
    }

    public function replaceForJob(int $jobId, int $orgId, array $itemsData): void
    {
        $items = [];
        foreach ($itemsData as $row) {
            $dimLength = (float)($row['dim_length'] ?? 0);
            $dimWidth = (float)($row['dim_width'] ?? 0);
            $dimHeight = (float)($row['dim_height'] ?? 0);
            $dimPcs = (int)($row['dim_pcs'] ?? 0);
            $volume = ($dimLength / 100) * ($dimWidth / 100) * ($dimHeight / 100) * $dimPcs;
            $cbm = $volume;

            $items[] = new JobItem(
                id: null,
                organizationId: $orgId,
                jobId: $jobId,
                dimLength: $dimLength,
                dimWidth: $dimWidth,
                dimHeight: $dimHeight,
                dimPcs: $dimPcs,
                dimVolume: $volume,
                dimCbm: $cbm,
            );
        }
        $this->repo->replaceForJob($jobId, $orgId, $items);
    }
}
