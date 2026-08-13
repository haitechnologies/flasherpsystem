<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CLI PDF Generator — called by PdfGeneratorHelper via shell_exec
| Eliminates cURL call from send_email.php. Runs in a separate PHP process
| so exit() in the included PDF script does not poison the parent process.
|--------------------------------------------------------------------------
| Usage: php gen_pdf.php --module=quotations --id=1022
*/

$module = '';
$id = 0;

foreach ($GLOBALS['argv'] ?? [] as $arg) {
    if (str_starts_with($arg, '--module=')) {
        $module = substr($arg, 9);
    } elseif (str_starts_with($arg, '--id=')) {
        $id = max(0, (int) substr($arg, 5));
    }
}

if ($module === '' || $id === 0) {
    exit(1);
}

$modules = [
    'quotations'         => ['page' => 'pdf_quotation.php',          'id_param' => 'id'],
    'invoices'           => ['page' => 'pdf_invoice.php',            'id_param' => 'id'],
    'sale_orders'        => ['page' => 'pdf_sale_order.php',         'id_param' => 'id'],
    'credit_notes'       => ['page' => 'pdf_credit_note.php',        'id_param' => 'credit_note_id'],
    'payments_received'  => ['page' => 'pdf_payment_received.php',   'id_param' => 'payment_received_id'],
    'recurring_invoices' => ['page' => 'pdf_recurring_invoice.php',  'id_param' => 'recurring_invoice_id'],
    'purchase_orders'    => ['page' => 'pdf_purchase_order.php',     'id_param' => 'id'],
    'purchases'          => ['page' => 'pdf_purchase.php',           'id_param' => 'id'],
    'debit_notes'        => ['page' => 'pdf_debit_note.php',         'id_param' => 'id'],
    'payments_made'      => ['page' => 'pdf_payment_made.php',       'id_param' => 'id'],
    'expenses'           => ['page' => 'pdf_expense.php',            'id_param' => 'id'],
];

$config = $modules[$module] ?? null;
if ($config === null) {
    exit(1);
}

// Bootstrap a minimal dashboard session so org-scoped PDF pages (which read
// Session::orgId()) resolve the organization in this headless CLI run.
// The system is locked to a single organization (id = 1).
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/admin_elements/error_handler_init.php';
require_once __DIR__ . '/../config/session.php';
startDashboardSession();
$pre = defined('PROJECT_PREFIX') ? PROJECT_PREFIX : 'flasherpsystem';
$_SESSION[$pre]['DASHBOARD']['organization_id'] = 1;

// Set GET params so the included PDF script sees them.
// The token is the same canonical hash each PDF page expects
// (hash("sha512", 'bushogai' . $id)) so generation succeeds headlessly.
$_GET = [
    'mode'               => 'save',
    'token'              => hash('sha512', 'bushogai' . $id),
    $config['id_param']  => (string) $id,
];
$_REQUEST = $_GET;

// Included PDF pages use CWD-relative requires (../config/globals.php,
// admin_elements/error_logger.php). Fix CWD to this script's directory so
// generation works regardless of how the web server invokes shell_exec.
chdir(__DIR__);

require __DIR__ . '/' . $config['page'];
