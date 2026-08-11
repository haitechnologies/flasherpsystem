<?php

declare(strict_types=1);

namespace App\Model;

/**
 * VendorContact DTO
 *
 * Readonly data transfer object representing a vendor contact person record.
 */
readonly class VendorContact
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public bool $isPrimary,
        public int $vendorId,
        public string $firstName,
        public string $lastName,
        public ?string $position,
        public string $email,
        public ?string $phone,
        public ?string $notes,
        public bool $publish = true,
        public bool $isActive = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0
    ) {
    }

    /**
     * Convert DTO to legacy array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'is_primary' => $this->isPrimary ? 1 : 0,
            'vendor_id' => $this->vendorId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'position' => $this->position,
            'email' => $this->email,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
