<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Incoterm;
use App\Core\ErrorCapture;

class IncotermRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Incoterm
    {
        $sql = "SELECT id, incoterm, description, is_active, created_by, created_at
                FROM `{DB::INCOTERMS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, incoterm, description, is_active, created_by, created_at
                FROM `{DB::INCOTERMS}` ORDER BY incoterm ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::INCOTERMS}` WHERE incoterm = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::INCOTERMS}` WHERE incoterm = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Incoterm $item): int
    {
        $sql = "INSERT INTO `{DB::INCOTERMS}` (incoterm, description, is_active, created_by)
                VALUES (:incoterm, :description, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'incoterm' => $item->incoterm,
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
        $sql = "UPDATE `{DB::INCOTERMS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("IncotermRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::INCOTERMS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Incoterm
    {
        return new Incoterm(
            id: (int)$row['id'],
            incoterm: (string)($row['incoterm'] ?? ''),
            description: (string)($row['description'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
