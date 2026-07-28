<?php

use App\Core\DB;

$payment_made_id = "";
if (isset($_REQUEST['payment_made_id'])) $payment_made_id = e_s__($_REQUEST['payment_made_id']);
if (isset($_POST['payment_made_id']))    $payment_made_id = e_s__($_POST['payment_made_id']);

?>
<?php
$display_no = getTableAttr('payment_no', DB::PAYMENTS_MADE, $payment_made_id);
$display_status = getTableAttr('payment_status', DB::PAYMENTS_MADE, $payment_made_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::PAYMENTS_MADE, $payment_made_id);
?>
<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex border-top py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="payment_made_overview.php?payment_made_id=<?php echo $payment_made_id; ?>" class="text-black"><?php echo $display_no; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $payment_made_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="payment_made_overview.php?payment_made_id=<?php echo $payment_made_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</a>
                    <a href="listing_<?php echo $module; ?>.php?action=delete_<?php echo $module; ?>&id=<?php echo $payment_made_id; ?>" class="dropdown-item"><i class="ph-trash me-2"></i> Delete</a>
                </div>
            </div>
        </div>
    </div>
</div>