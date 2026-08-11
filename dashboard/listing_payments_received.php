<?php

declare(strict_types=1);

use App\Core\DB;
use App\Core\Session;
use App\Core\Container;
use App\Service\PaymentReceivedService;
use App\Security\InputValidator;

include('admin_elements/admin_header.php');

$module = 'payments_received';
$module_caption = 'Payment Received';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::PAYMENTS_RECEIVED;
$error_message = '';
$success_message = '';
$page = $_GET['page'] ?? '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_payments_received.php', 'WARNING', __FILE__, __LINE__);
        $action = '';
    }
}

if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        flash_error("Invalid payment ID: " . $idResult['error']);
    } else {
        $paymentId = $idResult['value'];

        try {
            $paymentService = Container::getInstance()->get(PaymentReceivedService::class);
            $payment = $paymentService->getPayment($paymentId, $activeOrganizationId);

            $canDelete = has_full_access() || ($payment->createdBy === (int)Session::userId());
            if (!$canDelete) {
                flash_error("You do not have permission to delete this payment");
                log_error("IDOR attempt: User " . Session::userId() . " tried to delete payment $paymentId", 'WARNING', __FILE__, __LINE__);
            } else {
                $paymentService->deletePayment($paymentId, $activeOrganizationId);
                $success_message = "$module_caption Deleted Successfully.";
                flash_success($success_message);
                header("Location:listing_$module.php?page=$page");
                exit;
            }
        } catch (\Throwable $e) {
            flash_error($e->getMessage());
        }
    }
}
?>

<style>
.hover-primary:hover {
    color: #0d6efd !important;
}
.fs-7 {
    font-size: 0.85rem !important;
}
.fs-8 {
    font-size: 0.75rem !important;
}
.badge.bg-opacity-10 {
    border: 1px solid rgba(0, 0, 0, 0.05);
}
.dropdown-menu {
    border-radius: 8px;
}
.dropdown-item {
    transition: background-color 0.15s ease;
}
.dropdown-item:hover {
    background-color: #f8f9fa;
}
</style>

<div class="content-wrapper">

    <!-- Standardized Navbar/Header -->
    <div class="page-header page-header-light shadow mb-4 carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <!-- Left Side: Heading & Subtitle -->
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                </h1>
            </div>

            <!-- Right Side: Action Buttons -->
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if (empty($hide_add_button) && isset($module_id) && granted('create', $module_id)): ?>
                    <a href="payments_received.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <div class="content datatable-enhanced px-4">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
            <div class="card-body p-0">
                <!-- CSRF Protection Token -->
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="table table-hover align-middle mb-0 custom_datatables datatable-professional display responsive nowrap" width="100%">
                    <thead class="table-light border-bottom text-uppercase fs-8 fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Payment</th>
                            <th>Reference</th>
                            <th>Customer</th>
                            <th>Mode</th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                </table>
</div>
            </div>
        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<script>
$(document).ready(function() {

    window.HAIDatatableInitializer.init('#grid-<?php echo $module; ?>', '<?php echo $module; ?>', {
        stateSave: false,
        deferRender: true,
        retrieve: false,
        ajax: {
            url: 'datatables.php',
            type: 'POST',
            data: function(d) {
                d.csrf_token = window.HAI_CSRF_TOKEN || $('input[name="csrf_token"]').first().val() || '';
                d.action = '<?php echo $action; ?>';
                d.edit_permission = <?php echo granted('edit', $module_id) ? '1' : '0'; ?>;
                d.delete_permission = <?php echo granted('delete', $module_id) ? '1' : '0'; ?>;
                d.session_user_id = '<?php echo Session::userId() ?? ''; ?>';
                d.dt_session_role_id = '<?php echo Session::roleId() ?? ''; ?>';
                return d;
            },
            error: function(xhr, status, error) {
                console.error('[<?php echo ucfirst($module); ?>] DataTable AJAX error:', error);
                console.error('[<?php echo ucfirst($module); ?>] Response:', xhr.responseText);
            }
        },
        columns: [
            { data: null }, // col 0: Payment info
            { data: 2 }, // col 1: Reference
            { data: null }, // col 2: Customer
            { data: 5 }, // col 3: Mode
            { data: 6, className: 'text-end fw-semibold text-dark' }, // col 4: Amount
            { data: 4, className: 'text-center' } // col 5: Status
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, row) {
                    var payDate = row[0] || '-';
                    var payNo = row[1] || '';
                    var id = row[7] || '';
                    var html = '<div class="d-flex flex-column">';
                    html += '<a href="payment_received_overview.php?payment_received_id=' + id + '" class="fw-semibold text-primary text-decoration-none hover-primary">' + payNo + '</a>';
                    html += '<span class="text-muted fs-8">' + payDate + '</span>';
                    html += '</div>';
                    return html;
                }
            },
            {
                targets: 2,
                render: function(data, type, row) {
                    var custName = row[3] || '';
                    var id = row[7] || '';
                    return '<div class="d-flex flex-column">' +
                           '<a href="payment_received_overview.php?payment_received_id=' + id + '" class="text-dark fw-medium text-decoration-none hover-primary">' + custName + '</a>' +
                           '</div>';
                }
            },
            {
                targets: 5,
                render: function(data, type, row) {
                    return row[4] || '<span class="badge bg-secondary bg-opacity-10 text-secondary">Draft</span>';
                }
            },
        ],
        order: [[0, 'desc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: "<'dt-header d-flex justify-content-between align-items-center mb-3'<'dt-head-left'f><'dt-head-right'l>>rt<'dt-footer d-flex justify-content-between align-items-center mt-3'<'dt-foot-left'i><'dt-foot-right'p>>"
    });

});
</script>

<?php include('admin_elements/admin_footer.php'); ?>
