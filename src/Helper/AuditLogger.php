<?php

declare(strict_types=1);

namespace App\Helper;

use App\Core\Database;
use App\Core\Session;

class AuditLogger
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function log(string $action, string $module, ?int $entityId, ?array $changes = null, ?int $orgId = null, ?int $userId = null): void
    {
        $orgId ??= Session::orgId() ?? 0;
        $userId ??= Session::userId() ?? 0;

        $table = \App\Core\DB::table('audit_logs');
        $this->db->execute(
            "INSERT INTO `{$table}` (organization_id, user_id, module, action, entity_id, changes, created_at)
             VALUES (:org_id, :user_id, :module, :action, :entity_id, :changes, NOW())",
            [
                'org_id' => $orgId,
                'user_id' => $userId,
                'module' => $module,
                'action' => $action,
                'entity_id' => $entityId,
                'changes' => $changes !== null ? json_encode($changes, JSON_UNESCAPED_UNICODE) : null,
            ]
        );
    }
}
