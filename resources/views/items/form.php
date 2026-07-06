<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $itemType
 * @var string $itemName
 * @var string $unitPrice
 * @var string $sellingPrice
 * @var int $taxTreatmentId
 * @var bool $isExcise
 * @var int $publish
 * @var string $moduleCaption
 * @var string $module
 * @var bool $canCreate
 * @var bool $canEdit
 * @var int $saleAccount
 * @var string $saleDescription
 * @var int $purchaseAccount
 * @var string $purchaseDescription
 * @var string $costPrice
 * @var int $preferredVendorId
 * @var array $taxTreatments
 * @var array $vendors
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
        <?php if ($id > 0) { ?>
            <input type="hidden" name="action" value="update_<?php echo $module; ?>">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
        <?php } else { ?>
            <input type="hidden" name="action" value="add_<?php echo $module; ?>">
        <?php } ?>

        <div class="page-header page-header-light shadow">
            <div class="page-header-content border-top py-2 px-3 d-flex flex-wrap align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-3">
                    <h5 class="mb-0"><?php echo $id > 0 ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if ($id > 0 ? $canEdit : $canCreate) { ?>
                        <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm">Save</button>
                    <?php } ?>
                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
                </div>
            </div>
        </div>

        <div class="content-inner">
            <div class="content">
                <?php include 'admin_elements/breadcrumb.php'; ?>

                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Type:</label>
                                    <div class="col-lg-9">
                                        <div class="form-check form-check-inline">
                                            <input type="radio" class="form-check-input" name="item_type" value="services" <?php echo $itemType === 'services' ? 'checked' : ''; ?>>
                                            <label class="form-check-label">Services</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Item Name:*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="item_name" value="<?php echo htmlspecialchars($itemName); ?>" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" checked disabled>
                                    <label class="form-check-label fw-semibold">Sales Information</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Selling Price:*</span></label>
                                    <div class="col-lg-9">
                                        <div class="col-lg-6">
                                            <div class="form-control-feedback form-control-feedback-start mb-3">
                                                <input required type="text" name="selling_price" value="<?php echo htmlspecialchars($sellingPrice); ?>" class="form-control">
                                                <div class="form-control-feedback-icon"><?php echo BASE_CURRENCY['code']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Tax:</label>
                                    <div class="col-lg-9">
                                        <select class="form-select" name="tax_treatment_id">
                                            <option value="0">No Tax</option>
                                            <?php foreach ($taxTreatments as $t) { ?>
                                                <option value="<?php echo (int)$t['id']; ?>" <?php echo (int)$t['id'] === $taxTreatmentId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($t['tax_treatment'] ?? '')); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Account:*</span></label>
                                    <div class="col-lg-9">
                                        <select required class="form-select" name="sale_account">
                                            <option value="" class="fw-semibold text-black" disabled>Select Income Account</option>
                                            <?php echo fetchAccountsDropdown('Income', '', $saleAccount); ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Description:</label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="sale_description" rows="3"><?php echo htmlspecialchars($saleDescription); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header d-flex align-items-center">
                                <div class="form-check mb-2">
                                    <input type="checkbox" class="form-check-input" checked disabled>
                                    <label class="form-check-label fw-semibold">Purchase Information</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Cost Price:*</span></label>
                                    <div class="col-lg-9">
                                        <div class="col-lg-6">
                                            <div class="form-control-feedback form-control-feedback-start mb-3">
                                                <input required type="text" name="cost_price" value="<?php echo htmlspecialchars($costPrice); ?>" class="form-control">
                                                <div class="form-control-feedback-icon"><?php echo BASE_CURRENCY['code']; ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Account:*</span></label>
                                    <div class="col-lg-9">
                                        <select required class="form-select" name="purchase_account">
                                            <option value="" class="fw-semibold text-black" disabled>Select Expense Account</option>
                                            <?php echo fetchAccountsDropdown('Expense', '', $purchaseAccount); ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Description:</label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="purchase_description" rows="3"><?php echo htmlspecialchars($purchaseDescription); ?></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Preferred Vendor:</label>
                                    <div class="col-lg-9">
                                        <select class="form-select" name="preferred_vendor_id">
                                            <option value="0">None</option>
                                            <?php foreach ($vendors as $v) { ?>
                                                <option value="<?php echo (int)$v['id']; ?>" <?php echo (int)$v['id'] === $preferredVendorId ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($v['display_name'] ?? '')); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
    <?php include 'admin_elements/copyright.php'; ?>
</div>
<?php include 'admin_elements/admin_footer.php'; ?>
