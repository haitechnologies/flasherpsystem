<?php

declare(strict_types=1);

namespace App\Model;

/**
 * CreditNoteItem DTO
 *
 * Readonly data transfer object representing a credit note line item record.
 */
readonly class CreditNoteItem
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public int $creditNoteId,
        public int $service,
        public ?string $description = null,
        public float $qty = 1.0,
        public float $rate = 0.0,
        public ?string $discountType = null,
        public float $discountTypeValue = 0.0,
        public float $discountAmount = 0.0,
        public float $subTotal = 0.0,
        public float $tax = 0.0,
        public float $taxAmount = 0.0,
        public float $total = 0.0,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organizationId,
            'credit_note_id' => $this->creditNoteId,
            'service' => $this->service,
            'description' => $this->description,
            'qty' => $this->qty,
            'rate' => $this->rate,
            'discount_type' => $this->discountType,
            'discount_type_value' => $this->discountTypeValue,
            'discount_amount' => $this->discountAmount,
            'sub_total' => $this->subTotal,
            'tax' => $this->tax,
            'tax_amount' => $this->taxAmount,
            'total' => $this->total,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
