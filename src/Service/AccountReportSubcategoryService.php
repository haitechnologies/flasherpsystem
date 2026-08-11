<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\AccountReportSubcategory;
use App\Repository\AccountReportSubcategoryRepository;
use App\Exception\ValidationException;

class AccountReportSubcategoryService
{
    private AccountReportSubcategoryRepository $repo;

    public function __construct(AccountReportSubcategoryRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?AccountReportSubcategory
    {
        return $this->repo->find($id);
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['report_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['report_name' => 'Commodity type is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['report_name' => 'Commodity type already exists. Please enter a different one.']);
        }

        $item = new AccountReportSubcategory(
            id: 0,
            reportName: $name,
            slug: $data['slug'] ?? '',
            categoryId: (int)($data['category_id'] ?? 0),
            ordering: (int)($data['ordering'] ?? 0),
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

        $name = trim((string)($data['report_name'] ?? $existing->reportName));
        if ($name === '') {
            throw new ValidationException(['report_name' => 'Commodity type is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['report_name' => 'Commodity type already exists. Please enter a different one.']);
        }

        return $this->repo->update($id, [
            'report_name' => $name,
            'slug' => $data['slug'] ?? $existing->slug,
            'category_id' => (int)($data['category_id'] ?? $existing->categoryId),
            'ordering' => (int)($data['ordering'] ?? $existing->ordering),
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
