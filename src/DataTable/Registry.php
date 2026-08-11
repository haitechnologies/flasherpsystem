<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;

use function is_array;

/**
 * DataTable Handler Registry
 *
 * Manages registration and loading of DataTable handlers
 * Maps module names to their corresponding handler classes
 *
 * @package App\DataTable
 */
class Registry
{
    /**
     * Handler registry mapping
     * @var array
     */
    private static array $handlers = [];
    private static ?array $tableConfigs = null;

    /**
     * Database connection wrapper
     * @var Database
     */
    private Database $db;

    /**
     * Current user ID
     * @var int|null
     */
    private ?int $userId = null;

    /**
     * Current user role ID
     * @var int|null
     */
    private ?int $roleId = null;

    /**
     * Current organization ID
     * @var int|null
     */
    private ?int $organizationId = null;

    /**
     * Constructor
     *
     * @param mixed $db Database connection
     * @param int|null $userId Current user ID
     * @param int|null $roleId Current user role ID
     * @param int|null $organizationId Current organization ID
     */
    public function __construct(mixed $db, ?int $userId = null, ?int $roleId = null, ?int $organizationId = null)
    {
        if ($db instanceof Database) {
            $this->db = $db;
        } else {
            $this->db = new Database();
        }

        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->organizationId = $organizationId;
        $this->registerDefaultHandlers();
    }

    /**
     * Register a DataTable handler
     *
     * @param string $module Module name (e.g., 'customers', 'invoices')
     * @param string $handlerClass Handler class name (e.g., 'CustomersDataTable')
     * @return void
     */
    public function register(string $module, string $handlerClass): void
    {
        self::$handlers[strtolower($module)] = $handlerClass;
    }

    /**
     * Register multiple handlers at once
     *
     * @param array $handlers Array of ['module' => 'HandlerClass']
     * @return void
     */
    public function registerMultiple(array $handlers): void
    {
        foreach ($handlers as $module => $handlerClass) {
            $this->register($module, $handlerClass);
        }
    }

    /**
     * Get handler for a module
     *
     * @param string $module Module name
     * @return BaseDataTable|null Handler instance or null if not found
     */
    public function getHandler(string $module): ?BaseDataTable
    {
        $module = strtolower($module);

        $this->ensureModuleRegistration($module);

        // Check if module is registered
        if (!isset(self::$handlers[$module])) {
            ErrorCapture::record("DataTable: Unknown module '{$module}'");
            return null;
        }

        $handlerClass = self::$handlers[$module];

        $this->ensureHandlerClassLoaded($handlerClass);

        // Check if class exists
        if (!class_exists($handlerClass)) {
            ErrorCapture::record("DataTable: Handler class '{$handlerClass}' not found for module '{$module}'");
            return null;
        }

        try {
            // Instantiate handler with dependencies
            if ($handlerClass === GenericDataTable::class) {
                $cfgModule = $module;
                if (strpos($module, 'listing_') === 0) {
                    $cfgModule = substr($module, 8);
                }
                $cfg = $this->getTableConfig($cfgModule);
                if ($cfg === null) {
                    ErrorCapture::record("DataTable: No config for generic handler '{$module}'");
                    return null;
                }
                return new GenericDataTable($cfg, $this->db, $this->userId, $this->roleId, $this->organizationId);
            }
            return new $handlerClass($this->db, $this->userId, $this->roleId, $this->organizationId);
        } catch (\Throwable $e) {
            ErrorCapture::record("DataTable: Failed to instantiate handler for module '{$module}' [" . get_class($e) . "]: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Process a DataTable request
     *
     * @param string $module Module name
     * @param array $requestData Request data
     * @return array Response data
     */
    public function process(string $module, array $requestData): array
    {
        // Get handler
        $handler = $this->getHandler($module);

        if (!$handler) {
            return [
                'success' => false,
                'error' => "No handler found for module '{$module}'",
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0
            ];
        }

        // Process request
        return $handler->process($requestData);
    }

    /**
     * Check if a module handler is registered
     *
     * @param string $module Module name
     * @return bool
     */
    public function isRegistered(string $module): bool
    {
        $module = strtolower($module);
        $this->ensureModuleRegistration($module);
        return isset(self::$handlers[$module]);
    }

    /**
     * Get registered handler class name for a module.
     *
     * @param string $module Module name
     * @return string|null Handler class name or null when not registered
     */
    public function getHandlerClass(string $module): ?string
    {
        $module = strtolower($module);
        $this->ensureModuleRegistration($module);
        return self::$handlers[$module] ?? null;
    }

    /**
     * Get list of all registered modules
     *
     * @return array Module names
     */
    public function getRegisteredModules(): array
    {
        return array_keys(self::$handlers);
    }

    /**
     * Register default handlers
     *
     * Registers all available DataTable handlers
     */
    private function registerDefaultHandlers(): void
    {
        // Phase 2 Week 1: Core module handlers (completed)
        $this->register('listing_customers', CustomersDataTable::class);
        $this->register('listing_invoices', InvoicesDataTable::class);
        $this->register('listing_users', UsersDataTable::class);
        $this->register('listing_departments', DepartmentsDataTable::class);

        // Phase 2 Week 2: Category, Content & Setup handlers (completed)
        $this->register('listing_categories', CategoriesDataTable::class);
        $this->register('listing_subcategories', SubcategoriesDataTable::class);
        // $this->register('listing_pages', PagesDataTable::class); // decommissioned
        $this->register('listing_items', ItemsDataTable::class);
        $this->register('listing_hscodes', HSCodesDataTable::class);
        $this->register('listing_roles', RolesDataTable::class);
        $this->register('listing_company_categories', CompanyCategoriesDataTable::class);
        $this->register('listing_organizations', OrganizationsDataTable::class);
        $this->register('listing_setup_sources', SetupSourcesDataTable::class);
        $this->register('listing_setup_statuses', SetupStatusesDataTable::class);
        $this->register('listing_modules', ModulesDataTable::class);

        // Phase 2 Week 2: Email & Audit handlers (completed)
        $this->register('listing_inquiries', InquiriesDataTable::class);
        $this->register('listing_alerts', AlertsDataTable::class);
        $this->register('listing_email_providers', EmailProvidersDataTable::class);
        $this->register('listing_authentication_activity', AuthenticationActivityDataTable::class);

        // Phase 2 Final: Last handlers (completed)
        $this->register('listing_setup_tags', SetupTagsDataTable::class);
        $this->register('listing_banned_words', BannedWordsDataTable::class);
        $this->register('listing_email_history', EmailHistoryDataTable::class);
        $this->register('listing_email_queue', EmailQueueDataTable::class);

        // Phase 4: Master Data & Configuration handlers
        $this->register('listing_geo_countries', GeoCountriesDataTable::class);
        $this->register('listing_geo_states', GeoStatesDataTable::class);
        $this->register('listing_geo_cities', GeoCitiesDataTable::class);
        $this->register('listing_category_hs_codes', CategoryHSCodesDataTable::class);

        // Phase 5: Advanced Features & Automation handlers
        $this->register('listing_system_settings', SystemSettingsDataTable::class);

        // Phase 3 Final: Customer & Payment handlers (completed)
        $this->register('listing_payment_methods', PaymentMethodsDataTable::class);
        $this->register('listing_customer_contacts', CustomerContactsDataTable::class);
        $this->register('listing_customer_addresses', CustomerAddressesDataTable::class);
        $this->register('listing_customer_transactions', CustomerTransactionsDataTable::class);
        $this->register('listing_customer_statement', CustomerStatementDataTable::class);

        // Vendor module sub-listings
        $this->register('listing_vendor_contacts', VendorContactsDataTable::class);
        $this->register('listing_vendor_addresses', VendorAddressesDataTable::class);
        $this->register('listing_vendor_transactions', VendorTransactionsDataTable::class);
        $this->register('listing_vendor_statement', VendorStatementDataTable::class);
        $this->register('listing_vendor_logs', VendorLogsDataTable::class);
        $this->register('listing_vendor_comments', VendorCommentsDataTable::class);

        // Shipping module listings
        $this->register('listing_shipping_advices', ShippingAdvicesDataTable::class);
        $this->register('listing_shipping_invoices', ShippingInvoicesDataTable::class);
        $this->register('listing_shipping_stocks', ShippingStocksDataTable::class);
        $this->register('listing_ports', PortsDataTable::class);
        $this->register('listing_carriers', CarriersDataTable::class);
        $this->register('listing_consignees', ConsigneesDataTable::class);
        $this->register('listing_shippers', ShippersDataTable::class);

        // Phase 6: SEO & Utilities
        $this->register('sitemaps', SitemapsDataTable::class);

        // Security & Spam Prevention
        $this->register('listing_disposable_email_domains', DisposableEmailDomainsDataTable::class);

        // Lead/CRM filtered listings
        $this->register('listing_quotations', QuotationsDataTable::class);

        // Sale Orders
        $this->register('listing_sale_orders', SaleOrdersDataTable::class);
        $this->register('listing_sale_types', SaleTypesDataTable::class);

        // Payments Received
        $this->register('listing_payments_received', PaymentsReceivedDataTable::class);

        // Recurring Invoices
        $this->register('listing_recurring_invoices', RecurringInvoicesDataTable::class);

        // HR & Payroll listings
        $this->register('listing_gratuity_settlements', GratuitySettlementsDataTable::class);
        $this->register('listing_air_tickets', AirTicketsDataTable::class);
        $this->register('listing_annual_leave_entitlements', AnnualLeaveEntitlementsDataTable::class);
    }

    /**
     * Load handlers from configuration
     *
     * @param array $config Configuration array of handlers
     * @return void
     */
    public function loadFromConfig(array $config): void
    {
        $this->registerMultiple($config);
    }

    /**
     * Get handler statistics
     *
     * @return array Statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_handlers' => count(self::$handlers),
            'registered_modules' => $this->getRegisteredModules()
        ];
    }

    /**
     * Resolve handler class using the listing module naming convention.
     */
    private function resolveHandlerClassFromModule(string $module): ?string
    {
        if (strpos($module, 'listing_') !== 0) {
            return null;
        }

        $baseModule = substr($module, 8);
        if ($baseModule === '') {
            return null;
        }

        $classBase = str_replace(' ', '', ucwords(str_replace('_', ' ', $baseModule)));
        $handlerClass = 'App\\DataTable\\' . $classBase . 'DataTable';

        if (!class_exists($handlerClass)) {
            return null;
        }

        return $handlerClass;
    }

    /**
     * Register convention-resolved handlers into the runtime map when available.
     */
    private function ensureModuleRegistration(string $module): void
    {
        if (isset(self::$handlers[$module])) {
            return;
        }

        $resolvedHandlerClass = $this->resolveHandlerClassFromModule($module);
        if ($resolvedHandlerClass !== null && class_exists($resolvedHandlerClass)) {
            $this->register($module, $resolvedHandlerClass);
            return;
        }

        $normalizedModule = $module;
        if (strpos($module, 'listing_') === 0) {
            $normalizedModule = substr($module, 8);
        }

        $config = $this->getTableConfig($normalizedModule);
        if ($config !== null) {
            self::$handlers[strtolower($module)] = GenericDataTable::class;
        }
    }

    private static function loadTableConfigs(): array
    {
        if (self::$tableConfigs === null) {
            $config = (require __DIR__ . '/config.php');
            self::$tableConfigs = is_array($config) ? $config : [];
        }
        return self::$tableConfigs;
    }

    private function getTableConfig(string $module): ?array
    {
        $configs = self::loadTableConfigs();
        return $configs[$module] ?? null;
    }

    /**
     * Load handler class file on demand for convention-based handlers.
     */
    private function ensureHandlerClassLoaded(string $handlerClass): void
    {
        // Handled by Composer autoloading
    }
}
