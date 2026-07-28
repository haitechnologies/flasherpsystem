<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Account;
use App\Repository\AccountRepository;
use App\Exception\ValidationException;

class AccountService
{
    private AccountRepository $repo;

    public function __construct(AccountRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?Account
    {
        return $this->repo->find($id);
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function listParents(): array
    {
        return $this->repo->findParentAccounts();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['account_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['account_name' => 'Account name is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['account_name' => 'Account name already exists.']);
        }

        $item = new Account(
            id: 0,
            parentId: (int)($data['parent_id'] ?? 0),
            accountType: (string)($data['account_type'] ?? ''),
            accountName: $name,
            accountCode: (string)($data['account_code'] ?? ''),
            description: (string)($data['description'] ?? ''),
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $createdBy,
        );

        return $this->repo->insert($item);
    }

    public function update(int $id, array $data, int $updatedBy): bool
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return false;
        }

        $name = trim((string)($data['account_name'] ?? $existing->accountName));
        if ($name === '') {
            throw new ValidationException(['account_name' => 'Account name is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['account_name' => 'Account name already exists.']);
        }

        return $this->repo->update($id, [
            'parent_id' => (int)($data['parent_id'] ?? $existing->parentId),
            'account_type' => (string)($data['account_type'] ?? $existing->accountType),
            'account_name' => $name,
            'account_code' => (string)($data['account_code'] ?? $existing->accountCode),
            'description' => (string)($data['description'] ?? $existing->description),
            'is_active' => 1,
            'updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
