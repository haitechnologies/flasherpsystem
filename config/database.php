<?php

// Load environment variables from .env file
// Check multiple locations for .env file (in order of priority):
// 1. Parent directory: G:\xampp\.env (recommended for security)
// 2. Project root: G:\xampp\htdocs\flasherpsystem\.env (current location, less secure)

require_once __DIR__ . '/../vendor/autoload.php';

$envCandidates = [
	dirname(__DIR__, 1), // e.g., G:\xampp\htdocs\flasherpsystem (project root – checked first)
	dirname(__DIR__, 2), // e.g., G:\xampp\htdocs
	dirname(__DIR__, 3), // e.g., G:\xampp (last resort)
];

$envLoaded = false;
foreach ($envCandidates as $candidateDir) {
	if (is_file($candidateDir . '/.env')) {
		$dotenv = Dotenv\Dotenv::createImmutable($candidateDir);
		$dotenv->safeLoad();
		$envLoaded = true;
		break;
	}
}

// Backward compatibility fallback: don't hard-fail if .env is missing.
if (!$envLoaded) {
	$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__, 1));
	$dotenv->safeLoad();
}

require_once __DIR__ . '/constants.php';

// ============================================
// DATABASE CONFIGURATION
// ============================================
if (!function_exists('isRemote')) {
    function isRemote() {
        if (PHP_SAPI === 'cli') {
            $env = strtolower((string)($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: ''));
            if ($env === 'production' || $env === 'live') {
                return true;
            }
            if ($env === 'development' || $env === 'local') {
                return false;
            }
            $hostname = (string)gethostname();
            foreach (['local', 'desktop', 'laptop'] as $keyword) {
                if (str_contains($hostname, $keyword)) {
                    return false;
                }
            }
            return true;
        }
		if (preg_match('/flasherpsystem.local/', $_SERVER['HTTP_HOST'] ?? '') || 
            preg_match('/localhost/', $_SERVER['HTTP_HOST'] ?? '') || 
            preg_match('/127.0.0.1/', $_SERVER['HTTP_HOST'] ?? '')) {
            return false;
        }
        return true;
    }
}

// Load database configuration based on environment
$getEnv = static function ($key, $default = '') {
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return $value;
};

$app_env = strtolower((string)$getEnv('APP_ENV', ''));

$localDb = [
    'hostname' => $getEnv('DB_HOSTNAME', 'localhost'),
    'username' => $getEnv('DB_USERNAME', ''),
    'password' => $getEnv('DB_PASSWORD', ''),
    'database' => $getEnv('DB_DATABASE', ''),
];

$remoteDb = [
    'hostname' => $getEnv('REMOTE_DB_HOSTNAME', $localDb['hostname']),
    'username' => $getEnv('REMOTE_DB_USERNAME', $localDb['username']),
    'password' => $getEnv('REMOTE_DB_PASSWORD', $localDb['password']),
    'database' => $getEnv('REMOTE_DB_DATABASE', $localDb['database']),
];

if ($app_env === 'development') {
    $dbConfig = $localDb;
} else {
    $dbConfig = isRemote() ? $remoteDb : $localDb;
}

// Set global database config
$GLOBALS['DB'] = [
	'HOSTNAME' => $dbConfig['hostname'],
	'DATABASE' => $dbConfig['database'],
	'USERNAME' => $dbConfig['username'],
	'PASSWORD' => $dbConfig['password'],
];

// Establish database connection with short retry for transient saturation.
$maxConnectRetries = max(1, (int)$getEnv('DB_CONNECT_RETRIES', 2));
$connectRetryDelayMs = max(50, (int)$getEnv('DB_CONNECT_RETRY_DELAY_MS', 250));
$mysqli = null;
$lastConnectError = '';
$lastConnectErrno = 0;

for ($attempt = 1; $attempt <= $maxConnectRetries; $attempt++) {
	try {
		$mysqli = new \App\Core\DynamicPrefixMysqli(
			$dbConfig['hostname'],
			$dbConfig['username'],
			$dbConfig['password'],
			$dbConfig['database']
		);

		if (!$mysqli->connect_error) {
			break;
		}

		$lastConnectErrno = (int)$mysqli->connect_errno;
		$lastConnectError = (string)$mysqli->connect_error;
	} catch (mysqli_sql_exception $e) {
		$lastConnectErrno = (int)$e->getCode();
		$lastConnectError = (string)$e->getMessage();
	}

	if ($attempt < $maxConnectRetries) {
		usleep($connectRetryDelayMs * 1000);
	}
}

if (!($mysqli instanceof mysqli) || $mysqli->connect_error) {
	if (isRemote()) {
		http_response_code(503);
		header('Retry-After: 5');
		error_log('Database Connection Error (' . $lastConnectErrno . '): ' . $lastConnectError);
		exit('Service temporarily unavailable. Please retry shortly.');
	}

	die('Database Connection Error (' . $lastConnectErrno . '): ' . $lastConnectError);
}

// Note: Error logging is handled separately for frontend and dashboard:
// - Frontend: Uses FrontendErrorLogger (initialized in config/logging.php)
// - Dashboard: Uses dashboard ErrorLogger (required in admin_elements/admin_header.php)
// Do NOT require dashboard error logger here as it would override frontend error handling!

// --------------------------
// --------------------------
// SET TO utf8mb4 Encoding (Full Unicode + Emoji Support)
// --------------------------
$mysqli->set_charset("utf8mb4");
// --------------------------
// --------------------------


$GLOBALS['DB']['MSQLI']      = $mysqli;
$tbl_prefix                  = $_ENV['DB_PREFIX'] ?? $getEnv('DB_PREFIX', 'erp_');
$GLOBALS['TBL']['PREFIX']    = $tbl_prefix;
$project_pre = PROJECT_PREFIX; 			// Unique Session_id Name
$GLOBALS['project_pre']      = $project_pre;    	// fp__($tbl_name, $id) globals.php

// Create $conn alias for backward compatibility with frontend pages
$conn = $mysqli;

// Load global helper functions after DB is ready
require_once __DIR__ . '/globals.php';

// Initialize database schema (create missing tables if needed)
$autoInitSchemaEnv = strtolower((string)$getEnv('DB_AUTO_INIT_SCHEMA', isRemote() ? 'false' : 'true'));
if ($autoInitSchemaEnv === 'true' || $autoInitSchemaEnv === '1' || $autoInitSchemaEnv === 'yes') {
	\App\Core\DatabaseSchemaInitializer::init($mysqli);
}

// Ensure connections are explicitly released even on abrupt request endings.
register_shutdown_function(static function () use ($mysqli) {
	if ($mysqli instanceof mysqli) {
		try {
			$mysqli->close();
		} catch (Throwable $e) {
			// Connection may already be closed by request flow; ignore safely.
		}
	}
});


/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/


/*
|--------------------------------------------------------------------------
| 	Display All Errors
|--------------------------------------------------------------------------
|
*/

error_reporting(E_ALL);
ini_set('display_errors', $app_env === 'development' ? '1' : '0');


/*
|--------------------------------------------------------------------------
| 	SET SERVER TIMEZONE
|--------------------------------------------------------------------------
|
*/

$appTimezone = (string)$getEnv('APP_TIMEZONE', 'Asia/Dubai');
if (!date_default_timezone_set($appTimezone)) {
    date_default_timezone_set('Asia/Dubai');
}

$dbTimezone = (string)$getEnv('DB_TIMEZONE', '+00:00');
$mysqli->query("SET time_zone = '" . $mysqli->real_escape_string($dbTimezone) . "'");

/*
|--------------------------------------------------------------------------
| 	add WWW. TO http:// -> https
|--------------------------------------------------------------------------
|
*/
// if ( isRemote() ){
// 	if ( !preg_match ('/www./', $_SERVER['HTTP_HOST']) || empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off" ) {
// 		header('Location:https://www.' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . $_SERVER['REQUEST_URI']);
// 		exit();
// 	}
// // local move to ssl
// } else {
// if ( empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == "off" ) {
// 	header('Location:https://'.  $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
// 	exit();
// }
// }

/*
|--------------------------------------------------------------------------
| 	FORCE SSL -> https
|--------------------------------------------------------------------------
|
*/

if (!empty($_ENV['FORCE_HTTPS']) && $_ENV['FORCE_HTTPS'] === 'true' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
	header('Location:https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
	exit();
}


/*
|--------------------------------------------------------------------------
| 	GLOBAL SITE URL
|--------------------------------------------------------------------------
|
*/
// Note: $base_url is defined in config/globals.php, not loaded here yet
// $GLOBALS['SETTINGS']['SITE_URL']	= $base_url;


/*
|--------------------------------------------------------------------------
| 	LOAD DATABASE TABLE REGISTRY CLASS
/*
|--------------------------------------------------------------------------
| Load the modern DB class which provides IDE autocomplete and type safety
| for all database table names without requiring database queries
*/


/*
|--------------------------------------------------------------------------
| 	LOAD ROLES CONSTANTS CLASS
|--------------------------------------------------------------------------
| Load the Roles class which provides centralized role management
| and eliminates hardcoded role IDs throughout the application
*/


/*
|--------------------------------------------------------------------------
| 	LOAD UAE GEOGRAPHIC DATA CONSTANTS
|--------------------------------------------------------------------------
| Load UAE country and states data as constants instead of database tables
| This replaces geo_countries and geo_states tables with hardcoded data
*/

require_once __DIR__ . '/uae_geo_constants.php';

/*
|--------------------------------------------------------------------------
| 	BACKWARD COMPATIBILITY CONSTANTS
|--------------------------------------------------------------------------
| Define old tbl_ constants for backward compatibility with existing code.
| These are mapped to the modern DB class constants.
*/
define('tbl_users', \App\Core\DB::USERS);
define('tbl_roles', \App\Core\DB::ROLES);
define('tbl_departments', \App\Core\DB::DEPARTMENTS);
define('tbl_designations', \App\Core\DB::DESIGNATIONS);
define('tbl_attachments', \App\Core\DB::ATTACHMENTS);
define('tbl_attendance', \App\Core\DB::ATTENDANCE);
define('tbl_leave_requests', \App\Core\DB::LEAVE_REQUESTS);
define('tbl_leave_types', \App\Core\DB::LEAVE_TYPES);
define('tbl_payroll_components', \App\Core\DB::PAYROLL_COMPONENTS);
define('tbl_salary_structures', \App\Core\DB::SALARY_STRUCTURES);
define('tbl_employee_salaries', \App\Core\DB::EMPLOYEE_SALARIES);
define('tbl_payroll_runs', \App\Core\DB::PAYROLL_RUNS);
define('tbl_payslips', \App\Core\DB::PAYSLIPS);
define('tbl_system_settings', \App\Core\DB::SYSTEM_SETTINGS);
define('tbl_modules', \App\Core\DB::MODULES);
define('tbl_backend_error_logs', \App\Core\DB::BACKEND_ERROR_LOGS);
define('tbl_backend_log_coverage', \App\Core\DB::BACKEND_LOG_COVERAGE);
define('tbl_email_providers', \App\Core\DB::EMAIL_PROVIDERS);
define('tbl_email_history', \App\Core\DB::EMAIL_HISTORY);
define('tbl_email_queue', \App\Core\DB::EMAIL_QUEUE);
define('tbl_customers', \App\Core\DB::CUSTOMERS);
define('tbl_contacts', \App\Core\DB::CUSTOMER_CONTACTS);
define('tbl_addresses', \App\Core\DB::CUSTOMER_ADDRESSES);
define('tbl_entity_logs', \App\Core\DB::ENTITY_LOGS);
define('tbl_entity_notes', \App\Core\DB::ENTITY_NOTES);
define('tbl_invoices', \App\Core\DB::INVOICES);
define('tbl_invoice_items', \App\Core\DB::INVOICE_ITEMS);
define('tbl_payment_methods', \App\Core\DB::PAYMENT_METHODS);
define('tbl_audit_log', \App\Core\DB::AUDIT_LOG);
define('tbl_subscription_logs', \App\Core\DB::SUBSCRIPTION_LOGS);
define('tbl_subscription_plans', \App\Core\DB::SUBSCRIPTION_PLANS);
define('tbl_subscriptions', \App\Core\DB::SUBSCRIPTIONS);
define('tbl_subscription_plan_features', \App\Core\DB::SUBSCRIPTION_PLAN_FEATURES);
define('tbl_subscription_overrides', \App\Core\DB::SUBSCRIPTION_OVERRIDES);
define('tbl_api_keys', \App\Core\DB::API_KEYS);
define('tbl_geo_countries', \App\Core\DB::GEO_COUNTRIES);
define('tbl_geo_states', \App\Core\DB::GEO_STATES);
define('tbl_geo_cities', \App\Core\DB::GEO_CITIES);
define('tbl_items', \App\Core\DB::ITEMS);
define('tbl_organizations', \App\Core\DB::ORGANIZATIONS);
define('tbl_organization_memberships', \App\Core\DB::ORGANIZATION_MEMBERSHIPS);
define('tbl_organization_roles', \App\Core\DB::ORGANIZATION_ROLES);
define('tbl_organization_member_roles', \App\Core\DB::ORGANIZATION_MEMBER_ROLES);
define('tbl_organization_invites', \App\Core\DB::ORGANIZATION_INVITES);
define('tbl_organization_system_entitlements', \App\Core\DB::ORGANIZATION_SYSTEM_ENTITLEMENTS);
define('tbl_shipping_advices', \App\Core\DB::SHIPPING_ADVICES);
define('tbl_shipping_advice_items', \App\Core\DB::SHIPPING_ADVICE_ITEMS);
define('tbl_shipping_invoices', \App\Core\DB::SHIPPING_INVOICES);
define('tbl_shipping_invoice_items', \App\Core\DB::SHIPPING_INVOICE_ITEMS);
define('tbl_shipping_stocks', \App\Core\DB::SHIPPING_STOCKS);
define('tbl_shipping_stock_items', \App\Core\DB::SHIPPING_STOCK_ITEMS);
define('tbl_ports', \App\Core\DB::PORTS);
define('tbl_carriers', \App\Core\DB::CARRIERS);
define('tbl_consignees', \App\Core\DB::CONSIGNEES);
define('tbl_shippers', \App\Core\DB::SHIPPERS);
define('tbl_taxonomies', \App\Core\DB::TAXONOMIES);
define('tbl_banned_words', \App\Core\DB::BANNED_WORDS);
// define('tbl_pages', \App\Core\DB::PAGES); // decommissioned
define('tbl_hscodes_texts', \App\Core\DB::HS_CODE_TEXTS);
define('tbl_hscodes', \App\Core\DB::HS_CODES);
define('tbl_hs_code_mappings', \App\Core\DB::CATEGORY_HS_CODES);
define('tbl_companies', \App\Core\DB::COMPANIES);
define('tbl_referral_codes', \App\Core\DB::REFERRAL_CODES);
define('tbl_categories', \App\Core\DB::CATEGORIES);
define('tbl_subcategories', \App\Core\DB::SUBCATEGORIES);
define('tbl_category_items', \App\Core\DB::CATEGORY_ITEMS);
define('tbl_alerts', \App\Core\DB::ALERTS);
define('tbl_inquiries', \App\Core\DB::INQUIRIES);
define('tbl_inquiry_replies', \App\Core\DB::INQUIRY_REPLIES);
define('tbl_disposable_email_domains', \App\Core\DB::DISPOSABLE_EMAIL_DOMAINS);
define('tbl_accounts', \App\Core\DB::ACCOUNTS);
define('tbl_accounts_report_categories', \App\Core\DB::ACCOUNTS_REPORT_CATEGORIES);
define('tbl_accounts_report_subcategories', \App\Core\DB::ACCOUNTS_REPORT_SUBCATEGORIES);
define('tbl_journals', \App\Core\DB::JOURNALS);
define('tbl_journal_items', \App\Core\DB::JOURNAL_ITEMS);
define('tbl_quotations', \App\Core\DB::QUOTATIONS);
define('tbl_quotation_items', \App\Core\DB::QUOTATION_ITEMS);
define('tbl_sale_orders', \App\Core\DB::SALE_ORDERS);
define('tbl_sale_order_items', \App\Core\DB::SALE_ORDER_ITEMS);
define('tbl_document_types', \App\Core\DB::DOCUMENT_TYPES);
define('tbl_payments_received', \App\Core\DB::PAYMENTS_RECEIVED);
define('tbl_payment_received_items', \App\Core\DB::getPrefix() . 'payment_received_items');
define('tbl_credit_notes', \App\Core\DB::CREDIT_NOTES);
define('tbl_credit_note_items', \App\Core\DB::CREDIT_NOTE_ITEMS);
define('tbl_vendors', \App\Core\DB::VENDORS);
define('tbl_vendor_transactions', \App\Core\DB::VENDOR_TRANSACTIONS);
define('tbl_purchases', \App\Core\DB::PURCHASES);
define('tbl_purchase_items', \App\Core\DB::PURCHASE_ITEMS);
define('tbl_purchase_orders', \App\Core\DB::PURCHASE_ORDERS);
define('tbl_purchase_order_items', \App\Core\DB::PURCHASE_ORDER_ITEMS);
define('tbl_payments_made', \App\Core\DB::PAYMENTS_MADE);
define('tbl_payment_made_items', \App\Core\DB::getPrefix() . 'payment_made_items');
define('tbl_debit_notes', \App\Core\DB::DEBIT_NOTES);
define('tbl_debit_note_items', \App\Core\DB::DEBIT_NOTE_ITEMS);
define('tbl_expenses', \App\Core\DB::EXPENSES);
define('tbl_expense_items', \App\Core\DB::EXPENSE_ITEMS);
define('tbl_expense_attachments', \App\Core\DB::EXPENSE_ATTACHMENTS);
define('tbl_banks', \App\Core\DB::BANKS);
define('tbl_tax_treatments', \App\Core\DB::TAX_TREATMENTS);
define('tbl_payment_terms', \App\Core\DB::PAYMENT_TERMS);
define('tbl_currencies', \App\Core\DB::CURRENCIES);
define('tbl_leads', \App\Core\DB::LEADS);
define('tbl_projects', \App\Core\DB::PROJECTS);
define('tbl_jobs', \App\Core\DB::JOBS);
define('tbl_job_items', \App\Core\DB::JOB_ITEMS);
define('tbl_job_statuses', \App\Core\DB::JOB_STATUSES);
define('tbl_incoterms', \App\Core\DB::INCOTERMS);
define('tbl_exit_points', \App\Core\DB::EXIT_POINTS);
define('tbl_container_types', \App\Core\DB::CONTAINER_TYPES);
define('tbl_commodity_types', \App\Core\DB::COMMODITY_TYPES);
define('tbl_warehouses', \App\Core\DB::WAREHOUSES);
define('tbl_storage_types', \App\Core\DB::STORAGE_TYPES);
define('tbl_services', \App\Core\DB::SERVICES);
define('tbl_units', \App\Core\DB::UNITS);
define('tbl_document_categories', \App\Core\DB::DOCUMENT_CATEGORIES);


// config.php
define('BASE_CURRENCY', [
	'code' => 'AED',
	'symbol' => 'D',
	'precision' => 2,
	'full_name' => 'United Arab Emriates'
]);


/* 
	| ---------------------------------------------------------------------------------------------------
	| SETTINGS TBL
	| --------------------------------------------------------------------------------------------------- 
	*/

// $result_settings = $mysqli->query("SELECT * FROM `".$GLOBALS['TBL']['PREFIX']."settings` WHERE id=1");
// $row_settings		 = $result_settings->fetch_array();

// $GLOBALS['SETTINGS']['LOGO']						      			= s__($row_settings['logo']);
// $GLOBALS['SETTINGS']['LOGO_FOOTER']						     		= s__($row_settings['logo_footer']);

// $GLOBALS['SETTINGS']['SITEMAP_ROOT']								= s__($row_settings['sitemap_root']);
// $GLOBALS['SETTINGS']['SITEMAP_COMPANIES']							= s__($row_settings['sitemap_companies']);

// $GLOBALS['SETTINGS']['MINIFY_CSS']									= s__($row_settings['minify_css']);
// $GLOBALS['SETTINGS']['MINIFY_JS']									= s__($row_settings['minify_js']);
// $GLOBALS['SETTINGS']['FACEBOOK']									= s__($row_settings['facebook']);
// $GLOBALS['SETTINGS']['TWITTER']										= s__($row_settings['twitter']);
// $GLOBALS['SETTINGS']['PINTEREST']									= s__($row_settings['pinterest']);


