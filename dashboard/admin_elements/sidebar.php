<?php
require_once __DIR__ . '/../../config/globals.php';
// Output dynamic sidebar color variables for admin sidebar
echo generateColorVariablesCSS();
?>
<style>
.sidebar-main .nav-sidebar > .nav-item > .nav-link {
    color: var(--sidebar-text);
    background: none;
    transition: background 0.15s, color 0.15s;
}
.sidebar-main .nav-sidebar > .nav-item > .nav-link:hover,
.sidebar-main .nav-sidebar > .nav-item > .nav-link:focus {
    background: var(--sidebar-hover-bg) !important;
    color: var(--sidebar-active-text) !important;
}
.sidebar-main .nav-sidebar > .nav-item > .nav-link.active,
.sidebar-main .nav-sidebar > .nav-item > .nav-link[aria-current="page"] {
    background: var(--sidebar-active-bg) !important;
    color: var(--sidebar-active-text) !important;
}
</style>
<!-- Main Sidebar Navigation -->

<?php
// Get sidebar colors from settings (now using CSS variables in admin_header.php)
$sidebarColors = getSidebarColors();
?>

<aside class="sidebar sidebar-main sidebar-expand-lg" role="complementary" aria-label="Sidebar navigation" style="background: var(--sidebar-bg); color: var(--sidebar-text);">

<?php
/**
 * Sidebar Navigation Component
 * 
 * Displays dynamic navigation menu based on user permissions
 * @var string $current_page Current page filename
 * @var string $session_email User email from session
 */

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Check if menu item should be active
 */
if (!function_exists('isMenuActive')) {
    function isMenuActive($pages) {
        global $current_page;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        if (!is_array($pages)) {
            $pages = [$pages];
        }

        foreach ($pages as $page) {
            // If page contains a query string, match path and query string exactly
            if (strpos($page, '?') !== false) {
                list($base, $query) = explode('?', $page, 2);
                $base = '/' . ltrim($base, '/');
                if ((strpos($scriptName, $base) !== false) && ($queryString === $query)) {
                    return true;
                }
            } else {
                if ($current_page === $page) {
                    return true;
                }
            }
        }
        return false;
    }
}

/**
 * Check user access to module (prevent redeclaration if admin_header.php loaded first)
 */
if (!function_exists('hasModuleAccess')) {
	function hasModuleAccess($module) {
	    return granted_('view', $module) || granted_('create', $module) || 
	           granted_('edit', $module) || granted_('delete', $module);
	}
}

if (!function_exists('hasAnyModuleAccess')) {
    function hasAnyModuleAccess(array $modules): bool {
        if (function_exists('has_full_access') && has_full_access()) return true;
        foreach ($modules as $module) {
            if (function_exists('hasModuleAccess') && hasModuleAccess($module)) return true;
        }
        return false;
    }
}

/**
 * Render menu item
 */
if (!function_exists('renderMenuItem')) {
    function renderMenuItem($href, $label, $icon, $pages = []) {
        $activeClass = isMenuActive((array)$pages) ? 'active' : '';
        echo <<<HTML
        <li class="nav-item">
            <a href="{$href}" class="nav-link {$activeClass}" aria-label="{$label}">
                <i class="{$icon}" aria-hidden="true"></i>
                <span>{$label}</span>
            </a>
        </li>
HTML;
    }
}

/**
 * Render section divider and header
 */
function renderSectionHeader($title, $withDivider = true) {
    if ($withDivider) {
        echo '<li class="nav-item-divider"></li>';
    }
    echo <<<HTML
    <li class="nav-item-header">
        <div class="text-uppercase fs-sm lh-sm opacity-50 sidebar-resize-hide">
            {$title}
        </div>
        <i class="ph-dots-three sidebar-resize-show"></i>
    </li>
HTML;
}

// ============================================================================
// MENU CONFIGURATION
// ============================================================================

$menuConfig = [
    'dashboard' => [
        'label' => '',
        'items' => [
            [
                'href' => 'index.php',
                'label' => 'Home',
                'icon' => 'ph-house',
                'pages' => ['index.php'],
                'condition' => function() { return true; }
            ],
        ],
    ],
    'shipping' => [
        'label' => 'Shipping System',
        'items' => [
            [
                'href' => 'listing_shipping_advices.php',
                'label' => 'Shipping Advices',
                'icon' => 'ph-package',
                'pages' => ['listing_shipping_advices.php', 'shipping_advices.php', 'view_shipping_advice.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('shipping_advices'); }
            ],
            [
                'href' => 'listing_shipping_invoices.php',
                'label' => 'Shipping Invoices',
                'icon' => 'ph-receipt',
                'pages' => ['listing_shipping_invoices.php', 'shipping_invoices.php', 'view_shipping_invoice.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('shipping_invoices'); }
            ],
            [
                'href' => 'listing_shipping_stocks.php',
                'label' => 'Shipping Stocks',
                'icon' => 'ph-archive-box',
                'pages' => ['listing_shipping_stocks.php', 'shipping_stocks.php', 'view_shipping_stocks.php', 'report_shipping_stocks.php', 'listing_shipping_advice_items.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('shipping_stocks'); }
            ],
            [
                'href' => 'listing_shipping_customers.php',
                'label' => 'Shipping Customers',
                'icon' => 'ph-users-three',
                'pages' => ['listing_shipping_customers.php', 'shipping_customers.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('shipping_customers'); }
            ],
            [
                'href' => 'listing_hscodes.php',
                'label' => 'HS Codes',
                'icon' => 'ph-barcode',
                'pages' => ['listing_hscodes.php', 'hscodes.php', 'hscode_detail.php'],
                'condition' => function() { return has_full_access() && hasModuleAccess('hscodes'); }
            ],
            [
                'href' => '#shipping-settings-submenu',
                'label' => 'Settings',
                'icon' => 'ph-database',
                'pages' => ['listing_geo_countries.php', 'listing_ports.php', 'ports.php', 'listing_carriers.php', 'carriers.php', 'listing_consignees.php', 'consignees.php', 'listing_shippers.php', 'shippers.php'],
                'type' => 'submenu',
                'condition' => function() {
                    return has_full_access() || hasModuleAccess('geo_countries') || hasModuleAccess('ports') || hasModuleAccess('carriers') || hasModuleAccess('consignees') || hasModuleAccess('shippers');
                },
                'children' => [
                    ['href' => 'listing_geo_countries.php', 'label' => 'Countries', 'pages' => ['listing_geo_countries.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('geo_countries'); }],
                    ['href' => 'listing_ports.php', 'label' => 'Ports', 'pages' => ['listing_ports.php', 'ports.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('ports'); }],
                    ['href' => 'listing_carriers.php', 'label' => 'Carriers', 'pages' => ['listing_carriers.php', 'carriers.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('carriers'); }],
                    ['href' => 'listing_consignees.php', 'label' => 'Consignees', 'pages' => ['listing_consignees.php', 'consignees.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('consignees'); }],
                    ['href' => 'listing_shippers.php', 'label' => 'Shippers', 'pages' => ['listing_shippers.php', 'shippers.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('shippers'); }],
                ]
            ],
        ]
    ],
    'hr' => [
        'label' => 'HR System',
        'items' => [
            [
                'href' => 'listing_users.php',
                'label' => 'Employees',
                'icon' => 'ph-users',
                'pages' => ['listing_users.php', 'users.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('users'); }
            ],
            [
                'href' => 'listing_attendance.php',
                'label' => 'Attendance',
                'icon' => 'ph-calendar-check',
                'pages' => ['listing_attendance.php', 'attendance.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('attendance'); }
            ],
            [
                'href' => 'listing_leave_requests.php',
                'label' => 'Leave Requests',
                'icon' => 'ph-calendar-x',
                'pages' => ['listing_leave_requests.php', 'leave_requests.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('leave_requests'); }
            ],
            [
                'href' => '#hr-payroll-submenu',
                'label' => 'Payroll',
                'icon' => 'ph-calculator',
                'pages' => ['listing_salary_structures.php', 'salary_structures.php', 'listing_employee_salaries.php', 'listing_payroll_runs.php', 'payroll_runs.php', 'view_payroll_run.php', 'listing_payslips.php', 'payslips.php', 'view_payslip.php'],
                'type' => 'submenu',
                'condition' => function() {
                    return has_full_access()
                        || hasModuleAccess('salary_structures')
                        || hasModuleAccess('employee_salaries')
                        || hasModuleAccess('payroll_runs')
                        || hasModuleAccess('payslips');
                },
                'children' => [
                    ['href' => 'listing_salary_structures.php', 'label' => 'Salary Structures', 'pages' => ['listing_salary_structures.php', 'salary_structures.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('salary_structures'); }],
                    ['href' => 'listing_employee_salaries.php', 'label' => 'Employee Salaries', 'pages' => ['listing_employee_salaries.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('employee_salaries'); }],
                    ['href' => 'listing_payroll_runs.php', 'label' => 'Payroll Runs', 'pages' => ['listing_payroll_runs.php', 'payroll_runs.php', 'view_payroll_run.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('payroll_runs'); }],
                    ['href' => 'listing_payslips.php', 'label' => 'Payslips', 'pages' => ['listing_payslips.php', 'payslips.php', 'view_payslip.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('payslips'); }],
                ]
            ],
            [
                'href' => 'listing_user_documents.php',
                'label' => 'User Documents',
                'icon' => 'ph-file-text',
                'pages' => ['listing_user_documents.php', 'user_documents.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('user_documents'); }
            ],
            [
                'href' => 'listing_air_tickets.php',
                'label' => 'Air Tickets',
                'icon' => 'ph-airplane-takeoff',
                'pages' => ['air_tickets.php', 'listing_air_tickets.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('air_tickets'); }
            ],
            [
                'href' => 'listing_gratuity_settlements.php',
                'label' => 'Gratuity',
                'icon' => 'ph-coins',
                'pages' => ['gratuity_settlements.php', 'listing_gratuity_settlements.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('gratuity_settlements'); }
            ],
            [
                'href' => 'report_hr.php',
                'label' => 'HR Report',
                'icon' => 'ph-chart-line-up',
                'pages' => ['report_hr.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('report_hr'); }
            ],
            [
                'href' => '#hr-settings-submenu',
                'label' => 'Settings',
                'icon' => 'ph-gear',
                'pages' => ['listing_departments.php', 'departments.php', 'listing_designations.php', 'designations.php', 'listing_leave_types.php', 'leave_types.php', 'listing_document_categories.php', 'document_categories.php', 'listing_payroll_components.php', 'payroll_components.php'],
                'type' => 'submenu',
                'condition' => function() {
                    return has_full_access() || hasModuleAccess('departments') || hasModuleAccess('designations') || hasModuleAccess('leave_types') || hasModuleAccess('document_categories') || hasModuleAccess('payroll_components');
                },
                'children' => [
                    ['href' => 'listing_departments.php', 'label' => 'Departments', 'pages' => ['listing_departments.php', 'departments.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('departments'); }],
                    ['href' => 'listing_designations.php', 'label' => 'Designations', 'pages' => ['listing_designations.php', 'designations.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('designations'); }],
                    ['href' => 'listing_leave_types.php', 'label' => 'Leave Types', 'pages' => ['listing_leave_types.php', 'leave_types.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('leave_types'); }],
                    ['href' => 'listing_document_categories.php', 'label' => 'Document Categories', 'pages' => ['listing_document_categories.php', 'document_categories.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('document_categories'); }],
                    ['href' => 'listing_payroll_components.php', 'label' => 'Payroll Components', 'pages' => ['listing_payroll_components.php', 'payroll_components.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('payroll_components'); }],
                ]
            ],
            [
                'href' => 'hr_guide.php',
                'label' => 'HR Help Guide',
                'icon' => 'ph-question',
                'pages' => ['hr_guide.php'],
                'condition' => function() { return has_full_access() || dashboardHasSystemAccess('hr'); }
            ],
        ]
    ],
    'accounting' => [
        'label' => 'Accounting',
        'items' => [
            [
                'href' => 'listing_items.php',
                'label' => 'Items',
                'icon' => 'ph-archive',
                'pages' => ['listing_items.php', 'items.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('items'); }
            ],
            [
                'href' => 'listing_banks.php',
                'label' => 'Banking',
                'icon' => 'ph-bank',
                'pages' => ['listing_banks.php', 'banks.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('banks'); }
            ],
            [
                'href' => '#accounting-sales-submenu',
                'label' => 'Sales',
                'icon' => 'ph-shopping-cart',
                'pages' => ['listing_customers.php', 'customers.php', 'customer_overview.php', 'customer_billing_addresses.php', 'customer_shipping_addresses.php', 'customer_comments.php', 'customer_transactions.php', 'customer_mails.php', 'customer_statement.php', 'customer_logs.php', 'customer_contacts.php', 'listing_quotations.php', 'quotations.php', 'quotation_overview.php', 'listing_sale_orders.php', 'sale_orders.php', 'sale_order_overview.php', 'listing_invoices.php', 'invoices.php', 'invoice_overview.php', 'listing_payments_received.php', 'payments_received.php', 'payment_received_overview.php', 'listing_credit_notes.php', 'credit_notes.php', 'credit_note_overview.php', 'recurring_invoices.php', 'recurring_invoice_overview.php'],
                'type' => 'submenu',
                'condition' => function() {
                    return has_full_access()
                        || hasModuleAccess('customers')
                        || hasModuleAccess('quotations')
                        || hasModuleAccess('sale_orders')
                        || hasModuleAccess('invoices')
                        || hasModuleAccess('payments_received')
                        || hasModuleAccess('credit_notes');
                },
                'children' => [
                    ['href' => 'listing_customers.php', 'label' => 'Customers', 'pages' => ['listing_customers.php', 'customers.php', 'customer_overview.php', 'customer_billing_addresses.php', 'customer_shipping_addresses.php', 'customer_comments.php', 'customer_transactions.php', 'customer_mails.php', 'customer_statement.php', 'customer_logs.php', 'customer_contacts.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('customers'); }],
                    ['href' => 'listing_quotations.php', 'label' => 'Quotes', 'pages' => ['listing_quotations.php', 'quotations.php', 'quotation_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('quotations'); }],
                    ['href' => 'listing_sale_orders.php', 'label' => 'Sale Orders', 'pages' => ['listing_sale_orders.php', 'sale_orders.php', 'sale_order_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('sale_orders'); }],
                    ['href' => 'listing_invoices.php', 'label' => 'Invoices', 'pages' => ['listing_invoices.php', 'invoices.php', 'invoice_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('invoices'); }],
                    ['href' => 'listing_payments_received.php', 'label' => 'Payments Received', 'pages' => ['listing_payments_received.php', 'payments_received.php', 'payment_received_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('payments_received'); }],
                    ['href' => 'recurring_invoices.php', 'label' => 'Recurring Invoices', 'pages' => ['recurring_invoices.php', 'recurring_invoice_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('invoices'); }],
                    ['href' => 'listing_credit_notes.php', 'label' => 'Credit Notes', 'pages' => ['listing_credit_notes.php', 'credit_notes.php', 'credit_note_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('credit_notes'); }],
                ]
            ],
            [
                'href' => '#accounting-purchases-submenu',
                'label' => 'Purchases',
                'icon' => 'ph-package',
                'pages' => ['listing_vendors.php', 'vendors.php', 'vendor_overview.php', 'listing_expenses.php', 'expenses.php', 'expense_overview.php', 'listing_purchase_orders.php', 'purchase_orders.php', 'purchase_order_overview.php', 'listing_purchases.php', 'purchases.php', 'purchase_overview.php', 'listing_payments_made.php', 'payments_made.php', 'payments_made_overview.php', 'listing_debit_notes.php', 'debit_notes.php', 'debit_note_overview.php'],
                'type' => 'submenu',
                'condition' => function() {
                    return has_full_access()
                        || hasModuleAccess('vendors')
                        || hasModuleAccess('expenses')
                        || hasModuleAccess('purchase_orders')
                        || hasModuleAccess('purchases')
                        || hasModuleAccess('payments_made')
                        || hasModuleAccess('debit_notes');
                },
                'children' => [
                    ['href' => 'listing_vendors.php', 'label' => 'Vendors', 'pages' => ['listing_vendors.php', 'vendors.php', 'vendor_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('vendors'); }],
                    ['href' => 'listing_expenses.php', 'label' => 'Expenses', 'pages' => ['listing_expenses.php', 'expenses.php', 'expense_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('expenses'); }],
                    ['href' => 'listing_purchase_orders.php', 'label' => 'Purchase Orders', 'pages' => ['listing_purchase_orders.php', 'purchase_orders.php', 'purchase_order_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('purchase_orders'); }],
                    ['href' => 'listing_purchases.php', 'label' => 'Purchases', 'pages' => ['listing_purchases.php', 'purchases.php', 'purchase_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('purchases'); }],
                    ['href' => 'listing_payments_made.php', 'label' => 'Payments Made', 'pages' => ['listing_payments_made.php', 'payments_made.php', 'payments_made_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('payments_made'); }],
                    ['href' => 'listing_debit_notes.php', 'label' => 'Debit Notes', 'pages' => ['listing_debit_notes.php', 'debit_notes.php', 'debit_note_overview.php'], 'condition' => function() { return has_full_access() || hasModuleAccess('debit_notes'); }],
                ]
            ],
            [
                'href' => 'reports.php',
                'label' => 'Reports - Accounts',
                'icon' => 'ph-chart-bar',
                'pages' => [
                    'reports.php', 'report_profit_and_loss.php', 'report_balance_sheet.php', 'report_trial_balance.php',
                    'report_general_ledger.php', 'report_detailed_general_ledger.php', 'report_account_type_summary.php',
                    'report_account_transactions.php', 'report_cash_flow_statement.php', 'report_movement_of_equity.php',
                    'report_business_performance_ratios.php', 'report_clients.php', 'report_sales_summary.php',
                    'report_sales_by_customer.php', 'report_sales_by_item.php', 'report_sales_by_sales_person.php',
                    'report_journal_report.php', 'report_invoice_details.php', 'report_invoices.php',
                    'report_recurring_invoice_details.php', 'report_quote_details.php', 'report_reconciliation_status.php',
                    'report_refund_history.php', 'report_expense_details.php', 'report_expenses_by_customer.php',
                    'report_expenses_by_category.php', 'report_ar_aging_summary.php', 'report_ar_aging_details.php',
                    'report_ar_summary.php', 'report_billable_expense_details.php', 'report_customer_balance_summary.php',
                    'report_credit_note_details.php', 'report_payments_received.php', 'report_payable_summary.php',
                    'report_payable_details.php', 'report_receivable_summary.php', 'report_receivable_details.php',
                    'report_time_to_get_paid.php', 'report_vendor_balance_summary.php'
                ],
                'condition' => function() { return has_full_access() || hasModuleAccess('journals'); }
            ],
            [
                'href' => 'listing_journals.php',
                'label' => 'Manual Journal',
                'icon' => 'ph-notebook',
                'pages' => ['listing_journals.php', 'journals.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('journals'); }
            ],
            [
                'href' => 'listing_accounts.php',
                'label' => 'Chart of Accounts',
                'icon' => 'ph-book-open',
                'pages' => ['listing_accounts.php', 'accounts.php', 'listing_accounts_report_categories.php', 'accounts_report_categories.php', 'accounts_report_subcategories.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('accounts'); }
            ],
        ]
    ],
    'crm' => [
        'label' => 'CRM',
        'items' => [
            [
                'href' => 'listing_leads.php',
                'label' => 'Leads',
                'icon' => 'ph-funnel',
                'pages' => ['listing_leads.php', 'leads.php', 'lead.php', 'lead_notes.php', 'lead_attachments.php', 'lead_logs.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('leads'); }
            ],
            [
                'href' => 'listing_lead_quotations.php',
                'label' => 'Lead Quotations',
                'icon' => 'ph-file-text',
                'pages' => ['listing_lead_quotations.php', 'lead_quotations.php', 'lead_quotation.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('lead_quotations'); }
            ],
            [
                'href' => 'listing_projects.php',
                'label' => 'Projects',
                'icon' => 'ph-briefcase',
                'pages' => ['listing_projects.php', 'projects.php', 'view_project.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('projects'); }
            ],
            [
                'href' => 'listing_jobs.php',
                'label' => 'Jobs',
                'icon' => 'ph-wrench',
                'pages' => ['listing_jobs.php', 'jobs.php', 'view_job.php'],
                'condition' => function() { return has_full_access() || hasModuleAccess('jobs'); }
            ],
        ]
    ],
    'content' => [
        'label' => 'Content',
        'items' => [
            // Decommissioned: blogs, guest_posts, blog_categories, pages
            // User Favorites link moved to setup.php
        ]
     ],
];

// ============================================================================
// RENDER SIDEBAR
// ============================================================================
$sectionOrder = ['dashboard', 'crm', 'shipping', 'accounting', 'hr', 'content'];
$sectionSystemMap = [
    'shipping' => 'shipping',
    'accounting' => 'accounting',
    'crm' => 'crm',
    'hr' => 'hr',
];

$sectionModuleMap = [
    'shipping' => ['shipping_advices', 'shipping_invoices', 'shipping_stocks', 'shipping_customers', 'hscodes', 'geo_countries', 'ports', 'carriers', 'consignees', 'shippers'],
    'accounting' => ['items', 'banks', 'customers', 'quotations', 'sale_orders', 'invoices', 'payments_received', 'credit_notes', 'vendors', 'expenses', 'purchase_orders', 'purchases', 'payments_made', 'debit_notes', 'journals', 'accounts'],
    'crm' => ['leads', 'lead_quotations', 'projects', 'jobs', 'job_statuses'],
    'hr' => ['departments', 'designations', 'attendance', 'leave_requests', 'leave_types', 'annual_leave_entitlements', 'payroll_components', 'salary_structures', 'employee_salaries', 'payroll_runs', 'payslips', 'user_documents', 'document_categories', 'air_tickets', 'gratuity_settlements', 'report_hr'],
];

?>

    <div class="sidebar-content">
        <div class="sidebar-section">
            <ul class="nav nav-sidebar" data-nav-type="accordion">
                <?php
                // Render each menu section
                $sectionCount = 0;
                foreach ($sectionOrder as $sectionKey):
                    $requiredSystem = $sectionSystemMap[$sectionKey] ?? null;
                    if ($requiredSystem !== null && function_exists('dashboardHasSystemAccess') && !dashboardHasSystemAccess($requiredSystem)) {
                        continue;
                    }
                    if (!isset($menuConfig[$sectionKey])) {
                        continue;
                    }
                    $section = $menuConfig[$sectionKey];
                    $sectionModules = $sectionModuleMap[$sectionKey] ?? null;
                    if ($sectionModules !== null && !hasAnyModuleAccess($sectionModules)) {
                        continue;
                    }
                    // Operations role (4) — only dashboard, CRM and Shipping
                    if ((int)(\App\Core\Session::roleId() ?? 0) === 4 && !in_array($sectionKey, ['dashboard', 'crm', 'shipping'], true)) {
                        continue;
                    }
                    $visibleItems = array_filter($section['items'], function($item) {
                        return call_user_func($item['condition']);
                    });
                    if (empty($visibleItems)) {
                        continue;
                    }
                    if (!empty($section['label'])) {
                        renderSectionHeader($section['label'], $sectionCount > 0);
                    }
                    $sectionCount++;
                    foreach ($visibleItems as $item):
                        if (!empty($item['type']) && $item['type'] === 'submenu'):
                            $isOpen = isMenuActive($item['pages']);
                            echo '<li class="nav-item nav-item-submenu ' . ($isOpen ? 'nav-item-open' : '') . '">';
                            echo '<a href="#" class="nav-link">';
                            echo '<i class="' . $item['icon'] . '"></i>';
                            echo '<span>' . $item['label'] . '</span>';
                            echo '</a>';
                            echo '<ul class="nav-group-sub collapse ' . ($isOpen ? 'show' : '') . '">';
                            // No submenu for Companies; direct link only
                            if ($sectionKey === 'admin' && $item['label'] === 'Geo Locations'):
                                echo '<li class="nav-item"><a href="listing_geo_countries.php" class="nav-link ' . ($current_page === 'listing_geo_countries.php' ? 'active' : '') . '"><i class="ph-globe"></i><span>Countries</span></a></li>';
                                echo '<li class="nav-item"><a href="listing_geo_states.php" class="nav-link ' . ($current_page === 'listing_geo_states.php' ? 'active' : '') . '"><i class="ph-map-pin"></i><span>States</span></a></li>';
                                echo '<li class="nav-item"><a href="listing_geo_cities.php" class="nav-link ' . ($current_page === 'listing_geo_cities.php' ? 'active' : '') . '"><i class="ph-map-trifold"></i><span>Cities</span></a></li>';
                            endif;
                            if ($sectionKey === 'crm' && $item['label'] === 'Invoices'):
                                // Invoices submenu removed; invoices is now a direct link.
                            endif;
                            if (!empty($item['children']) && is_array($item['children'])):
                                foreach ($item['children'] as $child):
                                    $childVisible = !isset($child['condition']) || call_user_func($child['condition']);
                                    if (!$childVisible) {
                                        continue;
                                    }
                                    $childPages = $child['pages'] ?? [$child['href']];
                                    echo '<li class="nav-item"><a href="' . $child['href'] . '" class="nav-link ' . (isMenuActive($childPages) ? 'active' : '') . '"><i class="ph-caret-right"></i><span>' . $child['label'] . '</span></a></li>';
                                endforeach;
                            endif;
                            echo '</ul></li>';
                        else:
                            renderMenuItem($item['href'], $item['label'], $item['icon'], $item['pages']);
                        endif;
                    endforeach;
                endforeach;
                ?>
                <!-- Footer section -->
                <li class="nav-item-divider" style="margin: 16px 0 12px;"></li>
            </ul>
        </div>
    </div>

</aside>

<style>
/* Sidebar Professional Styling */
.nav-item-header {
    font-weight: 700;
    letter-spacing: 0.8px;
    font-size: 11px;
    margin-top: 4px;
}

.nav-item-header .text-uppercase {
    color: #6c757d;
    font-weight: 700;
}

.nav-item-divider {
    margin: 12px 0;
    opacity: 0.15;
}

.sidebar-main .nav-sidebar > .nav-item > .nav-link {
    transition: all 0.2s ease;
}

.sidebar-main .nav-sidebar > .nav-item > .nav-link:hover {
    background-color: rgba(103, 126, 234, 0.08);
    transform: translateX(2px);
}

.sidebar-main .nav-sidebar > .nav-item > .nav-link.active {
    background: linear-gradient(135deg, rgba(103, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
    border-left: 3px solid #667eea;
    font-weight: 600;
}

.sidebar-main .nav-sidebar > .nav-item > .nav-link i {
    font-size: 18px;
    width: 20px;
    text-align: center;
}

/* Submenu Styling */
.nav-group-sub .nav-link {
    padding-left: 3rem;
    font-size: 13px;
}

.nav-group-sub .nav-link:hover {
    background-color: rgba(103, 126, 234, 0.06);
}

.nav-group-sub .nav-link.active {
    color: #667eea;
    font-weight: 600;
    background-color: rgba(103, 126, 234, 0.1);
}

/* Icon colors for different sections */
.nav-item-header + .nav-item:nth-of-type(1) .nav-link i,
.nav-item-header + .nav-item-submenu .nav-link i {
    color: #667eea;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Ensure submenu links always navigate even if another listener blocks nav-link clicks.
    document.querySelectorAll('.nav-sidebar .nav-group-sub .nav-link[href]').forEach(function (link) {
        link.addEventListener('click', function (event) {
            var href = this.getAttribute('href');

            if (!href || href === '#' || this.classList.contains('disabled')) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            window.location.href = href;
        });
    });
});
</script>

