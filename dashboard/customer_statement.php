<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Container;
use App\Core\Session;
use App\Repository\CustomerRepository;
use App\Security\InputValidator;

include('admin_elements/admin_header.php');

$module = 'customers';
$module_caption = 'Customer Statement';
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed', 'WARNING', __FILE__, __LINE__);
    }
}

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;

if ($customer_id > 0) {
    /*
    |--------------------------------------------------------------------------
    | DETAILED STATEMENT VIEW (Zoho-Style)
    |--------------------------------------------------------------------------
    */
    try {
        $container = Container::getInstance();
        $customerRepo = $container->get(CustomerRepository::class);
        $customerData = $customerRepo->find($customer_id, $activeOrganizationId);
        
        if (!$customerData) {
            flash_error('Customer not found.');
            header("Location:customer_statement.php");
            exit;
        }

        // ---- Date Range Filters ----
        $date_filter = $_GET['date_filter'] ?? 'this_month';
        $custom_from = $_GET['custom_from'] ?? '';
        $custom_to = $_GET['custom_to'] ?? '';
        $today = new DateTime();

        switch ($date_filter) {
            case 'today':
                $start_date = $today->format('Y-m-d');
                $end_date = $today->format('Y-m-d');
                $period_label = 'Today';
                break;
            case 'this_week':
                $start_date = (clone $today)->modify('monday this week')->format('Y-m-d');
                $end_date = (clone $today)->modify('sunday this week')->format('Y-m-d');
                $period_label = 'This Week';
                break;
            case 'this_month':
                $start_date = $today->format('Y-m-01');
                $end_date = $today->format('Y-m-t');
                $period_label = 'This Month';
                break;
            case 'last_month':
                $start_date = (new DateTime('first day of last month'))->format('Y-m-d');
                $end_date = (new DateTime('last day of last month'))->format('Y-m-d');
                $period_label = 'Last Month';
                break;
            case 'this_quarter':
                $quarter = ceil((int)$today->format('n') / 3);
                $start_date = $today->format('Y-') . str_pad((string)(($quarter - 1) * 3 + 1), 2, '0', STR_PAD_LEFT) . '-01';
                $end_date = date('Y-m-t', strtotime($today->format('Y-') . str_pad((string)($quarter * 3), 2, '0', STR_PAD_LEFT) . '-01'));
                $period_label = 'This Quarter';
                break;
            case 'this_year':
                $start_date = $today->format('Y-01-01');
                $end_date = $today->format('Y-12-31');
                $period_label = 'This Year';
                break;
            case 'last_year':
                $lastYear = (int)$today->format('Y') - 1;
                $start_date = "$lastYear-01-01";
                $end_date = "$lastYear-12-31";
                $period_label = 'Last Year';
                break;
            case 'custom':
                $start_date = $custom_from ?: $today->format('Y-m-01');
                $end_date = $custom_to ?: $today->format('Y-m-t');
                $period_label = 'Custom Range';
                break;
            case 'all_time':
            default:
                $date_filter = 'all_time';
                $start_date = '2000-01-01';
                $end_date = '2099-12-31';
                $period_label = 'All Time';
                break;
        }

        // ---- Company Info from System Settings ----
        $company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));
        $company_phone = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="phone"'));
        $company_email = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="email"'));
        $company_trn = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="trn"'));
        $company_street1 = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="street1"'));
        $company_street2 = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="street2"'));
        $company_city = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="city"'));
        $company_country = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="country"'));

        $company_address_parts = array_filter([$company_street1, $company_street2, $company_city, $company_country]);
        $company_address = implode(', ', $company_address_parts);

        // ---- Calculate Opening Balance (all transactions BEFORE start date) ----
        $base_opening_balance = (float)($customerData->openingBalance ?? 0);

        $esc_start = $mysqli->real_escape_string($start_date);
        $esc_end = $mysqli->real_escape_string($end_date);

        // Prior invoices
        $prev_inv_query = "SELECT COALESCE(SUM(grand_total), 0) as amt 
            FROM `" . DB::INVOICES . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND invoice_date < '$esc_start' 
            AND invoice_status IN ('sent', 'partially_paid', 'overdue', 'paid')";
        $prev_inv_res = $mysqli->query($prev_inv_query);
        $prev_inv_total = $prev_inv_res ? (float)$prev_inv_res->fetch_object()->amt : 0;

        // Prior payments
        $prev_pay_query = "SELECT COALESCE(SUM(total_amount_received), 0) as amt 
            FROM `" . DB::PAYMENTS_RECEIVED . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND payment_status != 'void'
            AND payment_date < '$esc_start'";
        $prev_pay_res = $mysqli->query($prev_pay_query);
        $prev_pay_total = $prev_pay_res ? (float)$prev_pay_res->fetch_object()->amt : 0;

        // Prior credit notes
        $prev_cn_query = "SELECT COALESCE(SUM(grand_total), 0) as amt 
            FROM `" . DB::CREDIT_NOTES . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND credit_note_date < '$esc_start' 
            AND credit_note_status NOT IN ('draft', 'void')";
        $prev_cn_res = $mysqli->query($prev_cn_query);
        $prev_cn_total = $prev_cn_res ? (float)$prev_cn_res->fetch_object()->amt : 0;

        $opening_balance = $base_opening_balance + $prev_inv_total - $prev_pay_total - $prev_cn_total;

        // ---- Fetch Transactions Within Period ----
        $transactions = [];
        $total_invoiced = 0;
        $total_received = 0;
        $total_credits = 0;

        // Invoices
        $inv_query = "SELECT id, invoice_no as ref_no, invoice_date as trans_date, grand_total as amount, invoice_status as status, 'Invoice' as type 
            FROM `" . DB::INVOICES . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND invoice_date >= '$esc_start' AND invoice_date <= '$esc_end' 
            AND invoice_status IN ('sent', 'partially_paid', 'overdue', 'paid')";
        $inv_res = $mysqli->query($inv_query);
        if ($inv_res) {
            while ($row = $inv_res->fetch_assoc()) {
                $transactions[] = $row;
                $total_invoiced += (float)$row['amount'];
            }
        }

        // Payments Received
        $pay_query = "SELECT id, reference_no as ref_no, payment_date as trans_date, total_amount_received as amount, payment_status as status, 'Payment' as type 
            FROM `" . DB::PAYMENTS_RECEIVED . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND payment_status != 'void'
            AND payment_date >= '$esc_start' AND payment_date <= '$esc_end'";
        $pay_res = $mysqli->query($pay_query);
        if ($pay_res) {
            while ($row = $pay_res->fetch_assoc()) {
                $transactions[] = $row;
                $total_received += (float)$row['amount'];
            }
        }

        // Credit Notes
        $cn_query = "SELECT id, credit_note_no as ref_no, credit_note_date as trans_date, grand_total as amount, credit_note_status as status, 'Credit Note' as type 
            FROM `" . DB::CREDIT_NOTES . "` 
            WHERE customer_id = $customer_id 
            AND organization_id = $activeOrganizationId 
            AND credit_note_date >= '$esc_start' AND credit_note_date <= '$esc_end' 
            AND credit_note_status NOT IN ('draft', 'void')";
        $cn_res = $mysqli->query($cn_query);
        if ($cn_res) {
            while ($row = $cn_res->fetch_assoc()) {
                $transactions[] = $row;
                $total_credits += (float)$row['amount'];
            }
        }

        // Sort by date ascending, then by type (Invoice first)
        usort($transactions, function ($a, $b) {
            $dateCompare = strtotime($a['trans_date']) - strtotime($b['trans_date']);
            if ($dateCompare !== 0) return $dateCompare;
            // Within same date: Invoice first, then Credit Note, then Payment
            $order = ['Invoice' => 1, 'Credit Note' => 2, 'Payment' => 3];
            return ($order[$a['type']] ?? 9) - ($order[$b['type']] ?? 9);
        });

        $balance_due = $opening_balance + $total_invoiced - $total_received - $total_credits;

        // Currency
        $currency_code = defined('BASE_CURRENCY') && isset(BASE_CURRENCY['code']) ? BASE_CURRENCY['code'] : 'AED';

        require __DIR__ . '/views/customer_statement.view.php';
        exit;

    } catch (\Exception $e) {
        $error_message = 'Error loading customer statement.';
        log_error('Error fetching customer for statement: ' . $e->getMessage(), 'ERROR', __FILE__, __LINE__);
    }
}

/*
|--------------------------------------------------------------------------
| LISTING VIEW (No customer_id)
|--------------------------------------------------------------------------
*/
include('admin_elements/breadcrumb.php');
?>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <div class="page-header page-header-light shadow carriers-page-header">
            <h1><?php echo e($module_caption); ?></h1>
        </div>

        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>View Customer Statement</h5>
                        </div>
                        <div class="card-body">
                            <p>Select a customer to view their account statement.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="grid-<?php echo e($module); ?>" class="custom_datatables">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Balance</th>
                                    <th>Last Transaction</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>
<?php include('admin_elements/admin_footer.php'); ?>

<script>
$(document).ready(function() {
    $('#grid-<?php echo e($module); ?>').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'datatables.php',
            type: 'POST',
            data: {
                ajax_action: 'listing_customer_statement',
                csrf_token: $('input[name="csrf_token"]').val()
            }
        },
        columns: [
            {data: 'customer'},
            {data: 'balance'},
            {data: 'last_transaction'},
            {data: 'actions', orderable: false, searchable: false}
        ]
    });
});
</script>
