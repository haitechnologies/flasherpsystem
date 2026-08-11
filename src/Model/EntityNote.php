<?php

declare(strict_types=1);

namespace App\Model;

readonly class EntityNote
{
    public function __construct(
        public ?int $id,
        public string $entityType,
        public int $entityId,
        public ?string $notes,
        public int $createdBy,
        public ?int $updatedBy,
        public bool $isActive,
        public int $organizationId,
        public ?string $createdAt,
        public ?string $updatedAt,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'notes' => $this->notes,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
            'is_active' => $this->isActive ? 1 : 0,
            'organization_id' => $this->organizationId,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
