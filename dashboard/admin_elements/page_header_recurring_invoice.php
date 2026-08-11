<?php

use App\Core\DB;

$recurring_invoice_id = "";
if (isset($_REQUEST['recurring_invoice_id'])) $recurring_invoice_id = e_s__($_REQUEST['recurring_invoice_id']);
if (isset($_POST['recurring_invoice_id']))    $recurring_invoice_id = e_s__($_POST['recurring_invoice_id']);

$display_no = getTableAttr('invoice_no', DB::INVOICES, $recurring_invoice_id);
$display_status = getTableAttr('invoice_status', DB::INVOICES, $recurring_invoice_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::INVOICES, $recurring_invoice_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="recurring_invoice_overview.php?recurring_invoice_id=<?php echo $recurring_invoice_id; ?>" class="text-black"><?php echo s__($display_no); ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper(s__($display_status)); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_recurring_invoices.php" class="btn btn-light btn-sm">Cancel</a>

            <?php if (!empty($recurring_invoice_id)) { ?>
                <a class="btn btn-light btn-sm" href="send_email.php?current_module=recurring_invoices&id=<?php echo $recurring_invoice_id; ?>">
                    <i class="ph-envelope-simple pe-1"></i> Send Email
                </a>

                <?php $token = hash("sha512", 'bushogai' . $recurring_invoice_id); ?>
                <a class="btn btn-light btn-sm" href="pdf_recurring_invoice.php?recurring_invoice_id=<?php echo $recurring_invoice_id; ?>&token=<?php echo $token; ?>" target="_blank">
                    <i class="ph-file-pdf pe-1"></i> PDF
                </a>

                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                    <a href="recurring_invoices.php?action=edit_recurring_invoices&id=<?php echo $recurring_invoice_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
                <?php } ?>
            <?php } ?>

            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (!empty($recurring_invoice_id)) { ?>
                        <form method="post" action="recurring_invoice_overview.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="recurring_invoice_id" value="<?php echo $recurring_invoice_id; ?>">
                            <input type="hidden" name="action" value="clone_recurring_invoices">
                            <button type="submit" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</button>
                        </form>
                        <div class="dropdown-divider"></div>
                        <form method="post" action="listing_recurring_invoices.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_recurring_invoices">
                            <input type="hidden" name="id" value="<?php echo $recurring_invoice_id; ?>">
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to delete this Recurring Invoice Profile?');"><i class="ph-trash me-2"></i> Delete</button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
