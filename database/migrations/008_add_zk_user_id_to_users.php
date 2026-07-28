<?php

declare(strict_types=1);

/**
 * Migration: Add zk_user_id column to users table
 * 
 * Maps ZKTeco device user IDs to ERP employee records.
 */
class AddZkUserIdToUsers
{
    public function up(\mysqli $mysqli): void
    {
        $result = $mysqli->query("SHOW COLUMNS FROM `erp_users` LIKE 'zk_user_id'");
        if ($result && $result->num_rows === 0) {
            $sql = "ALTER TABLE `erp_users` ADD COLUMN `zk_user_id` VARCHAR(20) DEFAULT NULL AFTER `designation_id`";
            if (!$mysqli->query($sql)) {
                throw new RuntimeException('Migration failed: ' . $mysqli->error);
            }
        }
    }

    public function down(\mysqli $mysqli): void
    {
        $mysqli->query("ALTER TABLE `erp_users` DROP COLUMN IF EXISTS `zk_user_id`");
    }
}

return [
    'description' => 'Add zk_user_id column to users table',
    'up' => function (mysqli $conn): void {
        $m = new AddZkUserIdToUsers();
        $m->up($conn);
    },
];
