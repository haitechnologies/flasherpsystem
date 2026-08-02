<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Quotation DTO
 *
 * Readonly data transfer object representing a quotation record.
 */
readonly class Quotation
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $quotationNo,
        public int $customerId,
        public string $quotationStatus,
        public string $quotationDate,
        public string $expiryDate,
        public int $leadId = 0,
        public int $warehouseId = 0,
        public ?string $expectedShipmentDate = null,
        public int $paymentTerm = 0,
        public ?string $shipmentType = null,
        public int $salesPerson = 0,
        public ?string $jobReferenceNo = null,
        public ?string $masterAwbNo = null,
        public ?string $hwbHbol = null,
        public int $shipper = 0,
        public int $consignee = 0,
        public int $origin = 0,
        public int $originCountry = 0,
        public int $destination = 0,
        public int $destinationCountry = 0,
        public int $noOfPacks = 0,
        public float $grossWeight = 0.0,
        public float $chargeableWeight = 0.0,
        public float $volume = 0.0,
        public ?string $termsAndConditions = null,
        public float $grandSubtotal = 0.0,
        public string $grandDiscountType = '0.00',
        public float $grandDiscountTypeValue = 0.0,
        public float $grandDiscountAmount = 0.0,
        public float $grandAfterDiscount = 0.0,
        public ?string $customerNotes = null,
        public float $grandTax = 0.0,
        public float $grandTotal = 0.0,
        public bool $publish = true,
        public bool $isActive = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0,
        public ?string $pdf = null
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
            'quotation_no' => $this->quotationNo,
            'customer_id' => $this->customerId,
            'quotation_status' => $this->quotationStatus,
            'quotation_date' => $this->quotationDate,
            'expiry_date' => $this->expiryDate,
            'lead_id' => $this->leadId,
            'warehouse_id' => $this->warehouseId,
            'expected_shipment_date' => $this->expectedShipmentDate,
            'payment_term' => $this->paymentTerm,
            'shipment_type' => $this->shipmentType,
            'sales_person' => $this->salesPerson,
            'job_reference_no' => $this->jobReferenceNo,
            'mawb_bol' => $this->masterAwbNo,
            'hwb_hbol' => $this->hwbHbol,
            'shipper_id' => $this->shipper,
            'consignee_id' => $this->consignee,
            'origin_port' => $this->origin,
            'origin_country' => $this->originCountry,
            'destination_port' => $this->destination,
            'destination_country' => $this->destinationCountry,
            'no_of_packs' => $this->noOfPacks,
            'gross_weight' => $this->grossWeight,
            'chargeable_weight' => $this->chargeableWeight,
            'volume' => $this->volume,
            'terms_and_conditions' => $this->termsAndConditions,
            'grand_subtotal' => $this->grandSubtotal,
            'grand_discount_type' => $this->grandDiscountType,
            'grand_discount_type_value' => $this->grandDiscountTypeValue,
            'grand_discount_amount' => $this->grandDiscountAmount,
            'grand_after_discount' => $this->grandAfterDiscount,
            'customer_notes' => $this->customerNotes,
            'grand_tax' => $this->grandTax,
            'grand_total' => $this->grandTotal,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
            'pdf' => $this->pdf
        ];
    }
}
