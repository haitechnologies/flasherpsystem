<?php

use App\Core\DB;
/* -------------------------------------------------------------------------- */

$invoice_id = '';
if (isset($_REQUEST['invoice_id']))        $invoice_id     = e_s__($_REQUEST['invoice_id']);
if (isset($_POST['invoice_id']))           $invoice_id     = e_s__($_POST['invoice_id']);


$invoice_no     = getTableAttr('invoice_no', DB::INVOICES, $invoice_id);
$invoice_status = getTableAttr('invoice_status', DB::INVOICES, $invoice_id);

$journal_table = DB::JOURNALS;
$has_journal_entries = false;
$has_void_entry = false;
$has_writeoff_entry = false;
$outstanding_balance = 0;
$grand_total = 0;

if (!empty($invoice_id)) {
    $journal_check = $mysqli->query("SELECT id FROM `{$journal_table}` WHERE reference_type='invoice' AND reference_id={$invoice_id} LIMIT 1");
    $has_journal_entries = ($journal_check && $journal_check->num_rows > 0);

    $void_check = $mysqli->query("SELECT id FROM `{$journal_table}` WHERE reference_type='invoice_void' AND reference_id={$invoice_id} LIMIT 1");
    $has_void_entry = ($void_check && $void_check->num_rows > 0);

    $writeoff_check = $mysqli->query("SELECT id FROM `{$journal_table}` WHERE reference_type='invoice_writeoff' AND reference_id={$invoice_id} LIMIT 1");
    $has_writeoff_entry = ($writeoff_check && $writeoff_check->num_rows > 0);

    $grand_total = (float) getTableAttr('grand_total', DB::INVOICES, $invoice_id);
    $paid_result = $mysqli->query("SELECT COALESCE(SUM(amount_received), 0) as total_paid FROM " . DB::PAYMENT_RECEIVED_ITEMS . " WHERE invoice_id = {$invoice_id}");
    if ($paid_result && $paid_row = $paid_result->fetch_assoc()) {
        $total_paid = (float) $paid_row['total_paid'];
        $outstanding_balance = $grand_total - $total_paid;
    } else {
        $outstanding_balance = $grand_total;
    }
}

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
?>

<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h5 class="ms-2"><a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>" class="text-black"><?php echo $invoice_no; ?></a></h5>
            <div class="p-3 rounded mt-1">
                <label class="form-check-label text-muted small"><?php echo (!empty($invoice_status) ? strtoupper($invoice_status) : ''); ?></label>
            </div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">

                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>

                    <?php if (isset($module_id) && granted('edit', $module_id) && !$has_void_entry && !$has_writeoff_entry) { ?>
                        <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $invoice_id; ?>" class="btn btn-light btn-sm">
                            <i class="ph-pencil"></i> Edit
                        </a>
                    <?php } ?>

                    <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $invoice_id; ?>">
                        <i class="ph-envelope-simple pe-1"></i> Send Email
                    </a>

                    <?php $token = hash("sha512", 'bushogai' . $invoice_id); ?>
                    <a class="btn btn-light btn-sm" href="pdf_invoice.php?id=<?php echo $invoice_id; ?>&token=<?php echo $token; ?>" target="_blank">
                        <i class="ph-file-pdf pe-1"></i> PDF
                    </a>

                    <?php
                    $current_status = getTableAttr('invoice_status', DB::INVOICES, $invoice_id);
                    if ($current_status == 'draft') { ?>
                        <a class="btn btn-light btn-sm" href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>&action=update_<?php echo $module; ?>&invoice_status=sent">
                            <i class="ph-check pe-1"></i> Mark As Sent
                        </a>
                    <?php } else if ($outstanding_balance > 0 && !$has_void_entry && !$has_writeoff_entry) { ?>
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="ph-file-arrow-down pe-1"></i> Record Payment
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="payments_received.php?post_invoice_id=<?php echo $invoice_id; ?>">Record Payment</a>
                                <a class="dropdown-item" href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>&action=update_invoices&invoice_status=writeoff">Write Off</a>
                            </div>
                        </div>
                    <?php } ?>

                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                            <i class="ph-dots-three"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <a href="recurring_invoices.php?action=add_invoices&source_invoice_id=<?php echo $invoice_id; ?>" class="dropdown-item">
                                <i class="ph-arrows-clockwise me-2"></i> Make Recurring
                            </a>
                            <a href="credit_notes.php?action=add_credit_notes&source_invoice_id=<?php echo $invoice_id; ?>" class="dropdown-item">
                                <i class="ph-file-text me-2"></i> Create Credit Note
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item">
                                <i class="ph-copy me-2"></i> Clone
                            </a>
                            <?php if ($has_journal_entries && !$has_void_entry && !$has_writeoff_entry) { ?>
                            <a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>&action=update_<?php echo $module; ?>&invoice_status=void" class="dropdown-item">
                                <i class="ph-file-minus me-2"></i> Void
                            </a>
                            <?php } ?>
                            <div class="dropdown-divider"></div>
                            <?php if ($current_status == 'sent' || $has_journal_entries) { ?>
                                <a href="#journal" class="dropdown-item">
                                    <i class="ph-stack me-2"></i> View Journal
                                </a>
                            <?php } ?>
                            <?php if (!$has_void_entry && !$has_writeoff_entry) { ?>
                            <a href="listing_<?php echo $module; ?>.php?action=delete_<?php echo $module; ?>&id=<?php echo $invoice_id; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>" class="dropdown-item">
                                <i class="ph-trash me-2"></i> Delete
                            </a>
                            <?php } ?>
                        </div>
                    </div>

        </div>

    </div>
</div>
