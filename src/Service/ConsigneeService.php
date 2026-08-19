<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Consignee;
use App\Repository\ConsigneeRepository;
use App\Exception\ValidationException;

class ConsigneeService
{
    private ConsigneeRepository $repo;

    public function __construct(ConsigneeRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?Consignee
    {
        return $this->repo->find($id);
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['consignee_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['consignee_name' => 'Consignee name is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['consignee_name' => 'Consignee name already exists. Please enter a different one.']);
        }

        $item = new Consignee(
            id: 0,
            consigneeName: $name,
            addressLine1: (string)($data['address_line1'] ?? ''),
            addressLine2: (string)($data['address_line2'] ?? ''),
            city: (string)($data['city'] ?? ''),
            zipcode: (string)($data['zipcode'] ?? ''),
            province: (string)($data['province'] ?? ''),
            country: (int)($data['country'] ?? 0),
            email: (string)($data['email'] ?? ''),
            telephone: (string)($data['telephone'] ?? ''),
            mobile: (string)($data['mobile'] ?? ''),
            fax: (string)($data['fax'] ?? ''),
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

        $name = trim((string)($data['consignee_name'] ?? $existing->consigneeName));
        if ($name === '') {
            throw new ValidationException(['consignee_name' => 'Consignee name is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['consignee_name' => 'Consignee name already exists. Please enter a different one.']);
        }

        return $this->repo->update($id, [
            'consignee_name' => $name,
            'address_line1' => (string)($data['address_line1'] ?? $existing->addressLine1),
            'address_line2' => (string)($data['address_line2'] ?? $existing->addressLine2),
            'city' => (string)($data['city'] ?? $existing->city),
            'zipcode' => (string)($data['zipcode'] ?? $existing->zipcode),
            'province' => (string)($data['province'] ?? $existing->province),
            'country' => (int)($data['country'] ?? $existing->country),
            'email' => (string)($data['email'] ?? $existing->email),
            'telephone' => (string)($data['telephone'] ?? $existing->telephone),
            'mobile' => (string)($data['mobile'] ?? $existing->mobile),
            'fax' => (string)($data['fax'] ?? $existing->fax),
            'is_active' => (bool)($data['is_active'] ?? $existing->isActive) ? 1 : 0,
            'updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
