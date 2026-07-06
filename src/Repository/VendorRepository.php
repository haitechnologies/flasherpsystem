<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Vendor;
use App\Exception\NotFoundException;

class VendorRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?Vendor
    {
        $sql = "SELECT id, organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                       vendor_source, assigned_to, salutation, first_name, last_name, company_name,
                       display_name, address, email, phone, mobile, payment_term, tax_treatment, trn,
                       corporate_tax_number, license_number, license_expiry, sales_person, lead_category, cs_agent, rating,
                       currency, exchange_rate, website, department, designation,
                       x, facebook, instagram, photo, description, tags, contacted_date,
                       approved, approved_by, approved_at, publish, is_active,
                       created_at, updated_at, updated_by, created_by
                FROM `{DB::VENDORS}`
                WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findByEmail(string $email, int $orgId): ?Vendor
    {
        $sql = "SELECT id, organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                       vendor_source, assigned_to, salutation, first_name, last_name, company_name,
                       display_name, address, email, phone, mobile, payment_term, tax_treatment, trn,
                       corporate_tax_number, license_number, license_expiry, sales_person, lead_category, cs_agent, rating,
                       currency, exchange_rate, website, department, designation,
                       x, facebook, instagram, photo, description, tags, contacted_date,
                       approved, approved_by, approved_at, publish, is_active,
                       created_at, updated_at, updated_by, created_by
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
        $sql = "SELECT id, organization_id, lead_id, vendor_owner, vendor_type, vendor_status,
                       vendor_source, assigned_to, salutation, first_name, last_name, company_name,
                       display_name, address, email, phone, mobile, payment_term, tax_treatment, trn,
                       corporate_tax_number, license_number, license_expiry, sales_person, lead_category, cs_agent, rating,
                       currency, exchange_rate, website, department, designation,
                       x, facebook, instagram, photo, description, tags, contacted_date,
                       approved, approved_by, approved_at, publish, is_active,
                       created_at, updated_at, updated_by, created_by
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
                    company_name, display_name, address, email, phone, mobile,
                    payment_term, tax_treatment, trn, corporate_tax_number, license_number, license_expiry,
                    sales_person, lead_category, cs_agent, rating, currency,
                    exchange_rate, website, department, designation,
                    x, facebook, instagram, photo, description, tags, contacted_date,
                    approved, approved_by, approved_at, publish, is_active,
                    created_at, updated_at, updated_by, created_by
                ) VALUES (
                    :organization_id, :lead_id, :vendor_owner, :vendor_type, :vendor_status,
                    :vendor_source, :assigned_to, :salutation, :first_name, :last_name,
                    :company_name, :display_name, :address, :email, :phone, :mobile,
                     :payment_term, :tax_treatment, :trn, :corporate_tax_number, :license_number, :license_expiry,
                    :sales_person, :lead_category, :cs_agent, :rating, :currency,
                    :exchange_rate, :website, :department, :designation,
                    :x, :facebook, :instagram, :photo, :description, :tags, :contacted_date,
                    :approved, :approved_by, :approved_at, :publish, :is_active,
                    NOW(), NOW(), :updated_by, :created_by
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
                    display_name = :display_name, address = :address, email = :email,
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
                    updated_at = NOW(), updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $vendor->toArray();
        unset($params['created_at'], $params['created_by']);

        $this->db->execute($sql, $params);
        $updated = $this->find($vendor->id, (int)$vendor->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated vendor.");
        }
        return $updated;
    }

    public function delete(int $id, int $orgId): bool
    {
        $this->db->execute("DELETE FROM `{DB::VENDORS}` WHERE id = :id AND organization_id = :org_id", ['id' => $id, 'org_id' => $orgId]);
        return true;
    }

    public function getReceivables(int $vendorId, int $orgId): float
    {
        $sql = "SELECT COALESCE(SUM(grand_total), 0) AS total
                FROM `{DB::PURCHASES}`
                WHERE vendor_id = :vendor_id AND organization_id = :org_id
                  AND is_active = 1";
        $row = $this->db->fetchOne($sql, ['vendor_id' => $vendorId, 'org_id' => $orgId]);
        return (float)($row['total'] ?? 0);
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
            email: (string)($row['email'] ?? '') !== '' ? (string)$row['email'] : null,
            phone: (string)($row['phone'] ?? '') !== '' ? (string)$row['phone'] : null,
            mobile: (string)($row['mobile'] ?? '') !== '' ? (string)$row['mobile'] : null,
            paymentTerm: isset($row['payment_term']) ? (int)$row['payment_term'] : null,
            taxTreatment: isset($row['tax_treatment']) ? (int)$row['tax_treatment'] : null,
            trn: (string)($row['trn'] ?? '') !== '' ? (string)$row['trn'] : null,
            corporateTaxNumber: (string)($row['corporate_tax_number'] ?? '') !== '' ? (string)$row['corporate_tax_number'] : null,
            licenseNumber: isset($row['license_number']) ? (int)$row['license_number'] : null,
            licenseExpiry: (string)($row['license_expiry'] ?? ''),
            salesPerson: isset($row['sales_person']) ? (int)$row['sales_person'] : null,
            leadCategory: (string)($row['lead_category'] ?? '') !== '' ? (string)$row['lead_category'] : null,
            csAgent: isset($row['cs_agent']) ? (int)$row['cs_agent'] : null,
            rating: isset($row['rating']) ? (int)$row['rating'] : null,
            currency: isset($row['currency']) ? (int)$row['currency'] : null,
            exchangeRate: (int)($row['exchange_rate'] ?? 1),
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
