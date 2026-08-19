<?php

use App\Core\DB;

$purchase_order_id = "";
if (isset($_REQUEST['purchase_order_id'])) $purchase_order_id = e_s__($_REQUEST['purchase_order_id']);
if (isset($_POST['purchase_order_id']))    $purchase_order_id = e_s__($_POST['purchase_order_id']);
if (empty($purchase_order_id) && isset($_REQUEST['id'])) $purchase_order_id = e_s__($_REQUEST['id']);

?>
<?php
$display_no = getTableAttr('purchase_order_no', DB::PURCHASE_ORDERS, $purchase_order_id);
$display_status = getTableAttr('purchase_order_status', DB::PURCHASE_ORDERS, $purchase_order_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::PURCHASE_ORDERS, $purchase_order_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="purchase_order_overview.php?purchase_order_id=<?php echo $purchase_order_id; ?>" class="text-black"><?php echo $display_no; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $purchase_order_id; ?>"><i class="ph-envelope-simple pe-1"></i> Send Email</a>
            <?php $token = hash("sha512", 'bushogai' . $purchase_order_id); ?>
            <a class="btn btn-light btn-sm" href="pdf_purchase_order.php?id=<?php echo $purchase_order_id; ?>&token=<?php echo $token; ?>" target="_blank"><i class="ph-file-pdf pe-1"></i> PDF</a>
            <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                <form method="post" action="purchase_order_overview.php" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                    <input type="hidden" name="action" value="convert_<?php echo $module; ?>">
                    <input type="hidden" name="purchase_order_id" value="<?php echo $purchase_order_id; ?>">
                    <button type="submit" class="btn btn-light btn-sm"><i class="ph-file pe-1"></i> Convert to Purchase</button>
                </form>
            <?php } ?>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $purchase_order_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if ($display_status == 'draft') { ?>
                        <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                            <form method="post" action="purchase_order_overview.php" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                                <input type="hidden" name="purchase_order_id" value="<?php echo $purchase_order_id; ?>">
                                <input type="hidden" name="purchase_order_status" value="sent">
                                <button type="submit" class="dropdown-item"><i class="ph-check me-2"></i> Mark As Sent</button>
                            </form>
                        <?php } ?>
                    <?php } else if ($display_status != 'invoiced') { ?>
                        <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                            <form method="post" action="purchase_order_overview.php" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                                <input type="hidden" name="purchase_order_id" value="<?php echo $purchase_order_id; ?>">
                                <input type="hidden" name="purchase_order_status" value="accepted">
                                <button type="submit" class="dropdown-item"><i class="ph-check me-2"></i> Accepted</button>
                            </form>
                            <form method="post" action="purchase_order_overview.php" class="m-0">
                                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                                <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                                <input type="hidden" name="purchase_order_id" value="<?php echo $purchase_order_id; ?>">
                                <input type="hidden" name="purchase_order_status" value="declined">
                                <button type="submit" class="dropdown-item"><i class="ph-x me-2"></i> Declined</button>
                            </form>
                        <?php } ?>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <?php if (isset($module_id) && granted('create', $module_id)) { ?>
                        <form method="post" action="purchase_order_overview.php" class="m-0">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                            <input type="hidden" name="action" value="clone_<?php echo $module; ?>">
                            <input type="hidden" name="purchase_order_id" value="<?php echo $purchase_order_id; ?>">
                            <button type="submit" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</button>
                        </form>
                        <div class="dropdown-divider"></div>
                    <?php } ?>
                    <form method="post" action="listing_<?php echo $module; ?>.php" class="m-0" onsubmit="return confirm('Are you sure you want to delete this purchase order?');">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                        <input type="hidden" name="id" value="<?php echo $purchase_order_id; ?>">
                        <button type="submit" class="dropdown-item text-danger"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>