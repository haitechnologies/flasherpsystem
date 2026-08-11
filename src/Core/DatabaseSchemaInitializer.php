<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use PDO;
use PDOException;

/**
 * Database Schema Initializer
 *
 * Ensures all required tables exist before they are accessed.
 * Supporting both legacy mysqli and modernized PDO/Database connections.
 */
class DatabaseSchemaInitializer
{
    private static bool $initialized = false;

    /**
     * Resolve table name from DB constants with fallback.
     *
     * @param string $constantName
     * @param string $fallback
     * @return string
     */
    private static function tableName(string $constantName, string $fallback): string
    {
        $prefix = class_exists(DB::class) ? DB::getPrefix() : 'erp_';
        if (class_exists(DB::class) && defined(DB::class . '::' . $constantName)) {
            $name = (string) constant(DB::class . '::' . $constantName);
            if ($prefix !== 'erp_') {
                $name = (string) preg_replace('/^erp_/', $prefix, $name);
            }
            return $name;
        }
        if ($prefix !== 'erp_') {
            $fallback = (string) preg_replace('/^erp_/', $prefix, $fallback);
        }
        return $fallback;
    }

    /**
     * Initialize database schema - must be called after database connection is established
     *
     * @param mixed $conn Database connection object (mysqli, PDO, or Database)
     * @return void
     */
    public static function init(mixed $conn): void
    {
        // Prevent re-initialization in same request
        if (self::$initialized) {
            return;
        }

        if (!$conn) {
            ErrorCapture::record("DatabaseSchemaInitializer: No database connection provided");
            return;
        }

        // Unwrap Database wrapper to get PDO
        if ($conn instanceof Database) {
            $conn = $conn->getConnection();
        }

        self::$initialized = true;

        // Create all required tables
        self::createInquiriesTable($conn);
        self::createEmailQueueTable($conn);
        self::createRateLimitTable($conn);
        self::createDisposableEmailDomainsTable($conn);
        self::createBackendErrorLogsTable($conn);
        self::createBackendLogCoverageTable($conn);
        self::createInquiryRepliesTable($conn);
        self::ensureUsersMfaColumns($conn);
        self::ensurePaidDaysColumns($conn);
    }

    /**
     * Helper to execute a query.
     *
     * @param mixed $conn
     * @param string $sql
     * @param string $tableName
     * @return void
     */
    private static function executeQuery(mixed $conn, string $sql, string $tableName): void
    {
        if ($conn instanceof mysqli) {
            if (!$conn->query($sql)) {
                ErrorCapture::record("ERROR creating {$tableName} table: " . $conn->error);
            }
        } elseif ($conn instanceof PDO) {
            try {
                $conn->exec($sql);
            } catch (PDOException $e) {
                ErrorCapture::record("ERROR creating {$tableName} table: " . $e->getMessage());
            }
        }
    }

    /**
     * Create contact form inquiries table
     */
    private static function createInquiriesTable(mixed $conn): void
    {
        $tableName = self::tableName('INQUIRIES', 'erp_inquiries');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL,
            `subject` VARCHAR(100) NOT NULL,
            `message` LONGTEXT NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
            `status` ENUM('new', 'responded', 'closed') DEFAULT 'new',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX idx_email (`email`),
            INDEX idx_created_at (`created_at`),
            INDEX idx_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT 'Contact form inquiries from website visitors'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create email queue table
     */
    private static function createEmailQueueTable(mixed $conn): void
    {
        $tableName = self::tableName('EMAIL_QUEUE', 'erp_email_queue');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `recipient` VARCHAR(255) NOT NULL,
            `subject` VARCHAR(255) NOT NULL,
            `body` LONGTEXT NOT NULL,
            `headers` JSON DEFAULT NULL,
            `priority` INT(11) DEFAULT 2 COMMENT '1=high, 2=medium, 3=low',
            `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            `max_retries` INT(11) DEFAULT 3,
            `retries` INT(11) DEFAULT 0,
            `failed_reason` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `sent_at` TIMESTAMP NULL DEFAULT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            INDEX idx_status (`status`),
            INDEX idx_recipient (`recipient`),
            INDEX idx_created_at_status (`created_at`, `status`),
            INDEX idx_priority_status (`priority`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT 'Email queue for reliable email delivery with retry logic'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create rate limiting table
     */
    private static function createRateLimitTable(mixed $conn): void
    {
        $tableName = self::tableName('RATE_LIMIT_ATTEMPTS', 'erp_rate_limits');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT 'Rate limiting for brute-force attack prevention'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create disposable email domains table
     */
    private static function createDisposableEmailDomainsTable(mixed $conn): void
    {
        $tableName = self::tableName('DISPOSABLE_EMAIL_DOMAINS', 'erp_disposable_email_domains');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `domain` VARCHAR(255) NOT NULL,
            `is_disposable` TINYINT(1) NOT NULL DEFAULT 1,
            `is_allowlisted` TINYINT(1) NOT NULL DEFAULT 0,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `source` VARCHAR(100) DEFAULT 'disposable-email-domains',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_domain` (`domain`),
            KEY `idx_disposable_status` (`is_disposable`, `status`),
            KEY `idx_allowlisted_status` (`is_allowlisted`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT 'Disposable and allowlisted email domains for frontend auth validation'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create canonical backend error logs table.
     */
    private static function createBackendErrorLogsTable(mixed $conn): void
    {
        $tableName = self::tableName('BACKEND_ERROR_LOGS', 'erp_backend_error_logs');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `request_id` VARCHAR(64) DEFAULT NULL,
            `correlation_id` VARCHAR(64) DEFAULT NULL,
            `module_slug` VARCHAR(120) NOT NULL DEFAULT 'unknown',
            `module_id` INT DEFAULT NULL,
            `page_name` VARCHAR(255) NOT NULL DEFAULT 'unknown',
            `page_path` VARCHAR(500) DEFAULT NULL,
            `entrypoint_type` ENUM('page','ajax','api','datatable','cron','cli','unknown') NOT NULL DEFAULT 'unknown',
            `source_channel` VARCHAR(80) NOT NULL DEFAULT 'dashboard_runtime',
            `severity` ENUM('CRITICAL','ERROR','WARNING','NOTICE','INFO','DEBUG') NOT NULL DEFAULT 'ERROR',
            `error_code` VARCHAR(100) DEFAULT NULL,
            `message` TEXT NOT NULL,
            `context_json` LONGTEXT DEFAULT NULL,
            `stack_trace` LONGTEXT DEFAULT NULL,
            `source_file` VARCHAR(500) DEFAULT NULL,
            `source_line` INT DEFAULT NULL,
            `source_function` VARCHAR(255) DEFAULT NULL,
            `request_uri` VARCHAR(1000) DEFAULT NULL,
            `request_method` VARCHAR(12) DEFAULT NULL,
            `user_id` INT DEFAULT NULL,
            `role_id` INT DEFAULT NULL,
            `session_id` VARCHAR(255) DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(1000) DEFAULT NULL,
            `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
            `resolved_by` INT DEFAULT NULL,
            `resolved_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_module_page_time` (`module_slug`, `page_name`, `created_at`),
            KEY `idx_severity_time` (`severity`, `created_at`),
            KEY `idx_source_time` (`source_channel`, `created_at`),
            KEY `idx_request_id` (`request_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Canonical backend error events with module/page categorization'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create backend page/module logging coverage table.
     */
    private static function createBackendLogCoverageTable(mixed $conn): void
    {
        $tableName = self::tableName('BACKEND_LOG_COVERAGE', 'erp_backend_log_coverage');
        $sql = "CREATE TABLE IF NOT EXISTS `{$tableName}` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `module_slug` VARCHAR(120) NOT NULL DEFAULT 'unknown',
            `page_name` VARCHAR(255) NOT NULL,
            `page_path` VARCHAR(500) NOT NULL,
            `entrypoint_type` ENUM('page','ajax','api','datatable','cron','cli','unknown') NOT NULL DEFAULT 'unknown',
            `source_channel` VARCHAR(80) NOT NULL DEFAULT 'dashboard_runtime',
            `bootstrap_included` TINYINT(1) NOT NULL DEFAULT 0,
            `first_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen_error_at` DATETIME DEFAULT NULL,
            `seen_count` BIGINT UNSIGNED NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_page_entrypoint` (`page_path`, `entrypoint_type`),
            KEY `idx_module_last_seen` (`module_slug`, `last_seen_at`),
            KEY `idx_last_seen_error` (`last_seen_error_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Runtime backend page/module coverage tracking for zero-miss logging'";

        self::executeQuery($conn, $sql, $tableName);
    }

    /**
     * Create inquiry replies / email thread table.
     */
    private static function createInquiryRepliesTable(mixed $conn): void
    {
        $inquiryRepliesTable = self::tableName('INQUIRY_REPLIES', 'erp_inquiry_replies');
        $emailQueueTable = self::tableName('EMAIL_QUEUE', 'erp_email_queue');
        $sql = "CREATE TABLE IF NOT EXISTS `{$inquiryRepliesTable}` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `inquiry_id` INT(11) NOT NULL,
            `admin_user_id` INT(11) DEFAULT NULL,
            `admin_name` VARCHAR(200) NOT NULL DEFAULT '',
            `direction` ENUM('outbound','note') NOT NULL DEFAULT 'outbound'
                COMMENT 'outbound = email sent to customer, note = internal note',
            `recipient_email` VARCHAR(255) NOT NULL DEFAULT '',
            `subject` VARCHAR(500) NOT NULL DEFAULT '',
            `body` TEXT NOT NULL,
            `is_email_sent` TINYINT(1) NOT NULL DEFAULT 0,
            `email_queue_id` INT(11) DEFAULT NULL COMMENT 'FK to {$emailQueueTable}.id for delivery tracking',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_inquiry_id` (`inquiry_id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT 'Email thread replies for inquiry conversations'";

        self::executeQuery($conn, $sql, $inquiryRepliesTable);

        // Ensure email_queue_id column exists on pre-existing tables
        $hasColumn = false;
        $colQuery = "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                       AND COLUMN_NAME = 'email_queue_id'
                     LIMIT 1";

        if ($conn instanceof mysqli) {
            $check = $conn->prepare($colQuery);
            if ($check) {
                $check->bind_param('s', $inquiryRepliesTable);
                $check->execute();
                $check->store_result();
                $hasColumn = $check->num_rows > 0;
                $check->close();
            }
        } elseif ($conn instanceof PDO) {
            try {
                $check = $conn->prepare($colQuery);
                $check->execute([$inquiryRepliesTable]);
                $hasColumn = (bool) $check->fetch();
            } catch (PDOException $e) {
                ErrorCapture::record("ERROR checking email_queue_id column: " . $e->getMessage());
            }
        }

        if (!$hasColumn) {
            $alterSql = "ALTER TABLE `{$inquiryRepliesTable}`
                ADD COLUMN `email_queue_id` INT(11) DEFAULT NULL
                COMMENT 'FK to {$emailQueueTable}.id for delivery tracking'";
            if ($conn instanceof mysqli) {
                $conn->query($alterSql);
            } elseif ($conn instanceof PDO) {
                try {
                    $conn->exec($alterSql);
                } catch (PDOException $e) {
                    ErrorCapture::record("ERROR adding email_queue_id to inquiry replies: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Ensure leave_types and leave_requests tables have paid_days column.
     */
    private static function ensurePaidDaysColumns(mixed $conn): void
    {
        $tables = [
            ['constant' => 'LEAVE_TYPES', 'fallback' => 'erp_leave_types', 'after' => 'paid'],
            ['constant' => 'LEAVE_REQUESTS', 'fallback' => 'erp_leave_requests', 'after' => 'total_days'],
        ];

        $columnName = 'paid_days';
        $colQuery = "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                       AND COLUMN_NAME = ?
                     LIMIT 1";

        foreach ($tables as $t) {
            $tableName = self::tableName($t['constant'], $t['fallback']);
            $alterSql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` INT DEFAULT 0 AFTER `{$t['after']}`";

            $hasColumn = false;
            if ($conn instanceof mysqli) {
                $existsStmt = $conn->prepare($colQuery);
                if ($existsStmt) {
                    $existsStmt->bind_param('ss', $tableName, $columnName);
                    if ($existsStmt->execute()) {
                        $existsStmt->store_result();
                        $hasColumn = $existsStmt->num_rows > 0;
                    }
                    $existsStmt->close();
                }
            } elseif ($conn instanceof PDO) {
                try {
                    $existsStmt = $conn->prepare($colQuery);
                    $existsStmt->execute([$tableName, $columnName]);
                    $hasColumn = (bool) $existsStmt->fetch();
                } catch (PDOException $e) {
                    ErrorCapture::record("ERROR checking {$columnName} on {$tableName}: " . $e->getMessage());
                    continue;
                }
            }

            if ($hasColumn) {
                continue;
            }

            if ($conn instanceof mysqli) {
                if (!$conn->query($alterSql)) {
                    ErrorCapture::record("ERROR adding {$columnName} to {$tableName}: " . $conn->error);
                }
            } elseif ($conn instanceof PDO) {
                try {
                    $conn->exec($alterSql);
                } catch (PDOException $e) {
                    ErrorCapture::record("ERROR adding {$columnName} to {$tableName}: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Ensure users table contains MFA fields for dashboard login hardening.
     */
    private static function ensureUsersMfaColumns(mixed $conn): void
    {
        $tableName = self::tableName('USERS', 'erp_users');

        $requiredColumns = [
            'mfa_totp_enabled' => "ALTER TABLE `{$tableName}` ADD COLUMN `mfa_totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`",
            'mfa_totp_secret' => "ALTER TABLE `{$tableName}` ADD COLUMN `mfa_totp_secret` VARCHAR(255) NULL DEFAULT NULL AFTER `mfa_totp_enabled`",
            'mfa_recovery_codes' => "ALTER TABLE `{$tableName}` ADD COLUMN `mfa_recovery_codes` TEXT NULL DEFAULT NULL AFTER `mfa_totp_secret`",
            'mfa_enabled_at' => "ALTER TABLE `{$tableName}` ADD COLUMN `mfa_enabled_at` DATETIME NULL DEFAULT NULL AFTER `mfa_recovery_codes`",
        ];

        $colQuery = "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = ?
                       AND COLUMN_NAME = ?
                     LIMIT 1";

        foreach ($requiredColumns as $columnName => $alterSql) {
            $hasColumn = false;
            if ($conn instanceof mysqli) {
                $existsStmt = $conn->prepare($colQuery);
                if (!$existsStmt) {
                    ErrorCapture::record("ERROR preparing MFA column check for {$columnName}: " . $conn->error);
                    continue;
                }
                $existsStmt->bind_param('ss', $tableName, $columnName);
                if ($existsStmt->execute()) {
                    $existsStmt->store_result();
                    $hasColumn = $existsStmt->num_rows > 0;
                } else {
                    ErrorCapture::record("ERROR executing MFA column check for {$columnName}: " . $existsStmt->error);
                }
                $existsStmt->close();
            } elseif ($conn instanceof PDO) {
                try {
                    $existsStmt = $conn->prepare($colQuery);
                    $existsStmt->execute([$tableName, $columnName]);
                    $hasColumn = (bool) $existsStmt->fetch();
                } catch (PDOException $e) {
                    ErrorCapture::record("ERROR executing MFA column check for {$columnName}: " . $e->getMessage());
                    continue;
                }
            }

            if ($hasColumn) {
                continue;
            }

            if ($conn instanceof mysqli) {
                if (!$conn->query($alterSql)) {
                    ErrorCapture::record("ERROR adding {$columnName} to {$tableName}: " . $conn->error);
                }
            } elseif ($conn instanceof PDO) {
                try {
                    $conn->exec($alterSql);
                } catch (PDOException $e) {
                    ErrorCapture::record("ERROR adding {$columnName} to {$tableName}: " . $e->getMessage());
                }
            }
        }
    }
}
