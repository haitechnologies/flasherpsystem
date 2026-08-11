<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;

/**
 * IP-Based Rate Limiting for Public Pages
 * Prevents automated data scraping by limiting requests per IP.
 */
class IpRateLimiter
{
    private static ?Database $db = null;

    /**
     * Resolve table name for public rate limiting.
     */
    private static function tableName(): string
    {
        return (class_exists('DB') && defined('DB::RATE_LIMIT_PUBLIC'))
            ? DB::RATE_LIMIT_PUBLIC
            : 'erp_rate_limits';
    }

    /**
     * Initialize with database connection.
     */
    public static function init($db): void
    {
        if ($db instanceof Database) {
            self::$db = $db;
        } elseif ($db instanceof \mysqli || $db instanceof \PDO) {
            self::$db = new Database();
        } else {
            self::$db = new Database();
        }
    }

    /**
     * Check if IP is within rate limit.
     *
     * @param string $action Action type (search, listings, api_call, etc.)
     * @param int $maxRequests Maximum requests allowed
     * @param int $windowSeconds Time window in seconds
     * @return array ['allowed' => bool, 'remaining' => int, 'reset_in' => int]
     */
    public static function check(string $action, int $maxRequests = 100, int $windowSeconds = 60): array
    {
        if (!self::$db) {
            return ['allowed' => true, 'remaining' => $maxRequests, 'reset_in' => 0]; // Disabled if DB not initialized
        }

        $ip = self::getClientIP();
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);

        // Ensure table exists before querying.
        self::ensureTable();

        try {
            // Get current entry for this IP and action.
            $sql = "SELECT id, attempts, first_attempt_at FROM `" . self::tableName() . "` 
                    WHERE rate_limit_type = 'public' AND identifier = :ip AND action = :action LIMIT 1";
            $row = self::$db->fetchOne($sql, ['ip' => $ip, 'action' => $action]);

            if ($row) {
                $id = (int)$row['id'];
                $attempts = (int)$row['attempts'];
                $firstAttemptAt = strtotime((string)$row['first_attempt_at']);

                if ($now - $firstAttemptAt > $windowSeconds) {
                    // Window expired, reset counter and window start.
                    $attempts = 1;
                    $firstAttemptAt = $now;
                    $updateSql = "UPDATE `" . self::tableName() . "` 
                                  SET attempts = 1, first_attempt_at = :first_at, last_attempt_at = :last_at 
                                  WHERE id = :id";
                    self::$db->execute($updateSql, [
                        'first_at' => $nowStr,
                        'last_at' => $nowStr,
                        'id' => $id
                    ]);
                } else {
                    // Inside window, increment attempts.
                    $attempts++;
                    $updateSql = "UPDATE `" . self::tableName() . "` 
                                  SET attempts = :attempts, last_attempt_at = :last_at 
                                  WHERE id = :id";
                    self::$db->execute($updateSql, [
                        'attempts' => $attempts,
                        'last_at' => $nowStr,
                        'id' => $id
                    ]);
                }
            } else {
                // First request.
                $attempts = 1;
                $firstAttemptAt = $now;
                $insertSql = "INSERT INTO `" . self::tableName() . "` 
                              (rate_limit_type, identifier, action, attempts, first_attempt_at, last_attempt_at) 
                              VALUES ('public', :ip, :action, 1, :first_at, :last_at)";
                self::$db->execute($insertSql, [
                    'ip' => $ip,
                    'action' => $action,
                    'first_at' => $nowStr,
                    'last_at' => $nowStr
                ]);
            }

            $allowed = $attempts <= $maxRequests;
            $remaining = max(0, $maxRequests - $attempts);
            $resetIn = max(0, $windowSeconds - ($now - $firstAttemptAt));

            return [
                'allowed' => $allowed,
                'remaining' => $remaining,
                'reset_in' => $allowed ? 0 : $resetIn
            ];
        } catch (\Throwable $e) {
            ErrorCapture::record("Rate limiter check failed: " . $e->getMessage());
            return ['allowed' => true, 'remaining' => $maxRequests, 'reset_in' => 0]; // Fail open on DB error
        }
    }

    /**
     * Get client IP address (handles proxies).
     */
    private static function getClientIP(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        // Extract first IP if multiple.
        if (strpos($ip, ',') !== false) {
            $ips = array_map('trim', explode(',', $ip));
            $ip = $ips[0];
        }

        return $ip;
    }

    /**
     * Ensure rate limit table exists.
     */
    private static function ensureTable(): void
    {
        if (!self::$db) {
            return;
        }

        $tableName = self::tableName();

        // Check if table exists using PDO
        $tableExists = false;
        try {
            $checkSql = "SELECT 1 FROM INFORMATION_SCHEMA.TABLES 
                         WHERE TABLE_SCHEMA = DATABASE() 
                         AND TABLE_NAME = :table_name 
                         LIMIT 1";
            $res = self::$db->fetchOne($checkSql, ['table_name' => $tableName]);
            if ($res !== null) {
                $tableExists = true;
            }
        } catch (\Throwable $e) {
            // Fallback
        }

        if ($tableExists) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `" . $tableName . "` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rate_limit_type` VARCHAR(20) NOT NULL DEFAULT 'attempt',
            `identifier` VARCHAR(255) NOT NULL COMMENT 'IP address or user identifier',
            `action` VARCHAR(100) NOT NULL COMMENT 'Action type',
            `attempts` INT(11) NOT NULL DEFAULT 1,
            `first_attempt_at` DATETIME NOT NULL,
            `last_attempt_at` DATETIME NOT NULL,
            `banned_until` DATETIME DEFAULT NULL COMMENT 'NULL if not banned',
            PRIMARY KEY (`id`),
            UNIQUE KEY `type_identifier_action` (`rate_limit_type`, `identifier`, `action`),
            KEY `banned_until` (`banned_until`),
            KEY `last_attempt_at` (`last_attempt_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            self::$db->execute($sql);
        } catch (\Throwable $e) {
            ErrorCapture::record("Failed to create rate limit table: " . $e->getMessage());
        }
    }

    /**
     * Get stats for an IP.
     */
    public static function getStats(string $action): array
    {
        if (!self::$db) {
            return [];
        }

        $ip = self::getClientIP();
        try {
            $sql = "SELECT attempts as total, UNIX_TIMESTAMP(last_attempt_at) as last_request
                    FROM `" . self::tableName() . "`
                    WHERE rate_limit_type = 'public' AND identifier = :ip AND action = :action LIMIT 1";
            return self::$db->fetchOne($sql, [
                'ip' => $ip,
                'action' => $action
            ]) ?? [];
        } catch (\Throwable $e) {
            ErrorCapture::record("Rate limiter getStats failed: " . $e->getMessage());
            return [];
        }
    }
}
