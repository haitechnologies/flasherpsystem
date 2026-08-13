<?php

return [
    'systems' => [
        'crm' => [
            'label' => 'CRM',
            'icon' => 'ph-users-three',
            'summary' => 'Manage leads, customers, projects, jobs, and service delivery from one connected workspace.',
            'modules' => [
                'Leads pipeline',
                'Customer records',
                'Projects',
                'Jobs and execution tracking',
            ],
        ],
        'accounting' => [
            'label' => 'Accounting',
            'icon' => 'ph-currency-circle-dollar',
            'summary' => 'Run quotations, invoices, expenses, payments, journals, and account reporting in one finance layer.',
            'modules' => [
                'Quotations and invoices',
                'Expenses and purchase flow',
                'Payments received and made',
                'Reports and journals',
            ],
        ],
        'hr' => [
            'label' => 'HR',
            'icon' => 'ph-identification-card',
            'summary' => 'Cover attendance, leave, payroll, employee records, and workforce administration in one HR suite.',
            'modules' => [
                'Departments and designations',
                'Attendance and leave',
                'Payroll runs and payslips',
                'User documents',
            ],
        ],
        'shipping' => [
            'label' => 'Shipping',
            'icon' => 'ph-package',
            'summary' => 'Coordinate advice notes, invoices, stocks, ports, carriers, and shipping master data for operations teams.',
            'modules' => [
                'Shipping advices',
                'Shipping stocks',
                'Ports, carriers, consignees, shippers',
            ],
        ],
    ],
    'platform' => [
        'highlights' => [
            'Multi-organization access',
            'Role-based permissions',
            'Audit-friendly backend logs',
            'System availability toggles',
            'Admin management and quick access',
            'UAE-focused business operations workflow',
        ],
    ],
    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'price_monthly_aed' => 399,
            'price_annual_aed' => 3990,
            'best_for' => 'Small teams launching one core workflow fast.',
            'includes' => [
                '1 organization',
                'Up to 5 admin users',
                'CRM or Accounting suite',
                'Standard onboarding',
                'Email support',
            ],
        ],
        'growth' => [
            'label' => 'Growth',
            'price_monthly_aed' => 999,
            'price_annual_aed' => 9990,
            'best_for' => 'Growing companies needing multiple connected departments.',
            'includes' => [
                'Up to 3 organizations',
                'Up to 20 admin users',
                'CRM + Accounting + HR',
                'Implementation assistance',
                'Priority support',
            ],
        ],
        'enterprise' => [
            'label' => 'Enterprise',
            'price_monthly_aed' => null,
            'price_annual_aed' => null,
            'best_for' => 'Complex operations spanning finance, HR, shipping, and custom controls.',
            'includes' => [
                'Unlimited organizations',
                'Custom admin seats',
                'Full suite: CRM, Accounting, HR, Shipping',
                'Custom workflows and rollout planning',
                'Dedicated account management',
            ],
        ],
    ],
];