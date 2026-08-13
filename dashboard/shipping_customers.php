<?php

require_once __DIR__ . '/admin_elements/error_handler_init.php';

// ── Error Logging Bootstrap ──────────────────────────────────────────
require_once __DIR__ . '/admin_elements/error_logger.php';
if (function_exists('custom_error_handler')) {
    set_error_handler('custom_error_handler');
}
if (function_exists('custom_exception_handler')) {
    set_exception_handler('custom_exception_handler');
}
if (function_exists('handle_fatal_error')) {
    register_shutdown_function('handle_fatal_error');
}
// ─────────────────────────────────────────────────────────────────────

// Compatibility endpoint for a legacy shipping route.
// Canonical page for this module in flasherpsystem is listing_shipping_customers.php.
$GLOBALS['module'] = 'shipping_customers';
header('Location: listing_shipping_customers.php');
exit;

