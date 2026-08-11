<?php

use App\Core\DB;

$debit_note_id = "";
if (isset($_REQUEST['debit_note_id'])) $debit_note_id = e_s__($_REQUEST['debit_note_id']);
if (isset($_POST['debit_note_id']))    $debit_note_id = e_s__($_POST['debit_note_id']);
if (empty($debit_note_id) && isset($_REQUEST['id'])) $debit_note_id = e_s__($_REQUEST['id']);

?>
<?php
$display_no = getTableAttr('debit_note_no', DB::DEBIT_NOTES, $debit_note_id);
$display_status = getTableAttr('debit_note_status', DB::DEBIT_NOTES, $debit_note_id);
if (empty($display_no)) $display_no = getTableAttr('id', DB::DEBIT_NOTES, $debit_note_id);
?>
<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"><a href="debit_note_overview.php?debit_note_id=<?php echo $debit_note_id; ?>" class="text-black"><?php echo $display_no; ?></a></h1>
            <div class="ms-2 text-muted small"><?php echo strtoupper($display_status); ?></div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <a class="btn btn-light btn-sm" href="send_email.php?current_module=<?php echo $module; ?>&id=<?php echo $debit_note_id; ?>">
                <i class="ph-envelope-simple pe-1"></i> Send Email
            </a>
            <?php $token = hash("sha512", 'bushogai' . $debit_note_id); ?>
            <a class="btn btn-light btn-sm" href="pdf_debit_note.php?id=<?php echo $debit_note_id; ?>&token=<?php echo $token; ?>" target="_blank">
                <i class="ph-file-pdf pe-1"></i> PDF
            </a>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <a href="<?php echo $module; ?>.php?action=edit_<?php echo $module; ?>&id=<?php echo $debit_note_id; ?>" class="btn btn-light btn-sm"><i class="ph-pencil"></i> Edit</a>
            <?php } ?>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="dropdown"><i class="ph-dots-three"></i></button>
                <div class="dropdown-menu dropdown-menu-end">
                    <?php if (!in_array($display_status, ['open', 'closed', 'void'], true)) { ?>
                        <form method="post" action="debit_note_overview.php" class="dropdown-item">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                            <input type="hidden" name="debit_note_id" value="<?php echo $debit_note_id; ?>" />
                            <input type="hidden" name="action" value="update_<?php echo $module; ?>" />
                            <input type="hidden" name="debit_note_status" value="open" />
                            <button type="submit" class="dropdown-item m-0 p-0 border-0 bg-transparent"><i class="ph-arrows-clockwise me-2"></i> Convert to Open</button>
                        </form>
                    <?php } ?>
                    <?php if (!in_array($display_status, ['void', 'closed'], true)) { ?>
                        <form method="post" action="debit_note_overview.php" class="dropdown-item" onsubmit="return confirm('Void this debit note?');">
                            <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                            <input type="hidden" name="debit_note_id" value="<?php echo $debit_note_id; ?>" />
                            <input type="hidden" name="action" value="update_<?php echo $module; ?>" />
                            <input type="hidden" name="debit_note_status" value="void" />
                            <button type="submit" class="dropdown-item m-0 p-0 border-0 bg-transparent"><i class="ph-x me-2"></i> Void</button>
                        </form>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a href="debit_note_overview.php?debit_note_id=<?php echo $debit_note_id; ?>&action=clone_<?php echo $module; ?>" class="dropdown-item"><i class="ph-copy me-2"></i> Clone</a>
                    <?php if ($display_status === 'issued') { ?>
                        <div class="dropdown-divider"></div>
                        <a href="#journal" class="dropdown-item"><i class="ph-book-open me-2"></i> View Journal</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <form method="post" action="listing_<?php echo $module; ?>.php" class="dropdown-item" onsubmit="return confirm('Delete this debit note?');">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>" />
                        <input type="hidden" name="action" value="delete_<?php echo $module; ?>" />
                        <input type="hidden" name="id" value="<?php echo $debit_note_id; ?>" />
                        <button type="submit" class="dropdown-item m-0 p-0 border-0 bg-transparent"><i class="ph-trash me-2"></i> Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>