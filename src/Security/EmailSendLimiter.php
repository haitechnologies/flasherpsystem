<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Database;
use App\Core\Container;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

/**
 * EmailSendLimiter
 * Enforces a system-wide email send limit (e.g., 500 emails per 24h)
 */
class EmailSendLimiter
{
    public const LIMIT = 500;
    public const WINDOW_HOURS = 24;

    private static function getDatabase(mixed $conn = null): Database
    {
        if ($conn instanceof Database) {
            return $conn;
        }

        try {
            $container = Container::getInstance();
            if ($container->has(Database::class)) {
                $resolved = $container->get(Database::class);
                if ($resolved instanceof Database) {
                    return $resolved;
                }
            }
        } catch (Throwable $e) {
            // Ignore container resolution errors
        }

        return new Database();
    }

    /**
     * Returns true if sending another email is allowed under the limit
     *
     * @param mixed $conn
     * @return bool
     */
    public static function canSend(mixed $conn = null): bool
    {
        $windowStart = date('Y-m-d H:i:s', strtotime('-' . self::WINDOW_HOURS . ' hours'));
        $historyTable = class_exists('DB') ? DB::EMAIL_HISTORY : 'erp_email_history';
        $queueTable = class_exists('DB') ? DB::EMAIL_QUEUE : 'erp_email_queue';

        $sql = "SELECT (
            (SELECT COUNT(*) FROM `{$historyTable}` WHERE sent_at >= ?) +
            (SELECT COUNT(*) FROM `{$queueTable}` WHERE status IN ('pending','sent') AND created_at >= ?)
        ) AS sent_count";

        $db = self::getDatabase($conn);
        try {
            $row = $db->fetchOne($sql, [$windowStart, $windowStart]);
            return ((int)($row['sent_count'] ?? 0) < self::LIMIT);
        } catch (Throwable $e) {
            ErrorCapture::record('EmailSendLimiter::canSend() - Query failed: ' . $e->getMessage());
            return false; // Fail safe: block send
        }
    }
}
