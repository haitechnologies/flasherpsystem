<?php

declare(strict_types=1);

return [
    'description' => 'Add branch, address, iban columns to erp_banks',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $table = $prefix . 'banks';

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'branch'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `branch` VARCHAR(255) NOT NULL DEFAULT '' 
                AFTER `bank_name`");
        }

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'address'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `address` TEXT 
                AFTER `branch`");
        }

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'iban'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `iban` VARCHAR(34) NOT NULL DEFAULT '' 
                AFTER `address`");
        }
    },
];
