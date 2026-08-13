<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\SetupBank;

class SetupBankRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    private const COLUMNS = 'id, organization_id, institution_name, head_office, is_active, created_at, updated_at, created_by, updated_by';

    public function find(int $id, int $orgId): ?SetupBank
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM `{DB::SETUP_BANKS}` WHERE id = :id AND organization_id = :org_id';
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);

        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(int $orgId): array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM `{DB::SETUP_BANKS}` WHERE organization_id = :org_id ORDER BY institution_name ASC';
        $rows = $this->db->fetchAll($sql, ['org_id' => $orgId]);

        $banks = [];
        foreach ($rows as $row) {
            $banks[] = $this->mapRowToDto($row);
        }

        return $banks;
    }

    public function insert(SetupBank $bank): int
    {
        $sql = 'INSERT INTO `{DB::SETUP_BANKS}`
                (organization_id, institution_name, head_office, is_active, created_by)
                VALUES
                (:organization_id, :institution_name, :head_office, :is_active, :created_by)';
        $params = [
            'organization_id' => $bank->organizationId,
            'institution_name' => $bank->institutionName,
            'head_office' => $bank->headOffice,
            'is_active' => $bank->isActive ? 1 : 0,
            'created_by' => $bank->createdBy,
        ];

        return (int) $this->db->insert($sql, $params);
    }

    public function update(SetupBank $bank): bool
    {
        $sql = 'UPDATE `{DB::SETUP_BANKS}` SET
                    institution_name = :institution_name,
                    head_office = :head_office,
                    is_active = :is_active,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND organization_id = :organization_id';
        $params = [
            'institution_name' => $bank->institutionName,
            'head_office' => $bank->headOffice,
            'is_active' => $bank->isActive ? 1 : 0,
            'updated_by' => $bank->updatedBy,
            'id' => $bank->id,
            'organization_id' => $bank->organizationId,
        ];
        $this->db->execute($sql, $params);

        return true;
    }

    public function delete(int $id, int $orgId): bool
    {
        $stmt = $this->db->execute(
            'UPDATE `{DB::SETUP_BANKS}` SET is_active = 0 WHERE id = :id AND organization_id = :org_id',
            ['id' => $id, 'org_id' => $orgId]
        );

        return $stmt->rowCount() > 0;
    }

    private function mapRowToDto(array $row): SetupBank
    {
        return new SetupBank(
            id: (int) $row['id'],
            organizationId: (int) $row['organization_id'],
            institutionName: (string) $row['institution_name'],
            headOffice: (string) $row['head_office'],
            isActive: (bool) $row['is_active'],
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
            createdBy: (int) ($row['created_by'] ?? 0),
            updatedBy: (int) ($row['updated_by'] ?? 0),
        );
    }
}
