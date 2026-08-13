<?php

use App\Core\DB;
use App\Core\Session;
use App\Security\InputValidator;
include('admin_elements/admin_header.php');
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/InputValidator.php';

$module = 'invoices';
$module_caption = 'Invoice';
$module_id = getModuleIdBySlug($module, $mysqli);
$tbl_name = DB::INVOICES;  // Invoices table
$error_message = '';
$success_message = '';
$page = $_GET['page'] ?? '';

//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require '../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| GENEATE QR CODE AND PDF BOOKING
|--------------------------------------------------------------------------
|
*/
// --- Get From DB where qrcode=''
?>
<!-- <img src="generate_qrcode.php" alt=""> -->
<!-- <img src="generate.php?code=12345" alt=""> -->

<iframe src="generate_invoice_qrcode.php" width="1" height="1"></iframe>

<?php
// --- Get From DB where pdf=''
// include_once('pdf_invoice.php');

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();


/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
| Validate CSRF token for all POST requests
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in listing_invoices.php', 'WARNING', __FILE__, __LINE__);
        // Prevent further execution
        $action = '';
    }
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
if (($action == "delete_$module" && !empty($id)) && granted('delete', $module_id)) {

    // CSRF: enforce a valid token for the delete action (GET or POST)
    if (!validate_csrf_token($_GET['csrf_token'] ?? $_POST['csrf_token'] ?? '')) {
        log_error('CSRF token validation failed for delete in listing_invoices.php', 'WARNING', __FILE__, __LINE__);
        flash_error('Invalid security token. Please refresh the page and try again.');
    } else {
    // INPUT VALIDATION: Validate invoice ID
    $idResult = InputValidator::integer($id, 1);
    if (!$idResult['valid']) {
        flash_error("Invalid invoice ID: " . $idResult['error']);
    } else {
        $invoiceId = $idResult['value'];
        
        try {
            $invoiceService = \App\Core\Container::getInstance()->get(\App\Service\InvoiceService::class);
            $invoice = $invoiceService->getInvoice($invoiceId, $activeOrganizationId);

            // IDOR PROTECTION: Check ownership (unless system admin / full access)
            $canDelete = has_full_access() || ($invoice->createdBy === (int)Session::userId());
            
            if (!$canDelete) {
                flash_error("You do not have permission to delete this invoice");
                log_error("IDOR attempt: User Session::userId() tried to delete invoice $invoiceId", 'WARNING', __FILE__, __LINE__);
            } else {
                if ($invoiceService->deleteInvoice($invoiceId, $activeOrganizationId)) {
                    $success_message = "$module_caption Deleted Successfully.";
                    flash_success($success_message);
                    header("Location:listing_$module.php?page=$page");
                    exit;
                } else {
                    flash_error("Could not delete record. It may have already been deleted.");
                }
            }
        } catch (\Throwable $e) {
            log_error('Delete failed for invoice: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'invoices', 'action' => 'delete', 'id' => (int)($id ?? 0)]);
            flash_error($e->getMessage());
        }
    }
    }
}



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
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
                    <a href="invoices.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
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
                    <thead>
                        <tr>
                            <th class="ps-4">Invoice Info</th>
                            <th>Customer</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">Balance Due</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Days Overdue</th>
                            <th>Dates</th>
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
            { data: null }, // col 0: Invoice Info
            { data: null }, // col 1: Customer
            { data: 6, className: 'text-end' }, // col 2: Amount
            { data: 7, className: 'text-end' }, // col 3: Balance Due
            { data: 4, className: 'text-center' }, // col 4: Status
            { data: 9, className: 'text-center' }, // col 5: Days Overdue
            { data: null }, // col 6: Dates
        ],
        columnDefs: [
            {
                targets: 0,
                render: function(data, type, row) {
                    var invNo = row[1] || '';
                    var orderNo = row[2] || '';
                    var id = row[8] || '';
                    var html = '<div class="d-flex flex-column">';
                    html += '<a href="invoice_overview.php?invoice_id=' + id + '" class="text-primary text-decoration-none hover-primary">' + invNo + '</a>';
                    if (orderNo) {
                        html += '<span class="text-muted fs-8">SO: ' + orderNo + '</span>';
                    }
                    html += '</div>';
                    return html;
                }
            },
            {
                targets: 1,
                render: function(data, type, row) {
                    var custName = row[3] || '';
                    var id = row[8] || '';
                    return '<div class="d-flex flex-column">' +
                           '<a href="invoice_overview.php?invoice_id=' + id + '" class="text-dark text-decoration-none hover-primary">' + custName + '</a>' +
                           '</div>';
                }
            },
            {
                targets: 3,
                render: function(data, type, row) {
                    var balanceText = row[7] || '';
                    var numericVal = parseFloat(balanceText.replace(/[^0-9.-]+/g, '')) || 0;
                    var textClass = numericVal > 0 ? 'text-danger' : 'text-success';
                    return '<span class="' + textClass + '">' + balanceText + '</span>';
                }
            },
            {
                targets: 4,
                render: function(data, type, row) {
                    var status = (row[4] || '').toLowerCase().trim();
                    var badgeClass = 'bg-secondary text-dark bg-opacity-10';
                    
                    if (status === 'paid') badgeClass = 'bg-success bg-opacity-10 text-success';
                    else if (status === 'unpaid' || status === 'overdue') badgeClass = 'bg-danger bg-opacity-10 text-danger';
                    else if (status === 'draft') badgeClass = 'bg-warning bg-opacity-10 text-warning';
                    else if (status === 'sent') badgeClass = 'bg-info bg-opacity-10 text-info';
                    
                    return '<span class="badge ' + badgeClass + ' px-2.5 py-1.5 rounded-pill text-uppercase fs-8">' + status + '</span>';
                }
            },
            {
                targets: 5,
                render: function(data, type, row) {
                    var days = parseInt(row[9]) || 0;
                    var status = (row[4] || '').toLowerCase().trim();
                    if (status === 'paid') {
                        return '<span class="text-muted fs-7">-</span>';
                    }
                    if (days > 0) {
                        return '<span class="badge bg-danger bg-opacity-10 text-danger px-2.5 py-1 rounded fs-8">' + days + ' Days Overdue</span>';
                    } else if (days < 0) {
                        return '<span class="text-success fs-7">Due in ' + Math.abs(days) + ' days</span>';
                    } else {
                        return '<span class="text-warning fs-7">Due Today</span>';
                    }
                }
            },
            {
                targets: 6,
                render: function(data, type, row) {
                    var invDate = row[0] || '';
                    var dueDate = row[5] || '';
                    var htmlDate = '';
                    var htmlDue = '';
                    if (invDate && invDate !== '-') {
                        htmlDate = '<span class="text-dark"><span class="text-muted me-1">Issued:</span>' + invDate + '</span>';
                    } else {
                        htmlDate = '<span class="text-muted">Issued: -</span>';
                    }
                    if (dueDate && dueDate !== '-') {
                        htmlDue = '<span class="text-dark mt-0.5"><span class="text-muted me-1">Due:</span>' + dueDate + '</span>';
                    } else {
                        htmlDue = '<span class="text-muted mt-0.5">Due: -</span>';
                    }
                    return '<div class="d-flex flex-column fs-7">' + htmlDate + htmlDue + '</div>';
                }
            },
        ],
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        dom: "<'dt-header d-flex justify-content-between align-items-center mb-3'<'dt-head-left'f><'dt-head-right'l>>rt<'dt-footer d-flex justify-content-between align-items-center mt-3'<'dt-foot-left'i><'dt-foot-right'p>>"
    });

});
</script>

<?php include('admin_elements/admin_footer.php'); ?>

