<?php

use App\Core\DB;

$quotation_id = '';
if (isset($_REQUEST['quotation_id']))   $quotation_id = e_s__($_REQUEST['quotation_id']);
if (isset($_POST['quotation_id']))      $quotation_id = e_s__($_POST['quotation_id']);

$quotation_no     = getTableAttr('quotation_no', DB::QUOTATIONS, $quotation_id);
$quotation_status = getTableAttr('quotation_status', DB::QUOTATIONS, $quotation_id);

?>

<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex border-top py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2">
                <a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>" class="text-black"><?php echo $quotation_no; ?></a>
            </h1>
            <div class="ms-2 text-muted small"><?php echo (!empty($quotation_status) ? strtoupper($quotation_status) : ''); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $quotation_id; ?>" class="btn btn-light btn-sm">
                    <i class="ph-pencil"></i> Edit
                </a>
            <?php } ?>
            <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $quotation_id; ?>">
                <i class="ph-envelope-simple pe-1"></i> Send Email
            </a>
            <?php $token = hash("sha512", 'bushogai' . $quotation_id); ?>
            <a class="btn btn-light btn-sm" href="pdf_invoice.php?id=<?php echo $quotation_id; ?>&token=<?php echo $token; ?>" target="_blank">
                <i class="ph-file-pdf pe-1"></i> PDF
            </a>
            <?php if ($quotation_status == 'draft') { ?>
                <a class="btn btn-light btn-sm" href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>&action=update_<?php echo $module; ?>&quotation_status=sent">
                    <i class="ph-check pe-1"></i> Mark As Sent
                </a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                    <i class="ph-dots-three"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a href="quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item">
                        <i class="ph-copy me-2"></i> Clone
                    </a>
                    <a href="listing_<?php echo $module; ?>.php?action=delete_<?php echo $module; ?>&id=<?php echo $quotation_id; ?>" class="dropdown-item">
                        <i class="ph-trash me-2"></i> Delete
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
