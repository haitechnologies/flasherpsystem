<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Department DTO
 *
 * Readonly data transfer object representing a department record.
 * Adheres strictly to PSR.md rules.
 */
readonly class Department
{
    public function __construct(
        public ?int $id,
        public ?int $organizationId,
        public string $department,
        public bool $publish,
        public ?string $email = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public int $createdBy = 0
    ) {
    }

    /**
     * Convert DTO to a legacy-compatible array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'department' => $this->department,
            'email' => $this->email,
            'publish' => $this->publish ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'created_by' => $this->createdBy,
        ];
    }
}
