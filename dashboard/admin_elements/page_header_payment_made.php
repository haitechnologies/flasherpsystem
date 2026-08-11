<?php

use App\Core\DB;

$payment_id = "";
if (isset($_REQUEST['payment_id'])) $payment_id = e_s__($_REQUEST['payment_id']);
if (isset($_POST['payment_id']))    $payment_id = e_s__($_POST['payment_id']);
if (empty($payment_id) && isset($_REQUEST['id'])) $payment_id = e_s__($_REQUEST['id']);

$vendor_id = getTableAttr('vendor_id', DB::PAYMENTS_MADE, $payment_id);
$display_name = getTableAttr('display_name', DB::VENDORS, $vendor_id);
$display_status = getTableAttr('payment_status', DB::PAYMENTS_MADE, $payment_id);
$has_journals = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE (reference_type='payment_made' OR reference_type='payment_made_void') AND reference_id='" . intval($payment_id) . "' LIMIT 1")->num_rows;
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="payments_made_overview.php?payment_id=<?php echo $payment_id; ?>" class="text-black"><?php echo 'PM_' . $payment_id; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="payments_made_overview.php?payment_id=<?php echo $payment_id; ?>" class="btn btn-light btn-sm">Cancel</a>
            <a href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $payment_id; ?>" class="btn btn-light btn-sm"><i class="ph-envelope-simple pe-1"></i> Send Email</a>
            <?php $token = hash('sha512', 'bushogai' . $payment_id); ?>
            <a href="pdf_payment_made.php?id=<?php echo $payment_id; ?>&token=<?php echo $token; ?>" class="btn btn-light btn-sm" target="_blank"><i class="ph-file-pdf pe-1"></i> PDF</a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $payment_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($display_status == 'draft' || $display_status == 'unpaid') { ?>
                        <form method="post" action="payments_made_overview.php">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                            <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>" />
                            <button type="submit" name="mark_paid" value="1" class="dropdown-item"><i class="ph-check me-2"></i> Mark As Paid</button>
                        </form>
                    <?php } ?>
                    <?php if ($has_journals > 0) { ?>
                        <a href="payments_made_overview.php?payment_id=<?php echo $payment_id; ?>#journal" class="dropdown-item"><i class="ph-book-open me-2"></i> View Journal</a>
                    <?php } ?>
                    <?php if ($display_status == 'paid') { ?>
                        <form method="post" action="payments_made_overview.php">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                            <input type="hidden" name="payment_id" value="<?php echo $payment_id; ?>" />
                            <input type="hidden" name="action" value="void_<?php echo $module; ?>" />
                            <button type="submit" class="dropdown-item" onclick="return confirm('Void this payment?');"><i class="ph-x me-2"></i> Void</button>
                        </form>
                        <div class="dropdown-divider"></div>
                    <?php } ?>
                    <form method="post" action="listing_<?php echo $module; ?>.php">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>" />
                        <input type="hidden" name="id" value="<?php echo $payment_id; ?>" />
                        <button type="submit" class="dropdown-item" onclick="return confirm('Delete this payment?');"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
