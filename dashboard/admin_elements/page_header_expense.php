<?php

use App\Core\DB;

$expense_id = "";
if (isset($_REQUEST['expense_id'])) $expense_id = e_s__($_REQUEST['expense_id']);
if (isset($_POST['expense_id']))    $expense_id = e_s__($_POST['expense_id']);

?>
<?php
$display_no = getTableAttr('reference_no', DB::EXPENSES, $expense_id);
$display_status = getTableAttr('expense_status', DB::EXPENSES, $expense_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::EXPENSES, $expense_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="expense_overview.php?expense_id=<?php echo $expense_id; ?>" class="text-black"><?php echo $display_no; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <a href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $expense_id; ?>" class="btn btn-light btn-sm"><i class="ph-envelope-simple pe-1"></i> Send Email</a>
            <?php $token = hash('sha512', 'bushogai' . $expense_id); ?>
            <a href="pdf_expense.php?id=<?php echo $expense_id; ?>&token=<?php echo $token; ?>" class="btn btn-light btn-sm" target="_blank"><i class="ph-file-pdf pe-1"></i> PDF</a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $expense_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <a class="btn btn-light btn-sm" href="expense_overview.php?expense_id=<?php echo $expense_id; ?>&action=convert_to_invoice"><i class="ph-file pe-1"></i> Convert to Invoice</a>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="expense_overview.php?expense_id=<?php echo $expense_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</a>
                    <div class="dropdown-divider"></div>
                    <a href="#journal" class="dropdown-item"><i class="ph-stack me-2"></i> View Journal</a>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="listing_<?php echo $module; ?>.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this expense?');">
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                        <input type="hidden" name="id" value="<?php echo $expense_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <button type="submit" class="dropdown-item"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>