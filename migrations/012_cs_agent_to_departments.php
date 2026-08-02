<?php

declare(strict_types=1);

return [
    'description' => 'Remap cs_agent FK from cs_agents/users to departments in jobs, customers, vendors',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $deptTable = $prefix . 'departments';
        $jobsTable = $prefix . 'jobs';
        $customersTable = $prefix . 'customers';
        $vendorsTable = $prefix . 'vendors';

        $csAgentsMap = [
            1 => 'Accounts Movestic Cargo',
            2 => 'Sales Movestic Cargo',
            3 => 'Operations Movestic Cargo',
        ];

        foreach ($csAgentsMap as $oldId => $deptName) {
            $result = $conn->query("SELECT id FROM `{$deptTable}` WHERE `department` = '{$deptName}' LIMIT 1");
            if ($result && $dept = $result->fetch_assoc()) {
                $deptId = (int)$dept['id'];
                $conn->query("UPDATE `{$jobsTable}` SET `cs_agent` = {$deptId} WHERE `cs_agent` = {$oldId}");
                $conn->query("UPDATE `{$customersTable}` SET `cs_agent` = {$deptId} WHERE `cs_agent` = {$oldId}");
                $conn->query("UPDATE `{$vendorsTable}` SET `cs_agent` = {$deptId} WHERE `cs_agent` = {$oldId}");
            }
        }
    },
];

