<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Bank;

class BankRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?Bank
    {
        $sql = "SELECT id, organization_id, account_name, account_code, opening_balance, account_id, currency, bank_name,
                       branch, address, iban, routing_number, description, is_primary, is_active,
                       created_at, updated_at, updated_by, created_by
                FROM `{DB::BANKS}`
                WHERE id = :id AND organization_id = :org_id";

        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }

        return $this->mapRowToDto($row);
    }

    public function findAll(int $orgId): array
    {
        $sql = "SELECT id, organization_id, account_name, account_code, opening_balance, account_id, currency, bank_name,
                       branch, address, iban, routing_number, description, is_primary, is_active,
                       created_at, updated_at, updated_by, created_by
                FROM `{DB::BANKS}`
                WHERE organization_id = :org_id
                ORDER BY account_name ASC";

        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);
        $banks = [];
        foreach ($rows as $row) {
            $banks[] = $this->mapRowToDto($row);
        }

        return $banks;
    }

    public function existsByName(string $name, int $orgId, ?int $excludeId = null): bool
    {
        $params = ['name' => $name, 'org_id' => $orgId];
        if ($excludeId !== null) {
            $sql = "SELECT id FROM `{DB::BANKS}` WHERE account_name = :name AND organization_id = :org_id AND id != :exclude_id LIMIT 1";
            $params['exclude_id'] = $excludeId;
        } else {
            $sql = "SELECT id FROM `{DB::BANKS}` WHERE account_name = :name AND organization_id = :org_id LIMIT 1";
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    public function save(Bank $bank): Bank
    {
        if ($bank->id === null) {
            return $this->insert($bank);
        }
        return $this->update($bank);
    }

    private function insert(Bank $bank): Bank
    {
        $sql = "INSERT INTO `{DB::BANKS}` (organization_id, account_name, account_code, opening_balance, account_id, currency, bank_name,
                                           branch, address, iban, routing_number, description, is_primary, is_active, created_by)
                VALUES (:organization_id, :account_name, :account_code, :opening_balance, :account_id, :currency, :bank_name,
                        :branch, :address, :iban, :routing_number, :description, :is_primary, :is_active, :created_by)";

        $params = [
            'organization_id' => $bank->organizationId,
            'account_name' => $bank->accountName,
            'account_code' => $bank->accountCode,
            'opening_balance' => $bank->openingBalance,
            'account_id' => $bank->accountId,
            'currency' => $bank->currency,
            'bank_name' => $bank->bankName,
            'branch' => $bank->branch,
            'address' => $bank->address,
            'iban' => $bank->iban,
            'routing_number' => $bank->routingNumber,
            'description' => $bank->description,
            'is_primary' => $bank->isPrimary ? 1 : 0,
            'is_active' => $bank->isActive ? 1 : 0,
            'created_by' => $bank->createdBy,
        ];

        $insertId = (int)$this->db->insert($sql, $params);
        return $this->find($insertId, $bank->organizationId);
    }

    private function update(Bank $bank): Bank
    {
        $sql = "UPDATE `{DB::BANKS}`
                SET account_name = :account_name,
                    account_code = :account_code,
                    opening_balance = :opening_balance,
                    account_id = :account_id,
                    currency = :currency,
                    bank_name = :bank_name,
                    branch = :branch,
                    address = :address,
                    iban = :iban,
                    routing_number = :routing_number,
                    description = :description,
                    is_primary = :is_primary,
                    is_active = :is_active,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :organization_id";

        $params = [
            'account_name' => $bank->accountName,
            'account_code' => $bank->accountCode,
            'opening_balance' => $bank->openingBalance,
            'account_id' => $bank->accountId,
            'currency' => $bank->currency,
            'bank_name' => $bank->bankName,
            'branch' => $bank->branch,
            'address' => $bank->address,
            'iban' => $bank->iban,
            'routing_number' => $bank->routingNumber,
            'description' => $bank->description,
            'is_primary' => $bank->isPrimary ? 1 : 0,
            'is_active' => $bank->isActive ? 1 : 0,
            'updated_by' => $bank->updatedBy ?? 0,
            'id' => $bank->id,
            'organization_id' => $bank->organizationId,
        ];

        $this->db->execute($sql, $params);
        return $this->find((int)$bank->id, $bank->organizationId);
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->execute(
            "DELETE FROM `{DB::BANKS}` WHERE id = :id AND organization_id = :org_id",
            ['id' => $id, 'org_id' => $orgId]
        );
        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): Bank
    {
        return new Bank(
            id: (int)$row['id'],
            organizationId: (int)$row['organization_id'],
            accountName: (string)$row['account_name'],
            accountCode: (string)$row['account_code'],
            openingBalance: (float)($row['opening_balance'] ?? 0.00),
            accountId: isset($row['account_id']) ? (int)$row['account_id'] : null,
            currency: (int)$row['currency'],
            bankName: (string)$row['bank_name'],
            branch: (string)($row['branch'] ?? ''),
            address: (string)($row['address'] ?? ''),
            iban: (string)($row['iban'] ?? ''),
            routingNumber: (string)$row['routing_number'],
            description: (string)$row['description'],
            isPrimary: (bool)$row['is_primary'],
            isActive: (bool)$row['is_active'],
            createdAt: (string)($row['created_at'] ?? ''),
            updatedAt: (string)($row['updated_at'] ?? ''),
            updatedBy: isset($row['updated_by']) ? (int)$row['updated_by'] : null,
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }
}
