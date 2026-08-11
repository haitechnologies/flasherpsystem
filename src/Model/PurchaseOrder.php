<?php

declare(strict_types=1);

namespace App\Model;

readonly class PurchaseOrder
{
    public function __construct(
        public ?int $id,
        public int $organizationId,
        public string $purchaseOrderDate,
        public int $vendorId,
        public ?string $purchaseOrderNo = null,
        public string $purchaseOrderStatus = 'draft',
        public ?string $referenceNo = null,
        public ?string $subject = null,
        public int $warehouseId = 0,
        public ?string $vendorNotes = null,
        public ?string $termsAndConditions = null,
        public float $grandSubtotal = 0.0,
        public string $grandDiscountType = '0.00',
        public float $grandDiscountTypeValue = 0.0,
        public float $grandDiscountAmount = 0.0,
        public float $grandAfterDiscount = 0.0,
        public float $grandTax = 0.0,
        public float $grandTotal = 0.0,
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
            'purchase_order_date' => $this->purchaseOrderDate,
            'vendor_id' => $this->vendorId,
            'purchase_order_no' => $this->purchaseOrderNo,
            'purchase_order_status' => $this->purchaseOrderStatus,
            'reference_no' => $this->referenceNo,
            'subject' => $this->subject,
            'warehouse_id' => $this->warehouseId,
            'vendor_notes' => $this->vendorNotes,
            'terms_and_conditions' => $this->termsAndConditions,
            'grand_subtotal' => $this->grandSubtotal,
            'grand_discount_type' => $this->grandDiscountType,
            'grand_discount_type_value' => $this->grandDiscountTypeValue,
            'grand_discount_amount' => $this->grandDiscountAmount,
            'grand_after_discount' => $this->grandAfterDiscount,
            'grand_tax' => $this->grandTax,
            'grand_total' => $this->grandTotal,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
        ];
    }
}
