<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\CustomerContact;

class CustomerContactRepository
{
    private const CONTACT_COLUMNS = '
        id, organization_id, contactable_type, contactable_id, is_primary,
        first_name, last_name, position, email, phone, notes,
        publish, is_active, created_at, updated_at, updated_by, created_by';

    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?CustomerContact
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::CUSTOMER_CONTACTS}` WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToContact($row);
    }

    /**
     * @return CustomerContact[]
     */
    public function findByCustomer(int $customerId, int $orgId): array
    {
        $sql = "SELECT " . self::CONTACT_COLUMNS . " FROM `{DB::CUSTOMER_CONTACTS}` WHERE contactable_type = 'Customer' AND contactable_id = :customer_id AND organization_id = :org_id ORDER BY is_primary DESC, id ASC";
        $rows = $this->db->fetchAll($sql, ['customer_id' => $customerId, 'org_id' => $orgId]);
        return array_map([$this, 'mapRowToContact'], $rows);
    }

    public function save(CustomerContact $contact): CustomerContact
    {
        if ($contact->id === null) {
            return $this->insert($contact);
        }
        return $this->update($contact);
    }

    public function delete(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::CUSTOMER_CONTACTS}` WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    private function insert(CustomerContact $contact): CustomerContact
    {
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

        $inserted = $this->find($insertId, $contact->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted contact.");
        }

        return $inserted;
    }

    private function update(CustomerContact $contact): CustomerContact
    {
        $sql = "UPDATE `{DB::CUSTOMER_CONTACTS}` SET
                    is_primary = :is_primary,
                    first_name = :first_name,
                    last_name = :last_name,
                    position = :position,
                    email = :email,
                    phone = :phone,
                    notes = :notes,
                    publish = :publish,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND contactable_type = 'Customer' AND organization_id = :organization_id";

        $params = $contact->toArray();
        unset($params['customer_id'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$contact->id, $contact->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated contact.");
        }

        return $updated;
    }

    private function mapRowToContact(array $row): CustomerContact
    {
        return new CustomerContact(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            isPrimary: (bool)($row['is_primary'] ?? false),
            customerId: (int)($row['contactable_id'] ?? 0),
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
            createdBy: (int)($row['created_by'] ?? 0),
        );
    }
}
