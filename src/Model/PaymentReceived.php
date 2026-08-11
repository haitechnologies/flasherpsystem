<?php

declare(strict_types=1);

namespace App\Model;

/**
 * PaymentReceived DTO
 *
 * Readonly data transfer object representing a customer payment received record.
 */
readonly class PaymentReceived
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public ?string $paymentNo = null,
        public string $paymentStatus = 'draft',
        public int $customerId = 0,
        public float $totalAmountReceived = 0.0,
        public float $bankCharges = 0.0,
        public string $paymentDate = '1970-01-01',
        public ?int $paymentMethod = null,
        public int $depositTo = 0,
        public ?string $referenceNo = null,
        public bool $publish = true,
        public bool $isActive = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0
    ) {
    }

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'payment_no' => $this->paymentNo,
            'payment_status' => $this->paymentStatus,
            'customer_id' => $this->customerId,
            'total_amount_received' => $this->totalAmountReceived,
            'bank_charges' => $this->bankCharges,
            'payment_date' => $this->paymentDate,
            'payment_method' => $this->paymentMethod,
            'deposit_to' => $this->depositTo,
            'reference_no' => $this->referenceNo,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy
        ];
    }
}
