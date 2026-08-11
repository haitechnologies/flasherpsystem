<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\ModulePermission;
use App\Core\ErrorCapture;

class ModulePermissionRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?ModulePermission
    {
        $sql = "SELECT id, permission_name, description, is_active, created_by, created_at
                FROM `{DB::MODULE_PERMISSIONS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, permission_name, description, is_active, created_by, created_at
                FROM `{DB::MODULE_PERMISSIONS}` ORDER BY permission_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::MODULE_PERMISSIONS}` WHERE permission_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::MODULE_PERMISSIONS}` WHERE permission_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(ModulePermission $item): int
    {
        $sql = "INSERT INTO `{DB::MODULE_PERMISSIONS}` (permission_name, description, is_active, created_by)
                VALUES (:permission_name, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'permission_name' => $item->permissionName,
            'description' => $item->description,
            'is_active' => $item->isActive ? 1 : 0,
            'created_by' => $item->createdBy,
        ]);
    }

    public function update(int $id, array $data): bool
    {
        $sets = [];
        $params = [];
        foreach ($data as $col => $val) {
            $key = 'u_' . str_replace('.', '_', $col);
            $sets[] = "`{$col}` = :{$key}";
            $params[$key] = $val;
        }
        $params['id'] = $id;
        $sql = "UPDATE `{DB::MODULE_PERMISSIONS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("ModulePermissionRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::MODULE_PERMISSIONS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): ModulePermission
    {
        return new ModulePermission(
            id: (int)$row['id'],
            permissionName: (string)($row['permission_name'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
