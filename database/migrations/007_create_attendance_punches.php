<?php

declare(strict_types=1);

/**
 * Migration: Create attendance_punches table
 * 
 * Stores raw punch logs pulled from ZKTeco devices.
 */
class CreateAttendancePunchesTable
{
    public function up(\mysqli $mysqli): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `erp_attendance_punches` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `organization_id` INT UNSIGNED NOT NULL,
            `device_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `employee_id` INT UNSIGNED NOT NULL DEFAULT 0,
            `zk_user_id` VARCHAR(20) NOT NULL DEFAULT '',
            `punch_time` DATETIME NOT NULL,
            `punch_type` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `verification_mode` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `is_synced` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_device_user_time` (`device_id`, `zk_user_id`, `punch_time`),
            INDEX `idx_org` (`organization_id`),
            INDEX `idx_employee` (`employee_id`),
            INDEX `idx_device` (`device_id`),
            INDEX `idx_punch_time` (`punch_time`),
            INDEX `idx_sync` (`is_synced`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$mysqli->query($sql)) {
            throw new RuntimeException('Migration failed: ' . $mysqli->error);
        }
    }

    public function down(\mysqli $mysqli): void
    {
        $mysqli->query("DROP TABLE IF EXISTS `erp_attendance_punches`");
    }
}

return [
    'description' => 'Create attendance_punches table',
    'up' => function (mysqli $conn): void {
        $m = new CreateAttendancePunchesTable();
        $m->up($conn);
    },
];
