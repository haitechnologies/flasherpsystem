<?php

declare(strict_types=1);

namespace App\Model;

readonly class Bank
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $accountName,
        public string $accountCode,
        public int $currency,
        public string $bankName,
        public string $branch,
        public string $address,
        public string $iban,
        public string $routingNumber,
        public string $description,
        public bool $isPrimary,
        public bool $isActive,
        public float $openingBalance = 0.00,
        public ?int $accountId = null,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0,
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'account_name' => $this->accountName,
            'account_code' => $this->accountCode,
            'opening_balance' => $this->openingBalance,
            'account_id' => $this->accountId,
            'currency' => $this->currency,
            'bank_name' => $this->bankName,
            'branch' => $this->branch,
            'address' => $this->address,
            'iban' => $this->iban,
            'routing_number' => $this->routingNumber,
            'description' => $this->description,
            'is_primary' => $this->isPrimary ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
