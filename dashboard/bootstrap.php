<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\Roles;
use App\Core\DeletionManager;
use App\Security\SystemEntitlements;
use App\Service\SMTPMailer;

require_once __DIR__ . '/../config/session.php';
/*
|--------------------------------------------------------------------------
| Security Headers & HTTPS Enforcement
|--------------------------------------------------------------------------
| Comprehensive security headers to protect against common web vulnerabilities
*/

// HTTPS Enforcement (only in production)
$appEnv = strtolower((string)(getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? '')));
$serverName = strtolower((string)($_SERVER['SERVER_NAME'] ?? 'localhost'));
$isLocalHost = in_array($serverName, ['127.0.0.1', 'localhost'], true);
$isProduction = ($appEnv === 'production') || ($appEnv === '' && !$isLocalHost);
if ($isProduction && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

// Cache Control Headers
header("Expires: Sat, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0", false);
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Strict-Transport-Security (HSTS) - Only on HTTPS
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}

// Content-Security-Policy (CSP) - Strict but allows inline scripts/styles for compatibility
header("Content-Security-Policy: " . implode('; ', [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.datatables.net",
    "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdn.datatables.net",
    "font-src 'self' https://fonts.gstatic.com data:",
    "img-src 'self' data: https:",
    "connect-src 'self'",
    "frame-ancestors 'none'",
    "base-uri 'self'",
    "form-action 'self'"
]));

// Permissions-Policy (Feature Policy) - Restrict browser features
header("Permissions-Policy: " . implode(', ', [
    "geolocation=()",
    "microphone=()",
    "camera=()",
    "payment=()",
    "usb=()",
    "magnetometer=()",
    "gyroscope=()",
    "accelerometer=()"
]));

session_cache_limiter("must-revalidate");
ob_start();

// Load Time Calculation
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start_page_time = $time;

/*
|--------------------------------------------------------------------------
| Secure Session Configuration
|--------------------------------------------------------------------------
| Configure session security settings before starting session
*/

// Secure session cookie settings
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
ini_set('session.cookie_samesite', 'Strict'); // Prevent CSRF via cookies
ini_set('session.use_strict_mode', 1); // Reject uninitialized session IDs
ini_set('session.use_only_cookies', 1); // Only use cookies for session ID
ini_set('session.cookie_secure', $isProduction ? 1 : 0); // Secure flag (HTTPS only) in production

startDashboardSession();
header("Content-Type: text/html; charset=utf-8");
require_once('../config/globals.php');
require_once('../config/database.php');

// Register error/exception/fatal handlers early (before any bootstrap code runs)
require_once __DIR__ . '/admin_elements/error_handler_init.php';

// Ensure shared mail/database services can resolve the active DB handle in dashboard context.
if (!isset($GLOBALS['conn']) && isset($mysqli) && $mysqli instanceof mysqli) {
    $GLOBALS['conn'] = $mysqli;
}

// Initialize Dependency Injection Container
$container = \App\Core\Container::getInstance();

$container->register(\App\Core\Database::class, function () {
    return new \App\Core\Database();
});

$container->register(\App\Core\Logger::class, function () {
    $isProduction = isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production';
    return new \App\Core\Logger(null, $isProduction ? 'production' : 'development');
});

$container->register(\App\Core\ServerRequest::class, function () use ($project_pre) {
    return new \App\Core\ServerRequest(
        server: $_SERVER,
        query: $_GET,
        post: $_POST,
        files: $_FILES,
        cookies: $_COOKIE,
        sessionPrefix: $project_pre
    );
});

// --- Auto-wired Repositories ---
$container->autowire(\App\Repository\UserRepository::class);
$container->autowire(\App\Repository\DepartmentRepository::class);
$container->autowire(\App\Repository\DesignationRepository::class);
$container->autowire(\App\Repository\LeaveTypeRepository::class);
$container->autowire(\App\Repository\LeaveRequestRepository::class);
$container->autowire(\App\Repository\CustomerRepository::class);
$container->autowire(\App\Repository\BankRepository::class);
$container->autowire(\App\Repository\CurrencyRepository::class);
$container->autowire(\App\Repository\PaymentMethodRepository::class);
$container->autowire(\App\Repository\InvoiceRepository::class);
$container->autowire(\App\Repository\SetupGroupRepository::class);
$container->autowire(\App\Repository\SetupSourceRepository::class);
$container->autowire(\App\Repository\SetupStatusRepository::class);
$container->autowire(\App\Repository\SetupTagRepository::class);
$container->autowire(\App\Repository\UnitRepository::class);
$container->autowire(\App\Repository\CategoryRepository::class);
$container->autowire(\App\Repository\SubcategoryRepository::class);
$container->autowire(\App\Repository\CommodityTypeRepository::class);
$container->autowire(\App\Repository\ContainerTypeRepository::class);
$container->autowire(\App\Repository\DocumentCategoryRepository::class);
$container->autowire(\App\Repository\ExitPointRepository::class);
$container->autowire(\App\Repository\IncotermRepository::class);
$container->autowire(\App\Repository\BannedWordRepository::class);
$container->autowire(\App\Repository\PortRepository::class);
$container->autowire(\App\Repository\CarrierRepository::class);
$container->autowire(\App\Repository\ConsigneeRepository::class);
$container->autowire(\App\Repository\ShipperRepository::class);
$container->autowire(\App\Repository\HscodeRepository::class);
$container->autowire(\App\Repository\ItemRepository::class);
$container->autowire(\App\Repository\AccountRepository::class);
$container->autowire(\App\Repository\EmailProviderRepository::class);
$container->autowire(\App\Repository\AttendanceRepository::class);
$container->autowire(\App\Repository\AttendanceDeviceRepository::class);
$container->autowire(\App\Repository\PaymentTermRepository::class);
$container->autowire(\App\Repository\PurchaseTypeRepository::class);
$container->autowire(\App\Repository\SaleTypeRepository::class);
$container->autowire(\App\Repository\StorageTypeRepository::class);
$container->autowire(\App\Repository\TaxTreatmentRepository::class);
$container->autowire(\App\Repository\AlertRepository::class);
$container->autowire(\App\Repository\StorageSubtypeRepository::class);
$container->autowire(\App\Repository\ModuleRepository::class);
$container->autowire(\App\Repository\ServiceRepository::class);
$container->autowire(\App\Repository\VendorRepository::class);
$container->autowire(\App\Repository\PayrollComponentRepository::class);
$container->autowire(\App\Repository\AccountReportCategoryRepository::class);
$container->autowire(\App\Repository\PayrollRunRepository::class);
$container->autowire(\App\Repository\SalaryStructureRepository::class);
$container->autowire(\App\Repository\ModulePermissionRepository::class);
$container->autowire(\App\Repository\RoleRepository::class);
$container->autowire(\App\Repository\AccountReportSubcategoryRepository::class);
$container->autowire(\App\Repository\CategoryHsCodeRepository::class);
$container->autowire(\App\Repository\ExpenseRepository::class);
$container->autowire(\App\Repository\CreditNoteRepository::class);
$container->autowire(\App\Repository\DebitNoteRepository::class);
$container->autowire(\App\Repository\PurchaseRepository::class);
$container->autowire(\App\Repository\PurchaseOrderRepository::class);
$container->autowire(\App\Repository\SaleOrderRepository::class);
$container->autowire(\App\Repository\QuotationRepository::class);
$container->autowire(\App\Repository\LeadQuotationRepository::class);
$container->autowire(\App\Repository\JobRepository::class);
$container->autowire(\App\Repository\JobItemRepository::class);
$container->autowire(\App\Repository\ShippingAdviceRepository::class);
$container->autowire(\App\Repository\ShippingInvoiceRepository::class);
$container->autowire(\App\Repository\JournalRepository::class);
$container->autowire(\App\Repository\RecurringInvoiceRepository::class);
$container->autowire(\App\Repository\CustomerContactRepository::class);
$container->autowire(\App\Repository\EntityNoteRepository::class);
$container->autowire(\App\Repository\UserDocumentRepository::class);
$container->autowire(\App\Repository\LeadAttachmentRepository::class);
$container->autowire(\App\Repository\CustomerAddressRepository::class);
$container->autowire(\App\Repository\GratuitySettlementRepository::class);
$container->autowire(\App\Repository\AirTicketRepository::class);
$container->autowire(\App\Repository\AnnualLeaveEntitlementRepository::class);
$container->autowire(\App\Repository\HrTodoTaskRepository::class);

// --- Auto-wired Utility Classes ---
$container->autowire(\App\Core\AuditLogger::class);
$container->autowire(\App\Helper\DateHelper::class);

// --- Auto-wired Services (dependencies resolved via constructor inspection) ---
$container->autowire(\App\Service\DepartmentService::class);
$container->autowire(\App\Service\DesignationService::class);
$container->autowire(\App\Service\UserService::class);
$container->autowire(\App\Service\LeaveTypeService::class);
$container->autowire(\App\Service\LeaveRequestService::class);
$container->autowire(\App\Service\InvoiceService::class);
$container->autowire(\App\Service\CustomerService::class);
$container->autowire(\App\Service\CustomerContactService::class);
$container->autowire(\App\Service\DashboardService::class);
$container->autowire(\App\Service\BankService::class);
$container->autowire(\App\Service\CurrencyService::class);
$container->autowire(\App\Service\PaymentMethodService::class);
$container->autowire(\App\Service\JournalService::class);
$container->autowire(\App\Service\MembershipService::class);
$container->autowire(\App\Service\EmailProviderService::class);
$container->autowire(\App\Service\AttendanceService::class);
$container->autowire(\App\Service\AttendanceDeviceService::class);
$container->autowire(\App\Service\PaymentTermService::class);
$container->autowire(\App\Service\PurchaseTypeService::class);
$container->autowire(\App\Service\SaleTypeService::class);
$container->autowire(\App\Service\StorageTypeService::class);
$container->autowire(\App\Service\TaxTreatmentService::class);
$container->autowire(\App\Service\AlertService::class);
$container->autowire(\App\Service\StorageSubtypeService::class);
$container->autowire(\App\Service\ModuleService::class);
$container->autowire(\App\Service\ServiceService::class);
$container->autowire(\App\Service\VendorService::class);
$container->autowire(\App\Service\PayrollComponentService::class);
$container->autowire(\App\Service\AccountReportCategoryService::class);
$container->autowire(\App\Service\PayrollRunService::class);
$container->autowire(\App\Service\SalaryStructureService::class);
$container->autowire(\App\Service\ModulePermissionService::class);
$container->autowire(\App\Service\RoleService::class);
$container->autowire(\App\Service\AccountReportSubcategoryService::class);
$container->autowire(\App\Service\CategoryHsCodeService::class);
$container->autowire(\App\Service\ExpenseService::class);
$container->autowire(\App\Service\RecurringInvoiceService::class);
$container->autowire(\App\Service\CreditNoteService::class);
$container->autowire(\App\Service\DebitNoteService::class);
$container->autowire(\App\Service\PurchaseService::class);
$container->autowire(\App\Service\PurchaseOrderService::class);
$container->autowire(\App\Service\SaleOrderService::class);
$container->autowire(\App\Service\QuotationService::class);
$container->autowire(\App\Service\LeadQuotationService::class);
$container->autowire(\App\Service\JobService::class);
$container->autowire(\App\Service\JobItemService::class);
$container->autowire(\App\Service\ShippingAdviceService::class);
$container->autowire(\App\Service\ShippingInvoiceService::class);
$container->autowire(\App\Service\SetupGroupService::class);
$container->autowire(\App\Service\SetupSourceService::class);
$container->autowire(\App\Service\SetupStatusService::class);
$container->autowire(\App\Service\SetupTagService::class);
$container->autowire(\App\Service\UnitService::class);
$container->autowire(\App\Service\CategoryService::class);
$container->autowire(\App\Service\SubcategoryService::class);
$container->autowire(\App\Service\CommodityTypeService::class);
$container->autowire(\App\Service\ContainerTypeService::class);
$container->autowire(\App\Service\DocumentCategoryService::class);
$container->autowire(\App\Service\ExitPointService::class);
$container->autowire(\App\Service\IncotermService::class);
$container->autowire(\App\Service\BannedWordService::class);
$container->autowire(\App\Service\PortService::class);
$container->autowire(\App\Service\CarrierService::class);
$container->autowire(\App\Service\ConsigneeService::class);
$container->autowire(\App\Service\ShipperService::class);
$container->autowire(\App\Service\HscodeService::class);
$container->autowire(\App\Service\ItemService::class);
$container->autowire(\App\Service\AccountService::class);
$container->autowire(\App\Service\EmailProviderService::class);
$container->autowire(\App\Service\AttendanceService::class);
$container->autowire(\App\Service\PaymentTermService::class);
$container->autowire(\App\Service\PurchaseTypeService::class);
$container->autowire(\App\Service\SaleTypeService::class);
$container->autowire(\App\Service\StorageTypeService::class);
$container->autowire(\App\Service\TaxTreatmentService::class);
$container->autowire(\App\Service\AlertService::class);
$container->autowire(\App\Service\StorageSubtypeService::class);
$container->autowire(\App\Service\ModuleService::class);
$container->autowire(\App\Service\ServiceService::class);
$container->autowire(\App\Service\VendorService::class);
$container->autowire(\App\Service\PayrollComponentService::class);
$container->autowire(\App\Service\AccountReportCategoryService::class);
$container->autowire(\App\Service\PayrollRunService::class);
$container->autowire(\App\Service\SalaryStructureService::class);
$container->autowire(\App\Service\ModulePermissionService::class);
$container->autowire(\App\Service\RoleService::class);
$container->autowire(\App\Service\AccountReportSubcategoryService::class);
$container->autowire(\App\Service\CategoryHsCodeService::class);
$container->autowire(\App\Service\EntityNoteService::class);
$container->autowire(\App\Service\UserDocumentService::class);
$container->autowire(\App\Service\OrganizationDocumentService::class);
$container->autowire(\App\Service\LeadAttachmentService::class);
$container->autowire(\App\Service\CustomerAddressService::class);
$container->autowire(\App\Service\GratuitySettlementService::class);
$container->autowire(\App\Service\AirTicketService::class);
$container->autowire(\App\Service\AnnualLeaveEntitlementService::class);
$container->autowire(\App\Service\HrTodoTaskService::class);

// ---------------------------------------------------------------------------
// NEW HTTP CONTROLLER REGISTRATIONS (Modern Pattern)
// ---------------------------------------------------------------------------

$container->register(\App\Http\Controller\DepartmentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\DepartmentController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\DepartmentService::class)
    );
});

$container->register(\App\Http\Controller\DesignationController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\DesignationController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\DesignationService::class)
    );
});

$container->register(\App\Http\Controller\LeaveTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\LeaveTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\LeaveTypeService::class)
    );
});

$container->register(\App\Http\Controller\LeaveRequestController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\LeaveRequestController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\LeaveRequestService::class)
    );
});

$container->register(\App\Http\Controller\BankController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\BankController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\BankService::class),
        $c->get(\App\Service\CurrencyService::class)
    );
});

$container->register(\App\Http\Controller\CurrencyController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CurrencyController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CurrencyService::class)
    );
});

$container->register(\App\Http\Controller\PaymentMethodController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PaymentMethodController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\PaymentMethodService::class)
    );
});

$container->register(\App\Http\Controller\UserController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\UserController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\UserService::class),
        $c->get(\App\Service\UserDocumentService::class),
        $c->get(\App\Service\AirTicketService::class),
        $GLOBALS['project_pre'],
    );
});

$container->register(\App\Http\Controller\CustomerController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CustomerController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CustomerService::class)
    );
});

$container->register(\App\Http\Controller\CustomerContactController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CustomerContactController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CustomerContactService::class),
        $c->get(\App\Service\CustomerService::class)
    );
});

$container->register(\App\Http\Controller\InvoiceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\InvoiceController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\InvoiceService::class)
    );
});

// Setup Entity Controllers (Dashboard Page Migration)
$container->register(\App\Http\Controller\SetupGroupController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SetupGroupController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SetupGroupService::class)
    );
});
$container->register(\App\Http\Controller\SetupStatusController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SetupStatusController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SetupStatusService::class)
    );
});
$container->register(\App\Http\Controller\SetupSourceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SetupSourceController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SetupSourceService::class)
    );
});
$container->register(\App\Http\Controller\SetupTagController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SetupTagController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SetupTagService::class)
    );
});
$container->register(\App\Http\Controller\UnitController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\UnitController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\UnitService::class)
    );
});

$container->register(\App\Http\Controller\CategoryController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CategoryController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CategoryService::class)
    );
});

$container->register(\App\Http\Controller\SubcategoryController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SubcategoryController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SubcategoryService::class),
        $c->get(\App\Service\CategoryService::class)
    );
});

$container->register(\App\Http\Controller\CommodityTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CommodityTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CommodityTypeService::class)
    );
});


$container->register(\App\Http\Controller\ContainerTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ContainerTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ContainerTypeService::class)
    );
});

$container->register(\App\Http\Controller\DocumentCategoryController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\DocumentCategoryController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\DocumentCategoryService::class)
    );
});

$container->register(\App\Http\Controller\ExitPointController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ExitPointController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ExitPointService::class)
    );
});

$container->register(\App\Http\Controller\IncotermController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\IncotermController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\IncotermService::class)
    );
});

$container->register(\App\Http\Controller\BannedWordController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\BannedWordController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\BannedWordService::class)
    );
});

$container->register(\App\Http\Controller\PortController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PortController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\PortService::class)
    );
});

$container->register(\App\Http\Controller\CarrierController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CarrierController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\CarrierService::class)
    );
});

$container->register(\App\Http\Controller\ConsigneeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ConsigneeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ConsigneeService::class)
    );
});

$container->register(\App\Http\Controller\ShipperController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ShipperController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ShipperService::class)
    );
});

$container->register(\App\Http\Controller\HscodeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\HscodeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\HscodeService::class)
    );
});

$container->register(\App\Http\Controller\ItemController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ItemController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\ItemService::class));
});

$container->register(\App\Http\Controller\AccountController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AccountController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\AccountService::class));
});

$container->register(\App\Http\Controller\EmailProviderController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\EmailProviderController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\EmailProviderService::class));
});

$container->register(\App\Http\Controller\AttendanceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AttendanceController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\AttendanceService::class));
});

$container->register(\App\Http\Controller\AttendanceDeviceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AttendanceDeviceController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\AttendanceDeviceService::class));
});

$container->register(\App\Http\Controller\PaymentTermController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PaymentTermController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\PaymentTermService::class)
    );
});

$container->register(\App\Http\Controller\PurchaseTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PurchaseTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\PurchaseTypeService::class)
    );
});

$container->register(\App\Http\Controller\SaleTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SaleTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\SaleTypeService::class)
    );
});

$container->register(\App\Http\Controller\StorageTypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\StorageTypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\StorageTypeService::class)
    );
});

$container->register(\App\Http\Controller\TaxTreatmentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\TaxTreatmentController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\TaxTreatmentService::class)
    );
});

$container->register(\App\Http\Controller\AlertController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AlertController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\AlertService::class)
    );
});

$container->register(\App\Http\Controller\StorageSubtypeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\StorageSubtypeController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\StorageSubtypeService::class)
    );
});

$container->register(\App\Http\Controller\ModuleController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ModuleController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ModuleService::class)
    );
});

$container->register(\App\Http\Controller\ServiceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ServiceController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\ServiceService::class)
    );
});

$container->register(\App\Http\Controller\VendorController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\VendorController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\VendorService::class)
    );
});

$container->register(\App\Http\Controller\PayrollComponentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PayrollComponentController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId(),
        $c->get(\App\Service\PayrollComponentService::class)
    );
});
# --- Batch 5 controllers ---

$container->register(\App\Http\Controller\AccountReportCategoryController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AccountReportCategoryController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\AccountReportCategoryService::class));
});

$container->register(\App\Http\Controller\PayrollRunController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PayrollRunController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\PayrollRunService::class));
});

$container->register(\App\Http\Controller\SalaryStructureController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SalaryStructureController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\SalaryStructureService::class));
});

$container->register(\App\Http\Controller\ModulePermissionController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ModulePermissionController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\ModulePermissionService::class));
});
# --- Batch 6 controllers ---

$container->register(\App\Http\Controller\RoleController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\RoleController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\RoleService::class));
});

$container->register(\App\Http\Controller\AccountReportSubcategoryController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AccountReportSubcategoryController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\AccountReportSubcategoryService::class));
});

$container->register(\App\Http\Controller\CategoryHsCodeController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CategoryHsCodeController($c->get(\App\Core\Database::class), \App\Core\Session::userId(), \App\Core\Session::roleId(), \App\Core\Session::orgId(), $c->get(\App\Service\CategoryHsCodeService::class));
});
# --- P14e Phase 1 controllers ---
$container->register(\App\Http\Controller\ExpenseController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ExpenseController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\ExpenseService::class)
    );
});
# --- Journals & Recurring Invoices controllers ---
$container->register(\App\Http\Controller\JournalController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\JournalController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\JournalService::class)
    );
});
$container->register(\App\Http\Controller\RecurringInvoiceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\RecurringInvoiceController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\RecurringInvoiceService::class)
    );
});
# --- P14e Phase 1 controllers: Credit Notes & Debit Notes ---
$container->register(\App\Http\Controller\CreditNoteController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CreditNoteController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\CreditNoteService::class)
    );
});
$container->register(\App\Http\Controller\DebitNoteController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\DebitNoteController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\DebitNoteService::class)
    );
});
# --- P14e Phase 2 controllers: Purchases & Purchase Orders ---
$container->register(\App\Http\Controller\PurchaseController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PurchaseController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\PurchaseService::class)
    );
});
$container->register(\App\Http\Controller\PurchaseOrderController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\PurchaseOrderController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\PurchaseOrderService::class)
    );
});
# --- P14e Phase 3 controllers: Sale Orders & Quotations ---
$container->register(\App\Http\Controller\SaleOrderController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\SaleOrderController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\SaleOrderService::class)
    );
});
$container->register(\App\Http\Controller\QuotationController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\QuotationController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\QuotationService::class)
    );
});
# --- P14e Phase 4 controller: Lead Quotations ---
$container->register(\App\Http\Controller\LeadQuotationController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\LeadQuotationController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\LeadQuotationService::class)
    );
});
# --- P14e Phase 5 controller: Jobs ---
$container->register(\App\Http\Controller\JobController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\JobController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\JobService::class),
        $c->get(\App\Service\JobItemService::class)
    );
});
# --- Shipping modules ---
$container->register(\App\Http\Controller\ShippingAdviceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ShippingAdviceController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\ShippingAdviceService::class)
    );
});
$container->register(\App\Http\Controller\ShippingInvoiceController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\ShippingInvoiceController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\ShippingInvoiceService::class)
    );
});
# --- Entity Notes controllers (Customer Comments & Lead Notes) ---
$container->register(\App\Http\Controller\CustomerCommentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CustomerCommentController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\EntityNoteService::class)
    );
});
$container->register(\App\Http\Controller\LeadNoteController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\LeadNoteController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\EntityNoteService::class)
    );
});
# --- User Documents & Lead Attachments controllers ---
$container->register(\App\Http\Controller\UserDocumentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\UserDocumentController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\UserDocumentService::class)
    );
});
$container->register(\App\Http\Controller\LeadAttachmentController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\LeadAttachmentController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\LeadAttachmentService::class)
    );
});
# --- Customer Address module ---
$container->register(\App\Http\Controller\CustomerAddressController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\CustomerAddressController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\CustomerAddressService::class)
    );
});
# --- Gratuity Settlement module ---
$container->register(\App\Http\Controller\GratuitySettlementController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\GratuitySettlementController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\GratuitySettlementService::class)
    );
});
# --- Air Tickets module ---
$container->register(\App\Http\Controller\AirTicketController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AirTicketController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\AirTicketService::class)
    );
});
# --- Annual Leave Entitlements module ---
$container->register(\App\Http\Controller\AnnualLeaveEntitlementController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\AnnualLeaveEntitlementController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\AnnualLeaveEntitlementService::class)
    );
});

# --- HR To-Do Tasks module ---
$container->register(\App\Http\Controller\HrTodoTaskController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\HrTodoTaskController(
        $c->get(\App\Core\Database::class),
        \App\Core\Session::userId(),
        \App\Core\Session::roleId(),
        \App\Core\Session::orgId(),
        $c->get(\App\Service\HrTodoTaskService::class)
    );
});

// Initialize Deletion Manager (centralized deletion handling)
\App\Core\DeletionManager::init($mysqli, $project_pre);

include('../config/images.php');
include('admin_elements/security.php');
include('admin_elements/grab_vars.php');

/*
|--------------------------------------------------------------------------
| 	SESSION VARIABLES
|--------------------------------------------------------------------------
|
*/

$session_role_id = $_SESSION[$project_pre]['DASHBOARD']['role_id'] ?? null;
$session_user_id = $_SESSION[$project_pre]['DASHBOARD']['user_id'] ?? null;
$session_full_name = $_SESSION[$project_pre]['DASHBOARD']['full_name'] ?? '';
$session_email = $_SESSION[$project_pre]['DASHBOARD']['email'] ?? '';

if (!function_exists('dashboardGetSystemEntitlements')) {
    function dashboardGetSystemEntitlements(): array
    {
        global $project_pre;

        $default = SystemEntitlements::defaultEntitlements();
        $fromSession = $_SESSION[$project_pre]['DASHBOARD']['system_entitlements'] ?? null;

        if (!is_array($fromSession)) {
            return $default;
        }

        return array_replace($default, $fromSession);
    }
}

if (!function_exists('dashboardGetSubscriptionFeatures')) {
    function dashboardGetSubscriptionFeatures(): array
    {
        global $project_pre;

        $default = SystemEntitlements::defaultFeatures();
        $fromSession = $_SESSION[$project_pre]['DASHBOARD']['subscription_features'] ?? null;

        if (!is_array($fromSession)) {
            return $default;
        }

        return array_replace($default, $fromSession);
    }
}

if (!function_exists('dashboardHasSystemAccess')) {
    function dashboardHasSystemAccess(string $systemKey): bool
    {
        $systemKey = strtolower(trim($systemKey));
        if ($systemKey === '') {
            return true;
        }

        $entitlements = dashboardGetSystemEntitlements();
        if (!array_key_exists($systemKey, $entitlements)) {
            return true;
        }

        return (bool)$entitlements[$systemKey];
    }
}

if (!function_exists('dashboardCanCreateOrganizations')) {
    function dashboardCanCreateOrganizations(): bool
    {
        $features = dashboardGetSubscriptionFeatures();
        return !empty($features['can_create_organizations']) && $features['can_create_organizations'] !== '0';
    }
}

if (!function_exists('dashboardCanInviteMembers')) {
    function dashboardCanInviteMembers(): bool
    {
        $features = dashboardGetSubscriptionFeatures();
        return !empty($features['can_invite_members']) && $features['can_invite_members'] !== '0';
    }
}

if (!function_exists('dashboardMaxOrganizations')) {
    function dashboardMaxOrganizations(): int
    {
        $features = dashboardGetSubscriptionFeatures();
        return (int)($features['max_organizations'] ?? 0);
    }
}

if (!function_exists('dashboardMaxTeamMembers')) {
    function dashboardMaxTeamMembers(): int
    {
        $features = dashboardGetSubscriptionFeatures();
        return (int)($features['max_team_members'] ?? 0);
    }
}

if (!function_exists('dashboardOrganizationActiveMemberCount')) {
    function dashboardOrganizationActiveMemberCount(int $organizationId): int
    {
        if ($organizationId <= 0) {
            return 0;
        }

        $ms = new \App\Service\MembershipService();
        return $ms->countActiveMembers($organizationId);
    }
}

if (!function_exists('dashboardUserBelongsToOrganization')) {
    function dashboardUserBelongsToOrganization(int $organizationId, ?int $userId = null): bool
    {
        global $session_user_id;

        $resolvedUserId = $userId !== null ? (int)$userId : (int)$session_user_id;
        if ($organizationId <= 0 || $resolvedUserId <= 0) {
            return false;
        }

        $ms = new \App\Service\MembershipService();
        return $ms->hasActiveMembership($organizationId, $resolvedUserId);
    }
}

if (!function_exists('dashboardGetAccessibleOrganizations')) {
    function dashboardGetAccessibleOrganizations(?int $userId = null): array
    {
        global $mysqli, $session_user_id;

        if (!($mysqli instanceof mysqli)) {
            return [];
        }

        $resolvedUserId = $userId !== null ? (int)$userId : (int)$session_user_id;
        if ($resolvedUserId <= 0 && !Roles::currentUserHasFullAccess()) {
            return [];
        }

        if (Roles::currentUserHasFullAccess()) {
            $query = "SELECT id, warehouse_name, slug, status FROM `" . DB::ORGANIZATIONS . "` ORDER BY warehouse_name ASC";
            $result = $mysqli->query($query);
            $organizations = [];
            while ($row = $result instanceof mysqli_result ? $result->fetch_assoc() : null) {
                $organizations[] = $row;
            }
            if ($result instanceof mysqli_result) {
                $result->free();
            }
            return $organizations;
        }

        return [['id' => 1, 'warehouse_name' => 'Flash Logistics FZCO', 'slug' => 'flash-logistics', 'status' => 'active']];
    }
}

if (!function_exists('dashboardSetActiveOrganization')) {
    function dashboardSetActiveOrganization(int $organizationId, ?int $userId = null): bool
    {
        global $project_pre;

        if ($organizationId <= 0) {
            unset($_SESSION[$project_pre]['DASHBOARD']['organization_id']);
            unset($_SESSION[$project_pre]['DASHBOARD']['organization_name']);
            return false;
        }

        foreach (dashboardGetAccessibleOrganizations($userId) as $organization) {
            if ((int)($organization['id'] ?? 0) !== $organizationId) {
                continue;
            }

            $_SESSION[$project_pre]['DASHBOARD']['organization_id'] = $organizationId;
            $_SESSION[$project_pre]['DASHBOARD']['organization_name'] = (string)($organization['warehouse_name'] ?? '');
            return true;
        }

        return false;
    }
}

if (!function_exists('dashboardGetActiveOrganizationId')) {
    function dashboardGetActiveOrganizationId(bool $autoResolve = true): int
    {
        global $project_pre;

        $currentOrganizationId = (int)($_SESSION[$project_pre]['DASHBOARD']['organization_id'] ?? 0);
        if ($currentOrganizationId > 0 && dashboardSetActiveOrganization($currentOrganizationId)) {
            return $currentOrganizationId;
        }

        if (!$autoResolve) {
            return 0;
        }

        $organizations = dashboardGetAccessibleOrganizations();
        $fallbackOrganizationId = (int)($organizations[0]['id'] ?? 0);
        if ($fallbackOrganizationId > 0 && dashboardSetActiveOrganization($fallbackOrganizationId)) {
            return $fallbackOrganizationId;
        }

        return 0;
    }
}

if (!function_exists('dashboardGetActiveOrganizationName')) {
    function dashboardGetActiveOrganizationName(): string
    {
        global $project_pre;

        $activeOrganizationId = dashboardGetActiveOrganizationId();
        if ($activeOrganizationId <= 0) {
            return '';
        }

        $cachedName = trim((string)($_SESSION[$project_pre]['DASHBOARD']['organization_name'] ?? ''));
        if ($cachedName !== '') {
            return $cachedName;
        }

        foreach (dashboardGetAccessibleOrganizations() as $organization) {
            if ((int)($organization['id'] ?? 0) === $activeOrganizationId) {
                $resolvedName = trim((string)($organization['warehouse_name'] ?? ''));
                $_SESSION[$project_pre]['DASHBOARD']['organization_name'] = $resolvedName;
                return $resolvedName;
            }
        }

        return '';
    }
}

if (!function_exists('dashboardUserIsOrganizationOwner')) {
    function dashboardUserIsOrganizationOwner(int $organizationId, int $userId): bool
    {
        global $mysqli;

        if ($organizationId <= 0 || $userId <= 0) {
            return false;
        }

        if (!($mysqli instanceof mysqli)) {
            return false;
        }

        $stmt = $mysqli->prepare(
            "SELECT id FROM `" . DB::ORGANIZATIONS . "` WHERE id = ? AND owner_user_id = ? LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ii', $organizationId, $userId);
        $stmt->execute();
        $isOwner = (bool)$stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $isOwner;
    }
}

if (!function_exists('dashboardRequireActiveOrganization')) {
    function dashboardRequireActiveOrganization(
        bool $autoResolve = true,
        string $redirectTo = 'select_organization.php',
        string $message = 'Please select an organization to continue.'
    ): int {
        $activeOrganizationId = dashboardGetActiveOrganizationId($autoResolve);
        if ($activeOrganizationId > 0) {
            return $activeOrganizationId;
        }

        flash_error($message);
        header('Location:' . $redirectTo);
        exit;
    }
}

if (!function_exists('dashboardCreateOrganizationInvite')) {
    function dashboardCreateOrganizationInvite(int $organizationId, string $email, ?int $roleId = null): array
    {
        global $mysqli, $session_user_id;

        if (!dashboardCanInviteMembers()) {
            return ['success' => false, 'message' => 'Your subscription does not allow inviting members.'];
        }

        $maxTeamMembers = dashboardMaxTeamMembers();
        $activeMembers = dashboardOrganizationActiveMemberCount($organizationId);
        if ($maxTeamMembers > 0 && $activeMembers >= $maxTeamMembers) {
            return ['success' => false, 'message' => 'Your subscription team-member limit has been reached.'];
        }

        $ms = new \App\Service\MembershipService();
        $result = $ms->createInvite($organizationId, (int)$session_user_id, $email, $roleId);
        if (!empty($result['success'])) {
            $queueResult = dashboardQueueOrganizationInviteEmail(
                $organizationId,
                (string)($result['email'] ?? $email),
                (string)($result['invite_token'] ?? ''),
                'created',
                (int)($result['invite_id'] ?? 0)
            );
            $result['email_queued'] = !empty($queueResult['success']);
            if (empty($queueResult['success']) && !empty($queueResult['message'])) {
                $result['message'] = rtrim((string)$result['message'], '.') . '. Email queue warning: ' . $queueResult['message'];
            }
        }

        return $result;
    }
}

if (!function_exists('dashboardAcceptOrganizationInvite')) {
    function dashboardAcceptOrganizationInvite(string $token): array
    {
        global $session_user_id;

        $ms = new \App\Service\MembershipService();
        return $ms->acceptInviteByToken($token, (int)$session_user_id);
    }
}

if (!function_exists('dashboardResendOrganizationInvite')) {
    function dashboardResendOrganizationInvite(int $organizationId, int $inviteId, ?int $roleId = null): array
    {
        global $session_user_id;

        if (!dashboardCanInviteMembers()) {
            return ['success' => false, 'message' => 'Your subscription does not allow inviting members.'];
        }

        $ms = new \App\Service\MembershipService();
        $result = $ms->resendInvite(
            $organizationId,
            $inviteId,
            (int)$session_user_id,
            $roleId,
            ORGANIZATION_INVITE_EXPIRY_DAYS,
            Roles::currentUserHasFullAccess()
        );

        if (!empty($result['success'])) {
            $queueResult = dashboardQueueOrganizationInviteEmail(
                $organizationId,
                (string)($result['email'] ?? ''),
                (string)($result['invite_token'] ?? ''),
                'resent',
                (int)($result['invite_id'] ?? 0)
            );
            $result['email_queued'] = !empty($queueResult['success']);
            if (empty($queueResult['success']) && !empty($queueResult['message'])) {
                $result['message'] = rtrim((string)$result['message'], '.') . '. Email queue warning: ' . $queueResult['message'];
            }
        }

        return $result;
    }
}

if (!function_exists('dashboardRevokeOrganizationInvite')) {
    function dashboardRevokeOrganizationInvite(int $organizationId, int $inviteId): array
    {
        global $session_user_id;

        if (!dashboardCanInviteMembers()) {
            return ['success' => false, 'message' => 'Your subscription does not allow inviting members.'];
        }

        $ms = new \App\Service\MembershipService();
        return $ms->revokeInvite(
            $organizationId,
            $inviteId,
            (int)$session_user_id,
            Roles::currentUserHasFullAccess()
        );
    }
}

if (!function_exists('dashboardSendOrganizationInviteEmailNow')) {
    function dashboardSendOrganizationInviteEmailNow(int $organizationId, int $inviteId, int $queueId): array
    {
        global $mysqli;

        if (!dashboardCanInviteMembers()) {
            return ['success' => false, 'message' => 'Your subscription does not allow inviting members.'];
        }

        if ($organizationId <= 0 || $inviteId <= 0 || $queueId <= 0) {
            return ['success' => false, 'message' => 'Invalid organization invite email request.'];
        }

        $inviteStmt = $mysqli->prepare(
            "SELECT invite_token FROM `" . DB::ORGANIZATION_INVITES . "` WHERE id = ? AND organization_id = ? LIMIT 1"
        );
        if (!$inviteStmt) {
            return ['success' => false, 'message' => 'Unable to validate invite details.'];
        }

        $inviteStmt->bind_param('ii', $inviteId, $organizationId);
        $inviteStmt->execute();
        $inviteRow = $inviteStmt->get_result()->fetch_assoc();
        $inviteStmt->close();

        $inviteToken = trim((string)($inviteRow['invite_token'] ?? ''));
        if ($inviteToken === '') {
            return ['success' => false, 'message' => 'Invite record not found.'];
        }

        $queueStmt = $mysqli->prepare(
            "SELECT id, status, recipient_email, recipient, subject, body, headers, retries, max_retries
             FROM `" . DB::EMAIL_QUEUE . "`
             WHERE id = ?
             LIMIT 1"
        );
        if (!$queueStmt) {
            return ['success' => false, 'message' => 'Unable to validate queued invite email.'];
        }

        $queueStmt->bind_param('i', $queueId);
        $queueStmt->execute();
        $queueRow = $queueStmt->get_result()->fetch_assoc();
        $queueStmt->close();

        if (!$queueRow) {
            return ['success' => false, 'message' => 'Queued invite email not found.'];
        }

        $headers = [];
        if (!empty($queueRow['headers'])) {
            $decodedHeaders = json_decode((string)$queueRow['headers'], true);
            if (is_array($decodedHeaders)) {
                $headers = $decodedHeaders;
            }
        }

        if (
            trim((string)($headers['X-Invite-Token'] ?? '')) !== $inviteToken
            || (int)($headers['X-Organization-Id'] ?? 0) !== $organizationId
            || trim((string)($headers['X-Invite-Type'] ?? '')) !== 'organization'
        ) {
            return ['success' => false, 'message' => 'Queued email does not belong to this invite.'];
        }

        $status = strtolower(trim((string)($queueRow['status'] ?? '')));
        if (!in_array($status, ['pending', 'retry', 'queued', 'failed'], true)) {
            return ['success' => false, 'message' => 'Only pending/retry/queued/failed invite emails can be sent now.'];
        }

        $to = trim((string)($queueRow['recipient_email'] ?? ''));
        if ($to === '') {
            $to = trim((string)($queueRow['recipient'] ?? ''));
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invite email recipient is invalid.'];
        }

        $subject = (string)($queueRow['subject'] ?? 'Organization Invite');
        $body = (string)($queueRow['body'] ?? '');

        try {
            $mailer = new SMTPMailer();
            $sent = (bool)$mailer->send($to, $subject, $body, $headers);
            if ($sent) {
                $updateStmt = $mysqli->prepare(
                    "UPDATE `" . DB::EMAIL_QUEUE . "`
                     SET status = 'sent', sent_at = NOW(), updated_at = NOW(), failed_reason = NULL
                     WHERE id = ?"
                );
                if ($updateStmt) {
                    $updateStmt->bind_param('i', $queueId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }

                return ['success' => true, 'message' => 'Invite email sent successfully.'];
            }

            $retries = (int)($queueRow['retries'] ?? 0) + 1;
            $maxRetries = (int)($queueRow['max_retries'] ?? EMAIL_QUEUE_DEFAULT_MAX_RETRIES);
            if ($maxRetries <= 0) {
                $maxRetries = EMAIL_QUEUE_DEFAULT_MAX_RETRIES;
            }
            $nextStatus = $retries >= $maxRetries ? 'failed' : 'retry';
            $lastError = method_exists($mailer, 'getLastError') ? (string)$mailer->getLastError() : 'Manual send failed';

            $updateStmt = $mysqli->prepare(
                "UPDATE `" . DB::EMAIL_QUEUE . "`
                 SET status = ?, retries = ?, attempts = ?, failed_reason = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            if ($updateStmt) {
                $updateStmt->bind_param('siisi', $nextStatus, $retries, $retries, $lastError, $queueId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            return ['success' => false, 'message' => 'Invite email send failed.'];
        } catch (Throwable $e) {
            $retries = (int)($queueRow['retries'] ?? 0) + 1;
            $maxRetries = (int)($queueRow['max_retries'] ?? EMAIL_QUEUE_DEFAULT_MAX_RETRIES);
            if ($maxRetries <= 0) {
                $maxRetries = EMAIL_QUEUE_DEFAULT_MAX_RETRIES;
            }
            $nextStatus = $retries >= $maxRetries ? 'failed' : 'retry';
            $lastError = substr((string)$e->getMessage(), 0, 1000);

            $updateStmt = $mysqli->prepare(
                "UPDATE `" . DB::EMAIL_QUEUE . "`
                 SET status = ?, retries = ?, attempts = ?, failed_reason = ?, updated_at = NOW()
                 WHERE id = ?"
            );
            if ($updateStmt) {
                $updateStmt->bind_param('siisi', $nextStatus, $retries, $retries, $lastError, $queueId);
                $updateStmt->execute();
                $updateStmt->close();
            }

            return ['success' => false, 'message' => 'Invite email send encountered an exception.'];
        }
    }
}

if (!function_exists('dashboardQueueOrganizationInviteEmail')) {
    function dashboardQueueOrganizationInviteEmail(int $organizationId, string $recipientEmail, string $inviteToken, string $mode = 'created', int $inviteId = 0): array
    {
        global $mysqli, $admin_base_url, $session_user_id;

        $recipientEmail = strtolower(trim($recipientEmail));
        $inviteToken = trim($inviteToken);
        if (!($mysqli instanceof mysqli)) {
            return ['success' => false, 'message' => 'Database connection unavailable.'];
        }
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid invite email address.'];
        }
        if ($organizationId <= 0 || $inviteToken === '') {
            return ['success' => false, 'message' => 'Missing organization invite details.'];
        }

        $organizationName = 'your organization';
        $organizationStmt = $mysqli->prepare("SELECT warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE id = ? LIMIT 1");
        if ($organizationStmt) {
            $organizationStmt->bind_param('i', $organizationId);
            $organizationStmt->execute();
            $organizationRow = $organizationStmt->get_result()->fetch_assoc();
            $organizationStmt->close();
            if (!empty($organizationRow['warehouse_name'])) {
                $organizationName = (string)$organizationRow['warehouse_name'];
            }
        }

        $inviterName = 'Team Admin';
        $inviterStmt = $mysqli->prepare("SELECT full_name FROM `" . DB::USERS . "` WHERE id = ? LIMIT 1");
        if ($inviterStmt) {
            $inviterStmt->bind_param('i', $session_user_id);
            $inviterStmt->execute();
            $inviterRow = $inviterStmt->get_result()->fetch_assoc();
            $inviterStmt->close();
            if (!empty($inviterRow['full_name'])) {
                $inviterName = (string)$inviterRow['full_name'];
            }
        }

        $acceptUrl = rtrim((string)$admin_base_url, '/') . '/organization_accept_invite.php?token=' . rawurlencode($inviteToken);
        $subjectPrefix = $mode === 'resent' ? 'Reminder:' : 'You are invited to join';
        $subject = $subjectPrefix . ' ' . $organizationName;

        $body = '<p>Hello,</p>'
            . '<p><strong>' . htmlspecialchars($inviterName, ENT_QUOTES, 'UTF-8') . '</strong> has invited you to join <strong>'
            . htmlspecialchars($organizationName, ENT_QUOTES, 'UTF-8') . '</strong> on HAIZON.</p>'
            . '<p><a href="' . htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block;padding:10px 16px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:4px;">Accept Organization Invite</a></p>'
            . '<p>If the button does not work, copy this link into your browser:<br>'
            . htmlspecialchars($acceptUrl, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p>This invite link will expire in ' . ORGANIZATION_INVITE_EXPIRY_DAYS . ' days.</p>';

        $headers = [
            'X-Invite-Type' => 'organization',
            'X-Organization-Id' => (string)$organizationId,
            'X-Invite-Mode' => $mode,
            'X-Invite-Id' => (string)max(0, $inviteId),
            'X-Invite-Token' => $inviteToken,
        ];

        $queue = new EmailQueue();
        $queueId = $queue->enqueue($recipientEmail, $subject, $body, $headers, 1);

        if (!$queueId) {
            return ['success' => false, 'message' => 'Unable to queue invite email at this time.'];
        }

        return ['success' => true, 'queue_id' => (int)$queueId];
    }
}

$entitlementCacheTtl = ENTITLEMENT_CACHE_TTL;
$entitlementsCachedAt = (int)($_SESSION[$project_pre]['DASHBOARD']['system_entitlements_cached_at'] ?? 0);
$entitlementExpired = ($entitlementsCachedAt <= 0) || ((time() - $entitlementsCachedAt) >= $entitlementCacheTtl);

if ($entitlementExpired) {
    $dashboardSession = $_SESSION[$project_pre]['DASHBOARD'] ?? [];
    $resolvedFeatures = SystemEntitlements::resolveFeatureSnapshotForDashboardUser($mysqli, is_array($dashboardSession) ? $dashboardSession : []);
    $resolvedEntitlements = SystemEntitlements::resolveForDashboardUser($mysqli, is_array($dashboardSession) ? $dashboardSession : []);
    $_SESSION[$project_pre]['DASHBOARD']['subscription_features'] = $resolvedFeatures;
    $_SESSION[$project_pre]['DASHBOARD']['system_entitlements'] = $resolvedEntitlements;
    $_SESSION[$project_pre]['DASHBOARD']['system_entitlements_cached_at'] = time();
}

/*
|--------------------------------------------------------------------------
| Http Layer — Request, Kernel, Middleware (Phase 1 Migration)
|--------------------------------------------------------------------------
| Register the new Http namespace classes. Page files can optionally use
| $kernel->handle(Request::fromGlobals()) once controllers are created.
*/

$container->register(\App\Http\Controller\HrGuideController::class, function (\App\Core\Container $c) {
    return new \App\Http\Controller\HrGuideController(
        $c->get(\App\Core\Database::class),
        Session::userId(),
        Session::roleId(),
        Session::orgId()
    );
});

$container->register(\App\Http\Request::class, function () {
    return \App\Http\Request::fromGlobals();
});

$container->register(\App\Http\Kernel::class, function (\App\Core\Container $c) {
    $kernel = new \App\Http\Kernel();

    $kernel->addMiddleware(new \App\Http\Middleware\CsrfMiddleware());
    $kernel->addMiddleware(new \App\Http\Middleware\AuthMiddleware($project_pre));

    return $kernel;
});
