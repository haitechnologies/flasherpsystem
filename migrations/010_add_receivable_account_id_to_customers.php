<?php

declare(strict_types=1);

return [
    'description' => 'Add receivable_account_id column to erp_customers',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $table = $prefix . 'customers';

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'receivable_account_id'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `receivable_account_id` INT NULL DEFAULT NULL 
                AFTER `opening_balance`");
        }
    },
];
