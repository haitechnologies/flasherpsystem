<?php

declare(strict_types=1);

namespace App\Model;

readonly class SetupBank
{
    public function __construct(
        public int $id = 0,
        public int $organizationId = 1,
        public string $institutionName = '',
        public string $headOffice = '',
        public bool $isActive = true,
        public string $createdAt = '',
        public string $updatedAt = '',
        public int $createdBy = 0,
        public int $updatedBy = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'institution_name' => $this->institutionName,
            'head_office' => $this->headOffice,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
