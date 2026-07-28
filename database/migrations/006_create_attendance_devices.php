<?php

declare(strict_types=1);

/**
 * Migration: Create attendance_devices table
 * 
 * Stores ZKTeco device configuration for each organization.
 */
class CreateAttendanceDevicesTable
{
    public function up(\mysqli $mysqli): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `erp_attendance_devices` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `organization_id` INT UNSIGNED NOT NULL,
            `device_name` VARCHAR(100) NOT NULL DEFAULT '',
            `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
            `port` INT UNSIGNED NOT NULL DEFAULT 4370,
            `serial_number` VARCHAR(50) NOT NULL DEFAULT '',
            `device_password` VARCHAR(50) NOT NULL DEFAULT '0',
            `device_model` VARCHAR(50) NOT NULL DEFAULT '',
            `location` VARCHAR(255) NOT NULL DEFAULT '',
            `last_sync_at` DATETIME DEFAULT NULL,
            `last_punch_at` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_by` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_org` (`organization_id`),
            INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$mysqli->query($sql)) {
            throw new RuntimeException('Migration failed: ' . $mysqli->error);
        }
    }

    public function down(\mysqli $mysqli): void
    {
        $mysqli->query("DROP TABLE IF EXISTS `erp_attendance_devices`");
    }
}

return [
    'description' => 'Create attendance_devices table',
    'up' => function (mysqli $conn): void {
        $m = new CreateAttendanceDevicesTable();
        $m->up($conn);
    },
];
