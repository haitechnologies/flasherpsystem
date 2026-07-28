<?php

declare(strict_types=1);

return [
    'description' => 'Seed organization document categories (UAE corporate documents)',
    'up' => function (mysqli $conn): void {
        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'erp_';
        $table = $prefix . 'document_categories';

        $categories = [
            'Certificate of Incorporation / Formation',
            'Memorandum & Articles of Association (MOA/AOA)',
            'Trade License',
            'Trade Name Reservation Certificate',
            'External / Third-Party Approvals',
            'Ejari Certificate',
            'Lease Agreement / Flexi-Desk Contract',
            'Establishment Card (Immigration Card)',
            'MOHRE Company File / Labour Establishment Card',
            'E-Signature Card / UAE Pass Authorization',
            'Corporate Tax Registration Certificate',
            'VAT Registration Certificate',
            'Corporate Bank Account Details',
            'Ultimate Beneficial Owner (UBO) Register',
            'Anti-Money Laundering (AML) Framework',
            'Economic Substance Regulations (ESR) Records',
        ];

        $orgType = 'organizations';
        $stmt = $conn->prepare(
            "INSERT IGNORE INTO `{$table}` (document_category, document_category_type, is_active, created_by, created_at) VALUES (?, ?, 1, 1, NOW())"
        );

        foreach ($categories as $cat) {
            $stmt->bind_param('ss', $cat, $orgType);
            $stmt->execute();
        }

        $stmt->close();
        echo "[seed] Inserted " . count($categories) . " organization document categories.\n";
    },
];
