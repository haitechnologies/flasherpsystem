<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Customer DTO
 *
 * Readonly data transfer object representing a customer record.
 */
readonly class Customer
{
    public function __construct(
        public ?int $id,
        public ?int $organizationId,
        public ?int $leadId,
        public ?int $customerOwner,
        public string $customerType,
        public ?int $customerStatus,
        public ?int $customerSource,
        public ?int $assignedTo,
        public ?string $salutation,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $companyName,
        public string $displayName,
        public string $address,
        public ?string $email,
        public ?string $phone,
        public ?string $mobile,
        public ?int $paymentTerm,
        public ?int $taxTreatment,
        public ?string $trn,
        public ?string $corporateTaxNumber,
        public ?int $licenseNumber,
        public ?string $licenseExpiry,
        public ?int $salesPerson,
        public ?string $leadCategory,
        public ?int $csAgent,
        public ?int $rating,
        public ?int $currency,
        public float $openingBalance = 0.00,
        public ?int $receivableAccountId = null,
        public int $exchangeRate = 1,
        public ?string $website = null,
        public ?string $department = null,
        public ?string $designation = null,
        public ?string $x = null,
        public ?string $facebook = null,
        public ?string $instagram = null,
        public ?string $photo = null,
        public ?string $description = null,
        public ?string $tags = null,
        public ?string $contactedDate = null,
        public bool $approved = false,
        public ?int $approvedBy = null,
        public ?string $approvedAt = null,
        public bool $publish = false,
        public bool $isActive = false,
        public ?string $createdAt = null,
        public ?string $updatedAt = null,
        public ?int $updatedBy = null,
        public int $createdBy = 0,
        public float $creditLimit = 0.00,
        public ?string $discountType = null,
        public float $discountTypeValue = 0.00,
        public string $subscriptionTier = 'registered',
        public ?string $subscriptionExpiresAt = null
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
            'lead_id' => $this->leadId,
            'customer_owner' => $this->customerOwner,
            'customer_type' => $this->customerType,
            'customer_status' => $this->customerStatus,
            'customer_source' => $this->customerSource,
            'assigned_to' => $this->assignedTo,
            'salutation' => $this->salutation,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'company_name' => $this->companyName,
            'display_name' => $this->displayName,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'payment_term' => $this->paymentTerm,
            'tax_treatment' => $this->taxTreatment,
            'trn' => $this->trn,
            'corporate_tax_number' => $this->corporateTaxNumber,
            'license_number' => $this->licenseNumber,
            'license_expiry' => $this->licenseExpiry,
            'sales_person' => $this->salesPerson,
            'lead_category' => $this->leadCategory,
            'cs_agent' => $this->csAgent,
            'rating' => $this->rating,
            'currency' => $this->currency,
            'opening_balance' => $this->openingBalance,
            'receivable_account_id' => $this->receivableAccountId,
            'exchange_rate' => $this->exchangeRate,
            'website' => $this->website,
            'department' => $this->department,
            'designation' => $this->designation,
            'x' => $this->x,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'photo' => $this->photo,
            'description' => $this->description,
            'tags' => $this->tags,
            'contacted_date' => $this->contactedDate,
            'approved' => $this->approved ? 1 : 0,
            'approved_by' => $this->approvedBy,
            'approved_at' => $this->approvedAt,
            'publish' => $this->publish ? 1 : 0,
            'is_active' => $this->isActive ? 1 : 0,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'updated_by' => $this->updatedBy,
            'created_by' => $this->createdBy,
            'credit_limit' => $this->creditLimit,
            'discount_type' => $this->discountType,
            'discount_type_value' => $this->discountTypeValue,
            'subscription_tier' => $this->subscriptionTier,
            'subscription_expires_at' => $this->subscriptionExpiresAt,
        ];
    }
}
