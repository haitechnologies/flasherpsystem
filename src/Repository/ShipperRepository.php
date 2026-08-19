<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\Shipper;
use App\Core\ErrorCapture;

class ShipperRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?Shipper
    {
        $sql = "SELECT id, shipper_name, address_line1, address_line2, city, zipcode,
                       province, country, email, telephone, mobile, fax, is_active, created_by, created_at
                FROM `{DB::SHIPPERS}` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, shipper_name, address_line1, address_line2, city, zipcode,
                       province, country, email, telephone, mobile, fax, is_active, created_by, created_at
                FROM `{DB::SHIPPERS}` ORDER BY shipper_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `{DB::SHIPPERS}` WHERE shipper_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `{DB::SHIPPERS}` WHERE shipper_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(Shipper $item): int
    {
        $sql = "INSERT INTO `{DB::SHIPPERS}` (shipper_name, address_line1, address_line2, city, zipcode,
                        province, country, email, telephone, mobile, fax, is_active, created_by)
                VALUES (:shipper_name, :address_line1, :address_line2, :city, :zipcode,
                        :province, :country, :email, :telephone, :mobile, :fax, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'shipper_name' => $item->shipperName,
            'address_line1' => $item->addressLine1,
            'address_line2' => $item->addressLine2,
            'city' => $item->city,
            'zipcode' => $item->zipcode,
            'province' => $item->province,
            'country' => $item->country,
            'email' => $item->email,
            'telephone' => $item->telephone,
            'mobile' => $item->mobile,
            'fax' => $item->fax,
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
        $sql = "UPDATE `{DB::SHIPPERS}` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("ShipperRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `{DB::SHIPPERS}` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): Shipper
    {
        return new Shipper(
            id: (int)$row['id'],
            shipperName: (string)($row['shipper_name'] ?? ''),
            addressLine1: (string)($row['address_line1'] ?? ''),
            addressLine2: (string)($row['address_line2'] ?? ''),
            city: (string)($row['city'] ?? ''),
            zipcode: (string)($row['zipcode'] ?? ''),
            province: (string)($row['province'] ?? ''),
            country: (int)($row['country'] ?? 0),
            email: (string)($row['email'] ?? ''),
            telephone: (string)($row['telephone'] ?? ''),
            mobile: (string)($row['mobile'] ?? ''),
            fax: (string)($row['fax'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
