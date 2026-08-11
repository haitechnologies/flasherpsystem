<?php

use App\Core\Container;
use App\Core\DB;
use App\Core\Session;
use App\Service\JournalService;

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


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE id='" . $payment_id . "'");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_payments_received.php");
    exit;
}

// State-change actions are POST + CSRF only
if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid CSRF token. Please try again.');
        header("Location:payment_received_overview.php?payment_received_id=$payment_id");
        exit;
    }
}




/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$payment_status = 0;
if (isset($_REQUEST['payment_status']) && !empty($_REQUEST['payment_status'])) {
    $payment_status   = e_s__($_REQUEST['payment_status']);
}





// ------------------ IF ID DOES NOT EXIST - REDIRECT TO LISTING ----------------
$rs_exists     = $mysqli->query("SELECT id FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE id ='" . $id . "' ");
if ($rs_exists->num_rows == 0) {
    // header("Location:listing_$module.php?error_message=You are not Autorized to view the Record you're trying to access.");
}




/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$payment_status = 0;
if (isset($_REQUEST['payment_status']) && !empty($_REQUEST['payment_status'])) {
    $payment_status   = e_s__($_REQUEST['payment_status']);
}


// IF EMPTY ID - EXIT
if (isset($_REQUEST['payment_id']))     $id     = e_s__($_REQUEST['payment_id']);
if (empty($id) && isset($_REQUEST['payment_received_id'])) $id = e_s__($_REQUEST['payment_received_id']);
// if (empty($id)) header("Location:listing_$module.php");;



$payment_item_id = 0;
if (isset($_REQUEST['payment_item_id']) && !empty($_REQUEST['payment_item_id'])) {
    $payment_item_id     = e_s__($_REQUEST['payment_item_id']);
}


/*
|--------------------------------------------------------------------------
| UPDATE PAYMENT STATUS - MARK AS PAID
|--------------------------------------------------------------------------
|
*/

if (($action == "update_$module" && !empty($payment_id))) {
    
    $new_payment_status = '';
    if (isset($_REQUEST['payment_status']) && !empty($_REQUEST['payment_status'])) {
        $new_payment_status = e_s__($_REQUEST['payment_status']);
    }
    
    if ($new_payment_status == 'paid') {
        // Skip if already paid and journal already posted
        $existing_journal = getTableAttrV('id', DB::JOURNALS, " reference_type='payment_received' AND reference_id='$payment_id' ");
        if (!empty($existing_journal)) {
            flash_success('Payment is already marked as paid.');
            header("Location:payment_received_overview.php?payment_received_id=$payment_id");
            exit;
        }

        // Update payment status to paid
        $mysqli->query("UPDATE `$tbl_name` SET payment_status='paid' WHERE id=$payment_id AND organization_id=$activeOrganizationId");
        
        // Get payment details
        $result_payment = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$payment_id");
        $row_payment = $result_payment->fetch_array();
        
        $payment_date = $row_payment['payment_date'];
        $deposit_to = $row_payment['deposit_to'];
        $total_amount_received = $row_payment['total_amount_received'];
        $customer_id = $row_payment['customer_id'];
        
        // Look up Accounts Receivable dynamically
        $row_ar = $mysqli->query("SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1");
        $accounts_receivable_id = $row_ar && $row_ar->num_rows > 0 ? $row_ar->fetch_array()['id'] : 0;
        
        $journalManager = Container::getInstance()->get(JournalService::class);
        $journal_data = array(
            'journal_date' => $payment_date,
            'journal_status' => 'posted',
            'reference_type' => 'payment_received',
            'reference_id' => (int)$payment_id,
            'reference_no' => $row_payment['payment_no'] ?? '',
            'notes' => 'Payment Received #' . $payment_id,
            'reporting_method' => 'cash',
            'currency' => 'AED'
        );
        $journal_items = array(
            array('account' => (int)$deposit_to, 'debit' => (float)$total_amount_received, 'credit' => 0.0),
            array('account' => (int)$accounts_receivable_id, 'debit' => 0.0, 'credit' => (float)$total_amount_received)
        );
        
        try {
            $journalManager->createJournal($journal_data, $journal_items, (int)$activeOrganizationId, (int)Session::userId());
            $success_message = "Payment marked as paid and journal entry created.";
        } catch (\Throwable $e) {
            $error_message = "Payment marked as paid but journal entry failed: " . $e->getMessage();
        }
        
        flash_success($success_message);
        header("Location:payment_received_overview.php?payment_id=$payment_id");
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| CONVERT TO INVOICE
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($payment_id))) {
    
    // Get payment details
    $result_payment = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$payment_id");
    $row_payment = $result_payment->fetch_array();
    
    $payment_customer_id = $row_payment['customer_id'];
    $payment_amount = $row_payment['total_amount_received'];
    $payment_date = $row_payment['payment_date'];
    $payment_reference = $row_payment['reference_no'];
    
    // Get warehouse from the related invoice
    $warehouse_id = 1; // Default warehouse
    $result_warehouse = $mysqli->query("
        SELECT i.warehouse_id 
        FROM `" . DB::INVOICES . "` i
        INNER JOIN `" . DB::PAYMENT_RECEIVED_ITEMS . "` pri ON i.id = pri.invoice_id
        WHERE pri.payment_id = $payment_id
        LIMIT 1
    ");
    
    if ($result_warehouse && $result_warehouse->num_rows > 0) {
        $row_warehouse = $result_warehouse->fetch_array();
        $warehouse_id = $row_warehouse['warehouse_id'];
    }
    
    // ======================================================
    // INVOICE NO Auto Generation System
    // ======================================================
    $prefix = 'FL-IN' . date('ym');
    $sql = "SELECT invoice_no FROM `" . DB::INVOICES . "` WHERE invoice_no LIKE '{$prefix}-%' ORDER BY invoice_no DESC LIMIT 1";
    $result_invoice_no = $mysqli->query($sql);
    
    if ($row_invoice_no = $result_invoice_no->fetch_assoc()) {
        $last_serial = (int) substr($row_invoice_no['invoice_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        $new_serial = 1;
    }
    
    $invoice_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);
    
    // Create new invoice from payment
    $invoice_subject = 'Invoice from Payment #' . $payment_id;
    $result = $mysqli->query("INSERT INTO `" . DB::INVOICES . "` 
        (customer_id, warehouse_id, subject, reference_no, invoice_no, invoice_date, expiry_date, 
         grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, 
         grand_after_discount, grand_tax, grand_total, invoice_status, is_active, organization_id, created_at, updated_at)
        VALUES 
        ('$payment_customer_id', '$warehouse_id', '$invoice_subject', '$payment_reference', '$invoice_no', '$payment_date', '$payment_date',
         '$payment_amount', 'percentage', '0', '0', '$payment_amount', '0', '$payment_amount', 'draft', '1', '" . (int)$activeOrganizationId . "', NOW(), NOW())");
    
    $new_invoice_id = $mysqli->insert_id;
    fp__(DB::INVOICES, $new_invoice_id);
    
    // Create invoice item from payment
    $result = $mysqli->query("INSERT INTO `" . DB::INVOICE_ITEMS . "` 
        (invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, organization_id, created_at, updated_at, created_by)
        VALUES 
        ('$new_invoice_id', '1', 'Payment Receipt #$payment_id', '1', '$payment_amount', 'percentage', '0', '0', '0', '0', '$payment_amount', '$payment_amount', '" . (int)$activeOrganizationId . "', NOW(), NOW(), '" . Session::userId() . "')");
    
    fp__(DB::INVOICE_ITEMS, $mysqli->insert_id);
    
    $success_message = 'Payment has been converted to Invoice successfully. <a href="invoice_overview.php?invoice_id=' . $new_invoice_id . '"> ' . $invoice_no . '</a>';
    flash_success($success_message);
    header("Location:payment_received_overview.php?payment_id=$payment_id");
    exit;
}


/*
|--------------------------------------------------------------------------
| VOID PAYMENT
|--------------------------------------------------------------------------
|
*/

if (($action == "void_$module" && !empty($payment_id))) {
    
    // Get payment details
    $result_payment = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$payment_id");
    $row_payment = $result_payment->fetch_array();
    
    $payment_date = $row_payment['payment_date'];
    $deposit_to = $row_payment['deposit_to'];
    $total_amount_received = $row_payment['total_amount_received'];
    
    // Check if payment is already paid (has journal entries)
    $journal_id = getTableAttrV('id', DB::JOURNALS, " reference_type='payment_received' AND reference_id='$payment_id' ");
    
    if (!empty($journal_id)) {
        // Payment was paid, create reversing journal entry
        $journalManager = Container::getInstance()->get(JournalService::class);
        
        // Look up Accounts Receivable dynamically
        $row_ar = $mysqli->query("SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1");
        $accounts_receivable_id = $row_ar && $row_ar->num_rows > 0 ? $row_ar->fetch_array()['id'] : 0;
        
        $journal_items = array(
            array('account' => (int)$deposit_to, 'debit' => 0.0, 'credit' => (float)$total_amount_received),
            array('account' => (int)$accounts_receivable_id, 'debit' => (float)$total_amount_received, 'credit' => 0.0)
        );
        
        $journal_data = array(
            'journal_date' => date('Y-m-d'),
            'journal_status' => 'posted',
            'reference_type' => 'payment_received_void',
            'reference_id' => (int)$payment_id,
            'reference_no' => $row_payment['payment_no'] ?? '',
            'notes' => 'Payment Voided #' . $payment_id,
            'reporting_method' => 'cash',
            'currency' => 'AED'
        );
        
        try {
            $journalManager->createJournal($journal_data, $journal_items, (int)$activeOrganizationId, (int)Session::userId());
        } catch (\Throwable $e) {
            $error_message = "Void failed during journal reversal: " . $e->getMessage();
            flash_error($error_message);
            header("Location:payment_received_overview.php?payment_id=$payment_id");
            exit;
        }
    }
    
    // Update payment status to void
    $mysqli->query("UPDATE `$tbl_name` SET payment_status='void' WHERE id=$payment_id AND organization_id=$activeOrganizationId");
    
    $success_message = "Payment has been voided successfully.";
    flash_success($success_message);
    header("Location:payment_received_overview.php?payment_id=$payment_id");
    exit;
}




/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/

$payment_item_id_arr        = array();
$item_id_arr                = array();
$payment_account_arr        = array();
$description_arr            = array();
$total_arr                  = array();



if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows            = e_s__($_POST['total_rows']);
    // if ($total_rows == 0 || $total_rows == '') $total_rows = 1;
} else {
    $total_rows            = 1;
}



/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
*/
if (!empty($id)) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id");
    $row = $result->fetch_array();

    // $invoice_id             = s__($row['invoice_id']);
    // $invoice_no             = getTableAttr('invoice_no', DB::INVOICES, $invoice_id);

    // $invoice_date           = getTableAttr('invoice_date', DB::INVOICES, $invoice_id);
    $invoice_date               = (!empty($invoice_date) ? ddm_($invoice_date) : '');

    // $invoice_amount         = getTableAttr('grand_total', DB::INVOICES, $invoice_id);

    $total_amount_received      = s__($row['total_amount_received']);
    $payment_date               = s__($row['payment_date']);
    $reference_no               = s__($row['reference_no']);
    $payment_no                 = s__($row['payment_no'] ?? '');

    $payment_method             = s__($row['payment_method']);
    $payment_method             = getTableAttr('payment_method', DB::PAYMENT_METHODS, $payment_method);

    // $payment_date           = processDateYtoD($payment_date);
    $payment_date               = s__($row['payment_date']);
    $payment_date               = ddm_($payment_date);

    $customer_id                = s__($row['customer_id']);
    $customer_name              = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);

    $deposit_to                 = s__($row['deposit_to']);
    $deposit_to                 = getTableAttr("account_name", DB::ACCOUNTS, $deposit_to);
    
    $payment_status             = s__($row['payment_status']);
    $is_void                    = ($payment_status === 'void');
    $is_refund                  = ($payment_status === 'refund');

    // ------------------ TOTAL ITEMS ------------------
    // $result_payment_received_items     = $mysqli->query("SELECT * FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE id=$id ORDER BY id");
    // $total_rows                 = $result_payment_received_items->num_rows;


    // if ($total_rows > 0) {
    //     while ($row_payment_received_items = $result_payment_received_items->fetch_array()) {

    //         array_push($payment_received_item_id_arr,       $row_payment_received_items['id']);
    //         array_push($payment_received_account_arr,       $row_payment_received_items['payment_received_account']);
    //         array_push($description_arr,                    $row_payment_received_items['description']);
    //         array_push($total_arr,                          $row_payment_received_items['total']);
    //     }
    // }
}


if ($total_rows == 0)           $total_rows = 1;



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
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
                                $rs_warehouse   = $mysqli->query("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id=1");
                                $row_warehouse  = $rs_warehouse->fetch_array();

                                $warehouse_no       = s__($row_warehouse['warehouse_no']);
                                $warehouse_name     = s__($row_warehouse['warehouse_name']);
                                $street1            = s__($row_warehouse['street1']);
                                $street2            = s__($row_warehouse['street2']);

                                $country            = s__($row_warehouse['country']);
                                $country            = getTableAttr('country_name', DB::GEO_COUNTRIES, $country);

                                $state              = s__($row_warehouse['state']);
                                $state            = getTableAttr('state_name', DB::GEO_STATES, $state);

                                $phone              = s__($row_warehouse['phone']);
                                $email              = s__($row_warehouse['email']);
                                $trn                = s__($row_warehouse['trn']);

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

                        <?php

                        // ------------------------------------------------------------------------------------------------
                        $inv_no = '';
                        $rs_inv = $mysqli->query("SELECT * FROM `" . DB::PAYMENT_RECEIVED_ITEMS . "` WHERE payment_id=$payment_id");
                        // $row = $rs_inv->fetch_array();
                        while ($row_inv = $rs_inv->fetch_array()) {
                            $inv_id   = s__($row_inv['invoice_id']);
                            $inv_no = getTableAttr("invoice_no", DB::INVOICES, $inv_id);
                        }
                        // ------------------------------------------------------------------------------------------------
                        // Note: Variables already loaded earlier in the code (lines 215-240)
                        // Just update invoice_no from the items query; fall back to payment number when no items exist
                        $invoice_no = $inv_no !== '' ? $inv_no : (($payment_no !== '' && $payment_no !== '0') ? $payment_no : '#' . $payment_id);
                        ?>
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
                                    // ------------------------------------------------------------------
                                    $rs_items     = $mysqli->query("SELECT * FROM `" . DB::PAYMENT_RECEIVED_ITEMS . "` WHERE payment_id=$payment_id");
                                    if ($rs_items && $rs_items->num_rows > 0) {
                                        while ($row_items = $rs_items->fetch_array()) {

                                            $invoice_id             = $row_items['invoice_id'];

                                            $invoice_no             = getTableAttr('invoice_no', DB::INVOICES, $invoice_id);
                                            $invoice_date           = getTableAttr('invoice_date', DB::INVOICES, $invoice_id);
                                            $invoice_amount         = getTableAttr('grand_total', DB::INVOICES, $invoice_id);

                                            $amount_received_on     = $row_items['amount_received_on'];
                                            $amount_received        = $row_items['amount_received'];
                                            // ------------------------------------------------------------------
                                    ?>
                                        <tr>
                                            <td><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>"><?php echo $invoice_no; ?></a></td>
                                            <td><?php echo ddm_($invoice_date); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($invoice_amount) ? dec_($invoice_amount) : '0.00'); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($amount_received) ? dec_($amount_received) : '0.00'); ?></td>
                                        </tr>

                                    <?php 
                                        } // while
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
                // Get all journal entries for this payment - both original and void entries
                $rs_all_journals = $mysqli->query("SELECT id, reference_type, journal_date FROM `" . DB::JOURNALS . "` WHERE (reference_type='payment_received' OR reference_type='payment_received_void' OR reference_type='payment_received_refund') AND reference_id='$payment_id' ORDER BY id ASC");
                
                if ($rs_all_journals && $rs_all_journals->num_rows > 0) {
                    while ($row_journal = $rs_all_journals->fetch_array()) {
                        $current_journal_id = $row_journal['id'];
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

                                    //-------------------------------------------------------------------
                                    // -------- JOURNAL ITEMS FOR THIS ENTRY
                                    //-------------------------------------------------------------------

                                    $result_journal_items = $mysqli->query("SELECT * FROM `" . DB::JOURNAL_ITEMS . "` WHERE journal_id=$current_journal_id");
                                    while ($row_journal_items = $result_journal_items->fetch_array()) {

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
                    } // while each journal entry
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