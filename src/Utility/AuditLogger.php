<?php

declare(strict_types=1);

namespace App\Utility;

use App\Core\Container;
use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

class AuditLogger
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            try {
                $container = Container::getInstance();
                if ($container->has(Database::class)) {
                    $resolved = $container->get(Database::class);
                    if ($resolved instanceof Database) {
                        $this->db = $resolved;
                    } else {
                        $this->db = new Database();
                    }
                } else {
                    $this->db = new Database();
                }
            } catch (Throwable) {
                $this->db = new Database();
            }
        }
    }

    public function log(int $userId, string $action, string $entityType, int $entityId, string $description, ?int $organizationId = null, ?array $metadata = null): void
    {
        try {
            $this->db->insert(
                "INSERT INTO `" . DB::AUDIT_LOG . "` (user_id, action, entity_type, entity_id, description, organization_id, metadata, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $description,
                    $organizationId,
                    $metadata !== null ? json_encode($metadata) : null,
                    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    $_SERVER['HTTP_USER_AGENT'] ?? '',
                ]
            );
        } catch (Throwable $e) {
            ErrorCapture::record('AuditLogger::log() failed: ' . $e->getMessage());
        }
    }

    public function logCreate(int $userId, string $entityType, int $entityId, string $description, ?int $organizationId = null, ?array $metadata = null): void
    {
        $this->log($userId, 'create', $entityType, $entityId, $description, $organizationId, $metadata);
    }

    public function logUpdate(int $userId, string $entityType, int $entityId, string $description, ?int $organizationId = null, ?array $metadata = null): void
    {
        $this->log($userId, 'update', $entityType, $entityId, $description, $organizationId, $metadata);
    }

    public function logDelete(int $userId, string $entityType, int $entityId, string $description, ?int $organizationId = null, ?array $metadata = null): void
    {
        $this->log($userId, 'delete', $entityType, $entityId, $description, $organizationId, $metadata);
    }

    public function logLogin(int $userId, string $description = 'User logged in', ?int $organizationId = null): void
    {
        $this->log($userId, 'login', 'user', $userId, $description, $organizationId);
    }

    public function logLogout(int $userId, string $description = 'User logged out', ?int $organizationId = null): void
    {
        $this->log($userId, 'logout', 'user', $userId, $description, $organizationId);
    }
}
