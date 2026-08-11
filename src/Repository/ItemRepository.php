<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Item;
use App\Core\ErrorCapture;

class ItemRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Item
    {
        $sql = "SELECT id, item_type, item_name, unit_price, selling_price, tax_treatment_id,
                       is_excise, is_active, created_by, created_at,
                       sale_account, sale_description, purchase_account, purchase_description,
                       cost_price, preferred_vendor_id
                FROM `" . DB::ITEMS . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, item_type, item_name, unit_price, selling_price, tax_treatment_id,
                       is_excise, is_active, created_by, created_at,
                       sale_account, sale_description, purchase_account, purchase_description,
                       cost_price, preferred_vendor_id
                FROM `" . DB::ITEMS . "` ORDER BY item_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `" . DB::ITEMS . "` WHERE item_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `" . DB::ITEMS . "` WHERE item_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Item $item): int
    {
        $sql = "INSERT INTO `" . DB::ITEMS . "` (item_type, item_name, unit_price, selling_price, tax_treatment_id,
                is_excise, is_active, created_by,
                sale_account, sale_description, purchase_account, purchase_description,
                cost_price, preferred_vendor_id)
                VALUES (:item_type, :item_name, :unit_price, :selling_price, :tax_treatment_id,
                :is_excise, :is_active, :created_by,
                :sale_account, :sale_description, :purchase_account, :purchase_description,
                :cost_price, :preferred_vendor_id)";
        return (int)$this->db->insert($sql, [
            'item_type' => $item->itemType,
            'item_name' => $item->itemName,
            'unit_price' => $item->unitPrice,
            'selling_price' => $item->sellingPrice,
            'tax_treatment_id' => $item->taxTreatmentId,
            'is_excise' => $item->isExcise ? 1 : 0,
            'is_active' => $item->isActive ? 1 : 0,
            'created_by' => $item->createdBy,
            'sale_account' => $item->saleAccount,
            'sale_description' => $item->saleDescription,
            'purchase_account' => $item->purchaseAccount,
            'purchase_description' => $item->purchaseDescription,
            'cost_price' => $item->costPrice,
            'preferred_vendor_id' => $item->preferredVendorId,
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
        $sql = "UPDATE `" . DB::ITEMS . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("ItemRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::ITEMS . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Item
    {
        return new Item(
            id: (int)$row['id'],
            itemType: (string)($row['item_type'] ?? 'services'),
            itemName: (string)($row['item_name'] ?? ''),
            unitPrice: (string)($row['unit_price'] ?? '0'),
            sellingPrice: (string)($row['selling_price'] ?? '0'),
            taxTreatmentId: (int)($row['tax_treatment_id'] ?? 0),
            isExcise: (bool)($row['is_excise'] ?? false),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
            saleAccount: (int)($row['sale_account'] ?? 0),
            saleDescription: (string)($row['sale_description'] ?? ''),
            purchaseAccount: (int)($row['purchase_account'] ?? 0),
            purchaseDescription: (string)($row['purchase_description'] ?? ''),
            costPrice: (string)($row['cost_price'] ?? '0'),
            preferredVendorId: (int)($row['preferred_vendor_id'] ?? 0),
        );
    }
}
