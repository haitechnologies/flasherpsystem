<?php
    
use App\Core\DB;
use App\Security\Roles;
// ======================================================================================
    // MODULE PERMISSION CHECKER
    // ======================================================================================
    // Checks if user has any permission (view/create/edit/delete) to access current module
    // Requires: $module variable to be defined before including this file
    // ======================================================================================

    // Ensure $module is defined (get from current page or use default)
    if (!isset($module)) {
        $module = $current_page ?? 'unknown';
    }

    if (!function_exists('resolveModuleSystemKey')) {
        function resolveModuleSystemKey(string $module): ?string
        {
            static $systemMap = [
                // CRM
                'leads' => 'crm',
                'projects' => 'crm',
                'jobs' => 'crm',
                'job_statuses' => 'crm',

                // Accounting
                'accounts' => 'accounting',
                'quotations' => 'accounting',
                'sale_orders' => 'accounting',
                'sale_types' => 'accounting',
                'customers' => 'accounting',
                'invoices' => 'accounting',
                'payments_received' => 'accounting',
                'credit_notes' => 'accounting',
                'vendors' => 'accounting',
                'expenses' => 'accounting',
                'purchase_orders' => 'accounting',
                'purchases' => 'accounting',
                'payments_made' => 'accounting',
                'debit_notes' => 'accounting',
                'banks' => 'accounting',
                'journals' => 'accounting',
                'customer_transactions' => 'accounting',
                'customer_statement' => 'accounting',

                // HR
                'departments' => 'hr',
                'designations' => 'hr',
                'user_documents' => 'hr',
                'attendance' => 'hr',
                'attendance_devices' => 'hr',
                'leave_requests' => 'hr',
                'leave_types' => 'hr',
                'payroll_components' => 'hr',
                'salary_structures' => 'hr',
                'employee_salaries' => 'hr',
                'payroll_runs' => 'hr',
                'payslips' => 'hr',
                'report_hr' => 'hr',
                'hr_guide' => 'hr',
                'air_tickets' => 'hr',
                'document_categories' => 'hr',
                'gratuity_settlements' => 'hr',
                'email_history' => 'hr',

                // Shipping
                'shipping_advices' => 'shipping',
                'shipping_invoices' => 'shipping',
                'shipping_stocks' => 'shipping',
                'shipping_customers' => 'shipping',
                'ports' => 'shipping',
                'carriers' => 'shipping',
                'consignees' => 'shipping',
                'shippers' => 'shipping',
            ];

            $module = strtolower(trim($module));
            return $systemMap[$module] ?? null;
        }
    }

    if (function_exists('backend_log_coverage_heartbeat') && !empty($module)) {
        backend_log_coverage_heartbeat([
            'module' => (string)$module,
            'module_slug' => (string)$module,
            'entrypoint_type' => 'page',
        ]);
    }

    $requiredSystem = resolveModuleSystemKey((string)$module);
    if ($requiredSystem !== null && function_exists('dashboardHasSystemAccess') && !dashboardHasSystemAccess($requiredSystem)) {
        $error_message = 'Your active subscription does not include access to the ' . ucfirst($requiredSystem) . ' system.';

        if (function_exists('log_error')) {
            log_error('System entitlement denied module access', 'WARNING', __FILE__, __LINE__, [
                'system' => $requiredSystem,
                'module' => $module,
                'user_id' => $_SESSION[$project_pre]['DASHBOARD']['user_id'] ?? 'unknown',
                'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            ]);
        }

        $required_role_ids = null;
        $forbidden_page = __DIR__ . '/403_forbidden.php';
        if (file_exists($forbidden_page)) {
            include($forbidden_page);
        } else {
            http_response_code(403);
            echo '<!DOCTYPE html>';
            echo '<html><head><title>403 - Access Forbidden</title></head><body>';
            echo '<h1>403 - Access Forbidden</h1>';
            echo '<p>' . htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<a href="index.php">Go to Dashboard</a>';
            echo '</body></html>';
        }
        exit;
    }

    // Get Module ID based on module slug
    $module_id = getModuleIdBySlug($module, $mysqli);

    // System Admin and Super Admin have full access to all modules
    if (Roles::currentUserHasFullAccess()) {
        // Full access granted - no further checks needed
        
    } else {
        
        // ---------------------------------------------------------------------
        // Check if user has ANY permission to this module
        // User needs at least one of: view, create, edit, or delete permission
        // ---------------------------------------------------------------------
        
        $has_permission = false;
        
        if (isset($module_id)) {
            // Check each permission type
            $has_permission = granted('view', $module_id) 
                           || granted('create', $module_id) 
                           || granted('edit', $module_id) 
                           || granted('delete', $module_id);
        }
        
        // User has no permissions to this module
        if (!$has_permission) {
            
            // Log unauthorized module access attempt
            if (function_exists('log_error')) {
                $module_name = getTableAttrv('module_name', DB::MODULES, " id = '" . $module_id . "'") ?? $module;
                
                log_error(
                    'Unauthorized module access attempt', 
                    'WARNING', 
                    $_SERVER['PHP_SELF'] ?? 'unknown', 
                    0,
                    [
                        'user_id' => $_SESSION[$project_pre]['DASHBOARD']['user_id'] ?? 'unknown',
                        'user_role' => Roles::getName($_SESSION[$project_pre]['DASHBOARD']['role_id'] ?? 0),
                        'module' => $module,
                        'module_id' => $module_id,
                        'module_name' => $module_name,
                        'requested_page' => $_SERVER['REQUEST_URI'] ?? 'unknown',
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                    ]
                );
            }
            
            // Show professional 403 forbidden page
            $error_message = isset($module_id) 
                ? "You don't have permission to access the " . ucwords(str_replace('_', ' ', $module)) . " module"
                : "Module access denied";
            
            $required_role_ids = null; // Module permissions are not role-specific
            
            // Use the 403 forbidden page template
            $forbidden_page = __DIR__ . '/403_forbidden.php';
            if (file_exists($forbidden_page)) {
                include($forbidden_page);
            } else {
                // Fallback error display
                http_response_code(403);
                echo '<!DOCTYPE html>';
                echo '<html><head><title>403 - No Permission</title>';
                echo '<style>body{font-family:Arial;text-align:center;padding:50px;}';
                echo '.error{color:#dc3545;font-size:18px;margin:20px;}</style></head><body>';
                echo '<h1>403 - Access Forbidden</h1>';
                echo '<p class="error">' . htmlspecialchars($error_message) . '</p>';
                echo '<p>Contact your administrator to request access to this module.</p>';
                echo '<a href="index.php">Go to Dashboard</a>';
                echo '</body></html>';
            }
            exit;
        }
    }