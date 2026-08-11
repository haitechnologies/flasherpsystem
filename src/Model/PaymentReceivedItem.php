<?php

declare(strict_types=1);

namespace App\Model;

/**
 * PaymentReceivedItem DTO
 *
 * Readonly data transfer object representing a payment received line item
 * (allocation of received amount against a specific customer invoice).
 */
readonly class PaymentReceivedItem
{
    public function __construct(
        public ?int $id,
        public int $paymentId,
        public int $organizationId = 0,
        public int $invoiceId = 0,
        public string $amountReceivedOn = '1970-01-01',
        public float $amountReceived = 0.0,
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
            'payment_id' => $this->paymentId,
            'organization_id' => $this->organizationId,
            'invoice_id' => $this->invoiceId,
            'amount_received_on' => $this->amountReceivedOn,
            'amount_received' => $this->amountReceived,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy
        ];
    }
}
