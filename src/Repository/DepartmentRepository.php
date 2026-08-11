<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Model\Department;
use App\Core\DB;

/**
 * Department Repository
 *
 * Handles PDO-based data access for erp_departments table.
 * Adheres strictly to PSR.md rules: no raw SQL, no SELECT *, no string interpolation.
 */
class DepartmentRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Find a department by ID
     *
     * @param int $id
     * @return Department|null
     */
    public function find(int $id): ?Department
    {
        $sql = "SELECT id, organization_id, department, email, publish, created_at, updated_at, created_by 
                FROM DB::DEPARTMENTS 
                WHERE id = :id";

        $row = $this->db->fetchOne($sql, ['id' => $id]);
        if ($row === null) {
            return null;
        }

        return $this->mapRowToDto($row);
    }

    /**
     * Find all departments for an organization
     *
     * @param int $organizationId
     * @return Department[]
     */
    public function findAll(int $organizationId): array
    {
        $sql = "SELECT id, organization_id, department, email, publish, created_at, updated_at, created_by 
                FROM DB::DEPARTMENTS 
                WHERE organization_id = :organization_id 
                ORDER BY department ASC";

        $rows = $this->db->fetchAll($sql, ['organization_id' => $organizationId]);
        $departments = [];
        foreach ($rows as $row) {
            $departments[] = $this->mapRowToDto($row);
        }

        return $departments;
    }

    /**
     * Check if a department name exists, optionally excluding an ID
     *
     * @param string $name
     * @param int|null $excludeId
     * @return bool
     */
    public function existsByName(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId !== null) {
            $sql = "SELECT id FROM DB::DEPARTMENTS WHERE department = :name AND id != :exclude_id LIMIT 1";
            $params = ['name' => $name, 'exclude_id' => $excludeId];
        } else {
            $sql = "SELECT id FROM DB::DEPARTMENTS WHERE department = :name LIMIT 1";
            $params = ['name' => $name];
        }

        $row = $this->db->fetchOne($sql, $params);
        return $row !== null;
    }

    /**
     * Save a department record (Insert or Update)
     *
     * @param Department $department
     * @return Department
     */
    public function save(Department $department): Department
    {
        if ($department->id === null) {
            return $this->insert($department);
        }
        return $this->update($department);
    }

    /**
     * Insert a new department
     */
    private function insert(Department $dept): Department
    {
        $sql = "INSERT INTO DB::DEPARTMENTS (organization_id, department, email, publish, created_by) 
                VALUES (:organization_id, :department, :email, :publish, :created_by)";

        $params = [
            'organization_id' => $dept->organizationId,
            'department' => $dept->department,
            'email' => $dept->email,
            'publish' => $dept->publish ? 1 : 0,
            'created_by' => $dept->createdBy,
        ];

        $insertId = (int)$this->db->insert($sql, $params);
        return $this->find($insertId);
    }

    /**
     * Update an existing department
     */
    private function update(Department $dept): Department
    {
        $sql = "UPDATE DB::DEPARTMENTS 
                SET organization_id = :organization_id, 
                    department = :department,
                    email = :email,
                    publish = :publish, 
                    created_by = :created_by 
                WHERE id = :id";

        $params = [
            'organization_id' => $dept->organizationId,
            'department' => $dept->department,
            'email' => $dept->email,
            'publish' => $dept->publish ? 1 : 0,
            'created_by' => $dept->createdBy,
            'id' => $dept->id,
        ];

        $this->db->execute($sql, $params);
        return $this->find((int)$dept->id);
    }

    /**
     * Delete a department by ID
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM DB::DEPARTMENTS WHERE id = :id";
        $stmt = $this->db->execute($sql, ['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Map database row to Department DTO
     */
    private function mapRowToDto(array $row): Department
    {
        return new Department(
            id: (int)$row['id'],
            organizationId: $row['organization_id'] !== null ? (int)$row['organization_id'] : null,
            department: (string)$row['department'],
            publish: (bool)($row['publish'] ?? false),
            email: $row['email'] ?? null,
            createdAt: (string)($row['created_at'] ?? ''),
            updatedAt: (string)($row['updated_at'] ?? ''),
            createdBy: (int)($row['created_by'] ?? 0)
        );
    }
}
