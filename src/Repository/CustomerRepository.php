<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Customer;
use App\Model\CustomerContact;
use App\Model\CustomerAddress;

/**
 * Customer Repository
 *
 * Handles PDO-based data access for erp_customers, erp_customer_contacts,
 * and erp_customer_addresses tables with strict tenant isolation.
 */
class CustomerRepository
{
    private const CUSTOMER_COLUMNS = '
        id, organization_id, lead_id, customer_owner, customer_type, customer_status,
        customer_source, assigned_to, salutation, first_name, last_name,
        company_name, display_name, address, email, phone, mobile,
        payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
        sales_person, lead_category, cs_agent, rating, currency,
        opening_balance, receivable_account_id, exchange_rate, website, department, designation,
        x, facebook, instagram, photo, description, tags, contacted_date,
        approved, approved_by, approved_at, publish, is_active,
        created_at, updated_at, updated_by, created_by, credit_limit,
        discount_type, discount_type_value, subscription_tier, subscription_expires_at';

    private const CONTACT_COLUMNS = '
        id, organization_id, contactable_type, contactable_id, is_primary,
        first_name, last_name, position, email, phone, notes,
        publish, is_active, created_at, updated_at, updated_by, created_by';

    private const ADDRESS_COLUMNS = '
        id, organization_id, addressable_type, addressable_id, type,
        attention, country, address_line1, address_line2, city, state, zipcode,
        phone, fax, publish, is_active, created_at, updated_at, updated_by, created_by';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find customer by ID and organization
     */
    public function find(int $id, int $orgId): ?Customer
    {
        $sql = "SELECT " . self::CUSTOMER_COLUMNS . " FROM `{DB::CUSTOMERS}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToCustomer($row);
    }

    /**
     * Find customer by email and organization
     */
    public function findByEmail(string $email, int $orgId): ?Customer
    {
        $sql = "SELECT " . self::CUSTOMER_COLUMNS . " FROM `{DB::CUSTOMERS}` WHERE email = :email AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['email' => trim($email), 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToCustomer($row);
    }

    /**
     * Check if email exists for another customer in the organization
     */
    public function existsByEmail(string $email, int $orgId, ?int $excludeId = null): bool
    {
        $email = trim($email);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::CUSTOMERS}` WHERE email = :email AND organization_id = :org_id AND id != :exclude_id LIMIT 1";
            $params = ['email' => $email, 'org_id' => $orgId, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::CUSTOMERS}` WHERE email = :email AND organization_id = :org_id LIMIT 1";
            $params = ['email' => $email, 'org_id' => $orgId];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    /**
     * Find all customers in an organization
     */
    public function findAll(int $orgId): array
    {
        $sql = "SELECT " . self::CUSTOMER_COLUMNS . " FROM `{DB::CUSTOMERS}` WHERE organization_id = :org_id ORDER BY display_name ASC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $customers = [];
        foreach ($rows as $row) {
            $customers[] = $this->mapRowToCustomer($row);
        }
        return $customers;
    }

    /**
     * Save customer (Insert or Update)
     */
    public function save(Customer $customer): Customer
    {
        if ($customer->id === null) {
            return $this->insert($customer);
        }
        return $this->update($customer);
    }

    private function insert(Customer $customer): Customer
    {
        $sql = "INSERT INTO `{DB::CUSTOMERS}` (
                    organization_id, lead_id, customer_owner, customer_type, customer_status,
                    customer_source, assigned_to, salutation, first_name, last_name,
                    company_name, display_name, address, email, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    opening_balance, receivable_account_id, exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    approved, approved_by, approved_at, publish, is_active,
                    created_at, updated_at, updated_by, created_by, credit_limit,
                    discount_type, discount_type_value, subscription_tier, subscription_expires_at
                ) VALUES (
                    :organization_id, :lead_id, :customer_owner, :customer_type, :customer_status,
                    :customer_source, :assigned_to, :salutation, :first_name, :last_name,
                    :company_name, :display_name, :address, :email, :phone, :mobile,
                    :payment_term, :tax_treatment, :trn, :corporate_tax_number, :license_number, :license_expiry,
                    :sales_person, :lead_category, :cs_agent, :rating, :currency,
                    :opening_balance, :receivable_account_id, :exchange_rate, :website, :department, :designation,
                    :x, :facebook, :instagram, :photo, :description, :tags, :contacted_date,
                    :approved, :approved_by, :approved_at, :publish, :is_active,
                    NOW(), NOW(), :updated_by, :created_by, :credit_limit,
                    :discount_type, :discount_type_value, :subscription_tier, :subscription_expires_at
                )";

        $params = $customer->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, (int)$customer->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted customer.");
        }

        return $inserted;
    }

    private function update(Customer $customer): Customer
    {
        $sql = "UPDATE `{DB::CUSTOMERS}` SET
                    lead_id = :lead_id,
                    customer_owner = :customer_owner,
                    customer_type = :customer_type,
                    customer_status = :customer_status,
                    customer_source = :customer_source,
                    assigned_to = :assigned_to,
                    salutation = :salutation,
                    first_name = :first_name,
                    last_name = :last_name,
                    company_name = :company_name,
                    display_name = :display_name,
                    address = :address,
                    email = :email,
                    phone = :phone,
                    mobile = :mobile,
                    payment_term = :payment_term,
                    tax_treatment = :tax_treatment,
                    trn = :trn,
                    corporate_tax_number = :corporate_tax_number,
                    license_number = :license_number,
                    license_expiry = :license_expiry,
                    sales_person = :sales_person,
                    lead_category = :lead_category,
                    cs_agent = :cs_agent,
                    rating = :rating,
                    currency = :currency,
                    opening_balance = :opening_balance,
                    receivable_account_id = :receivable_account_id,
                    exchange_rate = :exchange_rate,
                    website = :website,
                    department = :department,
                    designation = :designation,
                    x = :x,
                    facebook = :facebook,
                    instagram = :instagram,
                    photo = :photo,
                    description = :description,
                    tags = :tags,
                    contacted_date = :contacted_date,
                    approved = :approved,
                    approved_by = :approved_by,
                    approved_at = :approved_at,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    credit_limit = :credit_limit,
                    discount_type = :discount_type,
                    discount_type_value = :discount_type_value,
                    subscription_tier = :subscription_tier,
                    subscription_expires_at = :subscription_expires_at
                WHERE id = :id AND organization_id = :organization_id";

        $params = $customer->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$customer->id, (int)$customer->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated customer.");
        }

        return $updated;
    }

    /**
     * Delete customer and cascade deletions to all related tables under a transaction
     */
    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->execute(
            "UPDATE `{DB::CUSTOMERS}` SET is_active = 0 WHERE id = :id AND organization_id = :org_id",
            ['id' => $id, 'org_id' => $orgId]
        );
        return $stmt->rowCount() > 0;
    }

    /**
     * Find contact by ID and organization
     */
    public function findContact(int $id, int $orgId): ?CustomerContact
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::CUSTOMER_CONTACTS}` WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToContact($row);
    }

    /**
     * Find contacts belonging to a customer
     */
    public function findContactsByCustomer(int $customerId, int $orgId): array
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::CUSTOMER_CONTACTS}` WHERE contactable_type = 'Customer' AND contactable_id = :customer_id AND organization_id = :org_id ORDER BY is_primary DESC, id ASC";
        $rows = $this->db->fetchAll($sql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        return array_map([$this, 'mapRowToContact'], $rows);
    }

    /**
     * Clear primary contact flag for all contacts of a customer
     */
    public function clearPrimaryContacts(int $customerId, int $orgId): void
    {
        $sql = "UPDATE `{DB::CUSTOMER_CONTACTS}` SET is_primary = 0 WHERE contactable_type = 'Customer' AND contactable_id = :customer_id AND organization_id = :org_id";
        $this->db->execute($sql, ['customer_id' => $customerId, 'org_id' => $orgId]);
    }

    /**
     * Save customer contact (Insert or Update)
     */
    public function saveContact(CustomerContact $contact): CustomerContact
    {
        if ($contact->id === null) {
            $sql = "INSERT INTO `{DB::CUSTOMER_CONTACTS}` (
                        organization_id, contactable_type, contactable_id, is_primary, first_name, last_name,
                        position, email, phone, notes, publish, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        :organization_id, 'Customer', :customer_id, :is_primary, :first_name, :last_name,
                        :position, :email, :phone, :notes, :publish, :is_active, :created_by, NOW(), NOW()
                    )";
            $params = $contact->toArray();
            unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);
            $insertId = (int)$this->db->insert($sql, $params);
            $inserted = $this->findContact($insertId, $contact->organizationId);
            if ($inserted === null) {
                throw new \RuntimeException("Failed to retrieve inserted contact.");
            }
            return $inserted;
        }

        $sql = "UPDATE `{DB::CUSTOMER_CONTACTS}` SET
                    is_primary = :is_primary,
                    first_name = :first_name,
                    last_name = :last_name,
                    position = :position,
                    email = :email,
                    phone = :phone,
                    notes = :notes,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :organization_id";
        $params = $contact->toArray();
        unset($params['customer_id'], $params['created_at'], $params['updated_at'], $params['created_by']);
        $this->db->execute($sql, $params);
        $updated = $this->findContact((int)$contact->id, $contact->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated contact.");
        }
        return $updated;
    }

    /**
     * Delete contact by ID
     */
    public function deleteContact(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::CUSTOMER_CONTACTS}` WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Find address by ID and organization
     */
    public function findAddress(int $id, int $orgId): ?CustomerAddress
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::CUSTOMER_ADDRESSES}` WHERE id = :id AND addressable_type = 'Customer' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToAddress($row);
    }

    /**
     * Find addresses belonging to a customer
     */
    public function findAddressesByCustomer(int $customerId, int $orgId): array
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::CUSTOMER_ADDRESSES}` WHERE addressable_type = 'Customer' AND addressable_id = :customer_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        return array_map([$this, 'mapRowToAddress'], $rows);
    }

    /**
     * Save customer address (Insert or Update)
     */
    public function saveAddress(CustomerAddress $address): CustomerAddress
    {
        if ($address->id === null) {
            $sql = "INSERT INTO `{DB::CUSTOMER_ADDRESSES}` (
                        organization_id, addressable_type, addressable_id, type, attention, country,
                        address_line1, address_line2, city, state, zipcode, phone, fax,
                        publish, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        :organization_id, 'Customer', :customer_id, :type, :attention, :country,
                        :address_line1, :address_line2, :city, :state, :zipcode, :phone, :fax,
                        :publish, :is_active, :created_by, NOW(), NOW()
                    )";
            $params = $address->toArray();
            unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);
            $insertId = (int)$this->db->insert($sql, $params);
            $inserted = $this->findAddress($insertId, $address->organizationId);
            if ($inserted === null) {
                throw new \RuntimeException("Failed to retrieve inserted address.");
            }
            return $inserted;
        }

        $sql = "UPDATE `{DB::CUSTOMER_ADDRESSES}` SET
                    attention = :attention,
                    country = :country,
                    address_line1 = :address_line1,
                    address_line2 = :address_line2,
                    city = :city,
                    state = :state,
                    zipcode = :zipcode,
                    phone = :phone,
                    fax = :fax,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND addressable_type = 'Customer' AND organization_id = :organization_id";
        $params = $address->toArray();
        unset($params['type'], $params['customer_id'], $params['created_at'], $params['updated_at'], $params['created_by']);
        $this->db->execute($sql, $params);
        $updated = $this->findAddress((int)$address->id, $address->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated address.");
        }
        return $updated;
    }

    /**
     * Delete address by ID
     */
    public function deleteAddress(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::CUSTOMER_ADDRESSES}` WHERE id = :id AND addressable_type = 'Customer' AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Map database row to Customer DTO
     */
    private function mapRowToCustomer(array $row): Customer
    {
        return new Customer(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            leadId: $row['lead_id'] !== null ? (int)$row['lead_id'] : null,
            customerOwner: $row['customer_owner'] !== null ? (int)$row['customer_owner'] : null,
            customerType: (string)$row['customer_type'],
            customerStatus: $row['customer_status'] !== null ? (int)$row['customer_status'] : null,
            customerSource: $row['customer_source'] !== null ? (int)$row['customer_source'] : null,
            assignedTo: $row['assigned_to'] !== null ? (int)$row['assigned_to'] : null,
            salutation: $row['salutation'] !== null ? (string)$row['salutation'] : null,
            firstName: $row['first_name'] !== null ? (string)$row['first_name'] : null,
            lastName: $row['last_name'] !== null ? (string)$row['last_name'] : null,
            companyName: $row['company_name'] !== null ? (string)$row['company_name'] : null,
            displayName: (string)$row['display_name'],
            address: (string)$row['address'],
            email: $row['email'] !== null ? (string)$row['email'] : null,
            phone: $row['phone'] !== null ? (string)$row['phone'] : null,
            mobile: $row['mobile'] !== null ? (string)$row['mobile'] : null,
            paymentTerm: $row['payment_term'] !== null ? (int)$row['payment_term'] : null,
            taxTreatment: $row['tax_treatment'] !== null ? (int)$row['tax_treatment'] : null,
            trn: $row['trn'] !== null ? (string)$row['trn'] : null,
            corporateTaxNumber: $row['corporate_tax_number'] !== null ? (string)$row['corporate_tax_number'] : null,
            licenseNumber: $row['license_number'] !== null ? (string)$row['license_number'] : null,
            licenseExpiry: $row['license_expiry'] !== null ? (string)$row['license_expiry'] : null,
            salesPerson: $row['sales_person'] !== null ? (int)$row['sales_person'] : null,
            leadCategory: $row['lead_category'] !== null ? (string)$row['lead_category'] : null,
            csAgent: $row['cs_agent'] !== null ? (int)$row['cs_agent'] : null,
            rating: $row['rating'] !== null ? (int)$row['rating'] : null,
            currency: $row['currency'] !== null ? (int)$row['currency'] : null,
            openingBalance: (float)($row['opening_balance'] ?? 0.00),
            receivableAccountId: $row['receivable_account_id'] !== null ? (int)$row['receivable_account_id'] : null,
            exchangeRate: (float)($row['exchange_rate'] ?? 1.0),
            website: $row['website'] !== null ? (string)$row['website'] : null,
            department: $row['department'] !== null ? (string)$row['department'] : null,
            designation: $row['designation'] !== null ? (string)$row['designation'] : null,
            x: $row['x'] !== null ? (string)$row['x'] : null,
            facebook: $row['facebook'] !== null ? (string)$row['facebook'] : null,
            instagram: $row['instagram'] !== null ? (string)$row['instagram'] : null,
            photo: $row['photo'] !== null ? (string)$row['photo'] : null,
            description: $row['description'] !== null ? (string)$row['description'] : null,
            tags: $row['tags'] !== null ? (string)$row['tags'] : null,
            contactedDate: $row['contacted_date'] !== null ? (string)$row['contacted_date'] : null,
            approved: (bool)($row['approved'] ?? false),
            approvedBy: $row['approved_by'] !== null ? (int)$row['approved_by'] : null,
            approvedAt: $row['approved_at'] !== null ? (string)$row['approved_at'] : null,
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? false),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
            creditLimit: (float)($row['credit_limit'] ?? 0.00),
            discountType: $row['discount_type'] !== null ? (string)$row['discount_type'] : null,
            discountTypeValue: (float)($row['discount_type_value'] ?? 0.00),
            subscriptionTier: (string)($row['subscription_tier'] ?? 'registered'),
            subscriptionExpiresAt: $row['subscription_expires_at'] !== null ? (string)$row['subscription_expires_at'] : null
        );
    }

    /**
     * Map database row to CustomerContact DTO
     */
    private function mapRowToContact(array $row): CustomerContact
    {
        return new CustomerContact(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            isPrimary: (bool)($row['is_primary'] ?? false),
            customerId: (int)($row['contactable_id'] ?? $row['customer_id'] ?? 0),
            firstName: (string)$row['first_name'],
            lastName: (string)$row['last_name'],
            position: $row['position'] !== null ? (string)$row['position'] : null,
            email: (string)$row['email'],
            phone: $row['phone'] !== null ? (string)$row['phone'] : null,
            notes: $row['notes'] !== null ? (string)$row['notes'] : null,
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? false),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }

    /**
     * Map database row to CustomerAddress DTO
     */
    private function mapRowToAddress(array $row): CustomerAddress
    {
        return new CustomerAddress(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            type: (string)$row['type'],
            customerId: (int)($row['addressable_id'] ?? $row['customer_id'] ?? 0),
            attention: $row['attention'] !== null ? (string)$row['attention'] : null,
            country: (int)$row['country'],
            addressLine1: $row['address_line1'] !== null ? (string)$row['address_line1'] : null,
            addressLine2: $row['address_line2'] !== null ? (string)$row['address_line2'] : null,
            city: $row['city'] !== null ? (string)$row['city'] : null,
            state: $row['state'] !== null ? (string)$row['state'] : null,
            zipcode: $row['zipcode'] !== null ? (string)$row['zipcode'] : null,
            phone: $row['phone'] !== null ? (string)$row['phone'] : null,
            fax: $row['fax'] !== null ? (string)$row['fax'] : null,
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? false),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            createdBy: $row['created_by'] !== null ? (int)$row['created_by'] : 0
        );
    }

    /**
     * Get total receivables for a customer
     */
    public function getReceivables(int $customerId, int $orgId): float
    {
        $sql = "SELECT COALESCE(SUM(grand_total), 0) as total 
                FROM `{DB::INVOICES}` 
                WHERE customer_id = :customer_id 
                AND organization_id = :org_id 
                AND invoice_status NOT IN ('draft', 'void', 'cancelled', 'writeoff')";
        $row = $this->db->fetchOne($sql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        $invoiceTotal = (float)($row['total'] ?? 0.00);

        $cust = $this->db->fetchOne(
            "SELECT COALESCE(opening_balance, 0) as ob FROM `{DB::CUSTOMERS}` WHERE id = :id AND organization_id = :org_id",
            ['id' => $customerId, 'org_id' => $orgId]
        );
        $openingBalance = (float)($cust['ob'] ?? 0.00);

        $paidSql = "SELECT COALESCE(SUM(total_amount_received), 0) as total 
                FROM `{DB::PAYMENTS_RECEIVED}` 
                WHERE customer_id = :customer_id 
                AND organization_id = :org_id 
                AND payment_status != 'void'";
        $paidRow = $this->db->fetchOne($paidSql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        $paidTotal = (float)($paidRow['total'] ?? 0.00);

        $crSql = "SELECT COALESCE(SUM(grand_total), 0) as total 
                FROM `{DB::CREDIT_NOTES}` 
                WHERE customer_id = :customer_id 
                AND organization_id = :org_id 
                AND credit_note_status NOT IN ('draft', 'void')";
        $crRow = $this->db->fetchOne($crSql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        $creditTotal = (float)($crRow['total'] ?? 0.00);

        return $openingBalance + $invoiceTotal - $paidTotal - $creditTotal;
    }

    /**
     * Clone a customer within an organization
     */
    public function clone(int $id, int $orgId, int $userId): int
    {
        $sql = "INSERT INTO `{DB::CUSTOMERS}` (
                    organization_id, lead_id, customer_owner, customer_type, customer_status,
                    customer_source, assigned_to, salutation, first_name, last_name,
                    company_name, display_name, address, email, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    opening_balance, receivable_account_id, exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    approved, approved_by, approved_at, is_active,
                    created_at, updated_at, created_by
                )
                SELECT 
                    organization_id, lead_id, customer_owner, customer_type, customer_status,
                    customer_source, assigned_to, salutation, first_name, last_name,
                    company_name, CONCAT(display_name, ' (Copy)'), address, NULL, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    opening_balance, receivable_account_id, exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    0, NULL, NULL, 0,
                    NOW(), NOW(), :user_id
                FROM `{DB::CUSTOMERS}`
                WHERE id = :id AND organization_id = :org_id";

        return (int)$this->db->insert($sql, ['id' => $id, 'org_id' => $orgId, 'user_id' => $userId]);
    }
}
