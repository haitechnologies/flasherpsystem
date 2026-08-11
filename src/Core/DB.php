<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Database Table Registry
 *
 * Central location for all database table names with organized categories.
 * Provides IDE autocomplete, type safety, and eliminates runtime database queries.
 *
 * ALIAS MAP — Constants that share the same physical table:
 * ┌──────────────────────────────┬──────────────────────────┬────────────────────────────┐
 * │ Constants                    │ Physical Table           │ Notes                      │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ RATE_LIMIT_ATTEMPTS,         │ erp_rate_limits          │ Single rate-limit table     │
 * │ RATE_LIMIT_PUBLIC            │                          │                            │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ CUSTOMER_CONTACTS,           │ erp_contacts             │ Polymorphic: entity_type    │
 * │ VENDOR_CONTACTS              │                          │ column distinguishes owner  │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ CUSTOMER_ADDRESSES,          │ erp_addresses            │ Polymorphic: entity_type    │
 * │ VENDOR_ADDRESSES             │                          │ column distinguishes owner  │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ ATTACHMENTS, USER_DOCUMENTS, │ erp_attachments          │ Polymorphic: entity_type    │
 * │ LEAD_ATTACHMENTS,            │                          │ column distinguishes owner  │
 * │ VENDOR_ATTACHMENTS           │                          │                            │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ CATEGORY_HS_CODES,           │ erp_hs_code_mappings     │ Junction: category<->hscode │
 * │ SUBCATEGORY_HS_CODES         │                          │                            │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ DEPARTMENTS, DEPARTMENT      │ erp_departments          │ Singular alias for compat   │
 * ├──────────────────────────────┼──────────────────────────┼────────────────────────────┤
 * │ WAREHOUSES, ORGANIZATIONS    │ erp_organizations        │ Warehouses ARE organizations│
 * └──────────────────────────────┴──────────────────────────┴────────────────────────────┘
 *
 * @package App\Core
 */
class DB
{
    /**
     * Table prefix for dashboard system
     */
    private const PREFIX = 'erp_';

    // ================================
    // USER & AUTHENTICATION TABLES
    // ================================

    /** System users table */
    public const USERS = self::PREFIX . 'users';

    /** User roles table */
    public const ROLES = self::PREFIX . 'roles';

    /** Authentication activity tracking table */
    public const AUTHENTICATION_ACTIVITY = self::PREFIX . 'authentication_activity';

    /** Rate limiting attempts tracking table */
    public const RATE_LIMIT_ATTEMPTS = self::PREFIX . 'rate_limits';

    /** Rate limiting public attempts tracking table */
    public const RATE_LIMIT_PUBLIC = self::PREFIX . 'rate_limits';

    /** System permissions table */
    public const PERMISSIONS = self::PREFIX . 'permissions';

    /** Module-specific permissions table */
    public const MODULE_PERMISSIONS = self::PREFIX . 'module_permissions';

    // ================================
    // FRONTEND TABLES
    // ================================

    /** @deprecated Table dropped — frontend users moved to erp_users */
    public const FRONTEND_USERS = self::PREFIX . 'frontend_users';

    /** @deprecated Table dropped — favorites removed */
    public const FRONTEND_USER_FAVORITES = self::PREFIX . 'frontend_user_favorites';

    // ================================
    // HR & PAYROLL TABLES
    // ================================

    /** Department master data */
    public const DEPARTMENTS = self::PREFIX . 'departments';
    /** @alias DEPARTMENTS */
    public const DEPARTMENT = self::PREFIX . 'departments';

    /** Employee designations */
    public const DESIGNATIONS = self::PREFIX . 'designations';

    /** @deprecated Merged into erp_users — use DB::USERS with employee_code, designation_id, join_date, employment_status */
    public const HR_EMPLOYEES = self::PREFIX . 'hr_employees';

    /** HR leave balance snapshots */
    public const HR_LEAVE_BALANCES = self::PREFIX . 'hr_leave_balances';

    /** User document records */
    public const USER_DOCUMENTS = self::PREFIX . 'attachments';

    /** Attendance records */
    public const ATTENDANCE = self::PREFIX . 'attendance';

    /** Leave request transactions */
    public const LEAVE_REQUESTS = self::PREFIX . 'leave_requests';

    /** Leave type definitions */
    public const LEAVE_TYPES = self::PREFIX . 'leave_types';

    /** Payroll component setup */
    public const PAYROLL_COMPONENTS = self::PREFIX . 'payroll_components';

    /** Salary structure setup */
    public const SALARY_STRUCTURES = self::PREFIX . 'salary_structures';

    /** Employee salary assignments */
    public const EMPLOYEE_SALARIES = self::PREFIX . 'employee_salaries';

    /** Payroll run headers */
    public const PAYROLL_RUNS = self::PREFIX . 'payroll_runs';

    /** Generated payslips */
    public const PAYSLIPS = self::PREFIX . 'payslips';

    /** Air ticket entitlements */
    public const AIR_TICKETS = self::PREFIX . 'air_tickets';

    /** Gratuity settlements */
    public const GRATUITY_SETTLEMENTS = self::PREFIX . 'gratuity_settlements';

    /** Annual leave entitlement records */
    public const ANNUAL_LEAVE_ENTITLEMENTS = self::PREFIX . 'annual_leave_entitlements';

    /** HR to-do tasks */
    public const HR_TODO_TASKS = self::PREFIX . 'hr_todo_tasks';

    /** Attendance device configurations */
    public const ATTENDANCE_DEVICES = self::PREFIX . 'attendance_devices';

    /** Raw attendance punch logs from devices */
    public const ATTENDANCE_PUNCHES = self::PREFIX . 'attendance_punches';

    /** Payroll run line items */
    public const HR_PAYROLL_RUN_ITEMS = self::PREFIX . 'hr_payroll_run_items';

    /** Payroll component-to-account mappings */
    public const HR_PAYROLL_COMPONENT_ACCOUNTS = self::PREFIX . 'hr_payroll_component_accounts';

    // ================================
    // SYSTEM & CONFIGURATION TABLES
    // ================================

    /** System settings table */
    public const SYSTEM_SETTINGS = self::PREFIX . 'system_settings';

    /** System modules table */
    public const MODULES = self::PREFIX . 'modules';

    /** @deprecated Merged into BACKEND_ERROR_LOGS — kept for backward compat */
    public const ERROR_LOG_STATUS = self::PREFIX . 'error_log_status';

    /** Canonical backend error events sink */
    public const BACKEND_ERROR_LOGS = self::PREFIX . 'backend_error_logs';

    /** Backend page/module logging coverage manifest */
    public const BACKEND_LOG_COVERAGE = self::PREFIX . 'backend_log_coverage';

    /** Email service providers configuration */
    public const EMAIL_PROVIDERS = self::PREFIX . 'email_providers';

    // ================================
    // EMAIL MARKETING TABLES
    // ================================

    /** Email sending history */
    public const EMAIL_HISTORY = self::PREFIX . 'email_history';

    /** Email send queue */
    public const EMAIL_QUEUE = self::PREFIX . 'email_queue';

    // ================================
    // CRM - CUSTOMER MANAGEMENT TABLES
    // ================================

    /** Customers master table */
    public const CUSTOMERS = self::PREFIX . 'customers';

    /** Customer contact persons */
    public const CUSTOMER_CONTACTS = self::PREFIX . 'contacts';

    /** Customer addresses (delivery, billing, etc.) */
    public const CUSTOMER_ADDRESSES = self::PREFIX . 'addresses';

    /** Customer transaction records (ledger entries) */
    public const CUSTOMER_TRANSACTIONS = self::PREFIX . 'customer_transactions';

    /** Unified entity activity logs (leads + customers) */
    public const ENTITY_LOGS = self::PREFIX . 'entity_logs';

    /** Unified entity notes (leads + customers) */
    public const ENTITY_NOTES = self::PREFIX . 'entity_notes';

    // ================================
    // SALES & INVOICING TABLES
    // ================================

    /** Sales invoices table */
    public const INVOICES = self::PREFIX . 'invoices';

    /** Invoice line items */
    public const INVOICE_ITEMS = self::PREFIX . 'invoice_items';

    // ================================
    // PAYMENTS & FINANCIAL TABLES
    // ================================

    /** Payment methods (cash, bank transfer, etc.) */
    public const PAYMENT_METHODS = self::PREFIX . 'payment_methods';

    // ================================
    // SUBSCRIPTION & MONETIZATION TABLES
    // ================================

    /** System audit log for tracking user actions and events */
    public const AUDIT_LOG = self::PREFIX . 'audit_log';

    /** Unified attachments and documents table */
    public const ATTACHMENTS = self::PREFIX . 'attachments';

    /** Subscription tier change audit log */
    public const SUBSCRIPTION_LOGS = self::PREFIX . 'subscription_logs';



    /** SaaS subscription plan catalog */
    public const SUBSCRIPTION_PLANS = self::PREFIX . 'subscription_plans';

    /** Main-account SaaS subscriptions */
    public const SUBSCRIPTIONS = self::PREFIX . 'subscriptions';

    /** Per-plan SaaS feature values and system toggles */
    public const SUBSCRIPTION_PLAN_FEATURES = self::PREFIX . 'subscription_plan_features';

    /** Superadmin/account-level SaaS feature overrides */
    public const SUBSCRIPTION_OVERRIDES = self::PREFIX . 'subscription_overrides';

    /** API keys for Pro/Enterprise users */
    public const API_KEYS = self::PREFIX . 'api_keys';

    /** Subscription payment records */
    public const SUBSCRIPTION_PAYMENTS = self::PREFIX . 'subscription_payments';

    // ================================
    // BUSINESS LISTING SUBSCRIPTION TABLES
    // ================================

    /** @deprecated Merged into SUBSCRIPTION_PLANS with plan_type='listing' — table dropped */
    public const LISTING_PLANS = self::PREFIX . 'listing_plans';

    /** @deprecated Merged into SUBSCRIPTIONS — table dropped */
    public const LISTING_SUBSCRIPTIONS = self::PREFIX . 'listing_subscriptions';

    // PUBLIC_ADS removed — decommissioned table

    // ================================
    // GEOGRAPHY & LOCATION TABLES
    // ================================

    /** Countries master data */
    public const GEO_COUNTRIES = self::PREFIX . 'geo_countries';

    /** States/Provinces master data */
    public const GEO_STATES = self::PREFIX . 'geo_states';

    /** Cities master data */
    public const GEO_CITIES = self::PREFIX . 'geo_cities';

    // ================================
    // INVENTORY & ORGANIZATION TABLES
    // ================================

    /** Items/Products master table */
    public const ITEMS = self::PREFIX . 'items';

    /** Organizations/Storage locations */
    public const ORGANIZATIONS = self::PREFIX . 'organizations';

    /** Organization membership records */
    public const ORGANIZATION_MEMBERSHIPS = self::PREFIX . 'organization_memberships';

    /** Organization-scoped role catalog */
    public const ORGANIZATION_ROLES = self::PREFIX . 'organization_roles';

    /** Organization membership to role assignments */
    public const ORGANIZATION_MEMBER_ROLES = self::PREFIX . 'organization_member_roles';

    /** Pending organization invite records */
    public const ORGANIZATION_INVITES = self::PREFIX . 'organization_invites';

    /** Effective organization system entitlements */
    public const ORGANIZATION_SYSTEM_ENTITLEMENTS = self::PREFIX . 'organization_system_entitlements';

    // ================================
    // SHIPPING & LOGISTICS TABLES
    // ================================

    /** @deprecated Table dropped. Merged into CUSTOMERS with entity_type='shipping' */
    // public const SHIPPING_CUSTOMERS = self::PREFIX . 'shipping_customers';

    /** Shipping advice documents */
    public const SHIPPING_ADVICES = self::PREFIX . 'shipping_advices';

    /** Shipping advice line items */
    public const SHIPPING_ADVICE_ITEMS = self::PREFIX . 'shipping_advice_items';

    /** Shipping invoices */
    public const SHIPPING_INVOICES = self::PREFIX . 'shipping_invoices';

    /** Shipping invoice line items */
    public const SHIPPING_INVOICE_ITEMS = self::PREFIX . 'shipping_invoice_items';

    /** Shipping inventory snapshots */
    public const SHIPPING_STOCKS = self::PREFIX . 'shipping_stocks';

    /** Shipping inventory snapshot items */
    public const SHIPPING_STOCK_ITEMS = self::PREFIX . 'shipping_stock_items';

    /** Ports master data */
    public const PORTS = self::PREFIX . 'ports';

    /** Carriers master data */
    public const CARRIERS = self::PREFIX . 'carriers';

    /** Consignees master data */
    public const CONSIGNEES = self::PREFIX . 'consignees';

    /** Shippers master data */
    public const SHIPPERS = self::PREFIX . 'shippers';

    /** CS Agents master data (Movestic cargo agents) */
    /** @deprecated Use DB::DEPARTMENTS instead */
    public const CS_AGENTS = self::PREFIX . 'cs_agents';

    /** Drivers master data */
    /** @deprecated Use DB::USERS with vehicle_id instead */
    public const DRIVERS = self::PREFIX . 'drivers';

    // ================================
    // SETUP & MASTER DATA TABLES
    // ================================

    /** Setup Groups (polymorphic, stored in taxonomies with type='setup_group') */
    public const SETUP_GROUPS = self::TAXONOMIES;
    /** Setup Sources (polymorphic, stored in taxonomies with type='lead_source'|'customer_source') */
    public const SETUP_SOURCES = self::TAXONOMIES;
    /** Setup Statuses (polymorphic, stored in taxonomies with type='lead_status'|'customer_status'|'vendor_status') */
    public const SETUP_STATUSES = self::TAXONOMIES;
    /** Setup Tags (polymorphic, stored in taxonomies with type='lead_tag'|'customer_tag'|'job_tag') */
    public const SETUP_TAGS = self::TAXONOMIES;

    /** Polymorphic taxonomies table for categorization */
    public const TAXONOMIES = self::PREFIX . 'taxonomies';

    /** @deprecated Dummy constant for dynamic reports — no physical table */
    public const BALANCE_SHEET = self::PREFIX . 'balance_sheet_dummy';
    /** @deprecated Dummy constant for dynamic reports — no physical table */
    public const GENERAL_LEDGER = self::PREFIX . 'general_ledger_dummy';
    /** @deprecated Dummy constant for dynamic reports — no physical table */
    public const TRIAL_BALANCE = self::PREFIX . 'trial_balance_dummy';

    /** Content moderation - banned words */
    public const BANNED_WORDS = self::PREFIX . 'banned_words';

    // BLOG_CATEGORIES, BLOGS removed — decommissioned tables

    // PAGES removed — table decommissioned

    // ================================
    // HS CODES (HARMONIZED SYSTEM)
    // ================================

    // HS_CODE_SETS removed — decommissioned tables
    /** @deprecated Table does not exist. HS code texts are embedded in erp_hscodes. */
    public const HS_CODE_TEXTS = self::PREFIX . 'hscodes_texts';

    /** HS Codes master table */
    public const HS_CODES = self::PREFIX . 'hscodes';

    /** HS Code to Category mappings (junction table) */
    public const CATEGORY_HS_CODES = self::PREFIX . 'hs_code_mappings';

    /** HS Code to Subcategory mappings (junction table) */
    public const SUBCATEGORY_HS_CODES = self::PREFIX . 'hs_code_mappings';

    // ================================
    // COMPANY DIRECTORY
    // ================================

    // COMPANIES_DETAILS, COMPANY_SOURCES, COMPANY_ENGAGEMENT removed — decommissioned tables
    /** @deprecated Table dropped in Phase 1 migration. Do not use. */
    public const COMPANIES = self::PREFIX . 'companies';

    /** @deprecated Table dropped. Referral codes decommissioned. */
    public const REFERRAL_CODES = self::PREFIX . 'referral_codes';



    // ================================
    // NEW HIERARCHICAL CATEGORY SYSTEM
    // ================================

    /** Level 1: Main categories (33 total) */
    public const CATEGORIES = self::PREFIX . 'categories';

    /** Level 2: Subcategories under main categories (111 total) */
    public const SUBCATEGORIES = self::PREFIX . 'subcategories';

    /** Category items junction table
     *  @deprecated Table not yet created. Use TAXONOMIES with type mapping. */
    public const CATEGORY_ITEMS = self::PREFIX . 'category_items';

    // IP_COUNTRIES removed — decommissioned table

    // ================================
    // ALERTS & NOTIFICATIONS
    // ================================

    /** System alerts and notifications */
    public const ALERTS = self::PREFIX . 'alerts';

    /** User/system notifications */
    public const NOTIFICATIONS = self::PREFIX . 'notifications';

    // ================================
    // SEARCH & ANALYTICS TABLES
    // ================================

    // SEARCHES removed — decommissioned table

    /** Contact form inquiries */
    public const INQUIRIES = self::PREFIX . 'inquiries';

    /** Email thread replies for inquiries */
    public const INQUIRY_REPLIES = self::PREFIX . 'inquiry_replies';

    /** Disposable and temporary email domains */
    public const DISPOSABLE_EMAIL_DOMAINS = self::PREFIX . 'disposable_email_domains';



    // ================================
    // ACCOUNTING - CHART OF ACCOUNTS
    // ================================

    /** Chart of accounts (ledger accounts) */
    public const ACCOUNTS = self::PREFIX . 'accounts';

    /** Account report categories (P&L, Balance Sheet groupings) */
    public const ACCOUNTS_REPORT_CATEGORIES = self::PREFIX . 'accounts_report_categories';

    /** Account report subcategories */
    public const ACCOUNTS_REPORT_SUBCATEGORIES = self::PREFIX . 'accounts_report_subcategories';

    /** Accounting dimension items (cost centers, projects, etc.) */
    public const DIMENSION_ITEMS = self::PREFIX . 'dimension_items';

    // ================================
    // ACCOUNTING - JOURNALS
    // ================================

    /** Journal entry headers */
    public const JOURNALS = self::PREFIX . 'journals';

    /** Journal entry line items */
    public const JOURNAL_ITEMS = self::PREFIX . 'journal_items';

    // ================================
    // ACCOUNTING - SALES TRANSACTIONS
    // ================================

    /** Sales quotations */
    public const QUOTATIONS = self::PREFIX . 'quotations';

    /** Quotation line items */
    public const QUOTATION_ITEMS = self::PREFIX . 'quotation_items';

    /** Sale orders */
    public const SALE_ORDERS = self::PREFIX . 'sale_orders';

    /** Sale order line items */
    public const SALE_ORDER_ITEMS = self::PREFIX . 'sale_order_items';

    /** Unified document type definitions (sale + purchase) */
    public const DOCUMENT_TYPES = self::PREFIX . 'document_types';

    /** @deprecated Use DOCUMENT_TYPES with context='sale' — table dropped */
    public const SALE_TYPES = self::PREFIX . 'sale_types';

    /** Payments received from customers */
    public const PAYMENTS_RECEIVED = self::PREFIX . 'payments_received';

    /** Payment received line items */
    public const PAYMENT_RECEIVED_ITEMS = self::PREFIX . 'payment_received_items';

    /** Credit note headers */
    public const CREDIT_NOTES = self::PREFIX . 'credit_notes';

    /** Credit note line items */
    public const CREDIT_NOTE_ITEMS = self::PREFIX . 'credit_note_items';

    // ================================
    // ACCOUNTING - PURCHASE TRANSACTIONS
    // ================================

    /** Vendors/Suppliers master table */
    public const VENDORS = self::PREFIX . 'vendors';

    /** Vendor contact persons */
    public const VENDOR_CONTACTS = self::PREFIX . 'contacts';

    /** Vendor addresses */
    public const VENDOR_ADDRESSES = self::PREFIX . 'addresses';

    /** Vendor attachments */
    public const VENDOR_ATTACHMENTS = self::PREFIX . 'attachments';

    /** Vendor transactions (purchase-side) */
    public const VENDOR_TRANSACTIONS = self::PREFIX . 'vendor_transactions';

    /** Purchase transactions */
    public const PURCHASES = self::PREFIX . 'purchases';

    /** Purchase line items */
    public const PURCHASE_ITEMS = self::PREFIX . 'purchase_items';

    /** Purchase orders */
    public const PURCHASE_ORDERS = self::PREFIX . 'purchase_orders';

    /** Purchase order line items */
    public const PURCHASE_ORDER_ITEMS = self::PREFIX . 'purchase_order_items';

    /** @deprecated Use DOCUMENT_TYPES with context='purchase' — table dropped */
    public const PURCHASE_TYPES = self::PREFIX . 'purchase_types';

    /** Payments made to vendors */
    public const PAYMENTS_MADE = self::PREFIX . 'payments_made';

    /** Payment made line items */
    public const PAYMENT_MADE_ITEMS = self::PREFIX . 'payment_made_items';

    /** Debit note headers */
    public const DEBIT_NOTES = self::PREFIX . 'debit_notes';

    /** Debit note line items */
    public const DEBIT_NOTE_ITEMS = self::PREFIX . 'debit_note_items';

    // ================================
    // ACCOUNTING - EXPENSES
    // ================================

    /** Expense transactions */
    public const EXPENSES = self::PREFIX . 'expenses';

    /** Expense line items */
    public const EXPENSE_ITEMS = self::PREFIX . 'expense_items';

    /** Expense attachments (receipts) */
    public const EXPENSE_ATTACHMENTS = self::PREFIX . 'expense_attachments';

    // ================================
    // ACCOUNTING - BANKING & FINANCE SETUP
    // ================================

    /** Bank accounts master data */
    public const BANKS = self::PREFIX . 'banks';

    /** Tax treatment definitions */
    public const TAX_TREATMENTS = self::PREFIX . 'tax_treatments';

    /** Payment terms (Net 30, etc.) */
    public const PAYMENT_TERMS = self::PREFIX . 'payment_terms';

    /** Currency definitions */
    public const CURRENCIES = self::PREFIX . 'currencies';

    // ================================
    // CRM - LEADS
    // ================================

    /** Leads/prospects master table */
    public const LEADS = self::PREFIX . 'leads';

    /** Lead attachments */
    public const LEAD_ATTACHMENTS = self::PREFIX . 'attachments';

    // ================================
    // CRM - PROJECTS & JOBS
    // ================================

    /** Projects master table */
    public const PROJECTS = self::PREFIX . 'projects';

    /** Jobs / service orders */
    public const JOBS = self::PREFIX . 'jobs';

    /** Job line items */
    public const JOB_ITEMS = self::PREFIX . 'job_items';

    /** Job status definitions */
    public const JOB_STATUSES = self::PREFIX . 'job_statuses';

    /** Task/work items linked to jobs/projects */
    public const TASKS = self::PREFIX . 'tasks';

    // ================================
    // OPERATIONAL SETUP
    // ================================

    /** Incoterms (EXW, FOB, CIF, etc.) */
    public const INCOTERMS = self::PREFIX . 'incoterms';

    /** Exit points / customs checkpoints */
    public const EXIT_POINTS = self::PREFIX . 'exit_points';

    /** Container type definitions
     *  @deprecated Table not yet created. Use INCOTERMS or create migration. */
    public const CONTAINER_TYPES = self::PREFIX . 'container_types';

    /** Commodity type definitions
     *  @deprecated Table not yet created. Use TAXONOMIES or create migration. */
    public const COMMODITY_TYPES = self::PREFIX . 'commodity_types';

    /** Industry classifications */
    public const INDUSTRIES = self::PREFIX . 'industries';

    /**
     * Timezone reference data.
     * @deprecated Table dropped; use config/timezones.php array instead.
     */
    public const TIMEZONES = self::PREFIX . 'timezones';

    /**
     * System/feature registry.
     * @deprecated Table merged into erp_modules (P9). Use MODULES with module_type='system'.
     */
    public const SYSTEMS = self::PREFIX . 'systems';

    // ================================
    // WAREHOUSE & STORAGE SETUP
    // ================================

    /** Warehouses master data (aliased to organizations) */
    public const WAREHOUSES = self::PREFIX . 'organizations';

    /** Storage type definitions */
    public const STORAGE_TYPES = self::PREFIX . 'storage_types';

    /** @deprecated Table dropped. Merged into STORAGE_TYPES with parent_id */
    // public const STORAGE_SUBTYPES = self::PREFIX . 'storage_subtypes';

    // ================================
    // PRODUCT / SERVICE SETUP
    // ================================

    /** Services master table
     *  @deprecated Table not yet created. Use ITEMS with type='service'. */
    public const SERVICES = self::PREFIX . 'services';

    /** Units of measure */
    public const UNITS = self::PREFIX . 'units';

    // ================================
    // SETUP GROUPS & DOCUMENT MANAGEMENT
    // ================================

    // SETUP_GROUPS removed — consolidated into TAXONOMIES

    /** Document categories */
    public const DOCUMENT_CATEGORIES = self::PREFIX . 'document_categories';

    // ================================
    // UTILITY METHODS
    // ================================

    /**
     * Get table name with dashboard prefix
     *
     * @param string $tableName Table name without prefix
     * @return string Full table name with erp_ prefix
     */
    public static function table(string $tableName): string
    {
        return self::getPrefix() . $tableName;
    }

    /**
     * Get all dashboard table constants as array
     *
     * @return array Array of all erp_ prefixed table names
     */
    public static function getAllTables(): array
    {
        $reflection = new \ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();

        // Filter only erp_ tables (exclude legacy and prefix constants)
        return array_filter($constants, function ($value) {
            return is_string($value) && strpos($value, self::PREFIX) === 0;
        });
    }

    /**
     * Check if table constant exists
     *
     * @param string $constantName Constant name (e.g., 'USERS')
     * @return bool True if constant exists
     */
    public static function hasTable(string $constantName): bool
    {
        $reflection = new \ReflectionClass(__CLASS__);
        return $reflection->hasConstant($constantName);
    }

    /**
     * Get dashboard table prefix
     *
     * @return string Returns 'erp_'
     */
    public static function getPrefix(): string
    {
        return $_ENV['DB_PREFIX'] ?? $GLOBALS['TBL']['PREFIX'] ?? self::PREFIX;
    }

    /**
     * Get constant name by table name (reverse lookup)
     *
     * @param string $tableName Full table name (e.g., 'erp_users')
     * @return string|null Constant name (e.g., 'USERS') or null if not found
     */
    public static function getConstantName(string $tableName): ?string
    {
        $reflection = new \ReflectionClass(__CLASS__);
        $constants = $reflection->getConstants();

        $key = array_search($tableName, $constants, true);
        return $key !== false ? $key : null;
    }

    /**
     * Retrieve the global MySQLi connection.
     *
     * @deprecated Use DB::pdo() or DB::conn() for new code. This method exists
     *             only for legacy dashboard pages that still use mysqli directly.
     *
     * @return \mysqli
     */
    public static function mysqli(): \mysqli
    {
        global $mysqli;
        if (!$mysqli instanceof \mysqli) {
            $mysqli = $GLOBALS['DB']['MSQLI'] ?? null;
        }
        if (!$mysqli instanceof \mysqli) {
            throw new \RuntimeException("MySQLi connection is not initialized.");
        }
        return $mysqli;
    }

    /**
     * Retrieve the Database PDO wrapper instance.
     *
     * @return Database
     */
    public static function pdo(): Database
    {
        $container = Container::getInstance();
        if (!$container->has(Database::class)) {
            $container->register(Database::class, function () {
                return new Database();
            });
        }
        return $container->get(Database::class);
    }

    /**
     * Retrieve the active PDO connection instance from the Database wrapper.
     *
     * @return \PDO
     */
    public static function conn(): \PDO
    {
        return self::pdo()->getConnection();
    }
}
