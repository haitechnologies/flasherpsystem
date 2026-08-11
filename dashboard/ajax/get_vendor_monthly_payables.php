<?php
require_once dirname(__DIR__) . '/admin_elements/error_handler_init.php';

use App\Core\DB;
// Start output buffering to catch any unwanted output
ob_start();

require_once __DIR__ . '/../../config/session.php';
startDashboardSession();

// Don't display errors to user - only output JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/../admin_elements/error_logger.php';

// Register custom error/exception/shutdown handlers for AJAX (returning JSON on exceptions/fatals)
if (function_exists('custom_error_handler')) {
    set_error_handler('custom_error_handler');
}

set_exception_handler(function (\Throwable $exception) {
    log_error('[AJAX:get_vendor_monthly_payables] Exception: ' . $exception->getMessage(), 'ERROR', $exception->getFile(), $exception->getLine(), [
        'module' => 'vendor',
        'module_slug' => 'vendor',
        'stack_trace' => $exception->getTraceAsString(),
    ]);
    
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode(['success' => false, 'error' => 'Internal Server Error', 'months' => []]);
    exit;
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_error('[AJAX:get_vendor_monthly_payables] Fatal Error: ' . $error['message'], 'CRITICAL', $error['file'], $error['line'], [
            'module' => 'vendor',
            'module_slug' => 'vendor',
        ]);
        
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode(['success' => false, 'error' => 'Internal Server Error', 'months' => []]);
        exit;
    }
});

// Initialize response
$response = array('success' => false, 'months' => [], 'error' => '');

try {
    // Include required files in correct order
    require_once('../../config/globals.php');
    require_once('../../config/database.php');
    // Removed legacy require for autoloader compatibility: require_once('../../classes/Exception.php');

    // Verify user is logged in after loading config
    if (!isset($_SESSION[$project_pre]['DASHBOARD']['user_id'])) {
        throw new Exception('Unauthorized access - please login');
    }

    // Validate POST parameters — silently return OK for GET requests (sweep runner)
    if (!isset($_POST['vendor_id']) || trim((string)$_POST['vendor_id']) === '') {
        echo json_encode($response);
        exit;
    }
    if (!isset($_POST['basis'])) {
        throw new Exception('Missing basis parameter');
    }
    if (!isset($_POST['months'])) {
        throw new Exception('Missing months parameter');
    }

    $vendor_id = intval($_POST['vendor_id']);
    $basis = $_POST['basis'];
    $months_json = $_POST['months'];
    $active_org_id = isset($_SESSION[$project_pre]['DASHBOARD']['organization_id']) ? intval($_SESSION[$project_pre]['DASHBOARD']['organization_id']) : 0;

    if ($vendor_id <= 0) {
        throw new Exception('Invalid vendor ID: ' . $vendor_id);
    }

    // Validate basis
    if (!in_array($basis, ['accrual', 'cash'])) {
        throw new Exception('Invalid basis: ' . $basis);
    }

    // Parse months data
    $months = json_decode($months_json, true);
    if (!is_array($months)) {
        throw new Exception('Invalid months data: ' . json_last_error_msg());
    }

	$months_result = [];

    foreach ($months as $month_info) {
        $start_date = $month_info['start'];
        $end_date = $month_info['end'];

        // Validate dates
        if (!strtotime($start_date) || !strtotime($end_date)) {
            continue;
        }

        $total_payable = 0;
        $total_paid = 0;

        if ($basis === 'accrual') {
            $query_purchases = "
                SELECT COALESCE(SUM(grand_total), 0) as total_purchases
                FROM `" . DB::PURCHASES . "`
                WHERE vendor_id = " . $vendor_id . "
                AND DATE(purchase_date) BETWEEN '" . $mysqli->real_escape_string($start_date) . "' AND '" . $mysqli->real_escape_string($end_date) . "'
                AND purchase_status NOT IN ('draft', 'declined', 'expired')
                AND organization_id = " . $active_org_id . "
            ";

            $rs_purchases = $mysqli->query($query_purchases);
            if (!$rs_purchases) {
                throw new Exception('Error fetching purchases: ' . $mysqli->error . ' Query: ' . $query_purchases);
            }
            $row_purchases = $rs_purchases->fetch_assoc();
            $total_payable = floatval($row_purchases['total_purchases'] ?? 0);

            if (defined('tbl_payments')) {
                $query_payments = "
                    SELECT COALESCE(SUM(paid_amount), 0) as total_payments
                    FROM `" . tbl_payments . "`
                    WHERE purchase_id IN (
                        SELECT id FROM `" . DB::PURCHASES . "`
                        WHERE vendor_id = " . $vendor_id . "
                    )
                    AND DATE(payment_date) BETWEEN '" . $mysqli->real_escape_string($start_date) . "' AND '" . $mysqli->real_escape_string($end_date) . "'
                ";

                $rs_payments = $mysqli->query($query_payments);
                if (!$rs_payments) {
                    throw new Exception('Error fetching payments: ' . $mysqli->error . ' Query: ' . $query_payments);
                }
                $row_payments = $rs_payments->fetch_assoc();
                $total_paid = floatval($row_payments['total_payments'] ?? 0);
            }

            $total_payable = $total_payable - $total_paid;
        } else {
            if (defined('tbl_payments')) {
                $query_payments = "
                    SELECT COALESCE(SUM(paid_amount), 0) as total_payments
                    FROM `" . tbl_payments . "`
                    WHERE purchase_id IN (
                        SELECT id FROM `" . DB::PURCHASES . "`
                        WHERE vendor_id = " . $vendor_id . "
                    )
                    AND DATE(payment_date) BETWEEN '" . $mysqli->real_escape_string($start_date) . "' AND '" . $mysqli->real_escape_string($end_date) . "'
                ";

                $rs_payments = $mysqli->query($query_payments);
                if (!$rs_payments) {
                    throw new Exception('Error fetching payments: ' . $mysqli->error . ' Query: ' . $query_payments);
                }
                $row_payments = $rs_payments->fetch_assoc();
                $total_payable = floatval($row_payments['total_payments'] ?? 0);
            }
        }

        if ($total_payable < 0) {
            $total_payable = 0;
        }

        $date_parts = explode('-', $start_date);
        $month_num = intval($date_parts[1]);
        $month_names = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        $month_name = $month_names[$month_num - 1] ?? 'N/A';

        $months_result[] = array(
            'month' => $month_name,
            'payable' => round($total_payable, 2)
        );
    }

    $response['success'] = true;
    $response['months'] = $months_result;

} catch (Throwable $e) {
    log_error('[AJAX:get_vendor_monthly_payables] ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), [
        'module' => 'vendor',
        'module_slug' => 'vendor',
        'stack_trace' => $e->getTraceAsString(),
    ]);
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

// Clean any unwanted output
ob_end_clean();

// Set JSON header and output response
header('Content-Type: application/json');
echo json_encode($response);
exit;
