<?php
declare(strict_types=1);
namespace App\Repository;
use App\Core\Database;
use App\Core\DB;
use App\Model\StorageSubtype;
use App\Core\ErrorCapture;
class StorageSubtypeRepository
{
    private Database $db;
    public function __construct(Database $db) { $this->db = $db; }
    public function find(int $id): ?StorageSubtype
    {
        $sql = "SELECT id, storage_subtype, storage_type_id, description, is_active, created_by, created_at FROM `" . DB::STORAGE_SUBTYPES . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }
    public function findAll(): array
    {
        $sql = "SELECT id, storage_subtype, storage_type_id, description, is_active, created_by, created_at FROM `" . DB::STORAGE_SUBTYPES . "` ORDER BY storage_subtype ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }
    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `" . DB::STORAGE_SUBTYPES . "` WHERE storage_subtype = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `" . DB::STORAGE_SUBTYPES . "` WHERE storage_subtype = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }
    public function insert(StorageSubtype $item): int
    {
        $sql = "INSERT INTO `" . DB::STORAGE_SUBTYPES . "` (storage_subtype, storage_type_id, description, is_active, created_by) VALUES (:storage_subtype, :storage_type_id, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'storage_subtype' => $item->storageSubtype,
            'storage_type_id' => $item->storageTypeId,
            'description' => $item->description,
            'is_active' => $item->isActive ? 1 : 0,
            'created_by' => $item->createdBy,
        ]);
    }
    public function update(int $id, array $data): bool
    {
        $sets = []; $params = [];
        foreach ($data as $col => $val) { $key = 'u_' . str_replace('.', '_', $col); $sets[] = "`{$col}` = :{$key}"; $params[$key] = $val; }
        $params['id'] = $id;
        $sql = "UPDATE `" . DB::STORAGE_SUBTYPES . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try { $this->db->execute($sql, $params); return true; } catch (\Throwable $e) { ErrorCapture::record("StorageSubtypeRepository: Update failed: " . $e->getMessage()); return false; }
    }
    public function delete(int $id): bool { $this->db->execute("DELETE FROM `" . DB::STORAGE_SUBTYPES . "` WHERE id = :id", ['id' => $id]); return true; }
    private function mapRowToDto(array $row): StorageSubtype
    {
        return new StorageSubtype(
            id: (int)$row['id'],
            storageSubtype: (string)($row['storage_subtype'] ?? ''),
            storageTypeId: (int)($row['storage_type_id'] ?? 0),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}