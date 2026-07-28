<?php

/**
 * Migration: Add date_of_joining column to erp_users table.
 */
return [
    'description' => 'Add date_of_joining column to users table',
    'up' => function (mysqli $conn): void {
        $table = \App\Core\DB::getPrefix() . 'users';

        $conn->query("ALTER TABLE `{$table}`
            ADD COLUMN `date_of_joining` DATE DEFAULT NULL AFTER `dob`,
            ADD INDEX `idx_date_of_joining` (`date_of_joining`)");
    },
];
