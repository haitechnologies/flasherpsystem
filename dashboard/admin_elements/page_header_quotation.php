<?php

use App\Core\DB;

$quotation_id = '';
if (isset($_REQUEST['quotation_id']))   $quotation_id = e_s__($_REQUEST['quotation_id']);
if (isset($_POST['quotation_id']))      $quotation_id = e_s__($_POST['quotation_id']);

$quotation_no     = getTableAttr('quotation_no', DB::QUOTATIONS, $quotation_id);
$quotation_status = getTableAttr('quotation_status', DB::QUOTATIONS, $quotation_id);

?>

<div class="page-header page-header-light shadow">
    <div class="page-header-content d-lg-flex border-top">
        <div class="row mt-3">
            <div class="col-lg-12">
                <h5 class="ms-2"><?php echo $quotation_no; ?></h5>
            </div>

            <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="p-3 rounded mt-1">
            <label class="form-check-label text-muted small"><?php echo (!empty($quotation_status) ? strtoupper($quotation_status) : ''); ?></label>
        </div>

        <div class="collapse d-lg-flex ms-lg-auto my-2" id="breadcrumb_elements">
            <div class="d-flex flex-wrap align-items-center gap-1">

                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">
                    Cancel
                </a>

                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $quotation_id; ?>" class="btn btn-light btn-sm">
                        <i class="ph-pencil"></i> Edit
                    </a>
                <?php } ?>

                <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $quotation_id; ?>">
                    <i class="ph-envelope-simple pe-1"></i> Send Email
                </a>

                <?php $token = hash("sha512", 'bushogai' . $quotation_id); ?>
                <a class="btn btn-light btn-sm" href="pdf_quotation.php?id=<?php echo $quotation_id; ?>&token=<?php echo $token; ?>" target="_blank">
                    <i class="ph-file-pdf pe-1"></i> PDF
                </a>

                <a class="btn btn-light btn-sm" href="#" onclick="postQuotationAction('convert_<?php echo $module; ?>','','');return false;">
                    <i class="ph-file pe-1"></i> Convert to Invoice
                </a>

                <?php $quotation_status = getTableAttr('quotation_status', DB::QUOTATIONS, $quotation_id); ?>

                <div class="dropdown">
                    <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown">
                        <i class="ph-dots-three"></i>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">

                        <?php if ($quotation_status == 'draft') { ?>
                            <a href="#" onclick="postQuotationAction('update_<?php echo $module; ?>','sent','');return false;" class="dropdown-item">
                                <i class="ph-check me-2"></i>
                                Mark As Sent
                            </a>
                        <?php } else if ($quotation_status != 'invoiced') { ?>
                            <a href="#" onclick="postQuotationAction('update_<?php echo $module; ?>','accepted','');return false;" class="dropdown-item">
                                <i class="ph-check me-2"></i>
                                Accepted
                            </a>
                            <a href="#" onclick="postQuotationAction('update_<?php echo $module; ?>','declined','');return false;" class="dropdown-item">
                                <i class="ph-x me-2"></i>
                                Declined
                            </a>
                        <?php } ?>

                        <div class="dropdown-divider"></div>
                        <a href="#" onclick="postQuotationAction('clone_<?php echo $module; ?>','','');return false;" class="dropdown-item">
                            <i class="ph-copy me-2"></i>
                            Clone
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="#" onclick="postQuotationAction('delete_<?php echo $module; ?>','','listing_<?php echo $module; ?>.php?id=<?php echo $quotation_id; ?>');return false;" class="dropdown-item">
                            <i class="ph-trash me-2"></i>
                            Delete
                        </a>

</div>

<script>
function postQuotationAction(action, status, target) {
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = target || 'quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>';
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = 'csrf_token';
    csrf.value = '<?php echo csrf_token(); ?>';
    f.appendChild(csrf);
    var a = document.createElement('input');
    a.type = 'hidden';
    a.name = 'action';
    a.value = action;
    f.appendChild(a);
    var qid = document.createElement('input');
    qid.type = 'hidden';
    qid.name = 'quotation_id';
    qid.value = '<?php echo $quotation_id; ?>';
    f.appendChild(qid);
    if (status) {
        var s = document.createElement('input');
        s.type = 'hidden';
        s.name = 'quotation_status';
        s.value = status;
        f.appendChild(s);
    }
    document.body.appendChild(f);
    f.submit();
}
</script>
                </div>

            </div>
        </div>

    </div>

</div>
