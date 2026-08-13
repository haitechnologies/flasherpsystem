<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Model\Vendor;
use App\Model\VendorContact;
use App\Model\VendorAddress;
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
            vendorType: !empty($data['vendor_type']) ? trim((string)$data['vendor_type']) : 'business',
            vendorStatus: !empty($data['vendor_status']) ? (int)$data['vendor_status'] : null,
            vendorSource: !empty($data['vendor_source']) ? (int)$data['vendor_source'] : null,
            assignedTo: !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            salutation: !empty($data['salutation']) ? trim((string)$data['salutation']) : null,
            firstName: !empty($data['first_name']) ? trim((string)$data['first_name']) : null,
            lastName: !empty($data['last_name']) ? trim((string)$data['last_name']) : null,
            companyName: !empty($data['company_name']) ? trim((string)$data['company_name']) : null,
            displayName: trim((string)($data['display_name'] ?? '')),
            address: trim((string)($data['address'] ?? '')),
            openingBalance: !empty($data['opening_balance']) ? (float)$data['opening_balance'] : 0.0,
            payableAccountId: !empty($data['payable_account_id']) ? (int)$data['payable_account_id'] : null,
            creditLimit: !empty($data['credit_limit']) ? (float)$data['credit_limit'] : 0.0,
            email: !empty($data['email']) ? trim((string)$data['email']) : null,
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            mobile: !empty($data['mobile']) ? trim((string)$data['mobile']) : null,
            paymentTerm: !empty($data['payment_term']) ? (int)$data['payment_term'] : null,
            taxTreatment: !empty($data['tax_treatment']) ? (int)$data['tax_treatment'] : null,
            trn: !empty($data['trn']) ? trim((string)$data['trn']) : null,
            corporateTaxNumber: !empty($data['corporate_tax_number']) ? trim((string)$data['corporate_tax_number']) : null,
            licenseNumber: !empty($data['license_number']) ? trim((string)$data['license_number']) : null,
            licenseExpiry: !empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01',
            salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : null,
            leadCategory: !empty($data['lead_category']) ? trim((string)$data['lead_category']) : null,
            csAgent: !empty($data['cs_agent']) ? (int)$data['cs_agent'] : null,
            rating: !empty($data['rating']) ? (int)$data['rating'] : null,
            currency: !empty($data['currency']) ? (int)$data['currency'] : null,
            exchangeRate: !empty($data['exchange_rate']) ? (float)$data['exchange_rate'] : 1.0,
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

        if (!empty($data['opening_balance']) && (float)$data['opening_balance'] != 0) {
            try {
                $this->createOpeningBalanceJournal((int)$saved->id, (float)$data['opening_balance'], $orgId, $userId, $saved->displayName, $saved->payableAccountId);
            } catch (\Throwable $e) {
                error_log('Vendor opening balance journal creation failed: ' . $e->getMessage());
            }
        }

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
            vendorType: isset($data['vendor_type']) ? (!empty($data['vendor_type']) ? trim((string)$data['vendor_type']) : $vendor->vendorType) : $vendor->vendorType,
            vendorStatus: isset($data['vendor_status']) ? (!empty($data['vendor_status']) ? (int)$data['vendor_status'] : null) : $vendor->vendorStatus,
            vendorSource: isset($data['vendor_source']) ? (!empty($data['vendor_source']) ? (int)$data['vendor_source'] : null) : $vendor->vendorSource,
            assignedTo: isset($data['assigned_to']) ? (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null) : $vendor->assignedTo,
            salutation: isset($data['salutation']) ? (!empty($data['salutation']) ? trim((string)$data['salutation']) : null) : $vendor->salutation,
            firstName: isset($data['first_name']) ? (!empty($data['first_name']) ? trim((string)$data['first_name']) : null) : $vendor->firstName,
            lastName: isset($data['last_name']) ? (!empty($data['last_name']) ? trim((string)$data['last_name']) : null) : $vendor->lastName,
            companyName: isset($data['company_name']) ? (!empty($data['company_name']) ? trim((string)$data['company_name']) : null) : $vendor->companyName,
            displayName: isset($data['display_name']) ? trim((string)$data['display_name']) : $vendor->displayName,
            address: isset($data['address']) ? trim((string)$data['address']) : $vendor->address,
            openingBalance: isset($data['opening_balance']) ? (float)$data['opening_balance'] : $vendor->openingBalance,
            payableAccountId: isset($data['payable_account_id']) ? (!empty($data['payable_account_id']) ? (int)$data['payable_account_id'] : null) : $vendor->payableAccountId,
            creditLimit: isset($data['credit_limit']) ? (float)$data['credit_limit'] : $vendor->creditLimit,
            email: isset($data['email']) ? (!empty($data['email']) ? trim((string)$data['email']) : null) : $vendor->email,
            phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $vendor->phone,
            mobile: isset($data['mobile']) ? (!empty($data['mobile']) ? trim((string)$data['mobile']) : null) : $vendor->mobile,
            paymentTerm: isset($data['payment_term']) ? (!empty($data['payment_term']) ? (int)$data['payment_term'] : null) : $vendor->paymentTerm,
            taxTreatment: isset($data['tax_treatment']) ? (!empty($data['tax_treatment']) ? (int)$data['tax_treatment'] : null) : $vendor->taxTreatment,
            trn: isset($data['trn']) ? (!empty($data['trn']) ? trim((string)$data['trn']) : null) : $vendor->trn,
            corporateTaxNumber: isset($data['corporate_tax_number']) ? (!empty($data['corporate_tax_number']) ? trim((string)$data['corporate_tax_number']) : null) : $vendor->corporateTaxNumber,
            licenseNumber: isset($data['license_number']) ? (!empty($data['license_number']) ? trim((string)$data['license_number']) : null) : $vendor->licenseNumber,
            licenseExpiry: isset($data['license_expiry']) ? (!empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01') : $vendor->licenseExpiry,
            salesPerson: isset($data['sales_person']) ? (!empty($data['sales_person']) ? (int)$data['sales_person'] : null) : $vendor->salesPerson,
            leadCategory: isset($data['lead_category']) ? (!empty($data['lead_category']) ? trim((string)$data['lead_category']) : null) : $vendor->leadCategory,
            csAgent: isset($data['cs_agent']) ? (!empty($data['cs_agent']) ? (int)$data['cs_agent'] : null) : $vendor->csAgent,
            rating: isset($data['rating']) ? (!empty($data['rating']) ? (int)$data['rating'] : null) : $vendor->rating,
            currency: isset($data['currency']) ? (!empty($data['currency']) ? (int)$data['currency'] : null) : $vendor->currency,
            exchangeRate: isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : $vendor->exchangeRate,
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

        $saved = $this->repo->save($updated);

        if ($vendor->openingBalance != $saved->openingBalance) {
            $this->syncOpeningBalanceJournal((int)$saved->id, (float)$saved->openingBalance, $orgId, $userId, $saved->displayName, $saved->payableAccountId);
        }

        return $saved;
    }

    public function deleteVendor(int $id, int $orgId): bool
    {
        $this->getVendor($id, $orgId);
        return $this->repo->delete($id, $orgId);
    }

    public function getPayables(int $vendorId, int $orgId): float
    {
        return $this->repo->getPayables($vendorId, $orgId);
    }

    /**
     * Create a new contact person record
     *
     * @throws ValidationException
     */
    public function createContact(array $data, int $orgId, int $userId): VendorContact
    {
        $this->validateContactData($data, $orgId);

        $vendorId = (int)($data['vendor_id'] ?? 0);
        $isPrimary = (bool)($data['is_primary'] ?? false);

        if ($isPrimary) {
            $this->repo->clearPrimaryContacts($vendorId, $orgId);
        }

        $contact = new VendorContact(
            id: null,
            organizationId: $orgId,
            isPrimary: $isPrimary,
            vendorId: $vendorId,
            firstName: trim((string)$data['first_name']),
            lastName: trim((string)$data['last_name']),
            position: !empty($data['position']) ? trim((string)$data['position']) : null,
            email: trim((string)$data['email']),
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            notes: !empty($data['notes']) ? trim((string)$data['notes']) : null,
            publish: (bool)($data['publish'] ?? true),
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $userId
        );

        return $this->repo->saveContact($contact);
    }

    /**
     * Update an existing contact person record
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateContact(int $id, array $data, int $orgId, int $userId): VendorContact
    {
        $contact = $this->repo->findContact($id, $orgId);
        if ($contact === null) {
            throw new NotFoundException("Contact with ID {$id} not found.");
        }

        $this->validateContactData($data, $orgId, $id);

        $isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : $contact->isPrimary;
        if ($isPrimary && !$contact->isPrimary) {
            $this->repo->clearPrimaryContacts($contact->vendorId, $orgId);
        }

        $updatedContact = new VendorContact(
            id: $contact->id,
            organizationId: $contact->organizationId,
            isPrimary: $isPrimary,
            vendorId: $contact->vendorId,
            firstName: isset($data['first_name']) ? trim((string)$data['first_name']) : $contact->firstName,
            lastName: isset($data['last_name']) ? trim((string)$data['last_name']) : $contact->lastName,
            position: isset($data['position']) ? (!empty($data['position']) ? trim((string)$data['position']) : null) : $contact->position,
            email: isset($data['email']) ? trim((string)$data['email']) : $contact->email,
            phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $contact->phone,
            notes: isset($data['notes']) ? (!empty($data['notes']) ? trim((string)$data['notes']) : null) : $contact->notes,
            publish: isset($data['publish']) ? (bool)$data['publish'] : $contact->publish,
            isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $contact->isActive,
            createdAt: $contact->createdAt,
            createdBy: $contact->createdBy,
            updatedBy: $userId
        );

        return $this->repo->saveContact($updatedContact);
    }

    /**
     * Delete a contact person record
     *
     * @throws NotFoundException
     */
    public function deleteContact(int $id, int $orgId): bool
    {
        $contact = $this->repo->findContact($id, $orgId);
        if ($contact === null) {
            throw new NotFoundException("Contact with ID {$id} not found.");
        }
        return $this->repo->deleteContact($id, $orgId);
    }

    public function getContactsByVendor(int $vendorId, int $orgId): array
    {
        return $this->repo->findContactsByVendor($vendorId, $orgId);
    }

    /**
     * Create a new address record
     *
     * @throws ValidationException
     */
    public function createAddress(array $data, int $orgId, int $userId): VendorAddress
    {
        $this->validateAddressData($data, $orgId);

        $address = new VendorAddress(
            id: null,
            organizationId: $orgId,
            type: trim((string)$data['type']),
            vendorId: (int)($data['vendor_id'] ?? 0),
            attention: !empty($data['attention']) ? trim((string)$data['attention']) : null,
            country: (int)$data['country'],
            addressLine1: !empty($data['address_line1']) ? trim((string)$data['address_line1']) : null,
            addressLine2: !empty($data['address_line2']) ? trim((string)$data['address_line2']) : null,
            city: !empty($data['city']) ? trim((string)$data['city']) : null,
            state: !empty($data['state']) ? trim((string)$data['state']) : null,
            zipcode: !empty($data['zipcode']) ? trim((string)$data['zipcode']) : null,
            phone: !empty($data['phone']) ? trim((string)$data['phone']) : null,
            fax: !empty($data['fax']) ? trim((string)$data['fax']) : null,
            publish: (bool)($data['publish'] ?? true),
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $userId
        );

        return $this->repo->saveAddress($address);
    }

    /**
     * Update an existing address record
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateAddress(int $id, array $data, int $orgId, int $userId): VendorAddress
    {
        $address = $this->repo->findAddress($id, $orgId);
        if ($address === null) {
            throw new NotFoundException("Address with ID {$id} not found.");
        }

        $this->validateAddressData($data, $orgId, $id);

        $updatedAddress = new VendorAddress(
            id: $address->id,
            organizationId: $address->organizationId,
            type: $address->type,
            vendorId: $address->vendorId,
            attention: isset($data['attention']) ? (!empty($data['attention']) ? trim((string)$data['attention']) : null) : $address->attention,
            country: isset($data['country']) ? (int)$data['country'] : $address->country,
            addressLine1: isset($data['address_line1']) ? (!empty($data['address_line1']) ? trim((string)$data['address_line1']) : null) : $address->addressLine1,
            addressLine2: isset($data['address_line2']) ? (!empty($data['address_line2']) ? trim((string)$data['address_line2']) : null) : $address->addressLine2,
            city: isset($data['city']) ? (!empty($data['city']) ? trim((string)$data['city']) : null) : $address->city,
            state: isset($data['state']) ? (!empty($data['state']) ? trim((string)$data['state']) : null) : $address->state,
            zipcode: isset($data['zipcode']) ? (!empty($data['zipcode']) ? trim((string)$data['zipcode']) : null) : $address->zipcode,
            phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $address->phone,
            fax: isset($data['fax']) ? (!empty($data['fax']) ? trim((string)$data['fax']) : null) : $address->fax,
            publish: isset($data['publish']) ? (bool)$data['publish'] : $address->publish,
            isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $address->isActive,
            createdAt: $address->createdAt,
            createdBy: $address->createdBy,
            updatedBy: $userId
        );

        return $this->repo->saveAddress($updatedAddress);
    }

    /**
     * Delete an address record
     *
     * @throws NotFoundException
     */
    public function deleteAddress(int $id, int $orgId): bool
    {
        $address = $this->repo->findAddress($id, $orgId);
        if ($address === null) {
            throw new NotFoundException("Address with ID {$id} not found.");
        }
        return $this->repo->deleteAddress($id, $orgId);
    }

    public function getAddressesByVendor(int $vendorId, int $orgId): array
    {
        return $this->repo->findAddressesByVendor($vendorId, $orgId);
    }

    /**
     * Validate contact inputs
     *
     * @throws ValidationException
     */
    private function validateContactData(array $data, int $orgId, ?int $id = null): void
    {
        $errors = [];

        $existing = null;
        if ($id !== null) {
            $existing = $this->repo->findContact($id, $orgId);
        }

        $firstName = isset($data['first_name']) ? trim($data['first_name']) : ($existing ? $existing->firstName : '');
        if ($firstName === '') {
            $errors['first_name'] = 'First name is mandatory.';
        }

        $lastName = isset($data['last_name']) ? trim($data['last_name']) : ($existing ? $existing->lastName : '');
        if ($lastName === '') {
            $errors['last_name'] = 'Last name is mandatory.';
        }

        $email = isset($data['email']) ? trim($data['email']) : ($existing ? $existing->email : '');
        if ($email === '') {
            $errors['email'] = 'Email is mandatory.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please provide a valid email address.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate address inputs
     *
     * @throws ValidationException
     */
    private function validateAddressData(array $data, int $orgId, ?int $id = null): void
    {
        $errors = [];

        $existing = null;
        if ($id !== null) {
            $existing = $this->repo->findAddress($id, $orgId);
        }

        $type = isset($data['type']) ? trim($data['type']) : ($existing ? $existing->type : '');
        if ($type === '') {
            $errors['type'] = 'Address type is mandatory.';
        }

        $country = isset($data['country']) ? (int)$data['country'] : ($existing ? $existing->country : 0);
        if ($country <= 0) {
            $errors['country'] = 'Please select a country.';
        }

        $addressLine1 = isset($data['address_line1']) ? trim($data['address_line1']) : ($existing ? $existing->addressLine1 : '');
        if ($addressLine1 === '') {
            $errors['address_line1'] = 'Address Line 1 is mandatory.';
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Approve vendor
     */
    public function approveVendor(int $id, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $updated = $this->rebuildVendor($vendor, [
            'approved' => true,
            'approvedBy' => $userId,
            'approvedAt' => date('Y-m-d H:i:s'),
            'updatedBy' => $userId,
        ]);
        return $this->repo->save($updated);
    }

    /**
     * Disapprove vendor
     */
    public function disapproveVendor(int $id, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $updated = $this->rebuildVendor($vendor, [
            'approved' => false,
            'approvedBy' => null,
            'approvedAt' => null,
            'updatedBy' => $userId,
        ]);
        return $this->repo->save($updated);
    }

    /**
     * Update vendor opening balance
     */
    public function updateOpeningBalance(int $id, float $balance, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $db = Container::getInstance()->get(Database::class);
        $db->beginTransaction();
        try {
            $updated = $this->rebuildVendor($vendor, [
                'openingBalance' => $balance,
                'updatedBy' => $userId,
            ]);
            $saved = $this->repo->save($updated);

            $this->createOpeningBalanceJournal($id, $balance, $orgId, $userId, $vendor->displayName, $vendor->payableAccountId);

            $db->commit();
            return $saved;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function createOpeningBalanceJournal(int $vendorId, float $balance, int $orgId, int $userId, string $vendorName, ?int $payableAccountId = null): void
    {
        if ($balance == 0) {
            return;
        }

        $journalService = Container::getInstance()->get(JournalService::class);

        $journalData = [
            'journal_status' => 'approved',
            'journal_date' => date('Y-m-d'),
            'reference_no' => 'VENDOR-OB-' . $vendorId,
            'notes' => "Opening balance for vendor: {$vendorName}",
            'reporting_method' => 'accrual',
            'reference_type' => 'vendor_opening_balance',
            'reference_id' => $vendorId,
            'currency' => 'AED',
            'warehouse_id' => 0,
        ];

        $itemsData = [
            [
                'account' => $payableAccountId ?? 26,
                'description' => "Opening Balance — {$vendorName}",
                'debit' => abs($balance),
                'credit' => 0.0,
            ],
            [
                'account' => 118,
                'description' => "Opening Balance Offset — {$vendorName}",
                'debit' => 0.0,
                'credit' => abs($balance),
            ],
        ];

        $journalService->createJournal($journalData, $itemsData, $orgId, $userId);
    }

    private function syncOpeningBalanceJournal(int $vendorId, float $balance, int $orgId, int $userId, string $vendorName, ?int $payableAccountId = null): void
    {
        try {
            $journalService = Container::getInstance()->get(JournalService::class);
            $journalRepo = Container::getInstance()->get(\App\Repository\JournalRepository::class);

            $existing = $journalRepo->findByReference('vendor_opening_balance', $vendorId, $orgId);
            foreach ($existing as $journal) {
                $journalService->deleteJournal((int)$journal->id, $orgId);
            }

            if ($balance != 0) {
                $this->createOpeningBalanceJournal($vendorId, $balance, $orgId, $userId, $vendorName, $payableAccountId);
            }
        } catch (\Throwable $e) {
            error_log('Vendor opening balance journal sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Clone vendor
     */
    public function cloneVendor(int $id, int $orgId, int $userId): Vendor
    {
        $this->getVendor($id, $orgId);
        $newId = $this->repo->clone($id, $orgId, $userId);
        return $this->getVendor($newId, $orgId);
    }

    /**
     * Mark vendor as active
     */
    public function markAsActive(int $id, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $updated = $this->rebuildVendor($vendor, ['isActive' => true, 'updatedBy' => $userId]);
        return $this->repo->save($updated);
    }

    /**
     * Mark vendor as inactive
     */
    public function markAsInactive(int $id, int $orgId, int $userId): Vendor
    {
        $vendor = $this->getVendor($id, $orgId);
        $updated = $this->rebuildVendor($vendor, ['isActive' => false, 'updatedBy' => $userId]);
        return $this->repo->save($updated);
    }

    /**
     * Mark contact as primary
     */
    public function markContactAsPrimary(int $contactId, int $vendorId, int $orgId): void
    {
        $this->repo->clearPrimaryContacts($vendorId, $orgId);
        $contact = $this->repo->findContact($contactId, $orgId);
        if ($contact !== null && $contact->vendorId === $vendorId) {
            $updated = new VendorContact(
                id: $contact->id,
                organizationId: $contact->organizationId,
                isPrimary: true,
                vendorId: $contact->vendorId,
                firstName: $contact->firstName,
                lastName: $contact->lastName,
                position: $contact->position,
                email: $contact->email,
                phone: $contact->phone,
                notes: $contact->notes,
                publish: $contact->publish,
                isActive: $contact->isActive,
                createdAt: $contact->createdAt,
                createdBy: $contact->createdBy
            );
            $this->repo->saveContact($updated);
        }
    }

    /**
     * Build the vendor activity timeline (entity logs + notes)
     *
     * @return array<int, array{
     *     type: string, icon: string, title: string, body: string,
     *     user_name: string, details_url: ?string, created_at: string
     * }>
     */
    public function getActivityTimeline(int $vendorId, int $orgId): array
    {
        $db = Container::getInstance()->get(Database::class);

        $logRows = $db->fetchAll(
            "SELECT id, module, action, record_id, created_by, created_at
             FROM `" . DB::ENTITY_LOGS . "`
             WHERE entity_type = 'vendor' AND entity_id = :id
               AND (organization_id IS NULL OR organization_id = :org)
             ORDER BY created_at DESC LIMIT 30",
            ['id' => $vendorId, 'org' => $orgId]
        );

        $noteRows = $db->fetchAll(
            "SELECT id, notes, created_by, created_at
             FROM `" . DB::ENTITY_NOTES . "`
             WHERE entity_type = 'vendor' AND entity_id = :id AND is_active = 1
               AND (organization_id IS NULL OR organization_id = :org)
             ORDER BY created_at DESC LIMIT 30",
            ['id' => $vendorId, 'org' => $orgId]
        );

        $userIds = [];
        foreach ($logRows as $row) {
            if (!empty($row['created_by'])) {
                $userIds[(int)$row['created_by']] = true;
            }
        }
        foreach ($noteRows as $row) {
            if (!empty($row['created_by'])) {
                $userIds[(int)$row['created_by']] = true;
            }
        }

        $userNames = [];
        if ($userIds !== []) {
            $ids = array_keys($userIds);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $users = $db->fetchAll(
                "SELECT id, full_name FROM `" . DB::USERS . "` WHERE id IN ($placeholders)",
                $ids
            );
            foreach ($users as $u) {
                $userNames[(int)$u['id']] = (string)($u['full_name'] ?? '');
            }
        }

        $purchaseRows = [];
        $paymentRows = [];
        foreach ($logRows as $row) {
            $recordId = (int)($row['record_id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }
            if ($row['module'] === 'purchase') {
                $purchaseRows[$recordId] = true;
            } elseif ($row['module'] === 'payment') {
                $paymentRows[$recordId] = true;
            }
        }

        $purchases = [];
        if ($purchaseRows !== []) {
            $ids = array_keys($purchaseRows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT id, purchase_no, grand_total FROM `" . DB::PURCHASES . "` WHERE id IN ($placeholders)",
                $ids
            );
            foreach ($rows as $r) {
                $purchases[(int)$r['id']] = $r;
            }
        }

        $payments = [];
        if ($paymentRows !== []) {
            $ids = array_keys($paymentRows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT id, reference_no, total_amount_paid FROM `" . DB::PAYMENTS_MADE . "` WHERE id IN ($placeholders)",
                $ids
            );
            foreach ($rows as $r) {
                $payments[(int)$r['id']] = $r;
            }
        }

        $currency = defined('BASE_CURRENCY') && is_array(BASE_CURRENCY) ? (BASE_CURRENCY['code'] ?? 'AED') : 'AED';
        $entries = [];

        foreach ($logRows as $row) {
            $module = (string)$row['module'];
            $action = (string)$row['action'];
            $recordId = (int)($row['record_id'] ?? 0);

            $entry = [
                'type' => 'action',
                'icon' => 'ph-user',
                'title' => '',
                'body' => '',
                'user_name' => $this->resolveUserName($userNames, $row['created_by'] ?? null),
                'details_url' => null,
                'created_at' => (string)$row['created_at'],
            ];

            if ($module === 'vendor') {
                $entry['icon'] = 'ph-user';
                $entry['title'] = match ($action) {
                    'add', 'created' => 'Vendor added',
                    'edit', 'updated' => 'Vendor updated',
                    'approved' => 'Vendor approved',
                    'disapproved' => 'Vendor disapproved',
                    'clone' => 'Vendor cloned',
                    'active' => 'Vendor marked active',
                    'inactive' => 'Vendor marked inactive',
                    'opening_balance' => 'Opening balance updated',
                    default => 'Vendor updated',
                };
            } elseif ($module === 'contact') {
                $entry['icon'] = 'ph-identification-card';
                $entry['title'] = match ($action) {
                    'add' => 'Contact added',
                    'edit' => 'Contact updated',
                    'delete' => 'Contact deleted',
                    'primary' => 'Contact set as primary',
                    default => 'Contact updated',
                };
            } elseif ($module === 'address') {
                $entry['icon'] = 'ph-map-pin';
                $entry['title'] = 'Address updated';
            } elseif ($module === 'purchase') {
                $entry['icon'] = 'ph-shopping-cart-simple';
                $entry['title'] = $action === 'add' ? 'Purchase added' : 'Purchase updated';
                if ($recordId > 0 && isset($purchases[$recordId])) {
                    $p = $purchases[$recordId];
                    $amount = number_format((float)($p['grand_total'] ?? 0), 2, '.', '');
                    $entry['body'] = 'Purchase ' . $p['purchase_no'] . ' of amount ' . $currency . $amount . ' created';
                    $entry['details_url'] = 'purchase_overview.php?purchase_id=' . $recordId;
                }
            } elseif ($module === 'payment') {
                $entry['icon'] = 'ph-bank';
                $entry['title'] = $action === 'add' ? 'Payment made' : 'Payment updated';
                if ($recordId > 0 && isset($payments[$recordId])) {
                    $p = $payments[$recordId];
                    $amount = number_format((float)($p['total_amount_paid'] ?? 0), 2, '.', '');
                    $entry['body'] = 'Payment of amount ' . $currency . $amount . ' made';
                    $entry['details_url'] = 'payments_made_overview.php?payment_id=' . $recordId;
                }
            } elseif ($module === 'comment' || $module === 'comments') {
                $entry['icon'] = 'ph-chat-centered-text';
                $entry['title'] = match ($action) {
                    'added', 'add' => 'Comment added',
                    'updated', 'edit' => 'Comment updated',
                    'deleted', 'delete' => 'Comment deleted',
                    default => 'Comment added',
                };
            }

            $entries[] = $entry;
        }

        foreach ($noteRows as $row) {
            $entries[] = [
                'type' => 'note',
                'icon' => 'ph-chat-centered-text',
                'title' => '',
                'body' => (string)($row['notes'] ?? ''),
                'user_name' => $this->resolveUserName($userNames, $row['created_by'] ?? null),
                'details_url' => null,
                'created_at' => (string)$row['created_at'],
            ];
        }

        usort($entries, function (array $a, array $b): int {
            return strcmp((string)$b['created_at'], (string)$a['created_at']);
        });

        return $entries;
    }

    private function resolveUserName(array $userNames, $createdBy): string
    {
        $userId = (int)$createdBy;
        if ($userId > 0 && isset($userNames[$userId]) && $userNames[$userId] !== '') {
            return $userNames[$userId];
        }
        return 'System';
    }

    private function rebuildVendor(Vendor $vendor, array $changes): Vendor
    {
        $data = [
            'leadId' => $vendor->leadId,
            'vendorOwner' => $vendor->vendorOwner,
            'vendorType' => $vendor->vendorType,
            'vendorStatus' => $vendor->vendorStatus,
            'vendorSource' => $vendor->vendorSource,
            'assignedTo' => $vendor->assignedTo,
            'salutation' => $vendor->salutation,
            'firstName' => $vendor->firstName,
            'lastName' => $vendor->lastName,
            'companyName' => $vendor->companyName,
            'displayName' => $vendor->displayName,
            'address' => $vendor->address,
            'openingBalance' => $vendor->openingBalance,
            'payableAccountId' => $vendor->payableAccountId,
            'creditLimit' => $vendor->creditLimit,
            'email' => $vendor->email,
            'phone' => $vendor->phone,
            'mobile' => $vendor->mobile,
            'paymentTerm' => $vendor->paymentTerm,
            'taxTreatment' => $vendor->taxTreatment,
            'trn' => $vendor->trn,
            'corporateTaxNumber' => $vendor->corporateTaxNumber,
            'licenseNumber' => $vendor->licenseNumber,
            'licenseExpiry' => $vendor->licenseExpiry,
            'salesPerson' => $vendor->salesPerson,
            'leadCategory' => $vendor->leadCategory,
            'csAgent' => $vendor->csAgent,
            'rating' => $vendor->rating,
            'currency' => $vendor->currency,
            'exchangeRate' => $vendor->exchangeRate,
            'website' => $vendor->website,
            'department' => $vendor->department,
            'designation' => $vendor->designation,
            'x' => $vendor->x,
            'facebook' => $vendor->facebook,
            'instagram' => $vendor->instagram,
            'photo' => $vendor->photo,
            'description' => $vendor->description,
            'tags' => $vendor->tags,
            'contactedDate' => $vendor->contactedDate,
            'approved' => $vendor->approved,
            'approvedBy' => $vendor->approvedBy,
            'approvedAt' => $vendor->approvedAt,
            'publish' => $vendor->publish,
            'isActive' => $vendor->isActive,
            'updatedBy' => $vendor->updatedBy,
        ];
        $data = array_merge($data, $changes);

        return new Vendor(
            id: $vendor->id,
            organizationId: $vendor->organizationId,
            leadId: $data['leadId'],
            vendorOwner: $data['vendorOwner'],
            vendorType: $data['vendorType'],
            vendorStatus: $data['vendorStatus'],
            vendorSource: $data['vendorSource'],
            assignedTo: $data['assignedTo'],
            salutation: $data['salutation'],
            firstName: $data['firstName'],
            lastName: $data['lastName'],
            companyName: $data['companyName'],
            displayName: $data['displayName'],
            address: $data['address'],
            openingBalance: $data['openingBalance'],
            payableAccountId: $data['payableAccountId'],
            creditLimit: $data['creditLimit'],
            email: $data['email'],
            phone: $data['phone'],
            mobile: $data['mobile'],
            paymentTerm: $data['paymentTerm'],
            taxTreatment: $data['taxTreatment'],
            trn: $data['trn'],
            corporateTaxNumber: $data['corporateTaxNumber'],
            licenseNumber: $data['licenseNumber'],
            licenseExpiry: $data['licenseExpiry'],
            salesPerson: $data['salesPerson'],
            leadCategory: $data['leadCategory'],
            csAgent: $data['csAgent'],
            rating: $data['rating'],
            currency: $data['currency'],
            exchangeRate: $data['exchangeRate'],
            website: $data['website'],
            department: $data['department'],
            designation: $data['designation'],
            x: $data['x'],
            facebook: $data['facebook'],
            instagram: $data['instagram'],
            photo: $data['photo'],
            description: $data['description'],
            tags: $data['tags'],
            contactedDate: $data['contactedDate'],
            approved: (bool)$data['approved'],
            approvedBy: $data['approvedBy'],
            approvedAt: $data['approvedAt'],
            publish: (bool)$data['publish'],
            isActive: (bool)$data['isActive'],
            createdAt: $vendor->createdAt,
            updatedAt: $vendor->updatedAt,
            updatedBy: $data['updatedBy'],
            createdBy: $vendor->createdBy,
        );
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

        $openingBalance = $data['opening_balance'] ?? ($existing ? $existing->openingBalance : null);
        if ($openingBalance !== null && $openingBalance !== '' && !is_numeric($openingBalance)) {
            $errors['opening_balance'] = 'Opening balance must be a valid number.';
        }

        $creditLimit = $data['credit_limit'] ?? ($existing ? $existing->creditLimit : null);
        if ($creditLimit !== null && $creditLimit !== '' && !is_numeric($creditLimit)) {
            $errors['credit_limit'] = 'Credit limit must be a valid number.';
        }

        $exchangeRate = $data['exchange_rate'] ?? ($existing ? $existing->exchangeRate : null);
        if ($exchangeRate !== null && $exchangeRate !== '') {
            if (!is_numeric($exchangeRate)) {
                $errors['exchange_rate'] = 'Exchange rate must be a valid number.';
            } elseif ((float)$exchangeRate <= 0) {
                $errors['exchange_rate'] = 'Exchange rate must be a positive number.';
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
