<?php

use App\Core\DB;
use App\Core\Session;
use App\Service\JournalService;
include('admin_elements/admin_header.php');

$module = 'credit_notes';
$module_caption = 'Credit Note';
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


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$credit_note_id = '';
if (isset($_REQUEST['credit_note_id']))        $credit_note_id     = e_s__($_REQUEST['credit_note_id']);
if (isset($_POST['credit_note_id']))           $credit_note_id     = e_s__($_POST['credit_note_id']);
if (empty($credit_note_id) && isset($_REQUEST['id'])) $credit_note_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_credit_notes . "` WHERE id='" . $credit_note_id . "'");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_credit_notes.php");
    exit;
}


/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$credit_note_status = 0;
if (isset($_REQUEST['credit_note_status']) && !empty($_REQUEST['credit_note_status'])) {
    $credit_note_status   = e_s__($_REQUEST['credit_note_status']);
}


/*
|--------------------------------------------------------------------------
| CONVERT
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($credit_note_id))) {

    // ======================================================
    // NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-CN' . date('ym');

    // Get the last credit_note number for this month
    $sql = "SELECT invoice_no  FROM `" . tbl_credit_notes . "`  WHERE invoice_no LIKE '{$prefix}-%'ORDER BY invoice_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['invoice_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First invoice of the month
        $new_serial = 1;
    }

    // Build new invoice number with zero padding
    $invoice_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Invoice
    $result = $mysqli->query("INSERT INTO `" . tbl_invoices . "` (customer_id, warehouse_id, subject, reference_no, invoice_date, expiry_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, invoice_status, is_active, created_at, updated_at)
    SELECT customer_id, warehouse_id, subject, reference_no, NOW(), NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_credit_notes . "` WHERE id = $invoice_id;");

    $new_invoice_id = $mysqli->insert_id;
    fp__($tbl_name, $new_invoice_id);

    // Update Invoice no
    $mysqli->query("UPDATE `" . tbl_invoices . "` SET invoice_no = '" . $invoice_no . "' WHERE id=$new_invoice_id");

    // -- Invoice Items
    $result = $mysqli->query("INSERT INTO `" . tbl_invoice_items . "` ( invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_invoice_id, service, description, qty, rate, discount_type, discount_type_value, discount_amount, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_invoice_items . "` WHERE invoice_id = $invoice_id");

    fp__(tbl_invoice_items, $mysqli->insert_id);


    $success_message = 'This Invoice has been Converted to Invoice Successfully. Please click here to view. <a href="invoice_overview.php?invoice_id=' . $new_invoice_id . '"> ' . $invoice_no . '</a>';




    /*
|--------------------------------------------------------------------------
| CLONE
|--------------------------------------------------------------------------
|
*/
} else if (($action == "clone_$module" && !empty($credit_note_id))) {

    // ======================================================
    // CREDIT NOTE NO Auto Generation System
    // ======================================================

    // Build the prefix for this month
    $prefix = 'FL-CN' . date('ym');

    // Get the last credit note number for this month
    $sql = "SELECT credit_note_no  FROM `" . tbl_credit_notes . "`  WHERE credit_note_no LIKE '{$prefix}-%'ORDER BY credit_note_no DESC LIMIT 1";
    $result = $mysqli->query($sql);

    if ($row = $result->fetch_assoc()) {
        // Extract the serial part after the dash
        $last_serial = (int) substr($row['credit_note_no'], -4);
        $new_serial = $last_serial + 1;
    } else {
        // First credit note of the month
        $new_serial = 1;
    }

    // Build new credit note number with zero padding
    $credit_note_no = $prefix . '-' . str_pad($new_serial, 4, '0', STR_PAD_LEFT);


    // -- Credit Note
    $result = $mysqli->query("INSERT INTO `" . tbl_credit_notes . "` (customer_id, warehouse_id, credit_note_no, reference_no, invoice_id, credit_note_date, grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, credit_note_status, is_active, created_at, updated_at)
    SELECT customer_id, warehouse_id, '" . $credit_note_no . "', reference_no, invoice_id, NOW(), grand_subtotal, grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount, grand_tax, grand_total, customer_notes, terms_and_conditions, 'draft', is_active, NOW(), NOW() FROM `" . tbl_credit_notes . "` WHERE id = $credit_note_id;");

    $new_cloned_id = $mysqli->insert_id;
    fp__($tbl_name, $new_cloned_id);

    // -- Credit Note Items
    $result = $mysqli->query("INSERT INTO `" . tbl_credit_note_items . "` (credit_note_id, service, description, qty, rate, tax, tax_amount, sub_total, total, created_at, updated_at, created_by) 
    SELECT $new_cloned_id, service, description, qty, rate, tax, tax_amount, sub_total, total, NOW(), NOW(), '" . Session::userId() . "' FROM `" . tbl_credit_note_items . "` WHERE credit_note_id = $credit_note_id");

    fp__(tbl_credit_note_items, $mysqli->insert_id);


    $success_message = 'Credit Note has been cloned successfully. Please click here to view. <a href="credit_note_overview.php?credit_note_id=' . $new_cloned_id . '"> ' . $credit_note_no . '</a>';

    flash_success($success_message);
    header("Location:credit_note_overview.php?credit_note_id=$new_cloned_id");
    exit;





    /*
|--------------------------------------------------------------------------
| UPDATE Credit Note STATUS
|--------------------------------------------------------------------------
|
*/
} else if (($action == "update_$module" && !empty($credit_note_id) && !empty($credit_note_status))) {

    $result = $mysqli->query("UPDATE `$tbl_name` SET credit_note_status = '" . $credit_note_status . "' WHERE id=$credit_note_id");

    if ($result) {
        $success_message = "The $module_caption status has been updated successfully.";



        // ------------ Credit Note Log -------------
        // if (isset($_POST['credit_note_log_comments']) && !empty($_POST['credit_note_log_comments'])) {
        //     $credit_note_log_comments     = e_s__($_POST['credit_note_log_comments']);

        //     $mysqli->query("INSERT INTO `" . tbl_credit_note_logs . "` (credit_note_id, credit_note_status, comments) VALUES ('" . $credit_note_id . "', '" . $credit_note_status . "', '" . $credit_note_log_comments . "'); ");
        //     fp__(tbl_credit_note_logs, $mysqli->insert_id);
        // }


        // =====================================================================
        // CREATE REVERSING JOURNAL ENTRY WHEN STATUS IS 'VOID'
        // =====================================================================
        if ($credit_note_status === 'void') {
            try {
                // Define table names
                $journals_table = defined('tbl_journals') ? tbl_journals : 'fls_journals';
                $journal_items_table = defined('tbl_journal_items') ? tbl_journal_items : 'fls_journal_items';

                // Avoid duplicate void entries
                $void_check = $mysqli->query("SELECT id FROM `{$journals_table}` WHERE reference_type='credit_note_void' AND reference_id={$credit_note_id} LIMIT 1");
                if ($void_check && $void_check->num_rows > 0) {
                    $success_message .= " Note: Void journal entry already exists.";
                } else {
                    // Initialize Journal Manager
                    $journal = new JournalService();
                    
                    // Get credit note data
                    $credit_note_no = getTableAttr('credit_note_no', tbl_credit_notes, $credit_note_id);
                    $grand_subtotal = getTableAttr('grand_subtotal', tbl_credit_notes, $credit_note_id);
                    $grand_total = getTableAttr('grand_total', tbl_credit_notes, $credit_note_id);
                    
                    // Check if original journal entry exists
                    $journal_check = $mysqli->query("SELECT id FROM `{$journals_table}` WHERE reference_type='credit_note' AND reference_id={$credit_note_id} LIMIT 1");
                    
                    if ($journal_check && $journal_check->num_rows > 0) {
                        // Get original journal entries
                        $original_journal = $journal_check->fetch_assoc();
                        $original_journal_id = $original_journal['id'];
                        
                        // Get all original journal items
                        $items_result = $mysqli->query("SELECT account, debit, credit FROM `{$journal_items_table}` WHERE journal_id={$original_journal_id}");
                        
                        // Build reversing entries (swap debit and credit)
                        $reversing_entries = array();
                        while ($item = $items_result->fetch_assoc()) {
                            if ($item['debit'] > 0) {
                                // Original debit becomes credit
                                $reversing_entries[] = array(
                                    'account' => $item['account'],
                                    'amount'  => $item['debit'],
                                    'type'    => 'credit'
                                );
                            }
                            if ($item['credit'] > 0) {
                                // Original credit becomes debit
                                $reversing_entries[] = array(
                                    'account' => $item['account'],
                                    'amount'  => $item['credit'],
                                    'type'    => 'debit'
                                );
                            }
                        }
                        
                        if (count($reversing_entries) > 0) {
                            // Create reversing journal entry
                            $void_journal_result = $journal->createJournalEntry(
                                array(
                                    'reference_type'   => 'credit_note_void',
                                    'reference_id'     => $credit_note_id,
                                    'reference_no'     => $credit_note_no . ' (VOID)',
                                    'journal_date'     => date('Y-m-d'),
                                    'description'      => 'VOID - Reversal of Credit Note #' . $credit_note_no,
                                    'currency'         => 'AED',
                                    'grand_subtotal'   => -$grand_subtotal,
                                    'grand_total'      => -$grand_total,
                                    'reporting_method' => 'accrual'
                                ),
                                $reversing_entries
                            );
                            
                            if ($void_journal_result['success']) {
                                error_log("Void journal entry created: ID {$void_journal_result['journal_id']} for Credit Note {$credit_note_id}");
                                $success_message .= " Reversing journal entry created successfully.";
                            } else {
                                error_log("Failed to create void journal for Credit Note {$credit_note_id}: " . $void_journal_result['message']);
                                $success_message .= " Warning: " . $void_journal_result['message'];
                            }
                        }
                    } else {
                        error_log("No original journal found to reverse for Credit Note {$credit_note_id}");
                        $success_message .= " Note: No journal entry found to reverse.";
                    }
                }
            } catch (Exception $e) {
                error_log("Void journal creation exception for Credit Note {$credit_note_id}: " . $e->getMessage());
                $success_message .= " Note: Reversing journal entry not created (" . $e->getMessage() . ")";
            }
        }

        // =====================================================================
        // CREATE JOURNAL ENTRY WHEN STATUS IS 'OPEN'
        // =====================================================================
        else if ($credit_note_status === 'open') {
            try {
                // Define table names
                $journals_table = defined('tbl_journals') ? tbl_journals : 'fls_journals';
                $journal_items_table = defined('tbl_journal_items') ? tbl_journal_items : 'fls_journal_items';

                // Check if journal entry already exists to avoid duplicates
                $journal_check = $mysqli->query("SELECT id FROM `{$journals_table}` WHERE reference_type='credit_note' AND reference_id={$credit_note_id} LIMIT 1");
                
                if ($journal_check && $journal_check->num_rows > 0) {
                    $success_message .= " Note: Journal entry already exists.";
                } else {
                    // Initialize Journal Manager
                    $journal = new JournalService();
                    
                    // Get credit note data
                    $credit_note_no = getTableAttr('credit_note_no', tbl_credit_notes, $credit_note_id);
                    $customer_id = getTableAttr('customer_id', tbl_credit_notes, $credit_note_id);
                    $grand_subtotal = getTableAttr('grand_subtotal', tbl_credit_notes, $credit_note_id);
                    $grand_tax = getTableAttr('grand_tax', tbl_credit_notes, $credit_note_id);
                    $grand_total = getTableAttr('grand_total', tbl_credit_notes, $credit_note_id);
                    
                    // Get account mappings from config

                    // Accounts for Credit Note
                    // DR: Sales Returns & Allowances (decrease revenue)
                    // CR: Accounts Receivable (decrease what customer owes)
                    $accounts_table = defined('tbl_accounts') ? tbl_accounts : 'fls_accounts';
                    $sales_returns_code = defined('CREDIT_NOTE_SALES_RETURNS') ? CREDIT_NOTE_SALES_RETURNS : '4160';
                    $ar_code = defined('ACCOUNTS_RECEIVABLE') ? ACCOUNTS_RECEIVABLE : '1200';

                    // Lookup account IDs with flexible matching (codes and names)
                    $sales_returns_account = $mysqli->query("
                        SELECT id, account_code FROM `{$accounts_table}`
                        WHERE account_code IN ('{$sales_returns_code}', '4160', '4150')
                           OR account_name LIKE '%Returns%'
                           OR account_name LIKE '%Allowances%'
                        LIMIT 1
                    ")->fetch_assoc();

                    $ar_account = $mysqli->query("
                        SELECT id, account_code FROM `{$accounts_table}`
                        WHERE account_code IN ('{$ar_code}', '1200', '1210', '1100')
                           OR account_name LIKE '%Receivable%'
                        LIMIT 1
                    ")->fetch_assoc();

                    // Auto-create missing accounts from config (if possible)
                    $ensure_account = function ($account_code) use ($mysqli, $accounts_table) {
                        if (empty($account_code) || !function_exists('getAccountInfo')) {
                            return null;
                        }

                        $code_esc = $mysqli->real_escape_string($account_code);
                        $existing = $mysqli->query("SELECT id, account_code FROM `{$accounts_table}` WHERE account_code = '{$code_esc}' LIMIT 1");
                        if ($existing && $existing->num_rows > 0) {
                            return $existing->fetch_assoc();
                        }

                        $info = getAccountInfo($account_code);
                        if (!$info || empty($info['name']) || empty($info['type'])) {
                            return null;
                        }

                        $parent_code_map = array(
                            'Assets'      => '1000',
                            'Liabilities' => '2000',
                            'Equity'      => '3000',
                            'Income'      => '4000',
                            'Expenses'    => '5000'
                        );

                        $parent_id = 0;
                        $parent_label = isset($info['parent']) ? $info['parent'] : null;
                        if (!empty($parent_label) && isset($parent_code_map[$parent_label])) {
                            $parent_code = $parent_code_map[$parent_label];
                            $parent_code_esc = $mysqli->real_escape_string($parent_code);
                            $parent_row = $mysqli->query("SELECT id FROM `{$accounts_table}` WHERE account_code = '{$parent_code_esc}' LIMIT 1");
                            if ($parent_row && $parent_row->num_rows > 0) {
                                $parent_id = (int) $parent_row->fetch_assoc()['id'];
                            } else {
                                $parent_name = $mysqli->real_escape_string($parent_label);
                                $parent_type = $mysqli->real_escape_string($info['type']);
                                $mysqli->query("INSERT INTO `{$accounts_table}` (parent_id, account_type, account_name, account_code, description, level, is_active, created_at, updated_at) VALUES (0, '{$parent_type}', '{$parent_name}', '{$parent_code_esc}', '', 1, 1, NOW(), NOW())");
                                $parent_id = (int) $mysqli->insert_id;
                            }
                        }

                        $name_esc = $mysqli->real_escape_string($info['name']);
                        $type_esc = $mysqli->real_escape_string($info['type']);
                        $desc_value = isset($info['desc']) ? $info['desc'] : '';
                        $desc_esc = $mysqli->real_escape_string($desc_value);
                        $level = $parent_id > 0 ? 2 : 1;

                        $insert_sql = "INSERT INTO `{$accounts_table}` (parent_id, account_type, account_name, account_code, description, level, is_active, created_at, updated_at)
                                       VALUES ({$parent_id}, '{$type_esc}', '{$name_esc}', '{$code_esc}', '{$desc_esc}', {$level}, 1, NOW(), NOW())";

                        if ($mysqli->query($insert_sql)) {
                            return array('id' => $mysqli->insert_id, 'account_code' => $account_code);
                        }

                        return null;
                    };

                    if (!$sales_returns_account) {
                        $sales_returns_account = $ensure_account($sales_returns_code);
                    }

                    if (!$ar_account) {
                        $ar_account = $ensure_account($ar_code);
                    }

                    if ($sales_returns_account && $ar_account) {
                        
                        // Build journal entries
                        $journal_entries = array(
                            array(
                                'account' => $sales_returns_account['id'],
                                'amount'  => $grand_total,
                                'type'    => 'debit'
                            ),
                            array(
                                'account' => $ar_account['id'],
                                'amount'  => $grand_total,
                                'type'    => 'credit'
                            )
                        );
                        
                        // Create journal entry
                        $journal_result = $journal->createJournalEntry(
                            array(
                                'reference_type'   => 'credit_note',
                                'reference_id'     => $credit_note_id,
                                'reference_no'     => $credit_note_no,
                                'journal_date'     => date('Y-m-d'),
                                'description'      => 'Credit Note #' . $credit_note_no . ' - Customer ID: ' . $customer_id,
                                'currency'         => 'AED',
                                'grand_subtotal'   => $grand_subtotal,
                                'grand_total'      => $grand_total,
                                'reporting_method' => 'accrual'
                            ),
                            $journal_entries
                        );
                        
                        if ($journal_result['success']) {
                            error_log("Credit Note journal entry created: ID {$journal_result['journal_id']} for Credit Note {$credit_note_id}");
                            $success_message .= " Journal entry created successfully.";
                        } else {
                            error_log("Failed to create journal for Credit Note {$credit_note_id}: " . $journal_result['message']);
                            $success_message .= " Warning: " . $journal_result['message'];
                        }
                    } else {
                        $missing = array();
                        if (!$sales_returns_account) $missing[] = "Sales Returns ({$sales_returns_code})";
                        if (!$ar_account) $missing[] = "AR ({$ar_code})";
                        $error_msg = "Missing accounts: " . implode(', ', $missing);
                        error_log("Credit Note {$credit_note_id}: {$error_msg}");
                        $success_message .= " Warning: {$error_msg}";
                    }
                }
            } catch (Exception $e) {
                error_log("Journal creation exception for Credit Note {$credit_note_id}: " . $e->getMessage());
                $success_message .= " Note: Journal entry not created (" . $e->getMessage() . ")";
            }
        }


        if ($credit_note_status == 'not_confirmed') {


            // Delete PDF - Next Time System will Generate New with Confirmed Status 
            $pdf        = getTableAttr('pdf', tbl_credit_notes, $credit_note_id);
            unlink("../pdfs_credit_notes/" . $pdf . ".pdf");
            $mysqli->query("UPDATE " . tbl_credit_notes . "  SET pdf = '' WHERE id=$credit_note_id");
        } else if ($credit_note_status == 'confirmed') {


            // Delete PDF - Next Time System will Generate New with Confirmed Status 
            // $pdf        = getTableAttr('pdf', tbl_credit_notes, $credit_note_id);
            // unlink("../pdfs_credit_notes/" . $pdf . ".pdf");
            // $mysqli->query("UPDATE " . tbl_credit_notes . "  SET pdf = '' WHERE id=$credit_note_id");


            // -----------------------------------------------------------------------------------------------------------------------------------------------
            // DELETE TRIPS - IF Credit Note STATUS IS CANCELLED
            // -----------------------------------------------------------------------------------------------------------------------------------------------
        } else if ($credit_note_status == 'cancelled' || $credit_note_status == 'on_hold') {

            // Delete PDF - Next Time System will Generate New 
            // $pdf        = getTableAttr('pdf', tbl_credit_notes, $credit_note_id);
            // unlink("../pdfs_credit_notes/" . $pdf . ".pdf");
            // $mysqli->query("UPDATE " . tbl_credit_notes . "  SET pdf = '' WHERE id=$credit_note_id");


            /* ---------------------- NOTIFICATIONS QUERY ---------------------- */
        }


        // --------------------------------------------------------------------------------
        flash_success($success_message);
        header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
        exit;
        // $error_message = "Sorry! $module Status Could Not Be Updated.";
    } else {
        $error_message = "Sorry! $module Status Could Not Be Updated.";
    }
}





/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/



if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows            = e_s__($_POST['total_rows']);
    // if ($total_rows == 0 || $total_rows == '') $total_rows = 1;
} else {
    $total_rows            = 1;
}



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
    <?php include('admin_elements/sidebar_credit_note.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
         
        <?php include('admin_elements/page_header_credit_note.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php
            /*
                |--------------------------------------------------------------------------
                | EDIT
                |--------------------------------------------------------------------------
                |
                */
            if (!empty($credit_note_id)) {

                $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$credit_note_id");
                $row = $result->fetch_array();

                $customer_id            = s__($row['customer_id']);
                $credit_note_no          = s__($row['credit_note_no']);
                $credit_note_status      = s__($row['credit_note_status']);
                $credit_note_date        = s__($row['credit_note_date']);
                $expiry_date            = s__($row['expiry_date']);
                $reference_no           = s__($row['reference_no']);

                $expected_shipment_date = s__($row['expected_shipment_date']);
                $payment_term           = s__($row['payment_term']);

                $shipment_type          = s__($row['shipment_type']);
                $sales_person           = s__($row['sales_person']);
                $job_reference_no       = s__($row['job_reference_no']);
                $master_awb_no          = s__($row['master_awb_no']);
                $shipper                = s__($row['shipper']);
                $consignee              = s__($row['consignee']);
                $origin                 = s__($row['origin']);
                $destination            = s__($row['destination']);
                $no_of_packs            = s__($row['no_of_packs']);
                $gross_weight           = s__($row['gross_weight']);
                $chargeable_weight      = s__($row['chargeable_weight']);
                $volume                 = s__($row['volume']);

                $customer_notes         = s__($row['customer_notes']);
                $terms_and_conditions   = s__($row['terms_and_conditions']);
                // Seprate Line Number on base of Space new line
                $final_terms_and_conditions = '';

                if (!empty($terms_and_conditions)) {
                    $desc = explode("\r", $terms_and_conditions);
                    $d_counter = 1;
                    if (count($desc) > 0) {
                        foreach ($desc as $d) {
                            if (!empty($d)) {
                                // $final_terms_and_conditions .= $d_counter++ . '. ' . $d . '<br />';
                                $final_terms_and_conditions .= $d . '<br />';
                            }
                        }
                    }
                }



                $grand_subtotal             = s__($row['grand_subtotal']);
                $grand_discount_type        = s__($row['grand_discount_type']);
                $grand_discount_type_value  = s__($row['grand_discount_type_value']);
                $grand_discount_amount      = s__($row['grand_discount_amount']);
                $grand_after_discount       = s__($row['grand_after_discount']);
                $grand_tax                  = s__($row['grand_tax']);
                $grand_total                = s__($row['grand_total']);

                $publish                = s__($row['is_active']);



                // --- Customer Information
                $rs = $mysqli->query("SELECT * FROM `" . tbl_customers . "` WHERE id=$customer_id");
                $row_customer = $rs->fetch_array();
                $salutation             = s__($row_customer['salutation']);
                $first_name             = s__($row_customer['first_name']);
                $last_name              = s__($row_customer['last_name']);
                $company_name           = s__($row_customer['company_name']);
                $display_name           = s__($row_customer['display_name']);
                $email                  = s__($row_customer['email']);
                $phone                  = s__($row_customer['phone']);
                $mobile                 = s__($row_customer['mobile']);
                $trn                    = s__($row_customer['trn']);

                // Customer Billing Address 
                $rs_billing     = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='billing' ");
                $row_billing    = $rs_billing->fetch_array();

                $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');
                $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
                $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
                $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
                $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
                $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
                $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
                $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
                $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');


                $credit_note_date         = processDateYtoD($credit_note_date);
                $expiry_date            = ($expiry_date == '1970-01-01') ? '' : processDateDtoY($expiry_date);
                $expected_shipment_date = ($expected_shipment_date == '1970-01-01') ? '' : processDateDtoY($expected_shipment_date);


                // Initialize all arrays to avoid the "null given" error
                $credit_note_item_id_arr = [];
                $service_arr             = [];
                $description_arr         = [];
                $qty_arr                 = [];
                $rate_arr                = [];
                $sub_total_arr           = [];
                $tax_arr                 = [];
                $tax_amount_arr          = [];
                $total_arr               = [];

                // ------------------ TOTAL ITEMS ------------------
                $result_credit_note_items       = $mysqli->query("SELECT * FROM `" . tbl_credit_note_items . "` WHERE credit_note_id=$credit_note_id ORDER BY id");
                $total_rows                     = $result_credit_note_items->num_rows;


                if ($total_rows > 0) {
                    while ($row_credit_note_items = $result_credit_note_items->fetch_array()) {

                        array_push($credit_note_item_id_arr,    $row_credit_note_items['id']);
                        array_push($service_arr,                $row_credit_note_items['service']);
                        array_push($description_arr,            $row_credit_note_items['description']);
                        array_push($qty_arr,                    $row_credit_note_items['qty']);
                        array_push($rate_arr,                   $row_credit_note_items['rate']);
                        array_push($sub_total_arr,              $row_credit_note_items['sub_total']);
                        array_push($tax_arr,                    $row_credit_note_items['tax']);
                        array_push($tax_amount_arr,             $row_credit_note_items['tax_amount']);
                        array_push($total_arr,                  $row_credit_note_items['total']);
                    }
                }
            }

            if ($total_rows == 0)           $total_rows = 1;

            ?>


            <div class="row">

                <div class="row p-lg-2">

                    <div class="col-lg-1">
                    </div>


                    <div class="card col-lg-10">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-4">

                                        <span class="text-muted">Credit Note To:</span>
                                        <ul class="list list-unstyled mb-0">
                                            <li>
                                                <h5 class="my-2"><?php echo $display_name; ?></h5>
                                            </li>
                                            <li><span class="fw-semibold"><?php echo $company_name; ?></span></li>
                                            <li><?php echo $billing_attention; ?></li>
                                            <li><?php echo $billing_country; ?></li>
                                            <li><?php echo $billing_address_line1; ?></li>
                                            <li><?php echo $billing_address_line2; ?></li>
                                            <li><?php echo $billing_city; ?></li>
                                            <li><?php echo $billing_state; ?></li>
                                            <li><?php echo $billing_zipcode; ?></li>
                                            <li><?php echo $billing_phone; ?></li>
                                            <li><?php echo $billing_fax; ?></li>
                                        </ul>

                                    </div>
                                </div>

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
                                $state            = getTableAttr('state_name', tbl_geo_states, $state);

                                $phone              = s__($row_warehouse['phone']);
                                $email              = s__($row_warehouse['email']);
                                $trn                = s__($row_warehouse['trn']);

                                $warehouse_information .= (!empty($warehouse_name) ? '<strong>' . $warehouse_name . '</strong><br />' : '');
                                $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
                                $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
                                $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
                                $warehouse_information .= (!empty($state) ? $state . ', ' : '');
                                $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
                                $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
                                $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
                                $warehouse_information .= (!empty($trn) ? $trn : '');
                                ?>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mb-4">
                                        <?php echo $warehouse_information; ?>
                                        <h6 class="text-primary mb-2 mt-lg-2">Credit Note #<?php echo $credit_note_no; ?></h6>
                                        <ul class="list list-unstyled mb-0">
                                            <li>Date: <span class="fw-semibold"><?php echo $credit_note_date; ?></span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>



                        <div class="table-responsive">
                            <table class="table table-lg">
                                <thead>
                                    <tr>
                                        <th>ITEM DETAILS</th>
                                        <th>DESCRIPTION</th>
                                        <th class="text-center">QUANTITY</th>
                                        <th class="text-center">RATE</th>
                                        <th class="text-center">SUBTOTAL</th>
                                        <th class="text-center">TAX</th>
                                        <th class="text-center">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    /*
                                    |------------------------------------------------------ Credit Note ITEMS  ----------------------------------------------------------|
                                    */
                                    // echo $total_rows;

                                    for ($credit_note_item = 1; $credit_note_item <= $total_rows; $credit_note_item++) {
                                        $index = $credit_note_item;
                                        $index = $index - 1;
                                        $credit_note_item_id                = $credit_note_item_id_arr[$index];
                                        //--------------------------------------------------------------------------------------------------------------------------------|
                                    ?>

                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo getTableAttr('item_name', tbl_items, $service_arr[$index]); ?></div>
                                                <span class="text-muted">
                                                    <?php
                                                    // ----------------------------------------------
                                                    // Seprate Line Number on base of Space new line
                                                    // ----------------------------------------------
                                                    $desc = explode("\r", $description_arr[$index]);
                                                    // print_r($desc);
                                                    $d_counter = 1;
                                                    if (count($desc) > 0) {
                                                        foreach ($desc as $d) {
                                                            if (!empty($d)) {
                                                                echo $d_counter++ . '. ' . $d;
                                                                echo '<br />';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo $description_arr[$index]; ?></td>
                                            <td class="text-center"><?php echo $qty_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $rate_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $sub_total_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $tax_arr[$index]; ?>% (<?php echo $tax_amount_arr[$index]; ?>)</td>
                                            <td class="text-end"><span class="fw-semibold"><?php echo $total_arr[$index]; ?></span></td>
                                        </tr>
                                    <?php
                                    } // for
                                    /*
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    */
                                    ?>


                                </tbody>
                            </table>
                        </div>

                        <div class="card-body border-top">
                            <div class="d-lg-flex flex-lg-wrap">

                                <div class="pt-2 mb-3">
                                    <ul class="list-unstyled text-muted">
                                        <li class="mb-3">Customer Notes: <br /><?php echo $customer_notes; ?></li>
                                        <li class="mb-3"><span class="fw-semibold">Terms and Conditions: </span> <br /><?php echo $final_terms_and_conditions; ?></li>
                                    </ul>
                                </div>

                                <div class="pt-2 mb-3 wmin-lg-400 ms-auto">
                                    <!-- <h6 class="mb-3">Total due</h6> -->
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <td>Grand Subtotal:</td>
                                                    <td class="text-end"><?php echo $grand_subtotal; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Discount Type: <?php echo $grand_discount_type; ?></td>
                                                    <td class="text-end"><?php echo $grand_discount_type_value; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Discount Amount: </td>
                                                    <td class="text-end"><?php echo $grand_discount_amount; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Subtotal: (Discounted): </td>
                                                    <td class="text-end"><?php echo $grand_after_discount; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Total Tax Amount:</td>
                                                    <td class="text-end"><?php echo $grand_tax; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Grand Total:</td>
                                                    <td class="text-end text-primary">
                                                        <h5 class="fw-semibold"><?php echo $grand_total; ?></h5>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- <div class="text-end mt-3">
                                        <button type="button" class="btn btn-primary">
                                            Send invoice
                                            <i class="ph-paper-plane-tilt ms-2"></i>
                                        </button>
                                    </div> -->

                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="text-muted">Thank you for your Business.</span>
                        </div>
                    </div>


                    <div class="col-lg-1">
                    </div>

                </div>


                <?php
                // ---------------------------------------------------------------------------------------------------------------------------------------
                $journal_id = getTableAttrV('id', tbl_journals, " reference_type='credit_note' AND reference_id='$credit_note_id' ");
                // ---------------------------------------------------------------------------------------------------------------------------------------

                if (!empty($journal_id)) {
                ?>

                    <p class="mb-0 opacity-50" id="journal">JOURNAL</p>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <!-- <p class="mb-0 fw-semibold"></p> -->

                            <div class="ms-auto small text-muted">
                                Amount is displayed in your base currency <span class="badge bg-success"><?php echo BASE_CURRENCY['code']; ?></span>
                            </div>
                        </div>

                        <!-- <div class="card-header">
                        <h5 class="mb-0">Basic table</h5>
                    </div> -->

                        <!-- <div class="card-body">
                        Example of a <code>basic</code> table. For basic styling (light padding and only horizontal dividers) add the base class <code>.table</code> to any <code>&lt;table&gt;</code>. It may seem super redundant, but given the widespread use of tables for other plugins like calendars and date pickers, we've opted to isolate our custom table styles.
                    </div> -->

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
                                    // -------- JOURNAL ENTRIES 
                                    //-------------------------------------------------------------------

                                    $result_journal_items     = $mysqli->query("SELECT * FROM `" . tbl_journal_items . "` WHERE journal_id=$journal_id");
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
                                            <td class="text-end"><?php echo $debit; ?>.00</td>
                                            <td class="text-end"><?php echo $credit; ?>.00</td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td></td>
                                        <td class="text-end fw-semibold"><?php echo $total_debit; ?>.00</td>
                                        <td class="text-end fw-semibold"><?php echo $total_credit; ?>.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php }  // JOURNAL 
                ?>

            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>



<?php include('admin_elements/admin_footer.php'); ?>