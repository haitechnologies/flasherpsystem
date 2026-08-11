<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\DB;
use App\Model\EntityNote;
use App\Repository\EntityNoteRepository;
use App\Exception\NotFoundException;
use App\Exception\ValidationException;

class EntityNoteService
{
    private EntityNoteRepository $repo;
    private Database $db;

    public function __construct(EntityNoteRepository $repo, Database $db)
    {
        $this->repo = $repo;
        $this->db = $db;
    }

    public function getById(int $id, int $orgId): EntityNote
    {
        $note = $this->repo->find($id, $orgId);
        if ($note === null) {
            throw new NotFoundException("Entity note with ID {$id} not found.");
        }
        return $note;
    }

    public function getByEntity(string $entityType, int $entityId, int $orgId): array
    {
        return $this->repo->findByEntity($entityType, $entityId, $orgId);
    }

    public function create(array $data, int $orgId, int $userId): EntityNote
    {
        $entityType = (string)($data['entity_type'] ?? '');
        $entityId = (int)($data['entity_id'] ?? 0);

        $this->validateEntityData($data);
        $this->validateParentEntityExists($entityType, $entityId);

        $note = new EntityNote(
            id: null,
            entityType: $entityType,
            entityId: $entityId,
            notes: !empty($data['notes']) ? trim((string)$data['notes']) : null,
            createdBy: $userId,
            updatedBy: null,
            isActive: !empty($data['is_active']),
            organizationId: $orgId,
            createdAt: null,
            updatedAt: null,
        );

        $this->db->beginTransaction();
        try {
            $saved = $this->repo->save($note);
            $this->db->commit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data, int $orgId, int $userId): EntityNote
    {
        $existing = $this->getById($id, $orgId);

        $this->validateEntityData($data);

        $note = new EntityNote(
            id: $existing->id,
            entityType: $existing->entityType,
            entityId: (int)($data['entity_id'] ?? $existing->entityId),
            notes: isset($data['notes']) ? (!empty($data['notes']) ? trim((string)$data['notes']) : null) : $existing->notes,
            createdBy: $existing->createdBy,
            updatedBy: $userId,
            isActive: isset($data['is_active']) ? !empty($data['is_active']) : $existing->isActive,
            organizationId: $existing->organizationId,
            createdAt: $existing->createdAt,
            updatedAt: $existing->updatedAt,
        );

        $this->db->beginTransaction();
        try {
            $saved = $this->repo->save($note);
            $this->db->commit();
            return $saved;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $id, int $orgId): bool
    {
        $existing = $this->getById($id, $orgId);

        $this->db->beginTransaction();
        try {
            $result = $this->repo->delete($id, $orgId);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validateEntityData(array $data): void
    {
        if (empty($data['notes'])) {
            throw new ValidationException(['notes' => 'Notes are mandatory.']);
        }
        if (empty($data['entity_type'])) {
            throw new ValidationException(['entity_type' => 'Entity type is required.']);
        }
        if (empty($data['entity_id']) || (int)$data['entity_id'] <= 0) {
            throw new ValidationException(['entity_id' => 'Entity ID is required.']);
        }
    }

    private function validateParentEntityExists(string $entityType, int $entityId): void
    {
        $tableMap = [
            'customer' => DB::CUSTOMERS,
            'lead'     => DB::LEADS,
            'vendor'   => DB::VENDORS,
        ];

        $table = $tableMap[$entityType] ?? null;
        if ($table === null) {
            return;
        }

        $row = $this->db->fetchOne(
            "SELECT id FROM `{$table}` WHERE id = :id LIMIT 1",
            ['id' => $entityId]
        );

        if ($row === null) {
            throw new NotFoundException(ucfirst($entityType) . " with ID {$entityId} not found.");
        }
    }
}
