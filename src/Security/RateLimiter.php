<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\Database;
use App\Core\Container;
use App\Core\DB;
use App\Core\ErrorCapture;
use Throwable;

/**
 * Rate Limiter Class
 *
 * Prevents brute-force attacks by limiting the number of attempts
 * for specific actions (login, password reset, etc.) within a time window.
 */
class RateLimiter
{
    /**
     * Database connection
     * @var mixed
     */
    private static mixed $conn = null;

    /**
     * Default configuration
     */
    public const DEFAULT_MAX_ATTEMPTS = 5;
    public const DEFAULT_TIME_WINDOW = 900; // 15 minutes
    public const DEFAULT_BAN_DURATION = 1800; // 30 minutes

    /**
     * Resolve attempts table name from DB constants when available.
     *
     * @return string
     */
    private static function tableName(): string
    {
        return class_exists('DB') ? DB::RATE_LIMIT_ATTEMPTS : 'erp_rate_limits';
    }

    /**
     * Initialize the rate limiter with database connection
     *
     * @param mixed $conn Database connection
     * @return void
     */
    public static function init(mixed $conn): void
    {
        self::$conn = $conn;
        self::createTableIfNotExists();
    }

    /**
     * Resolve the Database instance (either direct PDO wrapper or resolved from Container)
     */
    private static function getDatabase(): Database
    {
        if (self::$conn instanceof Database) {
            return self::$conn;
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
     * Create rate_limits table if it doesn't exist
     *
     * @return void
     */
    private static function createTableIfNotExists(): void
    {
        $db = self::getDatabase();
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::tableName() . "` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `rate_limit_type` VARCHAR(20) NOT NULL DEFAULT 'attempt',
            `identifier` VARCHAR(255) NOT NULL COMMENT 'IP address or user identifier',
            `action` VARCHAR(100) NOT NULL COMMENT 'Action type (login, password_reset, etc.)',
            `attempts` INT(11) NOT NULL DEFAULT 1,
            `first_attempt_at` DATETIME NOT NULL,
            `last_attempt_at` DATETIME NOT NULL,
            `banned_until` DATETIME DEFAULT NULL COMMENT 'NULL if not banned',
            PRIMARY KEY (`id`),
            UNIQUE KEY `type_identifier_action` (`rate_limit_type`, `identifier`, `action`),
            KEY `banned_until` (`banned_until`),
            KEY `last_attempt_at` (`last_attempt_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            $db->execute($sql);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::createTableIfNotExists() - Table creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if an action is allowed for a specific identifier
     *
     * @param string $identifier Unique identifier (typically IP address)
     * @param string $action Action type (login, password_reset, etc.)
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $timeWindow Time window in seconds
     * @return array ['allowed' => bool, 'remainingAttempts' => int, 'retryAfter' => int|null, 'reason' => string]
     */
    public static function check(
        string $identifier,
        string $action = 'login',
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $timeWindow = self::DEFAULT_TIME_WINDOW
    ): array {
        self::cleanup($timeWindow);

        $db = self::getDatabase();
        $sql = "SELECT banned_until, attempts FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action LIMIT 1";

        try {
            $row = $db->fetchOne($sql, ['identifier' => $identifier, 'action' => $action]);

            if ($row) {
                $bannedUntil = $row['banned_until'];
                $attempts = (int)$row['attempts'];

                if ($bannedUntil && strtotime((string)$bannedUntil) > time()) {
                    $retryAfter = strtotime((string)$bannedUntil) - time();

                    return [
                        'allowed' => false,
                        'remainingAttempts' => 0,
                        'retryAfter' => $retryAfter,
                        'reason' => 'Too many failed attempts. Please try again in ' . self::formatDuration($retryAfter) . '.'
                    ];
                }

                if ($attempts >= $maxAttempts) {
                    return [
                        'allowed' => false,
                        'remainingAttempts' => 0,
                        'retryAfter' => $timeWindow,
                        'reason' => 'Too many attempts. Please try again in ' . self::formatDuration($timeWindow) . '.'
                    ];
                }

                return [
                    'allowed' => true,
                    'remainingAttempts' => $maxAttempts - $attempts,
                    'retryAfter' => null,
                    'reason' => ''
                ];
            }
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::check() - Failed: ' . $e->getMessage());
        }

        return [
            'allowed' => true,
            'remainingAttempts' => $maxAttempts,
            'retryAfter' => null,
            'reason' => ''
        ];
    }

    /**
     * Record an attempt for a specific identifier and action
     *
     * @param string $identifier Unique identifier
     * @param string $action Action type
     * @param int $maxAttempts Maximum allowed attempts (used for ban calculation)
     * @param int $banDuration Duration of ban in seconds if max attempts exceeded
     * @return void
     */
    public static function recordAttempt(
        string $identifier,
        string $action = 'login',
        int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        int $banDuration = self::DEFAULT_BAN_DURATION
    ): void {
        $db = self::getDatabase();
        $now = date('Y-m-d H:i:s');

        try {
            $selectSql = "SELECT id, attempts FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action LIMIT 1";
            $row = $db->fetchOne($selectSql, ['identifier' => $identifier, 'action' => $action]);

            if ($row) {
                $id = (int)$row['id'];
                $attempts = (int)$row['attempts'] + 1;

                $bannedUntil = null;
                if ($attempts >= $maxAttempts) {
                    $bannedUntil = date('Y-m-d H:i:s', time() + $banDuration);
                }

                $updateSql = "UPDATE `" . self::tableName() . "` 
                              SET attempts = :attempts, last_attempt_at = :last_attempt_at, banned_until = :banned_until 
                              WHERE id = :id";
                $db->execute($updateSql, [
                    'attempts' => $attempts,
                    'last_attempt_at' => $now,
                    'banned_until' => $bannedUntil,
                    'id' => $id
                ]);
            } else {
                $insertSql = "INSERT INTO `" . self::tableName() . "` 
                              (rate_limit_type, identifier, action, attempts, first_attempt_at, last_attempt_at) 
                              VALUES ('attempt', :identifier, :action, 1, :first_attempt_at, :last_attempt_at)";
                $db->execute($insertSql, [
                    'identifier' => $identifier,
                    'action' => $action,
                    'first_attempt_at' => $now,
                    'last_attempt_at' => $now
                ]);
            }
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::recordAttempt() - Failed: ' . $e->getMessage());
        }
    }

    /**
     * Reset attempts for a specific identifier and action (e.g., after successful login)
     *
     * @param string $identifier Unique identifier
     * @param string $action Action type
     * @return void
     */
    public static function reset(string $identifier, string $action = 'login'): void
    {
        $db = self::getDatabase();
        try {
            $sql = "DELETE FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action";
            $db->execute($sql, ['identifier' => $identifier, 'action' => $action]);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::reset() - Failed: ' . $e->getMessage());
        }
    }

    /**
     * Clean up expired records beyond the time window
     *
     * @param int $timeWindow Time window in seconds
     * @return void
     */
    private static function cleanup(int $timeWindow = self::DEFAULT_TIME_WINDOW): void
    {
        $db = self::getDatabase();
        $expiredTime = date('Y-m-d H:i:s', time() - $timeWindow);

        try {
            $sql = "DELETE FROM `" . self::tableName() . "` 
                    WHERE rate_limit_type = 'attempt' AND last_attempt_at < :expiredTime 
                    AND (banned_until IS NULL OR banned_until < NOW())";
            $db->execute($sql, ['expiredTime' => $expiredTime]);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::cleanup() - Failed: ' . $e->getMessage());
        }
    }

    /**
     * Get rate limit status for a specific identifier
     *
     * @param string $identifier Unique identifier
     * @param string $action Action type
     * @return array|null Array with status info or null if no record
     */
    public static function getStatus(string $identifier, string $action = 'login'): ?array
    {
        $db = self::getDatabase();
        try {
            $sql = "SELECT * FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action LIMIT 1";
            return $db->fetchOne($sql, ['identifier' => $identifier, 'action' => $action]);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::getStatus() - Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ban a specific identifier for a duration
     *
     * @param string $identifier Unique identifier
     * @param string $action Action type
     * @param int $duration Ban duration in seconds
     * @return void
     */
    public static function ban(string $identifier, string $action = 'login', int $duration = self::DEFAULT_BAN_DURATION): void
    {
        $db = self::getDatabase();
        $now = date('Y-m-d H:i:s');
        $bannedUntil = date('Y-m-d H:i:s', time() + $duration);

        try {
            $selectSql = "SELECT id FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action LIMIT 1";
            $row = $db->fetchOne($selectSql, ['identifier' => $identifier, 'action' => $action]);

            if ($row) {
                $id = (int)$row['id'];
                $updateSql = "UPDATE `" . self::tableName() . "` 
                              SET banned_until = :banned_until, last_attempt_at = :last_attempt_at 
                              WHERE id = :id";
                $db->execute($updateSql, [
                    'banned_until' => $bannedUntil,
                    'last_attempt_at' => $now,
                    'id' => $id
                ]);
            } else {
                $insertSql = "INSERT INTO `" . self::tableName() . "` 
                              (rate_limit_type, identifier, action, attempts, first_attempt_at, last_attempt_at, banned_until) 
                              VALUES ('attempt', :identifier, :action, 99, :first_attempt_at, :last_attempt_at, :banned_until)";
                $db->execute($insertSql, [
                    'identifier' => $identifier,
                    'action' => $action,
                    'first_attempt_at' => $now,
                    'last_attempt_at' => $now,
                    'banned_until' => $bannedUntil
                ]);
            }
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::ban() - Failed: ' . $e->getMessage());
        }
    }

    /**
     * Unban a specific identifier
     *
     * @param string $identifier Unique identifier
     * @param string $action Action type
     * @return void
     */
    public static function unban(string $identifier, string $action = 'login'): void
    {
        $db = self::getDatabase();
        try {
            $sql = "UPDATE `" . self::tableName() . "` SET banned_until = NULL WHERE rate_limit_type = 'attempt' AND identifier = :identifier AND action = :action";
            $db->execute($sql, ['identifier' => $identifier, 'action' => $action]);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::unban() - Failed: ' . $e->getMessage());
        }
    }

    /**
     * Get client IP address (handles proxies)
     *
     * @return string Client IP address
     */
    public static function getClientIP(): string
    {
        $ipAddress = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ipAddress = trim($ipList[0]);
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        if (filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            return $ipAddress;
        }

        return '0.0.0.0';
    }

    /**
     * Format duration in human-readable format
     *
     * @param int $seconds Duration in seconds
     * @return string Formatted duration
     */
    private static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds !== 1 ? 's' : '');
        }

        if ($seconds < 3600) {
            $minutes = (int)floor($seconds / 60);
            return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
        }

        $hours = (int)floor($seconds / 3600);
        return $hours . ' hour' . ($hours !== 1 ? 's' : '');
    }

    /**
     * Get all banned identifiers
     *
     * @return array Array of banned records
     */
    public static function getBannedList(): array
    {
        $db = self::getDatabase();
        try {
            $sql = "SELECT * FROM `" . self::tableName() . "` 
                    WHERE rate_limit_type = 'attempt' AND banned_until IS NOT NULL 
                    AND banned_until > NOW() 
                    ORDER BY banned_until DESC";
            return $db->fetchAll($sql);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::getBannedList() - Failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get statistics for rate limiting
     *
     * @return array Statistics
     */
    public static function getStatistics(): array
    {
        $db = self::getDatabase();
        $stats = [
            'total_records' => 0,
            'currently_banned' => 0,
            'total_attempts_today' => 0
        ];

        try {
            $row1 = $db->fetchOne("SELECT COUNT(*) as count FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt'");
            if ($row1) {
                $stats['total_records'] = (int)$row1['count'];
            }

            $row2 = $db->fetchOne("SELECT COUNT(*) as count FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND banned_until IS NOT NULL AND banned_until > NOW()");
            if ($row2) {
                $stats['currently_banned'] = (int)$row2['count'];
            }

            $row3 = $db->fetchOne("SELECT SUM(attempts) as total FROM `" . self::tableName() . "` WHERE rate_limit_type = 'attempt' AND DATE(last_attempt_at) = CURDATE()");
            if ($row3) {
                $stats['total_attempts_today'] = (int)($row3['total'] ?? 0);
            }
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::getStatistics() - Failed: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * Check if a specific identifier is currently blocked/banned
     *
     * @param string $identifier Unique identifier (typically IP address)
     * @param string|null $action Action type (optional, checks if blocked for any action if not specified)
     * @return bool True if blocked, false otherwise
     */
    public static function isBlocked(string $identifier, ?string $action = null): bool
    {
        $db = self::getDatabase();
        try {
            if ($action === null) {
                $sql = "SELECT id FROM `" . self::tableName() . "` 
                        WHERE rate_limit_type = 'attempt' AND identifier = :identifier 
                        AND banned_until IS NOT NULL 
                        AND banned_until > NOW() 
                        LIMIT 1";
                $row = $db->fetchOne($sql, ['identifier' => $identifier]);
            } else {
                $sql = "SELECT id FROM `" . self::tableName() . "` 
                        WHERE rate_limit_type = 'attempt' AND identifier = :identifier 
                        AND action = :action 
                        AND banned_until IS NOT NULL 
                        AND banned_until > NOW() 
                        LIMIT 1";
                $row = $db->fetchOne($sql, ['identifier' => $identifier, 'action' => $action]);
            }

            return !empty($row);
        } catch (Throwable $e) {
            ErrorCapture::record('RateLimiter::isBlocked() - Failed: ' . $e->getMessage());
            return false;
        }
    }
}
