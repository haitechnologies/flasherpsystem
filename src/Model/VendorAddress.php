<?php

declare(strict_types=1);

namespace App\Model;

/**
 * VendorAddress DTO
 *
 * Readonly data transfer object representing a vendor address record (shipping/billing).
 */
readonly class VendorAddress
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $type,
        public int $vendorId,
        public ?string $attention,
        public int $country,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $city,
        public ?string $state,
        public ?string $zipcode,
        public ?string $phone,
        public ?string $fax,
        public bool $publish = true,
        public bool $isActive = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public ?int $createdBy = 0
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
            'type' => $this->type,
            'vendor_id' => $this->vendorId,
            'attention' => $this->attention,
            'country' => $this->country,
            'address_line1' => $this->addressLine1,
            'address_line2' => $this->addressLine2,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'phone' => $this->phone,
            'fax' => $this->fax,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
