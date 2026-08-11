<?php

use App\Core\DB;

$purchase_id = "";
if (isset($_REQUEST['purchase_id'])) $purchase_id = e_s__($_REQUEST['purchase_id']);
if (isset($_POST['purchase_id']))    $purchase_id = e_s__($_POST['purchase_id']);
if (empty($purchase_id) && isset($_REQUEST['id'])) $purchase_id = e_s__($_REQUEST['id']);

?>
<?php
$display_no = getTableAttr('purchase_no', DB::PURCHASES, $purchase_id);
$display_status = getTableAttr('purchase_status', DB::PURCHASES, $purchase_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::PURCHASES, $purchase_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="purchase_overview.php?purchase_id=<?php echo $purchase_id; ?>" class="text-black"><?php echo $display_no; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $purchase_id; ?>">
                <i class="ph-envelope-simple pe-1"></i> Send Email
            </a>
            <?php $token = hash("sha512", 'bushogai' . $purchase_id); ?>
            <a class="btn btn-light btn-sm" href="pdf_purchase.php?id=<?php echo $purchase_id; ?>&token=<?php echo $token; ?>" target="_blank">
                <i class="ph-file-pdf pe-1"></i> PDF
            </a>
            <?php if ($display_status == 'draft') { ?>
                <a href="purchase_overview.php?purchase_id=<?php echo $purchase_id; ?>&action=update_<?php echo $module; ?>&purchase_status=sent" class="btn btn-light btn-sm">
                    <i class="ph-check me-1"></i> Mark As Sent
                </a>
            <?php } ?>
            <a class="btn btn-light btn-sm" href="payments_made.php?purchase_id=<?php echo $purchase_id; ?>">
                <i class="ph-money me-1"></i> Record Payment
            </a>
            <a class="btn btn-light btn-sm" href="purchase_overview.php?purchase_id=<?php echo $purchase_id; ?>&action=convert_<?php echo $module; ?>">
                <i class="ph-file me-1"></i> Convert
            </a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $purchase_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="purchase_overview.php?purchase_id=<?php echo $purchase_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</a>
                    <form method="post" action="listing_<?php echo $module; ?>.php" onsubmit="return confirm('Are you sure you want to delete this record?');">
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                        <input type="hidden" name="id" value="<?php echo $purchase_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <button type="submit" class="dropdown-item"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>