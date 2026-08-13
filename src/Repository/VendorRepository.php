<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Vendor;
use App\Model\VendorContact;
use App\Model\VendorAddress;
use App\Exception\NotFoundException;

class VendorRepository
{
    private const VENDOR_COLUMNS = '
        id, organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
        vendor_source, assigned_to, salutation, first_name, last_name,
        company_name, display_name, address, opening_balance, payable_account_id,
        email, phone, mobile, payment_term, tax_treatment, trn,
        corporate_tax_number, license_number, license_expiry, sales_person, lead_category, cs_agent, rating, currency,
        exchange_rate, website, department, designation,
        x, facebook, instagram, photo, description, tags, contacted_date,
        approved, approved_by, approved_at, publish, is_active,
        created_at, updated_at, updated_by, created_by, credit_limit';

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

    public function find(int $id, int $orgId): ?Vendor
    {
        $sql = "SELECT " . self::VENDOR_COLUMNS . "
                FROM `{DB::VENDORS}`
                WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findByEmail(string $email, int $orgId): ?Vendor
    {
        $sql = "SELECT " . self::VENDOR_COLUMNS . "
                FROM `{DB::VENDORS}`
                WHERE email = :email AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['email' => trim($email), 'org_id' => $orgId]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function existsByEmail(string $email, int $orgId, ?int $excludeId = null): bool
    {
        $email = trim($email);
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::VENDORS}` WHERE email = :email AND organization_id = :org_id AND id != :exclude_id LIMIT 1";
            $params = ['email' => $email, 'org_id' => $orgId, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM `{DB::VENDORS}` WHERE email = :email AND organization_id = :org_id LIMIT 1";
            $params = ['email' => $email, 'org_id' => $orgId];
        }
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function findAll(int $orgId): array
    {
        $sql = "SELECT " . self::VENDOR_COLUMNS . "
                FROM `{DB::VENDORS}`
                WHERE organization_id = :org_id ORDER BY display_name ASC";
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        return array_map($this->mapRowToDto(...), $rows);
    }

    public function save(Vendor $vendor): Vendor
    {
        if ($vendor->id === null) {
            return $this->insert($vendor);
        }
        return $this->update($vendor);
    }

    private function insert(Vendor $vendor): Vendor
    {
        $sql = "INSERT INTO `{DB::VENDORS}` (
                    organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                    vendor_source, assigned_to, salutation, first_name, last_name,
                    company_name, display_name, address, opening_balance, payable_account_id,
                    email, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    approved, approved_by, approved_at, publish, is_active,
                    created_at, updated_at, updated_by, created_by, credit_limit
                ) VALUES (
                    :organization_id, :lead_id, :vendor_owner, :vendor_type, :vendor_status,
                    :vendor_source, :assigned_to, :salutation, :first_name, :last_name,
                    :company_name, :display_name, :address, :opening_balance, :payable_account_id,
                    :email, :phone, :mobile,
                     :payment_term, :tax_treatment, :trn, :corporate_tax_number, :license_number, :license_expiry,
                    :sales_person, :lead_category, :cs_agent, :rating, :currency,
                    :exchange_rate, :website, :department, :designation,
                    :x, :facebook, :instagram, :photo, :description, :tags, :contacted_date,
                    :approved, :approved_by, :approved_at, :publish, :is_active,
                    NOW(), NOW(), :updated_by, :created_by, :credit_limit
                )";

        $params = $vendor->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at']);

        $insertId = (int)$this->db->insert($sql, $params);
        $inserted = $this->find($insertId, (int)$vendor->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted vendor.");
        }
        return $inserted;
    }

    private function update(Vendor $vendor): Vendor
    {
        $sql = "UPDATE `{DB::VENDORS}` SET
                    lead_id = :lead_id, vendor_owner = :vendor_owner, vendor_type = :vendor_type,
                    vendor_status = :vendor_status, vendor_source = :vendor_source,
                    assigned_to = :assigned_to, salutation = :salutation,
                    first_name = :first_name, last_name = :last_name, company_name = :company_name,
                    display_name = :display_name, address = :address,
                    opening_balance = :opening_balance, payable_account_id = :payable_account_id,
                    email = :email,
                    phone = :phone, mobile = :mobile, payment_term = :payment_term,
                    tax_treatment = :tax_treatment, trn = :trn,
                    corporate_tax_number = :corporate_tax_number,
                    license_number = :license_number, license_expiry = :license_expiry,
                    sales_person = :sales_person, lead_category = :lead_category,
                    cs_agent = :cs_agent, rating = :rating, currency = :currency,
                    exchange_rate = :exchange_rate, website = :website,
                    department = :department, designation = :designation,
                    x = :x, facebook = :facebook, instagram = :instagram,
                    photo = :photo, description = :description, tags = :tags,
                    contacted_date = :contacted_date, approved = :approved,
                    approved_by = :approved_by, approved_at = :approved_at,
                    publish = :publish, is_active = :is_active,
                    updated_at = NOW(), updated_by = :updated_by, credit_limit = :credit_limit
                WHERE id = :id AND organization_id = :organization_id";

        $params = $vendor->toArray();
        unset($params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);
        $updated = $this->find($vendor->id, (int)$vendor->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated vendor.");
        }
        return $updated;
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->execute(
            "UPDATE `{DB::VENDORS}` SET is_active = 0 WHERE id = :id AND organization_id = :org_id",
            ['id' => $id, 'org_id' => $orgId]
        );
        return $stmt->rowCount() > 0;
    }

    public function getPayables(int $vendorId, int $orgId): float
    {
        $sql = "SELECT COALESCE(SUM(grand_total), 0) AS total
                FROM `{DB::PURCHASES}`
                WHERE vendor_id = :vendor_id AND organization_id = :org_id
                  AND is_active = 1
                  AND purchase_status NOT IN ('draft', 'declined', 'expired')";
        $row = $this->db->fetchOne($sql, ['vendor_id' => $vendorId, 'org_id' => $orgId]);
        $purchaseTotal = (float)($row['total'] ?? 0);

        $paid = $this->db->fetchOne(
            "SELECT COALESCE(SUM(total_amount_paid), 0) AS total
             FROM `{DB::PAYMENTS_MADE}`
             WHERE vendor_id = :vendor_id AND organization_id = :org_id
               AND payment_status != 'void'",
            ['vendor_id' => $vendorId, 'org_id' => $orgId]
        );
        $paidTotal = (float)($paid['total'] ?? 0);

        $ob = $this->db->fetchOne(
            "SELECT COALESCE(opening_balance, 0) AS ob FROM `{DB::VENDORS}` WHERE id = :id AND organization_id = :org_id",
            ['id' => $vendorId, 'org_id' => $orgId]
        );
        $openingBalance = (float)($ob['ob'] ?? 0);

        $dn = $this->db->fetchOne(
            "SELECT COALESCE(SUM(grand_total), 0) AS total
             FROM `{DB::DEBIT_NOTES}`
             WHERE vendor_id = :vendor_id AND organization_id = :org_id
               AND debit_note_status NOT IN ('draft', 'void')",
            ['vendor_id' => $vendorId, 'org_id' => $orgId]
        );
        $debitNoteTotal = (float)($dn['total'] ?? 0);

        return max($openingBalance + $purchaseTotal - $paidTotal - $debitNoteTotal, 0.0);
    }

    public function findContact(int $id, int $orgId): ?VendorContact
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::VENDOR_CONTACTS}` WHERE id = :id AND contactable_type = 'Vendor' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToContact($row);
    }

    public function findContactsByVendor(int $vendorId, int $orgId): array
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::VENDOR_CONTACTS}` WHERE contactable_type = 'Vendor' AND contactable_id = :vendor_id AND organization_id = :org_id ORDER BY is_primary DESC, id ASC";
        $rows = $this->db->fetchAll($sql, ['vendor_id' => $vendorId, 'org_id' => $orgId]);
        return array_map([$this, 'mapRowToContact'], $rows);
    }

    public function clearPrimaryContacts(int $vendorId, int $orgId): void
    {
        $sql = "UPDATE `{DB::VENDOR_CONTACTS}` SET is_primary = 0 WHERE contactable_type = 'Vendor' AND contactable_id = :vendor_id AND organization_id = :org_id";
        $this->db->execute($sql, ['vendor_id' => $vendorId, 'org_id' => $orgId]);
    }

    public function saveContact(VendorContact $contact): VendorContact
    {
        if ($contact->id === null) {
            $sql = "INSERT INTO `{DB::VENDOR_CONTACTS}` (
                        organization_id, contactable_type, contactable_id, is_primary, first_name, last_name,
                        position, email, phone, notes, publish, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        :organization_id, 'Vendor', :vendor_id, :is_primary, :first_name, :last_name,
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

        $sql = "UPDATE `{DB::VENDOR_CONTACTS}` SET
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
                WHERE id = :id AND contactable_type = 'Vendor' AND organization_id = :organization_id";
        $params = $contact->toArray();
        unset($params['vendor_id'], $params['publish'], $params['created_at'], $params['updated_at'], $params['created_by']);
        $this->db->execute($sql, $params);
        $updated = $this->findContact((int)$contact->id, $contact->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated contact.");
        }
        return $updated;
    }

    public function deleteContact(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::VENDOR_CONTACTS}` WHERE id = :id AND contactable_type = 'Vendor' AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function findAddress(int $id, int $orgId): ?VendorAddress
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::VENDOR_ADDRESSES}` WHERE id = :id AND addressable_type = 'Vendor' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToAddress($row);
    }

    public function findAddressesByVendor(int $vendorId, int $orgId): array
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::VENDOR_ADDRESSES}` WHERE addressable_type = 'Vendor' AND addressable_id = :vendor_id AND organization_id = :org_id ORDER BY id ASC";
        $rows = $this->db->fetchAll($sql, ['vendor_id' => $vendorId, 'org_id' => $orgId]);
        return array_map([$this, 'mapRowToAddress'], $rows);
    }

    public function saveAddress(VendorAddress $address): VendorAddress
    {
        if ($address->id === null) {
            $sql = "INSERT INTO `{DB::VENDOR_ADDRESSES}` (
                        organization_id, addressable_type, addressable_id, type, attention, country,
                        address_line1, address_line2, city, state, zipcode, phone, fax,
                        publish, is_active, created_by, created_at, updated_at
                    ) VALUES (
                        :organization_id, 'Vendor', :vendor_id, :type, :attention, :country,
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

        $sql = "UPDATE `{DB::VENDOR_ADDRESSES}` SET
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
                WHERE id = :id AND addressable_type = 'Vendor' AND organization_id = :organization_id";
        $params = $address->toArray();
        unset($params['type'], $params['vendor_id'], $params['created_at'], $params['updated_at'], $params['created_by']);
        $this->db->execute($sql, $params);
        $updated = $this->findAddress((int)$address->id, $address->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated address.");
        }
        return $updated;
    }

    public function deleteAddress(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::VENDOR_ADDRESSES}` WHERE id = :id AND addressable_type = 'Vendor' AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    public function clone(int $id, int $orgId, int $userId): int
    {
        $sql = "INSERT INTO `{DB::VENDORS}` (
                    organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                    vendor_source, assigned_to, salutation, first_name, last_name,
                    company_name, display_name, address, opening_balance, payable_account_id,
                    email, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    approved, approved_by, approved_at, publish, is_active,
                    created_at, updated_at, created_by, credit_limit
                )
                SELECT
                    organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                    vendor_source, assigned_to, salutation, first_name, last_name,
                    company_name, CONCAT(display_name, ' (Copy)'), address, opening_balance, payable_account_id,
                    NULL, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    0, NULL, NULL, publish, 0,
                    NOW(), NOW(), :user_id, credit_limit
                FROM `{DB::VENDORS}`
                WHERE id = :id AND organization_id = :org_id";

        return (int)$this->db->insert($sql, ['id' => $id, 'org_id' => $orgId, 'user_id' => $userId]);
    }

    private function mapRowToContact(array $row): VendorContact
    {
        return new VendorContact(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            isPrimary: (bool)($row['is_primary'] ?? false),
            vendorId: (int)($row['contactable_id'] ?? $row['vendor_id'] ?? 0),
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

    private function mapRowToAddress(array $row): VendorAddress
    {
        return new VendorAddress(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            type: (string)$row['type'],
            vendorId: (int)($row['addressable_id'] ?? $row['vendor_id'] ?? 0),
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

    private function mapRowToDto(array $row): Vendor
    {
        return new Vendor(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            leadId: isset($row['lead_id']) ? (int)$row['lead_id'] : null,
            vendorOwner: isset($row['vendor_owner']) ? (int)$row['vendor_owner'] : null,
            vendorType: (string)($row['vendor_type'] ?? ''),
            vendorStatus: isset($row['vendor_status']) ? (int)$row['vendor_status'] : null,
            vendorSource: isset($row['vendor_source']) ? (int)$row['vendor_source'] : null,
            assignedTo: isset($row['assigned_to']) ? (int)$row['assigned_to'] : null,
            salutation: (string)($row['salutation'] ?? '') !== '' ? (string)$row['salutation'] : null,
            firstName: (string)($row['first_name'] ?? '') !== '' ? (string)$row['first_name'] : null,
            lastName: (string)($row['last_name'] ?? '') !== '' ? (string)$row['last_name'] : null,
            companyName: (string)($row['company_name'] ?? '') !== '' ? (string)$row['company_name'] : null,
            displayName: (string)($row['display_name'] ?? ''),
            address: (string)($row['address'] ?? ''),
            openingBalance: (float)($row['opening_balance'] ?? 0.00),
            payableAccountId: isset($row['payable_account_id']) ? (int)$row['payable_account_id'] : null,
            creditLimit: (float)($row['credit_limit'] ?? 0.00),
            email: (string)($row['email'] ?? '') !== '' ? (string)$row['email'] : null,
            phone: (string)($row['phone'] ?? '') !== '' ? (string)$row['phone'] : null,
            mobile: (string)($row['mobile'] ?? '') !== '' ? (string)$row['mobile'] : null,
            paymentTerm: isset($row['payment_term']) ? (int)$row['payment_term'] : null,
            taxTreatment: isset($row['tax_treatment']) ? (int)$row['tax_treatment'] : null,
            trn: (string)($row['trn'] ?? '') !== '' ? (string)$row['trn'] : null,
            corporateTaxNumber: (string)($row['corporate_tax_number'] ?? '') !== '' ? (string)$row['corporate_tax_number'] : null,
            licenseNumber: (string)($row['license_number'] ?? '') !== '' ? (string)$row['license_number'] : null,
            licenseExpiry: (string)($row['license_expiry'] ?? ''),
            salesPerson: isset($row['sales_person']) ? (int)$row['sales_person'] : null,
            leadCategory: (string)($row['lead_category'] ?? '') !== '' ? (string)$row['lead_category'] : null,
            csAgent: isset($row['cs_agent']) ? (int)$row['cs_agent'] : null,
            rating: isset($row['rating']) ? (int)$row['rating'] : null,
            currency: isset($row['currency']) ? (int)$row['currency'] : null,
            exchangeRate: (float)($row['exchange_rate'] ?? 1.0),
            website: (string)($row['website'] ?? '') !== '' ? (string)$row['website'] : null,
            department: (string)($row['department'] ?? '') !== '' ? (string)$row['department'] : null,
            designation: (string)($row['designation'] ?? '') !== '' ? (string)$row['designation'] : null,
            x: (string)($row['x'] ?? '') !== '' ? (string)$row['x'] : null,
            facebook: (string)($row['facebook'] ?? '') !== '' ? (string)$row['facebook'] : null,
            instagram: (string)($row['instagram'] ?? '') !== '' ? (string)$row['instagram'] : null,
            photo: (string)($row['photo'] ?? '') !== '' ? (string)$row['photo'] : null,
            description: (string)($row['description'] ?? '') !== '' ? (string)$row['description'] : null,
            tags: (string)($row['tags'] ?? '') !== '' ? (string)$row['tags'] : null,
            contactedDate: (string)($row['contacted_date'] ?? '') !== '' ? (string)$row['contacted_date'] : null,
            approved: (bool)($row['approved'] ?? false),
            approvedBy: isset($row['approved_by']) ? (int)$row['approved_by'] : null,
            approvedAt: (string)($row['approved_at'] ?? '') !== '' ? (string)$row['approved_at'] : null,
            publish: (bool)($row['publish'] ?? false),
            isActive: (bool)($row['is_active'] ?? true),
            createdAt: (string)($row['created_at'] ?? ''),
            updatedAt: (string)($row['updated_at'] ?? ''),
            updatedBy: isset($row['updated_by']) ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }
}
