<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Vendor;
use App\Repository\VendorRepository;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;
use App\Helper\DateHelper;

class VendorService
{
    private VendorRepository $repo;

    public function __construct(VendorRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getVendor(int $id, int $orgId): Vendor
    {
        $vendor = $this->repo->find($id, $orgId);
        if ($vendor === null) {
            throw new NotFoundException('Vendor not found');
        }
        return $vendor;
    }

    public function list(int $orgId): array
    {
        return $this->repo->findAll($orgId);
    }

    public function createVendor(array $data, int $orgId, int $userId): Vendor
    {
        $this->validateVendorData($data, $orgId);

        $vendor = new Vendor(
            id: null,
            organizationId: $orgId,
            leadId: !empty($data['lead_id']) ? (int)$data['lead_id'] : null,
            vendorOwner: !empty($data['vendor_owner']) ? (int)$data['vendor_owner'] : null,
            vendorType: !empty($data['vendor_type']) ? trim((string)$data['vendor_type']) : '',
            vendorStatus: !empty($data['vendor_status']) ? (int)$data['vendor_status'] : null,
            vendorSource: !empty($data['vendor_source']) ? (int)$data['vendor_source'] : null,
            assignedTo: !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            salutation: !empty($data['salutation']) ? trim((string)$data['salutation']) : null,
            firstName: !empty($data['first_name']) ? trim((string)$data['first_name']) : null,
            lastName: !empty($data['last_name']) ? trim((string)$data['last_name']) : null,
            companyName: !empty($data['company_name']) ? trim((string)$data['company_name']) : null,
            displayName: trim((string)($data['display_name'] ?? '')),
            address: trim((string)($data['address'] ?? '')),
            email: !empty($data['email']) ? trim((string)$data['email']) : null,
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            mobile: !empty($data['mobile']) ? trim((string)$data['mobile']) : null,
            paymentTerm: !empty($data['payment_term']) ? (int)$data['payment_term'] : null,
            taxTreatment: !empty($data['tax_treatment']) ? (int)$data['tax_treatment'] : null,
            trn: !empty($data['trn']) ? trim((string)$data['trn']) : null,
            corporateTaxNumber: !empty($data['corporate_tax_number']) ? trim((string)$data['corporate_tax_number']) : null,
            licenseNumber: !empty($data['license_number']) ? (int)$data['license_number'] : null,
            licenseExpiry: !empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01',
            salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : null,
            leadCategory: !empty($data['lead_category']) ? trim((string)$data['lead_category']) : null,
            csAgent: !empty($data['cs_agent']) ? (int)$data['cs_agent'] : null,
            rating: !empty($data['rating']) ? (int)$data['rating'] : null,
            currency: !empty($data['currency']) ? (int)$data['currency'] : null,
            exchangeRate: !empty($data['exchange_rate']) ? (int)$data['exchange_rate'] : 1,
            website: !empty($data['website']) ? trim((string)$data['website']) : null,
            department: !empty($data['department']) ? trim((string)$data['department']) : null,
            designation: !empty($data['designation']) ? trim((string)$data['designation']) : null,
            x: !empty($data['x']) ? trim((string)$data['x']) : null,
            facebook: !empty($data['facebook']) ? trim((string)$data['facebook']) : null,
            instagram: !empty($data['instagram']) ? trim((string)$data['instagram']) : null,
            photo: !empty($data['photo']) ? trim((string)$data['photo']) : null,
            description: !empty($data['description']) ? trim((string)$data['description']) : null,
            tags: !empty($data['tags']) ? trim((string)$data['tags']) : null,
            contactedDate: !empty($data['contacted_date']) ? $this->convertDateTimeToDb((string)$data['contacted_date']) : null,
            approved: (bool)($data['approved'] ?? false),
            approvedBy: !empty($data['approved_by']) ? (int)$data['approved_by'] : null,
            approvedAt: !empty($data['approved_at']) ? trim((string)$data['approved_at']) : null,
            publish: (bool)($data['publish'] ?? true),
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $userId,
        );

        $saved = $this->repo->save($vendor);
        return $saved;
    }

    public function updateVendor(int $id, array $data, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $this->validateVendorData($data, $orgId, $id);

        $updated = new Vendor(
            id: $vendor->id,
            organizationId: $vendor->organizationId,
            leadId: isset($data['lead_id']) ? (!empty($data['lead_id']) ? (int)$data['lead_id'] : null) : $vendor->leadId,
            vendorOwner: isset($data['vendor_owner']) ? (!empty($data['vendor_owner']) ? (int)$data['vendor_owner'] : null) : $vendor->vendorOwner,
            vendorType: isset($data['vendor_type']) ? trim((string)$data['vendor_type']) : $vendor->vendorType,
            vendorStatus: isset($data['vendor_status']) ? (!empty($data['vendor_status']) ? (int)$data['vendor_status'] : null) : $vendor->vendorStatus,
            vendorSource: isset($data['vendor_source']) ? (!empty($data['vendor_source']) ? (int)$data['vendor_source'] : null) : $vendor->vendorSource,
            assignedTo: isset($data['assigned_to']) ? (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null) : $vendor->assignedTo,
            salutation: isset($data['salutation']) ? (!empty($data['salutation']) ? trim((string)$data['salutation']) : null) : $vendor->salutation,
            firstName: isset($data['first_name']) ? (!empty($data['first_name']) ? trim((string)$data['first_name']) : null) : $vendor->firstName,
            lastName: isset($data['last_name']) ? (!empty($data['last_name']) ? trim((string)$data['last_name']) : null) : $vendor->lastName,
            companyName: isset($data['company_name']) ? (!empty($data['company_name']) ? trim((string)$data['company_name']) : null) : $vendor->companyName,
            displayName: isset($data['display_name']) ? trim((string)$data['display_name']) : $vendor->displayName,
            address: isset($data['address']) ? trim((string)$data['address']) : $vendor->address,
            email: isset($data['email']) ? (!empty($data['email']) ? trim((string)$data['email']) : null) : $vendor->email,
            phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $vendor->phone,
            mobile: isset($data['mobile']) ? (!empty($data['mobile']) ? trim((string)$data['mobile']) : null) : $vendor->mobile,
            paymentTerm: isset($data['payment_term']) ? (!empty($data['payment_term']) ? (int)$data['payment_term'] : null) : $vendor->paymentTerm,
            taxTreatment: isset($data['tax_treatment']) ? (!empty($data['tax_treatment']) ? (int)$data['tax_treatment'] : null) : $vendor->taxTreatment,
            trn: isset($data['trn']) ? (!empty($data['trn']) ? trim((string)$data['trn']) : null) : $vendor->trn,
            corporateTaxNumber: isset($data['corporate_tax_number']) ? (!empty($data['corporate_tax_number']) ? trim((string)$data['corporate_tax_number']) : null) : $vendor->corporateTaxNumber,
            licenseNumber: isset($data['license_number']) ? (!empty($data['license_number']) ? (int)$data['license_number'] : null) : $vendor->licenseNumber,
            licenseExpiry: isset($data['license_expiry']) ? (!empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01') : $vendor->licenseExpiry,
            salesPerson: isset($data['sales_person']) ? (!empty($data['sales_person']) ? (int)$data['sales_person'] : null) : $vendor->salesPerson,
            leadCategory: isset($data['lead_category']) ? (!empty($data['lead_category']) ? trim((string)$data['lead_category']) : null) : $vendor->leadCategory,
            csAgent: isset($data['cs_agent']) ? (!empty($data['cs_agent']) ? (int)$data['cs_agent'] : null) : $vendor->csAgent,
            rating: isset($data['rating']) ? (!empty($data['rating']) ? (int)$data['rating'] : null) : $vendor->rating,
            currency: isset($data['currency']) ? (!empty($data['currency']) ? (int)$data['currency'] : null) : $vendor->currency,
            exchangeRate: isset($data['exchange_rate']) ? (int)$data['exchange_rate'] : $vendor->exchangeRate,
            website: isset($data['website']) ? (!empty($data['website']) ? trim((string)$data['website']) : null) : $vendor->website,
            department: isset($data['department']) ? (!empty($data['department']) ? trim((string)$data['department']) : null) : $vendor->department,
            designation: isset($data['designation']) ? (!empty($data['designation']) ? trim((string)$data['designation']) : null) : $vendor->designation,
            x: isset($data['x']) ? (!empty($data['x']) ? trim((string)$data['x']) : null) : $vendor->x,
            facebook: isset($data['facebook']) ? (!empty($data['facebook']) ? trim((string)$data['facebook']) : null) : $vendor->facebook,
            instagram: isset($data['instagram']) ? (!empty($data['instagram']) ? trim((string)$data['instagram']) : null) : $vendor->instagram,
            photo: isset($data['photo']) ? (!empty($data['photo']) ? trim((string)$data['photo']) : null) : $vendor->photo,
            description: isset($data['description']) ? (!empty($data['description']) ? trim((string)$data['description']) : null) : $vendor->description,
            tags: isset($data['tags']) ? (!empty($data['tags']) ? trim((string)$data['tags']) : null) : $vendor->tags,
            contactedDate: isset($data['contacted_date']) ? (!empty($data['contacted_date']) ? $this->convertDateTimeToDb((string)$data['contacted_date']) : null) : $vendor->contactedDate,
            approved: isset($data['approved']) ? (bool)$data['approved'] : $vendor->approved,
            approvedBy: isset($data['approved_by']) ? (!empty($data['approved_by']) ? (int)$data['approved_by'] : null) : $vendor->approvedBy,
            approvedAt: isset($data['approved_at']) ? (!empty($data['approved_at']) ? trim((string)$data['approved_at']) : null) : $vendor->approvedAt,
            publish: isset($data['publish']) ? (bool)$data['publish'] : $vendor->publish,
            isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $vendor->isActive,
            createdAt: $vendor->createdAt,
            updatedBy: isset($data['updated_by']) ? (int)$data['updated_by'] : $userId,
            createdBy: $vendor->createdBy,
        );

        return $this->repo->save($updated);
    }

    public function deleteVendor(int $id, int $orgId): bool
    {
        $this->getVendor($id, $orgId);
        return $this->repo->delete($id, $orgId);
    }

    public function getReceivables(int $vendorId, int $orgId): float
    {
        return $this->repo->getReceivables($vendorId, $orgId);
    }

    private function validateVendorData(array $data, int $orgId, ?int $id = null): void
    {
        $errors = [];

        $existing = null;
        if ($id !== null) {
            try {
                $existing = $this->getVendor($id, $orgId);
            } catch (NotFoundException $e) {
            }
        }

        $displayName = isset($data['display_name']) ? trim($data['display_name']) : ($existing ? $existing->displayName : '');
        if ($displayName === '') {
            $errors['display_name'] = 'Company name is mandatory.';
        }

        $address = isset($data['address']) ? trim($data['address']) : ($existing ? $existing->address : '');
        if ($address === '') {
            $errors['address'] = 'Address is mandatory.';
        }

        $email = isset($data['email']) ? trim($data['email']) : ($existing ? $existing->email : null);
        if ($email !== null && $email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please provide a valid email address.';
            } elseif ($this->repo->existsByEmail($email, $orgId, $id)) {
                $errors['email'] = 'Duplicate Email. Please enter different.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    private function convertDateToDb(string $dateStr): string
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            return '1970-01-01';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            return $dateStr;
        }
        try {
            $dt = \DateTime::createFromFormat('d-m-Y', $dateStr);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d');
            }
        } catch (\Throwable $e) {
        }
        $parts = explode('-', $dateStr);
        if (count($parts) === 3) {
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return '1970-01-01';
    }

    private function convertDateTimeToDb(string $dateTimeStr): string
    {
        $dateTimeStr = trim($dateTimeStr);
        if ($dateTimeStr === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $dateTimeStr)) {
            return $dateTimeStr;
        }
        try {
            $dt = \DateTime::createFromFormat('d-m-Y H:i', $dateTimeStr);
            if ($dt instanceof \DateTime) {
                return $dt->format('Y-m-d H:i:s');
            }
        } catch (\Throwable $e) {
        }
        return $dateTimeStr;
    }
}
