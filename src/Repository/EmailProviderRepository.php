<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;
use App\Core\DB;
use App\Model\EmailProvider;
use App\Core\ErrorCapture;

class EmailProviderRepository
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function find(int $id): ?EmailProvider
    {
        $sql = "SELECT id, provider_name, email_encryption, smtp_host, smtp_port, email, smtp_username, smtp_password, is_primary, is_active, created_by, created_at
                FROM `" . DB::EMAIL_PROVIDERS . "` WHERE id = :id";
        $row = $this->db->fetchOne($sql, ['id' => $id]);
        return $row === null ? null : $this->mapRowToDto($row);
    }

    public function findAll(): array
    {
        $sql = "SELECT id, provider_name, email_encryption, smtp_host, smtp_port, email, smtp_username, smtp_password, is_primary, is_active, created_by, created_at
                FROM `" . DB::EMAIL_PROVIDERS . "` ORDER BY provider_name ASC";
        return array_map($this->mapRowToDto(...), $this->db->fetchAll($sql));
    }

    public function exists(string $name, ?int $excludeId = null): bool
    {
        $sql = $excludeId !== null
            ? "SELECT id FROM `" . DB::EMAIL_PROVIDERS . "` WHERE provider_name = :name AND id != :exclude_id LIMIT 1"
            : "SELECT id FROM `" . DB::EMAIL_PROVIDERS . "` WHERE provider_name = :name LIMIT 1";
        $params = $excludeId !== null ? ['name' => $name, 'exclude_id' => $excludeId] : ['name' => $name];
        return $this->db->fetchOne($sql, $params) !== null;
    }

    public function insert(EmailProvider $item): int
    {
        $sql = "INSERT INTO `" . DB::EMAIL_PROVIDERS . "` (provider_name, email_encryption, smtp_host, smtp_port, email, smtp_username, smtp_password, is_primary, is_active, created_by)
                VALUES (:provider_name, :email_encryption, :smtp_host, :smtp_port, :email, :smtp_username, :smtp_password, :is_primary, :is_active, :created_by)";
        return (int)$this->db->insert($sql, [
            'provider_name' => $item->providerName,
            'email_encryption' => $item->emailEncryption,
            'smtp_host' => $item->smtpHost,
            'smtp_port' => $item->smtpPort,
            'email' => $item->email,
            'smtp_username' => $item->smtpUsername,
            'smtp_password' => $item->smtpPassword,
            'is_primary' => $item->isPrimary ? 1 : 0,
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
        $sql = "UPDATE `" . DB::EMAIL_PROVIDERS . "` SET " . implode(', ', $sets) . " WHERE id = :id";
        try {
            $this->db->execute($sql, $params);
            return true;
        } catch (\Throwable $e) {
            ErrorCapture::record("EmailProviderRepository: Update failed: " . $e->getMessage());
            return false;
        }
    }

    public function delete(int $id): bool
    {
        $this->db->execute("DELETE FROM `" . DB::EMAIL_PROVIDERS . "` WHERE id = :id", ['id' => $id]);
        return true;
    }

    private function mapRowToDto(array $row): EmailProvider
    {
        return new EmailProvider(
            id: (int)$row['id'],
            providerName: (string)($row['provider_name'] ?? ''),
            emailEncryption: (string)($row['email_encryption'] ?? 'NONE'),
            smtpHost: (string)($row['smtp_host'] ?? ''),
            smtpPort: (string)($row['smtp_port'] ?? ''),
            email: (string)($row['email'] ?? ''),
            smtpUsername: (string)($row['smtp_username'] ?? ''),
            smtpPassword: (string)($row['smtp_password'] ?? ''),
            isActive: (bool)($row['is_active'] ?? true),
            isPrimary: (bool)($row['is_primary'] ?? false),
            createdBy: (int)($row['created_by'] ?? 0),
            createdAt: (string)($row['created_at'] ?? ''),
        );
    }
}
