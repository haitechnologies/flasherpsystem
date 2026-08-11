<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\EntityNote;

class EntityNoteRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id, int $orgId): ?EntityNote
    {
        $sql = "SELECT * FROM `{DB::ENTITY_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $row = $this->db->fetchOne($sql, ['id' => $id, 'org_id' => $orgId]);
        if ($row === null) {
            return null;
        }
        return $this->mapRowToEntityNote($row);
    }

    public function findByEntity(string $entityType, int $entityId, int $orgId): array
    {
        $sql = "SELECT * FROM `{DB::ENTITY_NOTES}`
                WHERE entity_type = :entity_type AND entity_id = :entity_id AND organization_id = :org_id
                ORDER BY id DESC";
        $rows = $this->db->fetchAll($sql, [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'org_id' => $orgId,
        ]);
        $notes = [];
        foreach ($rows as $row) {
            $notes[] = $this->mapRowToEntityNote($row);
        }
        return $notes;
    }

    public function save(EntityNote $note): EntityNote
    {
        if ($note->id === null) {
            return $this->insert($note);
        }
        return $this->update($note);
    }

    private function insert(EntityNote $note): EntityNote
    {
        $sql = "INSERT INTO `{DB::ENTITY_NOTES}` (
                    entity_type, entity_id, notes,
                    created_by, is_active, organization_id, created_at, updated_at
                ) VALUES (
                    :entity_type, :entity_id, :notes,
                    :created_by, :is_active, :organization_id, NOW(), NOW()
                )";

        $params = $note->toArray();
        unset($params['id'], $params['created_at'], $params['updated_at'], $params['updated_by']);

        $insertId = (int)$this->db->insert($sql, $params);

        $inserted = $this->find($insertId, $note->organizationId);
        if ($inserted === null) {
            throw new \RuntimeException("Failed to retrieve inserted entity note.");
        }

        return $inserted;
    }

    private function update(EntityNote $note): EntityNote
    {
        $sql = "UPDATE `{DB::ENTITY_NOTES}` SET
                    entity_id = :entity_id,
                    notes = :notes,
                    is_active = :is_active,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id AND organization_id = :organization_id";

        $params = $note->toArray();
        unset($params['entity_type'], $params['created_at'], $params['updated_at'], $params['created_by']);

        $this->db->execute($sql, $params);

        $updated = $this->find((int)$note->id, $note->organizationId);
        if ($updated === null) {
            throw new \RuntimeException("Failed to retrieve updated entity note.");
        }

        return $updated;
    }

    public function delete(int $id, int $orgId): bool
    {
        $sql = "DELETE FROM `{DB::ENTITY_NOTES}` WHERE id = :id AND organization_id = :org_id";
        $stmt = $this->db->execute($sql, ['id' => $id, 'org_id' => $orgId]);
        return $stmt->rowCount() > 0;
    }

    private function mapRowToEntityNote(array $row): EntityNote
    {
        return new EntityNote(
            id: (int)$row['id'],
            entityType: (string)($row['entity_type'] ?? ''),
            entityId: (int)($row['entity_id'] ?? 0),
            notes: $row['notes'] !== null ? (string)$row['notes'] : null,
            createdBy: (int)($row['created_by'] ?? 0),
            updatedBy: $row['updated_by'] !== null ? (int)$row['updated_by'] : null,
            isActive: (bool)($row['is_active'] ?? true),
            organizationId: (int)($row['organization_id'] ?? 0),
            createdAt: $row['created_at'] !== null ? (string)$row['created_at'] : null,
            updatedAt: $row['updated_at'] !== null ? (string)$row['updated_at'] : null,
        );
    }
}
