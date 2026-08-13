<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\SetupBank;
use App\Repository\SetupBankRepository;
use App\Exception\ValidationException;
use App\Exception\NotFoundException;

class SetupBankService
{
    private SetupBankRepository $repo;

    public function __construct(SetupBankRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id, int $orgId): ?SetupBank
    {
        return $this->repo->find($id, $orgId);
    }

    public function create(array $data, int $orgId, int $createdBy): int
    {
        $institutionName = trim((string) ($data['institution_name'] ?? ''));
        $headOffice = trim((string) ($data['head_office'] ?? ''));

        $this->validate($institutionName);

        $bank = new SetupBank(
            id: 0,
            organizationId: $orgId,
            institutionName: $institutionName,
            headOffice: $headOffice,
            isActive: true,
            createdBy: $createdBy,
        );

        return $this->repo->insert($bank);
    }

    public function update(int $id, array $data, int $orgId, int $updatedBy): bool
    {
        $existing = $this->repo->find($id, $orgId);
        if ($existing === null) {
            throw new NotFoundException('Bank institution not found.');
        }

        $institutionName = trim((string) ($data['institution_name'] ?? $existing->institutionName));
        $headOffice = trim((string) ($data['head_office'] ?? $existing->headOffice));

        $this->validate($institutionName);

        $bank = new SetupBank(
            id: $id,
            organizationId: $orgId,
            institutionName: $institutionName,
            headOffice: $headOffice,
            isActive: $existing->isActive,
            updatedBy: $updatedBy,
        );

        return $this->repo->update($bank);
    }

    public function delete(int $id, int $orgId): bool
    {
        if ($this->repo->find($id, $orgId) === null) {
            return false;
        }

        return $this->repo->delete($id, $orgId);
    }

    private function validate(string $institutionName): void
    {
        if ($institutionName === '') {
            throw new ValidationException(['institution_name' => 'Institution name is mandatory.']);
        }
    }
}
