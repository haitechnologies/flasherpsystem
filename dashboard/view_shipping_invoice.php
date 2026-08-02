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
// Route old view page requests into the shipping invoice editor in flasherpsystem.
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    header('Location: shipping_invoices.php?action=edit_shipping_invoices&id=' . $id);
    exit;
}

header('Location: listing_shipping_invoices.php');
exit;

