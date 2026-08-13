<?php

use App\Core\DB;
use App\Core\Session;

$activeOrgId = Session::orgId();
$orgWhere = "AND organization_id = {$activeOrgId}";

$sale_order_id = "";
if (isset($_REQUEST['sale_order_id'])) $sale_order_id = e_s__($_REQUEST['sale_order_id']);
if (isset($_POST['sale_order_id']))    $sale_order_id = e_s__($_POST['sale_order_id']);

?>
<?php
$display_no = getTableAttr('sale_order_no', DB::SALE_ORDERS, $sale_order_id, $orgWhere);
$display_status = getTableAttr('sale_order_status', DB::SALE_ORDERS, $sale_order_id, $orgWhere);
$invoice_id = getTableAttr('invoice_id', DB::SALE_ORDERS, $sale_order_id, $orgWhere);
if (empty($display_no)) $display_no = getTableAttr('id', DB::SALE_ORDERS, $sale_order_id, $orgWhere);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="sale_order_overview.php?sale_order_id=<?php echo $sale_order_id; ?>" class="text-black"><?php echo s__($display_no); ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper(s__($display_status)); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $sale_order_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>

                <?php if ($display_status === 'invoiced' && !empty($invoice_id)) { ?>
                    <span class="badge bg-success">Converted to Invoice #<?php echo e_s__($invoice_id); ?></span>
                    <a href="invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>" class="btn btn-light btn-sm"><i class="ph-file-text"></i> View Invoice</a>
                <?php } else { ?>
                    <form method="post" action="sale_orders.php" style="display:inline">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="convert_sale_orders">
                        <input type="hidden" name="id" value="<?php echo $sale_order_id; ?>">
                        <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('Are you sure you want to convert this Sale Order to an Invoice?');"><i class="ph-file-text"></i> Convert to Invoice</button>
                    </form>
                <?php } ?>
            <?php } ?>
            <a href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $sale_order_id; ?>" class="btn btn-light btn-sm">
                <i class="ph-envelope-simple pe-1"></i> Send Email
            </a>
            <?php $token = hash("sha512", 'bushogai' . $sale_order_id); ?>
            <a href="pdf_sale_order.php?id=<?php echo $sale_order_id; ?>&token=<?php echo $token; ?>" class="btn btn-light btn-sm" target="_blank">
                <i class="ph-file-pdf pe-1"></i> PDF
            </a>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <form method="post" action="sale_order_overview.php?sale_order_id=<?php echo $sale_order_id; ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="clone_<?php echo $module; ?>">
                        <button type="submit" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</button>
                    </form>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="listing_<?php echo $module; ?>.php">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                        <input type="hidden" name="id" value="<?php echo $sale_order_id; ?>">
                        <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to delete this Sale Order?');"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>