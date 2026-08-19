<?php

use App\Core\Container;
use App\Core\DB;
use App\Core\Database;
use App\Core\Session;
use App\Security\Roles;

include('admin_elements/admin_header.php');

$module = 'payments_received';
$module_caption = 'Payment Received';
$tbl_name = DB::PAYMENTS_RECEIVED;
$error_message = '';
$success_message = '';

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


$payment_id = '';
if (isset($_REQUEST['payment_id']))        $payment_id     = e_s__($_REQUEST['payment_id']);
if (isset($_POST['payment_id']))           $payment_id     = e_s__($_POST['payment_id']);
if (empty($payment_id) && isset($_REQUEST['payment_received_id'])) $payment_id = e_s__($_REQUEST['payment_received_id']);
if (empty($payment_id) && isset($_REQUEST['id'])) $payment_id = e_s__($_REQUEST['id']);

$db = Container::getInstance()->get(Database::class);
$paymentService = Container::getInstance()->get(\App\Service\PaymentReceivedService::class);

// ------------------ CHECK IF EXISTS (org-scoped, prepared) ----------------
$rs_valid = $db->fetchOne("SELECT id FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE id = :id AND organization_id = :org_id", ['id' => (int)$payment_id, 'org_id' => $activeOrganizationId]);
if (!$rs_valid) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_payments_received.php");
    exit;
}

// Load payment + items via the service (org-scoped DTOs)
try {
    $payment = $paymentService->getPayment((int)$payment_id, $activeOrganizationId);
    $paymentItems = $paymentService->getPaymentItems((int)$payment_id, $activeOrganizationId);
} catch (\Throwable $e) {
    flash_error($e->getMessage());
    header("Location:listing_payments_received.php");
    exit;
}

// ------------------ PERMISSION / IDOR GATES ------------------
// Module-level view gate
if (!granted('view', $module_id)) {
    log_error("IDOR attempt: User " . Session::userId() . " tried to view payment $payment_id without permission", 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => 'view', 'payment_id' => (int)$payment_id]);
    flash_error('You do not have permission to view this payment.');
    header("Location:listing_payments_received.php");
    exit;
}

$canManageRecord = function () use ($payment): bool {
    return Roles::hasFullAccess((int)Session::roleId()) || $payment->createdBy === (int)Session::userId();
};

// State-change actions are POST + CSRF + permission/ownership gated
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        log_error('CSRF token validation failed in payment_received_overview.php', 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => (string)$action, 'payment_id' => (int)$payment_id]);
        flash_error('Invalid CSRF token. Please try again.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }
}


$publish = 1;

$id = $payment_id;


/*
|--------------------------------------------------------------------------
| UPDATE PAYMENT STATUS - MARK AS PAID (delegated to service journal logic)
|--------------------------------------------------------------------------
|
*/
if (($action == "update_$module" && !empty($payment_id))) {

    if (!granted('edit', $module_id) || !$canManageRecord()) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to mark payment $payment_id as paid without permission", 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => 'mark_paid', 'payment_id' => (int)$payment_id]);
        flash_error('You do not have permission to modify this payment.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }

    $new_payment_status = '';
    if (isset($_REQUEST['payment_status']) && !empty($_REQUEST['payment_status'])) {
        $new_payment_status = e_s__($_REQUEST['payment_status']);
    }

    if ($new_payment_status == 'paid') {
        if ($payment->paymentStatus === 'paid') {
            flash_success('Payment is already marked as paid.');
            header("Location:payment_received_overview.php?payment_received_id=$payment_id");
            exit;
        }

        // Skip if a journal already exists for this payment
        $existing_journal = $db->fetchOne("SELECT id FROM `" . DB::JOURNALS . "` WHERE reference_type = :rt AND reference_id = :rid LIMIT 1", ['rt' => 'payment_received', 'rid' => (int)$payment_id]);
        if (!empty($existing_journal)) {
            flash_success('Payment is already marked as paid.');
            header("Location:payment_received_overview.php?payment_received_id=$payment_id");
            exit;
        }

        // Rebuild data from the loaded payment and mark as paid via the service
        // (the service deletes existing journals and posts a bank-charges-aware journal).
        $data = [
            'customer_id' => (string)$payment->customerId,
            'payment_date' => $payment->paymentDate,
            'payment_method' => $payment->paymentMethod !== null ? (string)$payment->paymentMethod : '',
            'deposit_to' => (string)$payment->depositTo,
            'total_amount_received' => (string)$payment->totalAmountReceived,
            'bank_charges' => (string)$payment->bankCharges,
            'reference_no' => (string)$payment->referenceNo,
            'payment_status' => 'paid',
            'publish' => true,
            'is_active' => true,
        ];
        $itemsData = [];
        foreach ($paymentItems as $item) {
            $itemsData[] = [
                'invoice_id' => $item->invoiceId,
                'amount_received' => $item->amountReceived,
                'amount_received_on' => $item->amountReceivedOn,
            ];
        }

        try {
            $paymentService->updatePayment((int)$payment_id, $data, $itemsData, (int)$activeOrganizationId, (int)Session::userId());
            $success_message = "Payment marked as paid and journal entry created.";
        } catch (\Throwable $e) {
            log_error('Payment mark-as-paid failed: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'payments_received', 'action' => 'mark_paid', 'payment_id' => (int)$payment_id]);
            $error_message = "Payment marked as paid failed: " . $e->getMessage();
        }

        if (!empty($error_message)) {
            flash_error($error_message);
        } else {
            flash_success($success_message);
        }
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| REVERSE PAYMENT STATUS - MARK AS UNPAID (remove journal, set to draft)
|--------------------------------------------------------------------------
|
*/
if (($action == "unmark_$module" && !empty($payment_id))) {

    if (!granted('edit', $module_id) || !$canManageRecord()) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to unmark payment $payment_id as unpaid without permission", 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => 'mark_unpaid', 'payment_id' => (int)$payment_id]);
        flash_error('You do not have permission to modify this payment.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }

    if ($payment->paymentStatus !== 'paid') {
        flash_error('Payment is not marked as paid.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }

    try {
        $paymentService->unmarkPaid((int)$payment_id, (int)$activeOrganizationId, (int)Session::userId());
        $success_message = "Payment marked as unpaid and journal entry removed.";
    } catch (\Throwable $e) {
        log_error('Payment mark-as-unpaid failed: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'payments_received', 'action' => 'mark_unpaid', 'payment_id' => (int)$payment_id]);
        $error_message = "Payment marked as unpaid failed: " . $e->getMessage();
    }

    if (!empty($error_message)) {
        flash_error($error_message);
    } else {
        flash_success($success_message);
    }
    header("Location:payment_received_overview.php?payment_received_id=$payment_id");
    exit;
}


/*
|--------------------------------------------------------------------------
| CONVERT TO INVOICE (delegated to InvoiceService::createInvoice)
|--------------------------------------------------------------------------
|
*/
if (($action == "convert_$module" && !empty($payment_id))) {

    if (!granted('create', $module_id) || !$canManageRecord()) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to convert payment $payment_id without permission", 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => 'convert', 'payment_id' => (int)$payment_id]);
        flash_error('You do not have permission to convert this payment.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }

    $payment_customer_id = $payment->customerId;
    $payment_amount = $payment->totalAmountReceived;
    $payment_date = $payment->paymentDate;
    $payment_reference = $payment->referenceNo ?? '';
    if (trim((string)$payment_reference) === '') {
        $payment_reference = ($payment->paymentNo ?? '') !== '' ? $payment->paymentNo : 'PAY-' . $payment_id;
    }

    // Get warehouse from the related invoice (default 1)
    $warehouse_id = 1;
    if (!empty($paymentItems)) {
        $firstItem = $paymentItems[0];
        $row_warehouse = $db->fetchOne("SELECT warehouse_id FROM `" . DB::INVOICES . "` WHERE id = :id", ['id' => $firstItem->invoiceId]);
        if ($row_warehouse && !empty($row_warehouse['warehouse_id'])) {
            $warehouse_id = (int)$row_warehouse['warehouse_id'];
        }
    }

    // Pick a real services item for the invoice line (never hardcode service=1)
    $serviceItem = $db->fetchOne("SELECT id FROM `" . DB::ITEMS . "` WHERE item_type = 'services' AND is_active = 1 AND organization_id = :org_id ORDER BY id ASC LIMIT 1", ['org_id' => $activeOrganizationId]);

    try {
        $invoiceService = Container::getInstance()->get(\App\Service\InvoiceService::class);
        $invoiceData = [
            'customer_id' => (string)$payment_customer_id,
            'invoice_date' => $payment_date,
            'expiry_date' => $payment_date,
            'reference_no' => $payment_reference,
            'warehouse_id' => (string)$warehouse_id,
            'subject' => 'Invoice from Payment #' . $payment_id,
            'grand_subtotal' => (string)$payment_amount,
            'grand_discount_type' => 'percentage',
            'grand_discount_type_value' => '0',
            'grand_discount_amount' => '0',
            'grand_after_discount' => (string)$payment_amount,
            'grand_tax' => '0',
            'grand_total' => (string)$payment_amount,
            'invoice_status' => 'draft',
            'publish' => true,
            'is_active' => true,
        ];
        $itemsData = [
            [
                'service' => (string)($serviceItem['id'] ?? ''),
                'description' => 'Payment Receipt #' . $payment_id,
                'qty' => '1',
                'rate' => (string)$payment_amount,
                'discount_type' => 'percentage',
                'discount_type_value' => '0',
                'discount_amount' => '0',
                'tax' => '0',
                'tax_amount' => '0',
                'sub_total' => (string)$payment_amount,
                'total' => (string)$payment_amount,
            ],
        ];
        $newInvoice = $invoiceService->createInvoice($invoiceData, $itemsData, (int)$activeOrganizationId, (int)Session::userId());
        $new_invoice_id = $newInvoice->id;
        $invoice_no = $newInvoice->invoiceNo;

        $success_message = 'Payment has been converted to Invoice successfully. <a href="invoice_overview.php?invoice_id=' . $new_invoice_id . '"> ' . $invoice_no . '</a>';
        flash_success($success_message);
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    } catch (\Throwable $e) {
        log_error('Payment convert-to-invoice failed: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'payments_received', 'action' => 'convert', 'payment_id' => (int)$payment_id]);
        flash_error('Payment could not be converted to invoice: ' . $e->getMessage());
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| VOID PAYMENT (delegated to service: deletes old journals + reversal)
|--------------------------------------------------------------------------
|
*/
if (($action == "void_$module" && !empty($payment_id))) {

    if (!granted('edit', $module_id) || !$canManageRecord()) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to void payment $payment_id without permission", 'WARNING', __FILE__, __LINE__, ['module' => 'payments_received', 'action' => 'void', 'payment_id' => (int)$payment_id]);
        flash_error('You do not have permission to modify this payment.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }

    try {
        $paymentService->voidPayment((int)$payment_id, (int)$activeOrganizationId, (int)Session::userId());
        $success_message = "Payment has been voided successfully.";
        flash_success($success_message);
    } catch (\Throwable $e) {
        log_error('Payment void failed: ' . $e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'payments_received', 'action' => 'void', 'payment_id' => (int)$payment_id]);
        flash_error("Void failed: " . $e->getMessage());
    }
    header("Location:payment_received_overview.php?payment_received_id=$payment_id");
    exit;
}


/*
|--------------------------------------------------------------------------
| DISPLAY DATA (from service DTOs)
|--------------------------------------------------------------------------
*/
$total_amount_received = $payment->totalAmountReceived;
$payment_date          = $payment->paymentDate;
$reference_no          = $payment->referenceNo ?? '';
$payment_no            = $payment->paymentNo ?? '';
$payment_method_id     = $payment->paymentMethod;
$payment_method        = ($payment_method_id !== null && $payment_method_id !== 0) ? getTableAttr('payment_method', DB::PAYMENT_METHODS, $payment_method_id) : '';
$customer_id           = $payment->customerId;
$customer_name         = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
$deposit_to_id         = $payment->depositTo;
$deposit_to            = getTableAttr('account_name', DB::ACCOUNTS, $deposit_to_id);
$payment_status        = $payment->paymentStatus;
$is_void               = ($payment_status === 'void');
$is_refund             = ($payment_status === 'refund');

$payment_date = ddm_($payment_date);

$total_rows = max(1, count($paymentItems));

$invoice_no = $payment_no !== '' && $payment_no !== '0' ? $payment_no : '#' . $payment_id;
?>


<div class="sidebar sidebar-secondary sidebar-expand-lg">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_payment_received.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_payment_received.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">


                <div class="row p-lg-2">

                    <div class="col-lg-1">
                    </div>

                    <div class="card col-lg-10">

                        <div class="card-body">

                            <div class="row">
                                <?php
                                $warehouse_information = '';
                                $row_warehouse = $db->fetchOne("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id = 1 LIMIT 1");
                                $row_warehouse = $row_warehouse ?? [];

                                $warehouse_no       = s__($row_warehouse['warehouse_no'] ?? '');
                                $warehouse_name     = s__($row_warehouse['warehouse_name'] ?? '');
                                $street1            = s__($row_warehouse['street1'] ?? '');
                                $street2            = s__($row_warehouse['street2'] ?? '');

                                $country            = s__($row_warehouse['country'] ?? '');
                                $country            = !empty($country) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $country) : '';

                                $state              = s__($row_warehouse['state'] ?? '');
                                $state              = !empty($state) ? getTableAttr('state_name', DB::GEO_STATES, $state) : '';

                                $phone              = s__($row_warehouse['phone'] ?? '');
                                $email              = s__($row_warehouse['email'] ?? '');
                                $trn                = s__($row_warehouse['trn'] ?? '');

                                $warehouse_information .= (!empty($warehouse_name) ? '<h5>' . $warehouse_name . '</h5>' : '');
                                $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
                                $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
                                $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
                                $warehouse_information .= (!empty($state) ? $state . ', ' : '');
                                $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
                                $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
                                $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
                                $warehouse_information .= (!empty($trn) ? $trn : '');
                                ?>

                                <div class="mb-4">
                                    <?php echo $warehouse_information; ?>
                                </div>
                            </div>


                            <div class="row text-center">
                                <h6 class="text-muted">PAYMENT RECEIPT:</h6>
                            </div>

                        </div>

                        <div class="table-responsive">
                            <div class="table-responsive">
<table class="table">
                                <tbody>
                                    <tr>
                                        <td>
                                            <table class="table table-responsive">
                                                <tr>
                                                    <td width="200">Payment Date</td>
                                                    <td class="fw-semibold"><?php echo $payment_date; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Reference Number</td>
                                                    <td class="fw-semibold"><?php echo $reference_no; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Payment Mode</td>
                                                    <td class="fw-semibold"><?php echo $payment_method; ?></td>
                                                </tr>
                                            </table>
</div>
                                        </td>
                                        <td class="<?php echo $is_void ? 'bg-danger' : ($is_refund ? 'bg-warning' : 'bg-success'); ?>">
                                            <p class="text-white text-center">
                                                <?php if ($is_void) { ?>
                                                    <span class="badge bg-danger me-2">VOID</span><br />
                                                <?php } elseif ($is_refund) { ?>
                                                    <span class="badge bg-warning me-2">REFUND</span><br />
                                                <?php } ?>
                                                <?php echo $is_void ? 'Voided Amount' : ($is_refund ? 'Refunded Amount' : 'Amount Received'); ?>
                                            </p>
                                            <h5 class="text-white text-center"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($total_amount_received); ?></h5>
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>


                        <div class="row p-lg-4">
                            <h6 class="text-muted">Received From</h6>
                            <h6 class="text-muted"><a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>"><?php echo $customer_name; ?></a></h6>
                        </div>


                        <div class="table-responsive">
                            <div class="table-responsive">
<table class="table">
                                <thead>
                                    <tr>
                                        <th>Invoice Number</th>
                                        <th>Invoice Date</th>
                                        <th class="text-end">Invoice Amount</th>
                                        <th class="text-end">Payment Amount</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php
                                    if (!empty($paymentItems)) {
                                        foreach ($paymentItems as $row_items) {

                                            $invoice_id             = $row_items->invoiceId;

                                            $invoice_no             = getTableAttr('invoice_no', DB::INVOICES, $invoice_id);
                                            $invoice_date           = getTableAttr('invoice_date', DB::INVOICES, $invoice_id);
                                            $invoice_amount         = getTableAttr('grand_total', DB::INVOICES, $invoice_id);

                                            $amount_received_on     = $row_items->amountReceivedOn;
                                            $amount_received        = $row_items->amountReceived;
                                    ?>
                                        <tr>
                                            <td><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo $invoice_no; ?></a></td>
                                            <td><?php echo ddm_($invoice_date); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($invoice_amount) ? dec_($invoice_amount) : '0.00'); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($amount_received) ? dec_($amount_received) : '0.00'); ?></td>
                                        </tr>

                                    <?php 
                                        }
                                    } else {
                                    ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No payment items found</td>
                                        </tr>
                                    <?php
                                    }
                                    ?>

                                </tbody>
                            </table>
</div>
                        </div>

                        <div class="row p-lg-4">
                            <div class="row">
                                <div class="col-lg-3 text-muted">Deposit To:</div>
                                <div class="col-lg-4 text-muted"><?php echo $deposit_to; ?></div>
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-4">
                        <!-- upload receipts -->
                    </div>

                </div>


                <?php
                // ---------------------------------------------------------------------------------------------------------------------------------------
                // Get all journal entries for this payment - both original and void entries (prepared)
                $rs_all_journals = $db->fetchAll("SELECT id, reference_type, journal_date FROM `" . DB::JOURNALS . "` WHERE (reference_type='payment_received' OR reference_type='payment_received_void' OR reference_type='payment_received_refund') AND reference_id = :rid ORDER BY id ASC", ['rid' => (int)$payment_id]);

                if (!empty($rs_all_journals)) {
                    foreach ($rs_all_journals as $row_journal) {
                        $current_journal_id = (int)$row_journal['id'];
                        $journal_reference_type = $row_journal['reference_type'];
                        $journal_date = $row_journal['journal_date'];

                        // Determine journal label and badge color
                        if ($journal_reference_type === 'payment_received_void') {
                            $journal_label = 'Void Entry';
                            $badge_color = 'bg-danger';
                        } elseif ($journal_reference_type === 'payment_received_refund') {
                            $journal_label = 'Refund Entry';
                            $badge_color = 'bg-warning';
                        } else {
                            $journal_label = 'Payment Entry';
                            $badge_color = 'bg-success';
                        }
                ?>

                    <p class="mb-0 opacity-50" id="journal">JOURNAL ENTRIES</p>

                    <div class="card mb-3">
                        <div class="card-header d-flex align-items-center">
                            <p class="mb-0 fw-semibold">
                                Invoice Payment - <?php echo $invoice_no; ?> 
                                <span class="badge <?php echo $badge_color; ?> ms-2">
                                    <?php echo $journal_label; ?>
                                </span>
                            </p>

                            <div class="ms-auto small text-muted">
                                <?php echo ddm_($journal_date); ?> | 
                                Amount is displayed in your base currency <span class="badge bg-success"><?php echo BASE_CURRENCY['code']; ?></span>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <div class="table-responsive">
<table class="table">
                                <thead>
                                    <tr>
                                        <th class="opacity-50">ACCOUNT</th>
                                        <th class="text-end opacity-50">DEBIT</th>
                                        <th class="text-end opacity-50">CREDIT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_debit = 0;
                                    $total_credit = 0;

                                    $result_journal_items = $db->fetchAll("SELECT * FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id = :jid", ['jid' => $current_journal_id]);
                                    foreach ($result_journal_items as $row_journal_items) {

                                        $account    = $row_journal_items['account'];
                                        $account    = getTableAttr('account_name', DB::ACCOUNTS, $account);
                                        $debit      = $row_journal_items['debit'];
                                        $credit     = $row_journal_items['credit'];

                                        $total_debit += $debit;
                                        $total_credit += $credit;
                                    ?>
                                        <tr>
                                            <td><?php echo $account; ?></td>
                                            <td class="text-end"><?php echo dec_($debit); ?></td>
                                            <td class="text-end"><?php echo dec_($credit); ?></td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td></td>
                                        <td class="text-end fw-semibold"><?php echo dec_($total_debit); ?></td>
                                        <td class="text-end fw-semibold"><?php echo dec_($total_credit); ?></td>
                                    </tr>
                                </tbody>
                            </table>
</div>
                        </div>
                    </div>

                <?php
                    } // foreach journal entry
                } // if journals exist
                ?>

            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
