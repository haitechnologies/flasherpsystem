<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\CustomerAddress;

class CustomerAddressRepository
{
    private const ADDRESS_COLUMNS = '
        id, organization_id, addressable_type, addressable_id, type,
        attention, country, address_line1, address_line2, city, state, zipcode,
        phone, fax, publish, is_active, created_at, updated_at, updated_by, created_by';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findByCustomerAndType(int $customerId, int $orgId, string $type): ?CustomerAddress
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::CUSTOMER_ADDRESSES}` WHERE addressable_type = 'Customer' AND addressable_id = :customer_id AND type = :type AND organization_id = :org_id LIMIT 1";
        $row = $this->db->fetchOne($sql, ['customer_id' => $customerId, 'type' => $type, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToAddress($row);
    }

    public function save(CustomerAddress $address): CustomerAddress
    {
        if ($address->id === null) {
            return $this->insert($address);
        }
        return $this->update($address);
    }

    private function insert(CustomerAddress $address): CustomerAddress
    {
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

        $this->db->execute($sql, $params);
        $insertId = (int)$this->db->getConnection()->lastInsertId();

        $inserted = $this->findById($insertId, $address->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted address.");
        }

        return $inserted;
    }

    private function update(CustomerAddress $address): CustomerAddress
    {
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
        unset($params['customer_id'], $params['type'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->findById((int)$address->id, $address->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated address.");
        }

        return $updated;
    }

    private function findById(int $id, int $orgId): ?CustomerAddress
    {
        $sql = "SELECT " . self::ADDRESS_COLUMNS . " FROM `{DB::CUSTOMER_ADDRESSES}` WHERE id = :id AND addressable_type = 'Customer' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToAddress($row);
    }

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
            createdBy: $row['created_by'] !== null ? (int)$row['created_by'] : 0,
        );
    }
}
