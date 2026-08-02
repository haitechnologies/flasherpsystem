<?php

declare(strict_types=1);

return [
    'description' => 'Add email column to erp_departments and replace department data with 3 Movestic Cargo departments',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $deptTable = $prefix . 'departments';
        $usersTable = $prefix . 'users';

        // 1. Add nullable email column if it does not exist yet
        $result = $conn->query("SHOW COLUMNS FROM `{$deptTable}` LIKE 'email'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$deptTable}` 
                ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL 
                AFTER `department`");
        }

        // 2. Rename matching departments and attach their email (idempotent)
        $renames = [
            'Sales'      => ['Sales Movestic Cargo', 'sales@movesticargo.com'],
            'Accounts'   => ['Accounts Movestic Cargo', 'accounts@movesticargo.com'],
            'Operations' => ['Operations Movestic Cargo', 'cargo@movesticargo.com'],
        ];
        foreach ($renames as $oldName => [$newName, $email]) {
            $stmt = $conn->prepare(
                "UPDATE `{$deptTable}` 
                 SET `department` = ?, `email` = ? 
                 WHERE `department` = ? AND `email` IS NULL"
            );
            $stmt->bind_param('sss', $newName, $email, $oldName);
            $stmt->execute();
            $stmt->close();
        }

        // 3. Remove department links for users in departments that are being removed,
        //    plus any dangling department_id references (e.g. nonexistent dept 1)
        $conn->query(
            "UPDATE `{$usersTable}` 
             SET `department_id` = NULL 
             WHERE `department_id` IS NOT NULL 
               AND `department_id` NOT IN (SELECT `id` FROM `{$deptTable}`)"
        );

        // 4. Delete the old departments (idempotent - no-op if already gone)
        $stmt = $conn->prepare(
            "DELETE FROM `{$deptTable}` 
             WHERE `department` IN (?, ?, ?, ?)"
        );
        $marketing = 'Marketing';
        $shipping = 'Shipping & Logistics';
        $technical = 'Technical';
        $hr = 'HR';
        $stmt->bind_param('ssss', $marketing, $shipping, $technical, $hr);
        $stmt->execute();
        $stmt->close();
    },
];
