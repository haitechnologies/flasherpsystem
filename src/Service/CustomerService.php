<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Container;
use App\Core\DB;
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

        $openingBalance = !empty($data['opening_balance']) ? (float)$data['opening_balance'] : 0.00;
        $receivableAccountId = !empty($data['receivable_account_id']) ? (int)$data['receivable_account_id'] : null;

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
            licenseNumber: !empty($data['license_number']) ? trim((string)$data['license_number']) : null,
            licenseExpiry: !empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01',
            salesPerson: !empty($data['sales_person']) ? (int)$data['sales_person'] : null,
            leadCategory: !empty($data['lead_category']) ? trim((string)$data['lead_category']) : null,
            csAgent: !empty($data['cs_agent']) ? (int)$data['cs_agent'] : null,
            rating: !empty($data['rating']) ? (int)$data['rating'] : null,
            currency: !empty($data['currency']) ? (int)$data['currency'] : null,
            openingBalance: $openingBalance,
            receivableAccountId: $receivableAccountId,
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
            creditLimit: !empty($data['credit_limit']) ? (float)$data['credit_limit'] : 0.00,
            discountType: !empty($data['discount_type']) ? trim((string)$data['discount_type']) : null,
            discountTypeValue: !empty($data['discount_type_value']) ? (float)$data['discount_type_value'] : 0.00,
            subscriptionTier: !empty($data['subscription_tier']) ? trim((string)$data['subscription_tier']) : 'registered',
            subscriptionExpiresAt: !empty($data['subscription_expires_at']) ? trim((string)$data['subscription_expires_at']) : null
        );

        $saved = $this->customerRepo->save($customer);

        if ($openingBalance != 0) {
            try {
                $this->createOpeningBalanceJournal($saved->id, $openingBalance, $orgId, $userId, $saved->displayName, $receivableAccountId);
            } catch (\Throwable $e) {
                if (function_exists('log_error')) {
                    log_error(
                        'Failed to create opening balance journal: ' . $e->getMessage(),
                        'ERROR',
                        __FILE__,
                        __LINE__,
                        function_exists('backend_runtime_log_context')
                            ? backend_runtime_log_context(['module' => 'customers', 'module_slug' => 'customers', 'stack_trace' => $e->getTraceAsString(), 'error_code' => (string)$e->getCode(), 'customer_id' => $saved->id])
                            : ['module' => 'customers', 'module_slug' => 'customers', 'stack_trace' => $e->getTraceAsString(), 'error_code' => (string)$e->getCode(), 'customer_id' => $saved->id]
                    );
                } else {
                    error_log('Failed to create opening balance journal: ' . $e->getMessage());
                }
            }
        }

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
            licenseNumber: isset($data['license_number']) ? (!empty($data['license_number']) ? trim((string)$data['license_number']) : null) : $customer->licenseNumber,
            licenseExpiry: isset($data['license_expiry']) ? (!empty($data['license_expiry']) ? $this->convertDateToDb((string)$data['license_expiry']) : '1970-01-01') : $customer->licenseExpiry,
            salesPerson: isset($data['sales_person']) ? (!empty($data['sales_person']) ? (int)$data['sales_person'] : null) : $customer->salesPerson,
            leadCategory: isset($data['lead_category']) ? (!empty($data['lead_category']) ? trim((string)$data['lead_category']) : null) : $customer->leadCategory,
            csAgent: isset($data['cs_agent']) ? (!empty($data['cs_agent']) ? (int)$data['cs_agent'] : null) : $customer->csAgent,
            rating: isset($data['rating']) ? (!empty($data['rating']) ? (int)$data['rating'] : null) : $customer->rating,
            currency: isset($data['currency']) ? (!empty($data['currency']) ? (int)$data['currency'] : null) : $customer->currency,
            openingBalance: isset($data['opening_balance']) ? (float)$data['opening_balance'] : $customer->openingBalance,
            receivableAccountId: isset($data['receivable_account_id']) ? (!empty($data['receivable_account_id']) ? (int)$data['receivable_account_id'] : null) : $customer->receivableAccountId,
            exchangeRate: isset($data['exchange_rate']) ? (float)$data['exchange_rate'] : $customer->exchangeRate,
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
            subscriptionTier: isset($data['subscription_tier']) && $data['subscription_tier'] !== '' ? trim((string)$data['subscription_tier']) : $customer->subscriptionTier,
            subscriptionExpiresAt: isset($data['subscription_expires_at']) && $data['subscription_expires_at'] !== '' ? trim((string)$data['subscription_expires_at']) : $customer->subscriptionExpiresAt
        );

        $saved = $this->customerRepo->save($updatedCustomer);

        if ($customer->openingBalance != $saved->openingBalance) {
            $this->syncOpeningBalanceJournal((int)$saved->id, (float)$saved->openingBalance, $orgId, $userId, $saved->displayName, $saved->receivableAccountId);
        }

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

        $openingBalance = $data['opening_balance'] ?? ($existing ? $existing->openingBalance : null);
        if ($openingBalance !== null && $openingBalance !== '' && !is_numeric($openingBalance)) {
            $errors['opening_balance'] = 'Opening balance must be a valid number.';
        }

        $exchangeRate = $data['exchange_rate'] ?? ($existing ? $existing->exchangeRate : null);
        if ($exchangeRate !== null && $exchangeRate !== '' && (!is_numeric($exchangeRate) || (float)$exchangeRate <= 0)) {
            $errors['exchange_rate'] = 'Exchange rate must be a positive number.';
        }

        $creditLimit = $data['credit_limit'] ?? ($existing ? $existing->creditLimit : null);
        if ($creditLimit !== null && $creditLimit !== '' && !is_numeric($creditLimit)) {
            $errors['credit_limit'] = 'Credit limit must be a valid number.';
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
            receivableAccountId: $customer->receivableAccountId,
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
            receivableAccountId: $customer->receivableAccountId,
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
        $db = Container::getInstance()->get(\App\Core\Database::class);
        $db->beginTransaction();
        try {
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
                receivableAccountId: $customer->receivableAccountId,
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
            $saved = $this->customerRepo->save($updated);

            $this->createOpeningBalanceJournal($id, $balance, $orgId, $userId, $customer->displayName, $customer->receivableAccountId);

            $db->commit();
            return $saved;
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function createOpeningBalanceJournal(int $customerId, float $balance, int $orgId, int $userId, string $customerName, ?int $receivableAccountId = null): void
    {
        if ($balance == 0) {
            return;
        }

        $journalService = Container::getInstance()->get(JournalService::class);

        $journalData = [
            'journal_status' => 'approved',
            'journal_date' => date('Y-m-d'),
            'reference_no' => 'CUST-OB-' . $customerId,
            'notes' => "Opening balance for customer: {$customerName}",
            'reporting_method' => 'accrual',
            'reference_type' => 'customer_opening_balance',
            'reference_id' => $customerId,
            'currency' => 'AED',
            'warehouse_id' => 0,
        ];

        $itemsData = [
            [
                'account' => $receivableAccountId ?? 9,
                'description' => "Opening Balance — {$customerName}",
                'debit' => abs($balance),
                'credit' => 0.0,
            ],
            [
                'account' => 118,
                'description' => "Opening Balance Offset — {$customerName}",
                'debit' => 0.0,
                'credit' => abs($balance),
            ],
        ];

        $journalService->createJournal($journalData, $itemsData, $orgId, $userId);
    }

    private function syncOpeningBalanceJournal(int $customerId, float $balance, int $orgId, int $userId, string $customerName, ?int $receivableAccountId = null): void
    {
        try {
            $journalService = Container::getInstance()->get(\App\Service\JournalService::class);
            $journalRepo = Container::getInstance()->get(\App\Repository\JournalRepository::class);

            $existing = $journalRepo->findByReference('customer_opening_balance', $customerId, $orgId);
            foreach ($existing as $journal) {
                $journalService->deleteJournal((int)$journal->id, $orgId);
            }

            if ($balance != 0) {
                $this->createOpeningBalanceJournal($customerId, $balance, $orgId, $userId, $customerName, $receivableAccountId);
            }
        } catch (\Throwable $e) {
            error_log('Customer opening balance journal sync failed: ' . $e->getMessage());
        }
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
            receivableAccountId: $customer->receivableAccountId,
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
            receivableAccountId: $customer->receivableAccountId,
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

    /**
     * Build the customer activity timeline (entity logs + notes)
     *
     * @return array<int, array{
     *     type: string, icon: string, title: string, body: string,
     *     user_name: string, details_url: ?string, created_at: string
     * }>
     */
    public function getActivityTimeline(int $customerId, int $orgId): array
    {
        $db = Container::getInstance()->get(\App\Core\Database::class);

        $logRows = $db->fetchAll(
            "SELECT id, module, action, record_id, created_by, created_at
             FROM `" . DB::ENTITY_LOGS . "`
             WHERE entity_type = 'customer' AND entity_id = :id
               AND (organization_id IS NULL OR organization_id = :org)
             ORDER BY created_at DESC LIMIT 30",
            ['id' => $customerId, 'org' => $orgId]
        );

        $noteRows = $db->fetchAll(
            "SELECT id, notes, created_by, created_at
             FROM `" . DB::ENTITY_NOTES . "`
             WHERE entity_type = 'customer' AND entity_id = :id AND is_active = 1
               AND (organization_id IS NULL OR organization_id = :org)
             ORDER BY created_at DESC LIMIT 30",
            ['id' => $customerId, 'org' => $orgId]
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

        $quotationRows = [];
        $invoiceRows = [];
        foreach ($logRows as $row) {
            $recordId = (int)($row['record_id'] ?? 0);
            if ($recordId <= 0) {
                continue;
            }
            if ($row['module'] === 'quotation') {
                $quotationRows[$recordId] = true;
            } elseif ($row['module'] === 'invoice') {
                $invoiceRows[$recordId] = true;
            }
        }

        $quotations = [];
        if ($quotationRows !== []) {
            $ids = array_keys($quotationRows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT id, quotation_no, grand_total FROM `" . DB::QUOTATIONS . "` WHERE id IN ($placeholders)",
                $ids
            );
            foreach ($rows as $r) {
                $quotations[(int)$r['id']] = $r;
            }
        }

        $invoices = [];
        if ($invoiceRows !== []) {
            $ids = array_keys($invoiceRows);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $rows = $db->fetchAll(
                "SELECT id, invoice_no, grand_total FROM `" . DB::INVOICES . "` WHERE id IN ($placeholders)",
                $ids
            );
            foreach ($rows as $r) {
                $invoices[(int)$r['id']] = $r;
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

            if ($module === 'customer') {
                $entry['icon'] = 'ph-user';
                $entry['title'] = match ($action) {
                    'add', 'created' => 'Customer added',
                    'edit', 'updated' => 'Customer updated',
                    'approved' => 'Customer approved',
                    'disapproved' => 'Customer disapproved',
                    'clone' => 'Customer cloned',
                    'active' => 'Customer marked active',
                    'inactive' => 'Customer marked inactive',
                    'opening_balance' => 'Opening balance updated',
                    default => 'Customer updated',
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
            } elseif ($module === 'quotation') {
                $entry['icon'] = 'ph-file-text';
                $entry['title'] = $action === 'add' ? 'Quote added' : 'Quote updated';
                if ($recordId > 0 && isset($quotations[$recordId])) {
                    $q = $quotations[$recordId];
                    $amount = number_format((float)($q['grand_total'] ?? 0), 2, '.', '');
                    $entry['body'] = 'Quote ' . $q['quotation_no'] . ' of amount ' . $currency . $amount . ' created';
                    $entry['details_url'] = 'quotation_overview.php?quotation_id=' . $recordId;
                }
            } elseif ($module === 'invoice') {
                $entry['icon'] = 'ph-receipt';
                $entry['title'] = $action === 'add' ? 'Invoice added' : 'Invoice updated';
                if ($recordId > 0 && isset($invoices[$recordId])) {
                    $inv = $invoices[$recordId];
                    $amount = number_format((float)($inv['grand_total'] ?? 0), 2, '.', '');
                    $entry['body'] = 'Invoice ' . $inv['invoice_no'] . ' of amount ' . $currency . $amount . ' created';
                    $entry['details_url'] = 'invoice_overview.php?invoice_id=' . $recordId;
                }
            } elseif ($module === 'payment') {
                $entry['icon'] = 'ph-bank';
                $entry['title'] = $action === 'add' ? 'Payment received' : 'Payment updated';
                $entry['details_url'] = $recordId > 0 ? 'payment_received_overview.php?payment_id=' . $recordId : null;
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
}
