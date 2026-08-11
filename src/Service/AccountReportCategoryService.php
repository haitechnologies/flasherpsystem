<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AccountReportCategory;
use App\Repository\AccountReportCategoryRepository;
use App\Exception\ValidationException;

class AccountReportCategoryService
{
    private AccountReportCategoryRepository $repo;

    public function __construct(AccountReportCategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?AccountReportCategory
    {
        return $this->repo->find($id);
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['category_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['category_name' => 'Commodity type is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['category_name' => 'Commodity type already exists. Please enter a different one.']);
        }

        $item = new AccountReportCategory(
            id: 0,
            categoryName: $name,
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

        $name = trim((string)($data['category_name'] ?? $existing->categoryName));
        if ($name === '') {
            throw new ValidationException(['category_name' => 'Commodity type is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['category_name' => 'Commodity type already exists. Please enter a different one.']);
        }

        return $this->repo->update($id, [
            'category_name' => $name,
            'is_active' => (bool)($data['is_active'] ?? $existing->isActive) ? 1 : 0,
            'updated_by' => $updatedBy,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
