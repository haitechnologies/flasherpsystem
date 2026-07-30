<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Customer;
use App\Model\CustomerContact;
use App\Model\CustomerAddress;
use App\Repository\CustomerRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;
use App\Security\DisposableEmailValidator;

/**
 * Customer Service
 *
 * Implements business logic and validations for customer records,
 * contacts, and addresses.
 */
class CustomerService
{
    private CustomerRepository $customerRepo;
    private DisposableEmailValidator $emailValidator;

    public function __construct(CustomerRepository $customerRepo, ?DisposableEmailValidator $emailValidator = null)
    {
        $this->customerRepo = $customerRepo;
        $this->emailValidator = $emailValidator ?? new DisposableEmailValidator();
    }

    /**
     * Retrieve customer by ID and organization
     *
     * @throws NotFoundException
     */
    public function getCustomer(int $id, int $orgId): Customer
    {
        $customer = $this->customerRepo->find($id, $orgId);
        if ($customer === null) {
            throw new NotFoundException("Customer with ID {$id} not found.");
        }
        return $customer;
    }

    /**
     * Create a new customer record
     *
     * @throws ValidationException
     */
    public function createCustomer(array $data, int $orgId, int $userId): Customer
    {
        $this->validateCustomerData($data, $orgId);

        $customer = new Customer(
            id: null,
            organizationId: $orgId,
            leadId: !empty($data['lead_id']) ? (int)$data['lead_id'] : null,
            customerOwner: !empty($data['customer_owner']) ? (int)$data['customer_owner'] : null,
            customerType: !empty($data['customer_type']) ? trim((string)$data['customer_type']) : 'business',
            customerStatus: !empty($data['customer_status']) ? (int)$data['customer_status'] : null,
            customerSource: !empty($data['customer_source']) ? (int)$data['customer_source'] : null,
            assignedTo: !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
            salutation: !empty($data['salutation']) ? trim((string)$data['salutation']) : null,
            firstName: !empty($data['first_name']) ? trim((string)$data['first_name']) : null,
            lastName: !empty($data['last_name']) ? trim((string)$data['last_name']) : null,
            companyName: !empty($data['company_name']) ? trim((string)$data['company_name']) : null,
            displayName: trim((string)$data['display_name']),
            address: trim((string)$data['address']),
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
            openingBalance: !empty($data['opening_balance']) ? (float)$data['opening_balance'] : 0.00,
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
            creditLimit: !empty($data['credit_limit']) ? (float)$data['credit_limit'] : 0.00,
            discountType: !empty($data['discount_type']) ? trim((string)$data['discount_type']) : null,
            discountTypeValue: !empty($data['discount_type_value']) ? (float)$data['discount_type_value'] : 0.00,
            subscriptionTier: !empty($data['subscription_tier']) ? trim((string)$data['subscription_tier']) : 'registered',
            subscriptionExpiresAt: !empty($data['subscription_expires_at']) ? trim((string)$data['subscription_expires_at']) : null
        );

        $saved = $this->customerRepo->save($customer);

        return $saved;
    }

    /**
     * Update an existing customer record
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateCustomer(int $id, array $data, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $this->validateCustomerData($data, $orgId, $id);

        $updatedCustomer = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: isset($data['lead_id']) ? (!empty($data['lead_id']) ? (int)$data['lead_id'] : null) : $customer->leadId,
            customerOwner: isset($data['customer_owner']) ? (!empty($data['customer_owner']) ? (int)$data['customer_owner'] : null) : $customer->customerOwner,
            customerType: isset($data['customer_type']) ? trim((string)$data['customer_type']) : $customer->customerType,
            customerStatus: isset($data['customer_status']) ? (!empty($data['customer_status']) ? (int)$data['customer_status'] : null) : $customer->customerStatus,
            customerSource: isset($data['customer_source']) ? (!empty($data['customer_source']) ? (int)$data['customer_source'] : null) : $customer->customerSource,
            assignedTo: isset($data['assigned_to']) ? (!empty($data['assigned_to']) ? (int)$data['assigned_to'] : null) : $customer->assignedTo,
            salutation: isset($data['salutation']) ? (!empty($data['salutation']) ? trim((string)$data['salutation']) : null) : $customer->salutation,
            firstName: isset($data['first_name']) ? (!empty($data['first_name']) ? trim((string)$data['first_name']) : null) : $customer->firstName,
            lastName: isset($data['last_name']) ? (!empty($data['last_name']) ? trim((string)$data['last_name']) : null) : $customer->lastName,
            companyName: isset($data['company_name']) ? (!empty($data['company_name']) ? trim((string)$data['company_name']) : null) : $customer->companyName,
            displayName: isset($data['display_name']) ? trim((string)$data['display_name']) : $customer->displayName,
            address: isset($data['address']) ? trim((string)$data['address']) : $customer->address,
            email: isset($data['email']) ? (!empty($data['email']) ? trim((string)$data['email']) : null) : $customer->email,
            phone: isset($data['phone']) ? (!empty($data['phone']) ? trim((string)$data['phone']) : null) : $customer->phone,
            mobile: isset($data['mobile']) ? (!empty($data['mobile']) ? trim((string)$data['mobile']) : null) : $customer->mobile,
            paymentTerm: isset($data['payment_term']) ? (!empty($data['payment_term']) ? (int)$data['payment_term'] : null) : $customer->paymentTerm,
            taxTreatment: isset($data['tax_treatment']) ? (!empty($data['tax_treatment']) ? (int)$data['tax_treatment'] : null) : $customer->taxTreatment,
            trn: isset($data['trn']) ? (!empty($data['trn']) ? trim((string)$data['trn']) : null) : $customer->trn,
            corporateTaxNumber: isset($data['corporate_tax_number']) ? (!empty($data['corporate_tax_number']) ? trim((string)$data['corporate_tax_number']) : null) : $customer->corporateTaxNumber,
            licenseNumber: isset($data['license_number']) ? (!empty($data['license_number']) ? (int)$data['license_number'] : null) : $customer->licenseNumber,
            licenseExpiry: isset($data['license_expiry']) ? (!empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01') : $customer->licenseExpiry,
            salesPerson: isset($data['sales_person']) ? (!empty($data['sales_person']) ? (int)$data['sales_person'] : null) : $customer->salesPerson,
            leadCategory: isset($data['lead_category']) ? (!empty($data['lead_category']) ? trim((string)$data['lead_category']) : null) : $customer->leadCategory,
            csAgent: isset($data['cs_agent']) ? (!empty($data['cs_agent']) ? (int)$data['cs_agent'] : null) : $customer->csAgent,
            rating: isset($data['rating']) ? (!empty($data['rating']) ? (int)$data['rating'] : null) : $customer->rating,
            currency: isset($data['currency']) ? (!empty($data['currency']) ? (int)$data['currency'] : null) : $customer->currency,
            openingBalance: isset($data['opening_balance']) ? (float)$data['opening_balance'] : $customer->openingBalance,
            exchangeRate: isset($data['exchange_rate']) ? (int)$data['exchange_rate'] : $customer->exchangeRate,
            website: isset($data['website']) ? (!empty($data['website']) ? trim((string)$data['website']) : null) : $customer->website,
            department: isset($data['department']) ? (!empty($data['department']) ? trim((string)$data['department']) : null) : $customer->department,
            designation: isset($data['designation']) ? (!empty($data['designation']) ? trim((string)$data['designation']) : null) : $customer->designation,
            x: isset($data['x']) ? (!empty($data['x']) ? trim((string)$data['x']) : null) : $customer->x,
            facebook: isset($data['facebook']) ? (!empty($data['facebook']) ? trim((string)$data['facebook']) : null) : $customer->facebook,
            instagram: isset($data['instagram']) ? (!empty($data['instagram']) ? trim((string)$data['instagram']) : null) : $customer->instagram,
            photo: isset($data['photo']) ? (!empty($data['photo']) ? trim((string)$data['photo']) : null) : $customer->photo,
            description: isset($data['description']) ? (!empty($data['description']) ? trim((string)$data['description']) : null) : $customer->description,
            tags: isset($data['tags']) ? (!empty($data['tags']) ? trim((string)$data['tags']) : null) : $customer->tags,
            contactedDate: isset($data['contacted_date']) ? (!empty($data['contacted_date']) ? $this->convertDateTimeToDb((string)$data['contacted_date']) : null) : $customer->contactedDate,
            approved: isset($data['approved']) ? (bool)$data['approved'] : $customer->approved,
            approvedBy: isset($data['approved_by']) ? (!empty($data['approved_by']) ? (int)$data['approved_by'] : null) : $customer->approvedBy,
            approvedAt: isset($data['approved_at']) ? (!empty($data['approved_at']) ? trim((string)$data['approved_at']) : null) : $customer->approvedAt,
            publish: isset($data['publish']) ? (bool)$data['publish'] : $customer->publish,
            isActive: isset($data['is_active']) ? (bool)$data['is_active'] : $customer->isActive,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: isset($data['credit_limit']) ? (float)$data['credit_limit'] : $customer->creditLimit,
            discountType: isset($data['discount_type']) ? (!empty($data['discount_type']) ? trim((string)$data['discount_type']) : null) : $customer->discountType,
            discountTypeValue: isset($data['discount_type_value']) ? (float)$data['discount_type_value'] : $customer->discountTypeValue,
            subscriptionTier: isset($data['subscription_tier']) ? trim((string)$data['subscription_tier']) : $customer->subscriptionTier,
            subscriptionExpiresAt: isset($data['subscription_expires_at']) ? trim((string)$data['subscription_expires_at']) : $customer->subscriptionExpiresAt
        );

        $saved = $this->customerRepo->save($updatedCustomer);

        return $saved;
    }

    /**
     * List all customers in an organization
     */
    public function list(int $orgId): array
    {
        return $this->customerRepo->findAll($orgId);
    }

    /**
     * Delete customer
     *
     * @throws NotFoundException
     */
    public function deleteCustomer(int $id, int $orgId): bool
    {
        $this->getCustomer($id, $orgId);
        return $this->customerRepo->delete($id, $orgId);
    }

    /**
     * Create a new contact person record
     *
     * @throws ValidationException
     */
    public function createContact(array $data, int $orgId, int $userId): CustomerContact
    {
        $this->validateContactData($data, $orgId);

        $customerId = (int)($data['customer_id'] ?? 0);
        $isPrimary = (bool)($data['is_primary'] ?? false);

        if ($isPrimary) {
            $this->customerRepo->clearPrimaryContacts($customerId, $orgId);
        }

        $contact = new CustomerContact(
            id: null,
            organizationId: $orgId,
            isPrimary: $isPrimary,
            customerId: $customerId,
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

        $saved = $this->customerRepo->saveContact($contact);

        return $saved;
    }

    /**
     * Update an existing contact person record
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateContact(int $id, array $data, int $orgId, int $userId): CustomerContact
    {
        $contact = $this->customerRepo->findContact($id, $orgId);
        if ($contact === null) {
            throw new NotFoundException("Contact with ID {$id} not found.");
        }

        $this->validateContactData($data, $orgId, $id);

        $isPrimary = isset($data['is_primary']) ? (bool)$data['is_primary'] : $contact->isPrimary;
        if ($isPrimary && !$contact->isPrimary) {
            $this->customerRepo->clearPrimaryContacts($contact->customerId, $orgId);
        }

        $updatedContact = new CustomerContact(
            id: $contact->id,
            organizationId: $contact->organizationId,
            isPrimary: $isPrimary,
            customerId: $contact->customerId,
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

        $saved = $this->customerRepo->saveContact($updatedContact);

        return $saved;
    }

    /**
     * Delete a contact person record
     *
     * @throws NotFoundException
     */
    public function deleteContact(int $id, int $orgId): bool
    {
        $contact = $this->customerRepo->findContact($id, $orgId);
        if ($contact === null) {
            throw new NotFoundException("Contact with ID {$id} not found.");
        }

        $deleted = $this->customerRepo->deleteContact($id, $orgId);

        return $deleted;
    }

    /**
     * Find contacts belonging to a customer
     */
    public function getContactsByCustomer(int $customerId, int $orgId): array
    {
        return $this->customerRepo->findContactsByCustomer($customerId, $orgId);
    }

    /**
     * Create a new address record
     *
     * @throws ValidationException
     */
    public function createAddress(array $data, int $orgId, int $userId): CustomerAddress
    {
        $this->validateAddressData($data, $orgId);

        $customerId = (int)($data['customer_id'] ?? 0);

        $address = new CustomerAddress(
            id: null,
            organizationId: $orgId,
            type: trim((string)$data['type']),
            customerId: $customerId,
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

        $saved = $this->customerRepo->saveAddress($address);

        return $saved;
    }

    /**
     * Update an existing address record
     *
     * @throws NotFoundException
     * @throws ValidationException
     */
    public function updateAddress(int $id, array $data, int $orgId, int $userId): CustomerAddress
    {
        $address = $this->customerRepo->findAddress($id, $orgId);
        if ($address === null) {
            throw new NotFoundException("Address with ID {$id} not found.");
        }

        $this->validateAddressData($data, $orgId, $id);

        $updatedAddress = new CustomerAddress(
            id: $address->id,
            organizationId: $address->organizationId,
            type: $address->type,
            customerId: $address->customerId,
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

        $saved = $this->customerRepo->saveAddress($updatedAddress);

        return $saved;
    }

    /**
     * Delete an address record
     *
     * @throws NotFoundException
     */
    public function deleteAddress(int $id, int $orgId): bool
    {
        $address = $this->customerRepo->findAddress($id, $orgId);
        if ($address === null) {
            throw new NotFoundException("Address with ID {$id} not found.");
        }

        $deleted = $this->customerRepo->deleteAddress($id, $orgId);

        return $deleted;
    }

    /**
     * Get addresses belonging to a customer
     */
    public function getAddressesByCustomer(int $customerId, int $orgId): array
    {
        return $this->customerRepo->findAddressesByCustomer($customerId, $orgId);
    }

    /**
     * Validate customer inputs
     *
     * @throws ValidationException
     */
    private function validateCustomerData(array $data, int $orgId, ?int $id = null): void
    {
        $errors = [];

        $existing = null;
        if ($id !== null) {
            $existing = $this->customerRepo->find($id, $orgId);
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
            } else {
                // Check disposable email
                $emailResult = $this->emailValidator->validate($email);
                if (!$emailResult[0]) {
                    $errors['email'] = $emailResult[1];
                } elseif ($this->customerRepo->existsByEmail($email, $orgId, $id)) {
                    $errors['email'] = 'Duplicate Email. Please enter different.';
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
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
            $existing = $this->customerRepo->findContact($id, $orgId);
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
        } else {
            // Check disposable email
            $emailResult = $this->emailValidator->validate($email);
            if (!$emailResult[0]) {
                $errors['email'] = $emailResult[1];
            }
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
            $existing = $this->customerRepo->findAddress($id, $orgId);
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
     * Convert date string from d-m-Y to Y-m-d format
     */
    private function convertDateToDb(string $dateStr): string
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '') {
            return '1970-01-01';
        }

        // Check if already in Y-m-d format
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

    /**
     * Convert datetime string to Db format
     */
    private function convertDateTimeToDb(string $dateTimeStr): string
    {
        $dateTimeStr = trim($dateTimeStr);
        if ($dateTimeStr === '') {
            return date('Y-m-d H:i:s');
        }

        try {
            $dt = new \DateTime($dateTimeStr);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return date('Y-m-d H:i:s');
        }
    }

    /**
     * Get total receivables for a customer
     */
    public function getReceivables(int $customerId, int $orgId): float
    {
        return $this->customerRepo->getReceivables($customerId, $orgId);
    }

    /**
     * Approve customer
     */
    public function approveCustomer(int $id, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $updated = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: $customer->leadId,
            customerOwner: $customer->customerOwner,
            customerType: $customer->customerType,
            customerStatus: $customer->customerStatus,
            customerSource: $customer->customerSource,
            assignedTo: $customer->assignedTo,
            salutation: $customer->salutation,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            companyName: $customer->companyName,
            displayName: $customer->displayName,
            address: $customer->address,
            email: $customer->email,
            phone: $customer->phone,
            mobile: $customer->mobile,
            paymentTerm: $customer->paymentTerm,
            taxTreatment: $customer->taxTreatment,
            trn: $customer->trn,
            corporateTaxNumber: $customer->corporateTaxNumber,
            licenseNumber: $customer->licenseNumber,
            licenseExpiry: $customer->licenseExpiry,
            salesPerson: $customer->salesPerson,
            leadCategory: $customer->leadCategory,
            csAgent: $customer->csAgent,
            rating: $customer->rating,
            currency: $customer->currency,
            openingBalance: $customer->openingBalance,
            exchangeRate: $customer->exchangeRate,
            website: $customer->website,
            department: $customer->department,
            designation: $customer->designation,
            x: $customer->x,
            facebook: $customer->facebook,
            instagram: $customer->instagram,
            photo: $customer->photo,
            description: $customer->description,
            tags: $customer->tags,
            contactedDate: $customer->contactedDate,
            approved: true,
            approvedBy: $userId,
            approvedAt: date('Y-m-d H:i:s'),
            publish: $customer->publish,
            isActive: $customer->isActive,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: $customer->creditLimit,
            discountType: $customer->discountType,
            discountTypeValue: $customer->discountTypeValue,
            subscriptionTier: $customer->subscriptionTier,
            subscriptionExpiresAt: $customer->subscriptionExpiresAt
        );
        return $this->customerRepo->save($updated);
    }

    /**
     * Disapprove customer
     */
    public function disapproveCustomer(int $id, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $updated = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: $customer->leadId,
            customerOwner: $customer->customerOwner,
            customerType: $customer->customerType,
            customerStatus: $customer->customerStatus,
            customerSource: $customer->customerSource,
            assignedTo: $customer->assignedTo,
            salutation: $customer->salutation,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            companyName: $customer->companyName,
            displayName: $customer->displayName,
            address: $customer->address,
            email: $customer->email,
            phone: $customer->phone,
            mobile: $customer->mobile,
            paymentTerm: $customer->paymentTerm,
            taxTreatment: $customer->taxTreatment,
            trn: $customer->trn,
            corporateTaxNumber: $customer->corporateTaxNumber,
            licenseNumber: $customer->licenseNumber,
            licenseExpiry: $customer->licenseExpiry,
            salesPerson: $customer->salesPerson,
            leadCategory: $customer->leadCategory,
            csAgent: $customer->csAgent,
            rating: $customer->rating,
            currency: $customer->currency,
            openingBalance: $customer->openingBalance,
            exchangeRate: $customer->exchangeRate,
            website: $customer->website,
            department: $customer->department,
            designation: $customer->designation,
            x: $customer->x,
            facebook: $customer->facebook,
            instagram: $customer->instagram,
            photo: $customer->photo,
            description: $customer->description,
            tags: $customer->tags,
            contactedDate: $customer->contactedDate,
            approved: false,
            approvedBy: null,
            approvedAt: null,
            publish: $customer->publish,
            isActive: $customer->isActive,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: $customer->creditLimit,
            discountType: $customer->discountType,
            discountTypeValue: $customer->discountTypeValue,
            subscriptionTier: $customer->subscriptionTier,
            subscriptionExpiresAt: $customer->subscriptionExpiresAt
        );
        return $this->customerRepo->save($updated);
    }

    /**
     * Update customer opening balance
     */
    public function updateOpeningBalance(int $id, float $balance, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $updated = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: $customer->leadId,
            customerOwner: $customer->customerOwner,
            customerType: $customer->customerType,
            customerStatus: $customer->customerStatus,
            customerSource: $customer->customerSource,
            assignedTo: $customer->assignedTo,
            salutation: $customer->salutation,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            companyName: $customer->companyName,
            displayName: $customer->displayName,
            address: $customer->address,
            email: $customer->email,
            phone: $customer->phone,
            mobile: $customer->mobile,
            paymentTerm: $customer->paymentTerm,
            taxTreatment: $customer->taxTreatment,
            trn: $customer->trn,
            corporateTaxNumber: $customer->corporateTaxNumber,
            licenseNumber: $customer->licenseNumber,
            licenseExpiry: $customer->licenseExpiry,
            salesPerson: $customer->salesPerson,
            leadCategory: $customer->leadCategory,
            csAgent: $customer->csAgent,
            rating: $customer->rating,
            currency: $customer->currency,
            openingBalance: $balance,
            exchangeRate: $customer->exchangeRate,
            website: $customer->website,
            department: $customer->department,
            designation: $customer->designation,
            x: $customer->x,
            facebook: $customer->facebook,
            instagram: $customer->instagram,
            photo: $customer->photo,
            description: $customer->description,
            tags: $customer->tags,
            contactedDate: $customer->contactedDate,
            approved: $customer->approved,
            approvedBy: $customer->approvedBy,
            approvedAt: $customer->approvedAt,
            publish: $customer->publish,
            isActive: $customer->isActive,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: $customer->creditLimit,
            discountType: $customer->discountType,
            discountTypeValue: $customer->discountTypeValue,
            subscriptionTier: $customer->subscriptionTier,
            subscriptionExpiresAt: $customer->subscriptionExpiresAt
        );
        return $this->customerRepo->save($updated);
    }

    /**
     * Clone customer
     */
    public function cloneCustomer(int $id, int $orgId, int $userId): Customer
    {
        // Ensure customer exists
        $this->getCustomer($id, $orgId);
        $newId = $this->customerRepo->clone($id, $orgId, $userId);
        return $this->getCustomer($newId, $orgId);
    }

    /**
     * Mark customer as active
     */
    public function markAsActive(int $id, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $updated = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: $customer->leadId,
            customerOwner: $customer->customerOwner,
            customerType: $customer->customerType,
            customerStatus: $customer->customerStatus,
            customerSource: $customer->customerSource,
            assignedTo: $customer->assignedTo,
            salutation: $customer->salutation,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            companyName: $customer->companyName,
            displayName: $customer->displayName,
            address: $customer->address,
            email: $customer->email,
            phone: $customer->phone,
            mobile: $customer->mobile,
            paymentTerm: $customer->paymentTerm,
            taxTreatment: $customer->taxTreatment,
            trn: $customer->trn,
            corporateTaxNumber: $customer->corporateTaxNumber,
            licenseNumber: $customer->licenseNumber,
            licenseExpiry: $customer->licenseExpiry,
            salesPerson: $customer->salesPerson,
            leadCategory: $customer->leadCategory,
            csAgent: $customer->csAgent,
            rating: $customer->rating,
            currency: $customer->currency,
            openingBalance: $customer->openingBalance,
            exchangeRate: $customer->exchangeRate,
            website: $customer->website,
            department: $customer->department,
            designation: $customer->designation,
            x: $customer->x,
            facebook: $customer->facebook,
            instagram: $customer->instagram,
            photo: $customer->photo,
            description: $customer->description,
            tags: $customer->tags,
            contactedDate: $customer->contactedDate,
            approved: $customer->approved,
            approvedBy: $customer->approvedBy,
            approvedAt: $customer->approvedAt,
            publish: $customer->publish,
            isActive: true,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: $customer->creditLimit,
            discountType: $customer->discountType,
            discountTypeValue: $customer->discountTypeValue,
            subscriptionTier: $customer->subscriptionTier,
            subscriptionExpiresAt: $customer->subscriptionExpiresAt
        );
        return $this->customerRepo->save($updated);
    }

    /**
     * Mark customer as inactive
     */
    public function markAsInactive(int $id, int $orgId, int $userId): Customer
    {
        $customer = $this->getCustomer($id, $orgId);
        $updated = new Customer(
            id: $customer->id,
            organizationId: $customer->organizationId,
            leadId: $customer->leadId,
            customerOwner: $customer->customerOwner,
            customerType: $customer->customerType,
            customerStatus: $customer->customerStatus,
            customerSource: $customer->customerSource,
            assignedTo: $customer->assignedTo,
            salutation: $customer->salutation,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            companyName: $customer->companyName,
            displayName: $customer->displayName,
            address: $customer->address,
            email: $customer->email,
            phone: $customer->phone,
            mobile: $customer->mobile,
            paymentTerm: $customer->paymentTerm,
            taxTreatment: $customer->taxTreatment,
            trn: $customer->trn,
            corporateTaxNumber: $customer->corporateTaxNumber,
            licenseNumber: $customer->licenseNumber,
            licenseExpiry: $customer->licenseExpiry,
            salesPerson: $customer->salesPerson,
            leadCategory: $customer->leadCategory,
            csAgent: $customer->csAgent,
            rating: $customer->rating,
            currency: $customer->currency,
            openingBalance: $customer->openingBalance,
            exchangeRate: $customer->exchangeRate,
            website: $customer->website,
            department: $customer->department,
            designation: $customer->designation,
            x: $customer->x,
            facebook: $customer->facebook,
            instagram: $customer->instagram,
            photo: $customer->photo,
            description: $customer->description,
            tags: $customer->tags,
            contactedDate: $customer->contactedDate,
            approved: $customer->approved,
            approvedBy: $customer->approvedBy,
            approvedAt: $customer->approvedAt,
            publish: $customer->publish,
            isActive: false,
            createdAt: $customer->createdAt,
            createdBy: $customer->createdBy,
            updatedBy: $userId,
            creditLimit: $customer->creditLimit,
            discountType: $customer->discountType,
            discountTypeValue: $customer->discountTypeValue,
            subscriptionTier: $customer->subscriptionTier,
            subscriptionExpiresAt: $customer->subscriptionExpiresAt
        );
        return $this->customerRepo->save($updated);
    }

    /**
     * Mark contact as primary
     */
    public function markContactAsPrimary(int $contactId, int $customerId, int $orgId): void
    {
        $this->customerRepo->clearPrimaryContacts($customerId, $orgId);
        $contact = $this->customerRepo->findContact($contactId, $orgId);
        if ($contact !== null && $contact->customerId === $customerId) {
            $updated = new CustomerContact(
                id: $contact->id,
                organizationId: $contact->organizationId,
                isPrimary: true,
                customerId: $contact->customerId,
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
            $this->customerRepo->saveContact($updated);
        }
    }
}
