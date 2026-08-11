<?php
use App\Core\DB;
use App\Core\Session;
use App\Service\JournalService;
include('admin_elements/admin_header.php');

$module = 'payments_made';
$module_caption = 'Payment Made';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

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
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$payment_id = '';
if (isset($_REQUEST['id']))                $payment_id     = e_s__($_REQUEST['id']);
if (isset($_REQUEST['payment_id']))        $payment_id     = e_s__($_REQUEST['payment_id']);
if (isset($_POST['payment_id']))           $payment_id     = e_s__($_POST['payment_id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_payments_made . "` WHERE id='" . $payment_id . "' AND organization_id = " . (int)$activeOrganizationId);
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_payments_made.php");
    exit;
}

// Get action parameter
$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

/*
|--------------------------------------------------------------------------
| VOID PAYMENT (POST Action — CSRF protected)
|--------------------------------------------------------------------------
*/

if (($action == "void_$module" && !empty($payment_id))) {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid security token. Payment was not voided.');
        header("Location: payments_made_overview.php?payment_id=$payment_id");
        exit;
    }

    // Get payment details
    $result_payment = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$payment_id AND organization_id = " . (int)$activeOrganizationId);
    $row_payment = $result_payment->fetch_array();
    
    $payment_status = $row_payment['payment_status'];
    
    if ($payment_status == 'void') {
        flash_error('Payment is already voided.');
        header("Location: payments_made_overview.php?payment_id=$payment_id");
        exit;
    }
    
    $payment_date = $row_payment['payment_date'];
    $paid_from = $row_payment['paid_from'];
    $total_amount_paid = $row_payment['total_amount_paid'];
    $vendor_id = $row_payment['vendor_id'];
    
    // Check if payment has journal entries
    $journal_id = getTableAttrV('id', tbl_journals, " reference_type='payment_made' AND reference_id='$payment_id' ");
    
    if (!empty($journal_id)) {
        // Payment was paid, create reversing journal entry
        
        // Determine A/P account
        $ap_account_id = 2100; // Default Accounts Payable (code)
        $rs_ap = $mysqli->query("SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_code IN ('2100', '2110', '2000') OR account_name LIKE '%Payable%' LIMIT 1");
        if ($rs_ap && $rs_ap->num_rows > 0) {
            $row_ap = $rs_ap->fetch_array();
            if (!empty($row_ap['id'])) {
                $ap_account_id = $row_ap['id'];
            }
        }
        
        // Create reversing journal entry
        $journal_data = array(
            'journal_date' => date('Y-m-d'),
            'journal_status' => 'posted',
            'reporting_method' => 'accrual',
            'reference_type' => 'payment_made_void',
            'reference_id' => (int)$payment_id,
            'currency' => 'AED',
            'warehouse_id' => 0,
            'notes' => 'Void reversal of Payment Made #' . $payment_id
        );
        
        $journal_entries = array(
            // Credit: Accounts Payable (increase liability back)
            array(
                'account' => (int)$ap_account_id,
                'debit' => 0.0,
                'credit' => (float)$total_amount_paid
            ),
            // Debit: Bank/Cash Account (restore asset)
            array(
                'account' => (int)$paid_from,
                'debit' => (float)$total_amount_paid,
                'credit' => 0.0
            )
        );
        
        try {
            $journalManager = \App\Core\Container::getInstance()->get(JournalService::class);
            $journalManager->createJournal($journal_data, $journal_entries, (int)$activeOrganizationId, (int)Session::userId());
        } catch (\Throwable $e) {
            log_error("Journal Void Error for Payment $payment_id: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $module,
                'module_slug' => $module,
                'payment_id' => $payment_id,
            ]));
        }
    }
    
    // Update payment status to void
    $mysqli->query("UPDATE `" . tbl_payments_made . "` SET payment_status='void' WHERE id='$payment_id' AND organization_id = " . (int)$activeOrganizationId);
    
    $success_message = 'Payment has been voided' . (!empty($journal_id) ? ' and reversing journal entry created' : '') . '.';
    flash_success($success_message);
    header("Location: payments_made_overview.php?payment_id=$payment_id");
    exit;
}

//-------------------------------------------------------------------
// -------- ACTION: MARK AS PAID
//-------------------------------------------------------------------
if (isset($_POST['mark_paid']) && !empty($payment_id)) {

    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Payment was not marked as paid.';
    } else {

    // Check if already has journal entry
    $existing_journal_id = getTableAttrV('id', tbl_journals, " reference_type='payment_made' AND reference_id='$payment_id' ");
    
    if (empty($existing_journal_id)) {
        // Get payment data
        $payment_date = getTableAttr('payment_date', tbl_payments_made, $payment_id);
        $total_amount_paid = getTableAttr('total_amount_paid', tbl_payments_made, $payment_id);
        $paid_from = getTableAttr('paid_from', tbl_payments_made, $payment_id);
        $vendor_id = getTableAttr('vendor_id', tbl_payments_made, $payment_id);
        
        // Determine A/P account based on vendor
        $ap_account_id = 2100; // Default Accounts Payable (code)
        $rs_ap = $mysqli->query("SELECT id FROM `" . DB::ACCOUNTS . "` WHERE account_code IN ('2100', '2110', '2000') OR account_name LIKE '%Payable%' LIMIT 1");
        if ($rs_ap && $rs_ap->num_rows > 0) {
            $row_ap = $rs_ap->fetch_array();
            if (!empty($row_ap['id'])) {
                $ap_account_id = $row_ap['id'];
            }
        }
        
        // Create journal entry
        $journal_data = array(
            'journal_date' => $payment_date,
            'journal_status' => 'posted',
            'reporting_method' => 'accrual',
            'reference_type' => 'payment_made',
            'reference_id' => (int)$payment_id,
            'currency' => 'AED',
            'warehouse_id' => 0,
            'notes' => 'Payment Made #' . $payment_id
        );
        
        $journal_entries = array(
            // Debit: Accounts Payable (reduce liability)
            array(
                'account' => (int)$ap_account_id,
                'debit' => (float)$total_amount_paid,
                'credit' => 0.0
            ),
            // Credit: Bank/Cash Account (reduce asset)
            array(
                'account' => (int)$paid_from,
                'debit' => 0.0,
                'credit' => (float)$total_amount_paid
            )
        );
        
        try {
            $journalManager = \App\Core\Container::getInstance()->get(JournalService::class);
            $journalManager->createJournal($journal_data, $journal_entries, (int)$activeOrganizationId, (int)Session::userId());
        } catch (\Throwable $e) {
            $error_message = 'Payment marked as paid but journal creation failed: ' . $e->getMessage();
            log_error("Journal Error for Payment $payment_id: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                'module' => $module,
                'module_slug' => $module,
                'payment_id' => $payment_id,
            ]));
        }
        
        // Update payment status
        $mysqli->query("UPDATE `" . tbl_payments_made . "` SET payment_status='paid' WHERE id='$payment_id' AND organization_id = " . (int)$activeOrganizationId);
        
        $success_message = 'Payment has been marked as PAID and journal entry created.';
        flash_success($success_message);
        header("Location: payments_made_overview.php?payment_id=$payment_id");
        exit;
    } else {
        $error_message = 'Payment is already marked as paid with journal entry.';
    }
    }
}

// Void handler moved to URL action parameter section above


$vendor_id      = getTableAttr('vendor_id', tbl_payments_made, $payment_id);
$display_name   = getTableAttr('display_name', tbl_vendors, $vendor_id);
$approved       = getTableAttr('approved', tbl_vendors, $vendor_id);
$approved_at    = getTableAttr('approved_at', tbl_vendors, $vendor_id);
$publish        = getTableAttr('is_active', tbl_vendors, $vendor_id);
$created_at     = getTableAttr('created_at', tbl_vendors, $vendor_id);
$created_by     = getTableAttr('created_by', tbl_vendors, $vendor_id);

// Get payment data
$result_payment = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id = " . intval($payment_id) . " AND organization_id = " . (int)$activeOrganizationId);
if ($result_payment && $result_payment->num_rows > 0) {
    $payment_row = $result_payment->fetch_array();
    $payment_date = ddm_($payment_row['payment_date']);
    $total_amount_paid = $payment_row['total_amount_paid'];
    $payment_status = $payment_row['payment_status'];
    $bank_charges = $payment_row['bank_charges'];
    $reference_no = $payment_row['reference_no'];
    $payment_method_id = $payment_row['payment_method'];
    $paid_from_account_id = $payment_row['paid_from'];
    
    // Get payment method and account names
    $payment_method = getTableAttr('payment_method', tbl_payment_methods, $payment_method_id);
    $paid_from_account = getTableAttr('account_name', tbl_accounts, $paid_from_account_id);
    
    // Check if void
    $is_void = ($payment_status == 'void');
} else {
    flash_error('Payment not found.');
    header("Location:listing_payments_made.php");
    exit;
}

?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_payment_made.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_payment_made.php'); ?>
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
                                $rs_warehouse   = $mysqli->query("SELECT * FROM `" . tbl_warehouses . "` WHERE id=1");
                                $row_warehouse  = $rs_warehouse->fetch_array();

                                $warehouse_no       = s__($row_warehouse['warehouse_no']);
                                $warehouse_name     = s__($row_warehouse['warehouse_name']);
                                $street1            = s__($row_warehouse['street1']);
                                $street2            = s__($row_warehouse['street2']);

                                $country            = s__($row_warehouse['country']);
                                $country            = getTableAttr('country_name', tbl_geo_countries, $country);

                                $state              = s__($row_warehouse['state']);
                                $state              = getTableAttr('state_name', tbl_geo_states, $state);

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
                                <h6 class="text-muted">PAYMENT MADE:</h6>
                            </div>

                        </div>

                        <?php
                        // ------------------------------------------------------------------------------------------------
                        $purchase_no_list = '';
                        $rs_purch = $mysqli->query("SELECT * FROM `" . tbl_payment_made_items . "` WHERE payment_id=$payment_id");
                        while ($row_purch = $rs_purch->fetch_array()) {
                            $purch_id   = s__($row_purch['purchase_id']);
                            $purchase_no_list = getTableAttr("purchase_no", tbl_purchases, $purch_id);
                        }
                        // ------------------------------------------------------------------------------------------------
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
                                        <td class="<?php echo $is_void ? 'bg-danger' : 'bg-success'; ?>">
                                            <p class="text-white text-center">
                                                <?php if ($is_void) { ?>
                                                    <span class="badge bg-danger me-2">VOID</span><br />
                                                <?php } ?>
                                                <?php echo $is_void ? 'Voided Amount' : 'Amount Paid'; ?>
                                            </p>
                                            <h5 class="text-white text-center"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($total_amount_paid); ?></h5>
                                        </td>
                                    </tr>

                                </tbody>
                            </table>
                        </div>


                        <div class="row p-lg-4">
                            <h6 class="text-muted">Paid To</h6>
                            <h6 class="text-muted"><a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>"><?php echo $display_name; ?></a></h6>
                        </div>


                        <div class="table-responsive">
                            <div class="table-responsive">
<table class="table">
                                <thead>
                                    <tr>
                                        <th>Purchase Number</th>
                                        <th>Purchase Date</th>
                                        <th class="text-end">Purchase Amount</th>
                                        <th class="text-end">Payment Amount</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php
                                    // ------------------------------------------------------------------
                                    $result_items = $mysqli->query("
                                        SELECT pmi.*, p.purchase_no, p.purchase_date, p.grand_total
                                        FROM `" . tbl_payment_made_items . "` pmi
                                        JOIN `" . tbl_purchases . "` p ON pmi.purchase_id = p.id
                                        WHERE pmi.payment_id = " . intval($payment_id) . "
                                        ORDER BY p.id DESC
                                    ");
                                    
                                    if ($result_items && $result_items->num_rows > 0) {
                                        while ($item_row = $result_items->fetch_array()) {

                                            $purchase_id = $item_row['purchase_id'];
                                            $purchase_no = $item_row['purchase_no'];
                                            $purchase_date = $item_row['purchase_date'];
                                            $purchase_amount = $item_row['grand_total'];
                                            $amount_paid = $item_row['amount_paid'];
                                            // ------------------------------------------------------------------
                                    ?>
                                        <tr>
                                            <td><a href="purchase_overview.php?purchase_id=<?php echo $purchase_id; ?>"><?php echo $purchase_no; ?></a></td>
                                            <td><?php echo ddm_($purchase_date); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($purchase_amount) ? dec_($purchase_amount) : '0.00'); ?></td>
                                            <td class="text-end"><?php echo BASE_CURRENCY['code']; ?> <?php echo (!empty($amount_paid) ? dec_($amount_paid) : '0.00'); ?></td>
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
                                <div class="col-lg-3 text-muted">Paid From:</div>
                                <div class="col-lg-4 text-muted"><?php echo $paid_from_account; ?></div>
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
                $rs_all_journals = $mysqli->query("SELECT id, reference_type, journal_date FROM `" . tbl_journals . "` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id='$payment_id' ORDER BY id ASC");
                
                if ($rs_all_journals && $rs_all_journals->num_rows > 0) {
                    while ($row_journal = $rs_all_journals->fetch_array()) {
                        $current_journal_id = $row_journal['id'];
                        $journal_reference_type = $row_journal['reference_type'];
                        $journal_date = $row_journal['journal_date'];
                        
                        // Determine journal label and badge color
                        if ($journal_reference_type === 'payment_made_void') {
                            $journal_label = 'Void Entry';
                            $badge_color = 'bg-danger';
                        } else {
                            $journal_label = 'Payment Entry';
                            $badge_color = 'bg-success';
                        }
                        
                        // Get purchase numbers for this payment
                        $purchase_numbers = [];
                        $rs_purchases = $mysqli->query("
                            SELECT p.purchase_no 
                            FROM `" . tbl_payment_made_items . "` pmi
                            JOIN `" . tbl_purchases . "` p ON pmi.purchase_id = p.id
                            WHERE pmi.payment_id = " . intval($payment_id)
                        );
                        while ($row_purch = $rs_purchases->fetch_array()) {
                            $purchase_numbers[] = $row_purch['purchase_no'];
                        }
                        $purchase_list = implode(', ', $purchase_numbers);
                ?>

                    <p class="mb-0 opacity-50" id="journal">JOURNAL ENTRIES</p>
                    
                    <div class="card mb-3">
                        <div class="card-header d-flex align-items-center">
                            <p class="mb-0 fw-semibold">
                                Vendor Payment - <?php echo $purchase_list; ?> 
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

                                    $result_journal_items = $mysqli->query("SELECT * FROM `" . tbl_journal_items . "` WHERE journal_id=$current_journal_id");
                                    while ($row_journal_items = $result_journal_items->fetch_array()) {

                                        $account    = $row_journal_items['account'];
                                        $account    = getTableAttr('account_name', tbl_accounts, $account);
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

                                    <tr class="fw-semibold border-top border-2">
                                        <td>TOTAL</td>
                                        <td class="text-end"><?php echo dec_($total_debit); ?></td>
                                        <td class="text-end"><?php echo dec_($total_credit); ?></td>
                                    </tr>
                                </tbody>
                            </table>
</div>
                        </div>
                    </div>

                <?php 
                    } // end while journals
                } // end if journals exist
                ?>

            </div>

            <!-- /content area -->

        </div>
        <!-- /inner content -->

    </div>
    <!-- /content wrapper -->


    <?php include('admin_elements/copyright.php'); ?>

</div>

<?php include('admin_elements/admin_footer.php'); ?>
