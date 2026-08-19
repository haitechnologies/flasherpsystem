<?php

use App\Core\DB;

$payment_received_id = "";
if (isset($_REQUEST['payment_received_id'])) $payment_received_id = e_s__($_REQUEST['payment_received_id']);
if (isset($_POST['payment_received_id']))    $payment_received_id = e_s__($_POST['payment_received_id']);

$display_no = getTableAttr('payment_no', DB::PAYMENTS_RECEIVED, $payment_received_id);
$display_status = getTableAttr('payment_status', DB::PAYMENTS_RECEIVED, $payment_received_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::PAYMENTS_RECEIVED, $payment_received_id);

$has_journal_entries = false;
$has_void_entry = false;
if (!empty($payment_received_id)) {
    $journal_check = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE reference_type='payment_received' AND reference_id=" . (int)$payment_received_id . " LIMIT 1");
    $has_journal_entries = ($journal_check && $journal_check->num_rows > 0);
    $void_check = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE reference_type='payment_received_void' AND reference_id=" . (int)$payment_received_id . " LIMIT 1");
    $has_void_entry = ($void_check && $void_check->num_rows > 0);
}
$total_amount = (float) getTableAttr('total_amount_received', DB::PAYMENTS_RECEIVED, $payment_received_id);
$deposit_to = (int) getTableAttr('deposit_to', DB::PAYMENTS_RECEIVED, $payment_received_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="payment_received_overview.php?payment_received_id=<?php echo $payment_received_id; ?>" class="text-black"><?php echo s__($display_no); ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper(s__($display_status)); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>

            <?php if (!empty($payment_received_id)) { ?>
                <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $payment_received_id; ?>">
                    <i class="ph-envelope-simple pe-1"></i> Send Email
                </a>

                <?php $token = hash("sha512", 'bushogai' . $payment_received_id); ?>
                <a class="btn btn-light btn-sm" href="pdf_payment_received.php?payment_received_id=<?php echo $payment_received_id; ?>&token=<?php echo $token; ?>" target="_blank">
                    <i class="ph-file-pdf pe-1"></i> PDF
                </a>

                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $payment_received_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>

                    <?php if ($display_status === 'draft' && !$has_void_entry) { ?>
                        <form method="post" action="payment_received_overview.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="payment_received_id" value="<?php echo $payment_received_id; ?>">
                            <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                            <input type="hidden" name="payment_status" value="paid">
                            <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('Mark this payment as paid and post the journal entry?');"><i class="ph-check pe-1"></i> Mark As Paid</button>
                        </form>
                    <?php } elseif ($display_status === 'paid' && !$has_void_entry) { ?>
                        <form method="post" action="payment_received_overview.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="payment_received_id" value="<?php echo $payment_received_id; ?>">
                            <input type="hidden" name="action" value="unmark_<?php echo $module; ?>">
                            <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('Mark this payment as unpaid and remove the journal entry?');"><i class="ph-x pe-1"></i> Mark as Unpaid</button>
                        </form>
                    <?php } ?>

                    <?php if (($display_status === 'draft' || $display_status === 'paid') && !$has_void_entry) { ?>
                        <form method="post" action="payment_received_overview.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="payment_received_id" value="<?php echo $payment_received_id; ?>">
                            <input type="hidden" name="action" value="convert_<?php echo $module; ?>">
                            <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('Convert this payment receipt into an Invoice?');"><i class="ph-file-text pe-1"></i> Convert to Invoice</button>
                        </form>
                    <?php } ?>
                <?php } ?>
            <?php } ?>

            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (!empty($payment_received_id)) { ?>
                        <?php if ($display_status === 'paid' && $total_amount > 0 && !$has_void_entry) { ?>
                            <form method="post" action="payment_received_overview.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="payment_received_id" value="<?php echo $payment_received_id; ?>">
                                <input type="hidden" name="action" value="void_<?php echo $module; ?>">
                                <button type="submit" class="dropdown-item" onclick="return confirm('Void this payment? A reversing journal entry will be created.');"><i class="ph-file-minus me-2"></i> Void</button>
                            </form>
                        <?php } ?>
                        <?php if ($has_journal_entries) { ?>
                            <a href="#journal" class="dropdown-item"><i class="ph-stack me-2"></i> View Journal</a>
                        <?php } ?>
                        <form method="post" action="<?php echo $module; ?>.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="clone_<?php echo $module; ?>">
                            <input type="hidden" name="id" value="<?php echo $payment_received_id; ?>">
                            <button type="submit" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</button>
                        </form>
                        <div class="dropdown-divider"></div>
                        <form method="post" action="listing_<?php echo $module; ?>.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                            <input type="hidden" name="id" value="<?php echo $payment_received_id; ?>">
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to delete this Payment Received?');"><i class="ph-trash me-2"></i> Delete</button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>