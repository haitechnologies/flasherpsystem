<?php

use App\Core\DB;

$credit_note_id = "";
if (isset($_REQUEST['credit_note_id'])) $credit_note_id = e_s__($_REQUEST['credit_note_id']);
if (isset($_POST['credit_note_id']))    $credit_note_id = e_s__($_POST['credit_note_id']);

$display_no = getTableAttr('credit_note_no', DB::CREDIT_NOTES, $credit_note_id);
$display_status = getTableAttr('credit_note_status', DB::CREDIT_NOTES, $credit_note_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::CREDIT_NOTES, $credit_note_id);

$has_journal_entries = false;
$has_void_entry = false;
if (!empty($credit_note_id)) {
    $journal_check = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE reference_type='credit_note' AND reference_id=" . (int)$credit_note_id . " LIMIT 1");
    $has_journal_entries = ($journal_check && $journal_check->num_rows > 0);
    $void_check = $mysqli->query("SELECT id FROM `" . DB::JOURNALS . "` WHERE reference_type='credit_note_void' AND reference_id=" . (int)$credit_note_id . " LIMIT 1");
    $has_void_entry = ($void_check && $void_check->num_rows > 0);
}
$total_amount = (float) getTableAttr('grand_total', DB::CREDIT_NOTES, $credit_note_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="credit_note_overview.php?credit_note_id=<?php echo $credit_note_id; ?>" class="text-black"><?php echo s__($display_no); ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper(s__($display_status)); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>

            <?php if (!empty($credit_note_id)) { ?>
                <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $credit_note_id; ?>">
                    <i class="ph-envelope-simple pe-1"></i> Send Email
                </a>

                <?php $token = hash("sha512", 'bushogai' . $credit_note_id); ?>
                <a class="btn btn-light btn-sm" href="pdf_credit_note.php?credit_note_id=<?php echo $credit_note_id; ?>&token=<?php echo $token; ?>" target="_blank">
                    <i class="ph-file-pdf pe-1"></i> PDF
                </a>

                <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $credit_note_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>

                    <?php if (($display_status === 'draft' || $display_status === 'sent' || $display_status === 'approved' || $display_status === 'paid') && !$has_journal_entries) { ?>
                        <form method="post" action="<?php echo $module; ?>.php" style="display:inline">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $credit_note_id; ?>">
                            <input type="hidden" name="action" value="open_<?php echo $module; ?>">
                            <button type="submit" class="btn btn-light btn-sm" onclick="return confirm('Open this Credit Note and post the journal entry (debit Sales Returns / credit Accounts Receivable)?');"><i class="ph-check pe-1"></i> Mark As Open</button>
                        </form>
                    <?php } ?>
                <?php } ?>
            <?php } ?>

            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (!empty($credit_note_id)) { ?>
                        <?php if ($has_journal_entries && !$has_void_entry && $total_amount > 0) { ?>
                            <form method="post" action="<?php echo $module; ?>.php">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="id" value="<?php echo $credit_note_id; ?>">
                                <input type="hidden" name="action" value="void_<?php echo $module; ?>">
                                <button type="submit" class="dropdown-item" onclick="return confirm('Void this Credit Note? A reversing journal entry will be created.');"><i class="ph-file-minus me-2"></i> Void</button>
                            </form>
                        <?php } ?>
                        <?php if ($has_journal_entries) { ?>
                            <a href="#journal" class="dropdown-item"><i class="ph-stack me-2"></i> View Journal</a>
                        <?php } ?>
                        <form method="post" action="<?php echo $module; ?>.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="clone_<?php echo $module; ?>">
                            <input type="hidden" name="id" value="<?php echo $credit_note_id; ?>">
                            <button type="submit" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</button>
                        </form>
                        <div class="dropdown-divider"></div>
                        <form method="post" action="listing_<?php echo $module; ?>.php">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_<?php echo $module; ?>">
                            <input type="hidden" name="id" value="<?php echo $credit_note_id; ?>">
                            <button type="submit" class="dropdown-item" onclick="return confirm('Are you sure you want to delete this Credit Note?');"><i class="ph-trash me-2"></i> Delete</button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
