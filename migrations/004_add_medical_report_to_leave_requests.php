<?php

declare(strict_types=1);

return [
    'description' => 'Add medical_report_provided and medical_report_file columns to erp_leave_requests',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $table = $prefix . 'leave_requests';

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'medical_report_provided'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `medical_report_provided` TINYINT(1) NOT NULL DEFAULT 0 
                AFTER `status`");
        }

        $result = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE 'medical_report_file'");
        if ($result && $result->num_rows === 0) {
            $conn->query("ALTER TABLE `{$table}` 
                ADD COLUMN `medical_report_file` VARCHAR(255) DEFAULT NULL 
                AFTER `medical_report_provided`");
        }
    },
];
