<?php

declare(strict_types=1);

namespace App\Model;

/**
 * CreditNote DTO
 *
 * Readonly data transfer object representing a credit note record.
 */
readonly class CreditNote
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $creditNoteNo,
        public string $creditNoteDate,
        public string $creditNoteStatus,
        public ?string $referenceNo = null,
        public int $customerId = 0,
        public int $invoiceId = 0,
        public int $warehouseId = 0,
        public ?string $subject = null,
        public int $paymentTerm = 0,
        public ?string $expiryDate = null,
        public ?string $expectedShipmentDate = null,
        public ?string $shipmentType = null,
        public int $salesPerson = 0,
        public ?string $jobReferenceNo = null,
        public ?string $masterAwbNo = null,
        public int $shipper = 0,
        public int $consignee = 0,
        public int $origin = 0,
        public int $destination = 0,
        public int $noOfPacks = 0,
        public float $grossWeight = 0.0,
        public float $chargeableWeight = 0.0,
        public float $volume = 0.0,
        public ?string $qrcode = null,
        public ?string $pdf = null,
        public ?string $customerNotes = null,
        public ?string $termsAndConditions = null,
        public float $grandSubtotal = 0.0,
        public string $grandDiscountType = '0.00',
        public float $grandDiscountTypeValue = 0.0,
        public float $grandDiscountAmount = 0.0,
        public float $grandAfterDiscount = 0.0,
        public float $grandTax = 0.0,
        public float $grandTotal = 0.0,
        public bool $publish = true,
        public bool $isActive = true,
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
            'credit_note_no' => $this->creditNoteNo,
            'credit_note_date' => $this->creditNoteDate,
            'credit_note_status' => $this->creditNoteStatus,
            'reference_no' => $this->referenceNo,
            'customer_id' => $this->customerId,
            'invoice_id' => $this->invoiceId,
            'warehouse_id' => $this->warehouseId,
            'subject' => $this->subject,
            'payment_term' => $this->paymentTerm,
            'expiry_date' => $this->expiryDate,
            'expected_shipment_date' => $this->expectedShipmentDate,
            'shipment_type' => $this->shipmentType,
            'sales_person' => $this->salesPerson,
            'job_reference_no' => $this->jobReferenceNo,
            'master_awb_no' => $this->masterAwbNo,
            'shipper' => $this->shipper,
            'consignee' => $this->consignee,
            'origin' => $this->origin,
            'destination' => $this->destination,
            'no_of_packs' => $this->noOfPacks,
            'gross_weight' => $this->grossWeight,
            'chargeable_weight' => $this->chargeableWeight,
            'volume' => $this->volume,
            'qrcode' => $this->qrcode,
            'pdf' => $this->pdf,
            'customer_notes' => $this->customerNotes,
            'terms_and_conditions' => $this->termsAndConditions,
            'grand_subtotal' => $this->grandSubtotal,
            'grand_discount_type' => $this->grandDiscountType,
            'grand_discount_type_value' => $this->grandDiscountTypeValue,
            'grand_discount_amount' => $this->grandDiscountAmount,
            'grand_after_discount' => $this->grandAfterDiscount,
            'grand_tax' => $this->grandTax,
            'grand_total' => $this->grandTotal,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
