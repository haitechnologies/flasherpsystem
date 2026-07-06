<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\Item;
use App\Repository\ItemRepository;
use App\Exception\ValidationException;

class ItemService
{
    private ItemRepository $repo;

    public function __construct(ItemRepository $repo)
    {
        $this->repo = $repo;
    }

    public function getById(int $id): ?Item
    {
        return $this->repo->find($id);
    }

    public function list(): array
    {
        return $this->repo->findAll();
    }

    public function create(array $data, int $createdBy): int
    {
        $name = trim((string)($data['item_name'] ?? ''));
        if ($name === '') {
            throw new ValidationException(['item_name' => 'Item name is mandatory.']);
        }
        if ($this->repo->exists($name)) {
            throw new ValidationException(['item_name' => 'Item name already exists.']);
        }

        $saleAccount = (int)($data['sale_account'] ?? 0);
        $purchaseAccount = (int)($data['purchase_account'] ?? 0);
        if ($saleAccount <= 0) {
            throw new ValidationException(['sale_account' => 'Sale account is mandatory.']);
        }
        if ($purchaseAccount <= 0) {
            throw new ValidationException(['purchase_account' => 'Purchase account is mandatory.']);
        }

        $sellingPrice = (string)($data['selling_price'] ?? '');
        $costPrice = (string)($data['cost_price'] ?? '');
        if ($sellingPrice === '' || (float)$sellingPrice < 0) {
            throw new ValidationException(['selling_price' => 'Selling price is mandatory.']);
        }
        if ($costPrice === '' || (float)$costPrice < 0) {
            throw new ValidationException(['cost_price' => 'Cost price is mandatory.']);
        }

        $item = new Item(
            id: 0,
            itemType: (string)($data['item_type'] ?? 'services'),
            itemName: $name,
            unitPrice: (string)($data['unit_price'] ?? '0'),
            sellingPrice: $sellingPrice,
            taxTreatmentId: (int)($data['tax_treatment_id'] ?? 0),
            isExcise: (bool)($data['is_excise'] ?? false),
            isActive: (bool)($data['is_active'] ?? true),
            createdBy: $createdBy,
            saleAccount: $saleAccount,
            saleDescription: (string)($data['sale_description'] ?? ''),
            purchaseAccount: $purchaseAccount,
            purchaseDescription: (string)($data['purchase_description'] ?? ''),
            costPrice: $costPrice,
            preferredVendorId: (int)($data['preferred_vendor_id'] ?? 0),
        );

        return $this->repo->insert($item);
    }

    public function update(int $id, array $data, int $updatedBy): bool
    {
        $existing = $this->repo->find($id);
        if ($existing === null) {
            return false;
        }

        $name = trim((string)($data['item_name'] ?? $existing->itemName));
        if ($name === '') {
            throw new ValidationException(['item_name' => 'Item name is mandatory.']);
        }
        if ($this->repo->exists($name, $id)) {
            throw new ValidationException(['item_name' => 'Item name already exists.']);
        }

        $saleAccount = (int)($data['sale_account'] ?? $existing->saleAccount);
        $purchaseAccount = (int)($data['purchase_account'] ?? $existing->purchaseAccount);
        if ($saleAccount <= 0) {
            throw new ValidationException(['sale_account' => 'Sale account is mandatory.']);
        }
        if ($purchaseAccount <= 0) {
            throw new ValidationException(['purchase_account' => 'Purchase account is mandatory.']);
        }

        $sellingPrice = (string)($data['selling_price'] ?? $existing->sellingPrice);
        $costPrice = (string)($data['cost_price'] ?? $existing->costPrice);
        if ($sellingPrice === '' || (float)$sellingPrice < 0) {
            throw new ValidationException(['selling_price' => 'Selling price is mandatory.']);
        }
        if ($costPrice === '' || (float)$costPrice < 0) {
            throw new ValidationException(['cost_price' => 'Cost price is mandatory.']);
        }

        return $this->repo->update($id, [
            'item_type' => (string)($data['item_type'] ?? $existing->itemType),
            'item_name' => $name,
            'unit_price' => (string)($data['unit_price'] ?? $existing->unitPrice),
            'selling_price' => $sellingPrice,
            'tax_treatment_id' => (int)($data['tax_treatment_id'] ?? $existing->taxTreatmentId),
            'is_excise' => (bool)($data['is_excise'] ?? $existing->isExcise) ? 1 : 0,
            'is_active' => (bool)($data['is_active'] ?? $existing->isActive) ? 1 : 0,
            'sale_account' => $saleAccount,
            'sale_description' => (string)($data['sale_description'] ?? $existing->saleDescription),
            'purchase_account' => $purchaseAccount,
            'purchase_description' => (string)($data['purchase_description'] ?? $existing->purchaseDescription),
            'cost_price' => $costPrice,
            'preferred_vendor_id' => (int)($data['preferred_vendor_id'] ?? $existing->preferredVendorId),
            'updated_by' => $updatedBy,
        ]);
    }

    public function delete(int $id): bool
    {
        return $this->repo->delete($id);
    }
}
