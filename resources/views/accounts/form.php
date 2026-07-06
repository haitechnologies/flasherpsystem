<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var int $parentId
 * @var string $accountType
 * @var string $accountName
 * @var string $accountCode
 * @var string $description
 * @var int $publish
 * @var string $moduleCaption
 * @var string $module
 * @var bool $canCreate
 * @var bool $canEdit
 * @var array $parentAccounts
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo $id > 0 ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <span class="text-muted small">(<?php echo $publish ? 'Active' : 'InActive'; ?>)</span>
            </div>
            <div class="my-1 d-inline-flex align-items-center me-2">
                <div class="form-check form-check-inline form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success" name="is_active" id="is_active" <?php echo $publish ? 'checked="checked"' : ''; ?> form="frm<?php echo $module; ?>">
                    <label class="form-check-label" for="is_active">Active</label>
                </div>
            </div>
            <div class="my-1">
                <?php if ($id > 0 ? $canEdit : $canCreate) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm me-2">Save</button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Accounts are the building blocks of your accounting system. Every transaction posts to an account. The account type (Asset, Liability, Equity, Income, Expense) determines how it appears in financial reports.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <?php if ($id > 0) { ?>
                    <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php } else { ?>
                    <input type="hidden" name="action" value="add_<?php echo $module; ?>">
                <?php } ?>
                <div class="card col-lg-6">
                    <div class="card-body clearfix">
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Parent Account:</label>
                            <div class="col-lg-9">
                                <select name="parent_id" class="form-select">
                                    <option value="">-- Root Account --</option>
                                    <?php foreach ($parentAccounts as $p) { ?>
                                        <option value="<?php echo $p['id']; ?>" <?php echo (int)$p['id'] === $parentId ? 'selected' : ''; ?>><?php echo htmlspecialchars($p['account_name']); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Account Type:</label>
                            <div class="col-lg-9">
                                <input type="text" name="account_type" value="<?php echo htmlspecialchars($accountType); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label"><span class="text-danger">Account Name:*</span></label>
                            <div class="col-lg-9">
                                <input required type="text" name="account_name" value="<?php echo htmlspecialchars($accountName); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Account Code:</label>
                            <div class="col-lg-9">
                                <input type="text" name="account_code" value="<?php echo htmlspecialchars($accountCode); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Description:</label>
                            <div class="col-lg-9">
                                <textarea class="form-control" name="description" style="field-sizing: content;"><?php echo htmlspecialchars($description); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'admin_elements/copyright.php'; ?>
    </div>
</div>
<?php include 'admin_elements/admin_footer.php';
