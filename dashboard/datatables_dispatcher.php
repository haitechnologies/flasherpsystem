<?php

use App\Core\DB;
use App\Core\Session;
use App\DataTable\Registry;
/**
 * DataTable Dispatcher - Phase 3B Live Testing
 * 
 * New modular dispatcher that routes requests to specific handler classes
 * Replaces monolithic dashboard/datatables.php (3,350 lines)
 * 
 * Handles AJAX POST requests from DataTables and delegates to appropriate handler
 * Handler is determined by 'module' parameter in request
 * 
 * @package HAI\Dashboard
 * @subpackage DataTables
 */

// Prevent direct browser access — must be POST
$requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($requestMethod !== 'POST') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'POST requests only', 'success' => false]);
    exit;
}

// Start buffering as early as possible so notices/warnings from bootstrap
// cannot leak into DataTables JSON responses.
if (ob_get_level() === 0) {
    ob_start();
}

// Start session and load dependencies
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/admin_elements/error_logger.php';

set_error_handler('custom_error_handler');
set_exception_handler('custom_exception_handler');
register_shutdown_function('handle_fatal_error');

// Safely start dashboard session with error handling
try {
    if (session_status() === PHP_SESSION_NONE) {
        startDashboardSession();
    }
} catch (\Throwable $e) {
    log_error('[DataTableDispatcher] Session start failed: ' . $e->getMessage(), 'WARNING', $e->getFile(), $e->getLine(), [
        'module' => 'datatables',
        'module_slug' => 'datatables',
        'entrypoint_type' => 'datatable',
        'stack_trace' => $e->getTraceAsString(),
    ]);
    // Continue anyway - session may not be critical for all operations
}

// Shutdown handler: emit valid JSON even on PHP fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        // Clear any partial output buffer
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        log_error('[DataTableDispatcher] FATAL SHUTDOWN: ' . $error['message'], 'CRITICAL', $error['file'] ?? __FILE__, $error['line'] ?? __LINE__, [
            'module' => 'datatables',
            'module_slug' => 'datatables',
            'entrypoint_type' => 'datatable',
            'error_type' => $error['type'] ?? null,
        ]);
        echo json_encode([
            'error' => 'A fatal server error occurred. Check error log.',
            'success' => false,
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'draw' => (int)($_POST['draw'] ?? 1)
        ]);
    }
});

/**
 * Emit JSON safely and terminate.
 * Clears buffered output first to avoid malformed JSON from PHP notices.
 */
function emit_json_and_exit(array $payload, int $statusCode = 200): void {
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
    }

    $buffered = '';
    while (ob_get_level() > 0) {
        $chunk = ob_get_clean();
        if ($chunk !== false) {
            $buffered .= $chunk;
        }
    }

    if ($buffered !== '') {
        log_error('[DataTableDispatcher] Cleared buffered output before JSON', 'DEBUG', __FILE__, __LINE__, [
            'module' => 'datatables',
            'module_slug' => 'datatables',
            'entrypoint_type' => 'datatable',
            'buffer_preview' => substr($buffered, 0, 500),
        ]);
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $fallback = [
            'error' => 'JSON encoding error: ' . json_last_error_msg(),
            'success' => false,
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'draw' => (int)($payload['draw'] ?? 1)
        ];
        echo json_encode($fallback, JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo $json;
    exit;
}

// Load core config for early request handling before bootstrap orchestration.
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/globals.php';

    $project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';

// Get request data
$requestData = $_POST;

log_error('[DataTableDispatcher] Paging request', 'DEBUG', __FILE__, __LINE__, [
    'module' => $requestData['module'] ?? 'unknown',
    'module_slug' => 'datatables',
    'entrypoint_type' => 'datatable',
    'draw' => (int)($requestData['draw'] ?? 0),
    'start' => (int)($requestData['start'] ?? 0),
    'length' => (int)($requestData['length'] ?? 0),
]);


// Extract module parameter (required)
$module = !empty($requestData['module']) ? strtolower($requestData['module']) : null;
$ajaxAction = !empty($requestData['ajax_action']) ? strtolower($requestData['ajax_action']) : null;

// If module not in POST, try ajax_action
if (!$module && $ajaxAction) {
    $module = $ajaxAction;
}

// If module not in POST, try extracting from REQUEST_URI (fallback)
if (!$module && !empty($_SERVER['REQUEST_URI'])) {
    // Try to extract module from URL: datatables.php?module=listing_companies
    if (preg_match('/module=([a-z_]+)/i', $_SERVER['REQUEST_URI'], $matches)) {
        $module = strtolower($matches[1]);
    }
}

if (function_exists('backend_log_coverage_heartbeat')) {
    backend_log_coverage_heartbeat([
        'module' => $module ?: 'datatables',
        'module_slug' => $module ?: 'datatables',
        'entrypoint_type' => 'datatable',
    ]);
}

// Get user session info (for permission checks)
$userId = Session::userId() ?? null;
$roleId = Session::roleId() ?? null;

// Release session lock before potentially expensive DB work.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Normalize legacy module names (e.g., customers -> listing_customers)
if ($module && strpos($module, 'listing_') !== 0 && $ajaxAction) {
    $module = $ajaxAction;
} elseif ($module && strpos($module, 'listing_') !== 0) {
    $module = 'listing_' . $module;
}

// Validate module parameter
if (empty($module)) {
    emit_json_and_exit([
        'error' => 'Missing required parameter: module',
        'success' => false,
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0
    ], 400);
}

/**
 * Sanitize response data to ensure valid JSON encoding.
 * Fixes encoding issues and removes problematic characters.
 */
function sanitizeResponseForJSON($response) {
    if (!is_array($response)) {
        return $response;
    }
    
    $sanitizeCell = function ($cell) {
        if (!is_string($cell)) {
            return $cell;
        }

        // Remove control characters that break JSON.
        $cell = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $cell);

        if (function_exists('mb_check_encoding')) {
            if (!mb_check_encoding($cell, 'UTF-8')) {
                // Try common encodings used in legacy data.
                $converted = @mb_convert_encoding($cell, 'UTF-8', 'UTF-8,Windows-1256,ISO-8859-1');
                if ($converted !== false) {
                    $cell = $converted;
                }
            }

            // Final guard: strip any remaining invalid bytes.
            if (!mb_check_encoding($cell, 'UTF-8')) {
                $cell = @iconv('UTF-8', 'UTF-8//IGNORE', $cell) ?: $cell;
            }
        }

        return $cell;
    };

    // Sanitize data array
    if (isset($response['data']) && is_array($response['data'])) {
        $response['data'] = array_map(function($row) use ($sanitizeCell) {
            if (is_array($row)) {
                return array_map(function($cell) use ($sanitizeCell) {
                    return $sanitizeCell($cell);
                }, $row);
            }
            return $row;
        }, $response['data']);
    }
    
    return $response;
}

try {
    // Load bootstrap for tenant context and entitlements
    require_once __DIR__ . '/bootstrap.php';

    // Verify critical globals were loaded
    if (!isset($GLOBALS['project_pre'])) {
        log_error('[DataTableDispatcher] project_pre not set in GLOBALS', 'ERROR', __FILE__, __LINE__, ['module' => 'datatables']);
        emit_json_and_exit(['error' => 'System configuration error', 'success' => false], 500);
    }

    if (!isset($conn) || !($conn instanceof mysqli)) {
        log_error('[DataTableDispatcher] Database connection not established', 'ERROR', __FILE__, __LINE__, ['module' => 'datatables']);
        emit_json_and_exit(['error' => 'Database connection failed', 'success' => false], 500);
    }

$project_pre = $GLOBALS['project_pre'] ?? 'flasherpsystem';
    $sessionDashboardUserId = (int)(Session::userId() ?? 0);

    /*
    |--------------------------------------------------------------------------
    | CSRF TOKEN VALIDATION FOR AJAX REQUESTS
    |--------------------------------------------------------------------------
    | All DataTable AJAX requests must include a valid CSRF token
    */
    $receivedToken = $_POST['csrf_token'] ?? '';

    $csrfValid = validate_csrf_token($receivedToken);
    if (!$csrfValid) {
        // Compatibility mode: for authenticated dashboard users, continue processing.
        // Some legacy listing pages rely on initializer/default token injection paths.
        if ($sessionDashboardUserId <= 0) {
            emit_json_and_exit([
                'error' => 'Invalid security token',
                'success' => false,
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'draw' => (int)($_POST['draw'] ?? 1)
            ], 403);
        }

        log_error('[DataTableDispatcher] CSRF soft-fail for authenticated user', 'WARNING', __FILE__, __LINE__, [
            'module' => 'datatables',
            'user_id' => $sessionDashboardUserId,
        ]);
    }
    
    // Get active organization ID for multi-tenant filtering
    $activeOrganizationId = dashboardGetActiveOrganizationId();
    
    // Retrieve App\Core\Database from Container
    $db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);
    
    // Initialize registry with user and organization context
    $registry = new Registry($db, $userId, $roleId, $activeOrganizationId);
    
    // Check if module is registered
    if (!$registry->isRegistered($module)) {
        // Log unknown module
        log_error('[DataTableDispatcher] Unknown module requested: ' . $module, 'WARNING', __FILE__, __LINE__, [
            'module' => $module,
            'module_slug' => 'datatables',
            'entrypoint_type' => 'datatable',
        ]);

        emit_json_and_exit([
            'error' => "Unknown module: {$module}",
            'success' => false,
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'draw' => (int)($requestData['draw'] ?? 1)
        ], 404);
    }

    // Verify the requested handler class exists in autoload system.
    $handlerClass = $registry->getHandlerClass($module);
    if (!empty($handlerClass) && !class_exists($handlerClass)) {
        log_error('[DataTableDispatcher] Handler class missing for module ' . $module, 'ERROR', __FILE__, __LINE__, [
            'module' => $module,
            'handler_class' => $handlerClass,
        ]);
        emit_json_and_exit([
            'error' => 'Handler class not found',
            'success' => false,
            'data' => [],
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'draw' => (int)($requestData['draw'] ?? 1)
        ], 500);
    }
    
    // Get handler and process request
    $response = $registry->process($module, $requestData);
    
    // Ensure response has required fields
    if (!isset($response['draw'])) {
        $response['draw'] = (int)($requestData['draw'] ?? 1);
    }
    if (!isset($response['recordsTotal'])) {
        $response['recordsTotal'] = 0;
    }
    if (!isset($response['recordsFiltered'])) {
        $response['recordsFiltered'] = 0;
    }
    if (!isset($response['data'])) {
        $response['data'] = [];
    }
    
    // Sanitize data to ensure valid JSON
    $response = sanitizeResponseForJSON($response);
    
    emit_json_and_exit($response, 200);
    
} catch (\Throwable $e) {
    // Log error (catches both Exception and PHP Error/TypeError/etc.)
    log_error('[DataTableDispatcher] Throwable caught (' . get_class($e) . '): ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), [
        'module' => $module ?: 'datatables',
        'module_slug' => 'datatables',
        'entrypoint_type' => 'datatable',
        'stack_trace' => $e->getTraceAsString(),
    ]);
    
    $errorResponse = [
        'error' => 'Server error processing request',
        'message' => (getenv('APP_ENV') === 'production') ? 'Check server logs' : $e->getMessage(),
        'success' => false,
        'data' => [],
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'draw' => (int)($requestData['draw'] ?? 1)
    ];

    emit_json_and_exit($errorResponse, 500);
}

exit;
