<?php

declare(strict_types=1);

namespace App\Model;

readonly class JobItem
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public int $jobId,
        public float $dimLength,
        public float $dimWidth,
        public float $dimHeight,
        public int $dimPcs,
        public float $dimVolume,
        public float $dimCbm,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'job_id' => $this->jobId,
            'dim_length' => $this->dimLength,
            'dim_width' => $this->dimWidth,
            'dim_height' => $this->dimHeight,
            'dim_pcs' => $this->dimPcs,
            'dim_volume' => $this->dimVolume,
            'dim_cbm' => $this->dimCbm,
        ];
    }
}
