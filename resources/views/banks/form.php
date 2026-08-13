<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $accountName
 * @var string $accountCode
 * @var string $openingBalance
 * @var string $accountId
 * @var string $currency
 * @var string $bankName
 * @var string $branch
 * @var string $address
 * @var string $iban
 * @var string $routingNumber
 * @var string $description
 * @var int $isPrimary
 * @var string $moduleCaption
 * @var string $module
 * @var array $allCurrencies
 * @var array $allBanks
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content d-flex flex-wrap align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-3">
                <h5 class="mb-0"><?php echo $id > 0 ? 'Edit' : 'New'; ?> Bank Account</h5>
                <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success" name="is_primary" id="is_primary" form="frmbanks" <?php echo $isPrimary ? 'checked="checked"' : ''; ?>>
                    <label class="form-check-label fw-semibold" for="is_primary">Is Primary?</label>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if ($canCreate) { ?>
                    <button type="submit" form="frmbanks" class="btn btn-primary btn-sm">Save</button>
                <?php } ?>
                <a href="listing_banks.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Bank accounts are linked to your Chart of Accounts. Use them when recording payments, expenses, and receipts. The opening balance sets your starting point for bank reconciliation.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <form class="steps-basic clearfix" method="post" id="frmbanks" name="frmbanks" action="banks.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <?php if ($id > 0) { ?>
                    <input type="hidden" name="action" value="update_banks">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php } else { ?>
                    <input type="hidden" name="action" value="add_banks">
                <?php } ?>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Bank Name:*</span> <a href="listing_setup_banks.php" class="text-primary" title="Manage Banks (Institutions)"><i class="ph-bank"></i></a></label>
                                    <div class="col-lg-9">
                                        <select required class="form-select select2-enable" name="bank_name" id="uae_bank_select">
                                            <option value="">Please select</option>
                                            <?php foreach ($allBanks as $b) { ?>
                                                <option value="<?php echo e($b['institution_name']); ?>" <?php echo $bankName === $b['institution_name'] ? 'selected' : ''; ?>><?php echo e($b['institution_name']); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Account Name:*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="account_name" value="<?php echo e($accountName); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Account Code:*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="account_code" value="<?php echo e($accountCode); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Opening Balance:</label>
                                    <div class="col-lg-9">
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo BASE_CURRENCY['symbol']; ?></span>
                                            <input type="text" name="opening_balance" value="<?php echo e($openingBalance); ?>" class="form-control" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Linked Account:</label>
                                    <div class="col-lg-9">
                                        <select class="form-select" name="account_id">
                                            <option value="">None</option>
                                            <?php echo fetchAccountsDropdown(array(1), '', (int)$accountId); ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Currency:*</span></label>
                                    <div class="col-lg-9">
                                        <select class="form-select" name="currency">
                                            <?php foreach ($allCurrencies as $cur) { ?>
                                                <option value="<?php echo $cur['id']; ?>" <?php echo ((string)$cur['id'] === (string)$currency) ? 'selected' : ''; ?>>
                                                    <?php echo e($cur['currency']); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Branch:</label>
                                    <div class="col-lg-9">
                                        <input type="text" name="branch" value="<?php echo e($branch); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Address:</label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="address" style="field-sizing: content;"><?php echo e($address); ?></textarea>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">IBAN:</label>
                                    <div class="col-lg-9">
                                        <input type="text" name="iban" value="<?php echo e($iban); ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Rounting number (SWIFT):</label>
                                    <div class="col-lg-9">
                                        <input type="text" name="routing_number" value="<?php echo e($routingNumber); ?>" class="form-control">
                                        <div class="form-text text-muted">e.g 026009593</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label class="col-lg-3 col-form-label">Description:</label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="description" style="field-sizing: content;"><?php echo e($description); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'admin_elements/copyright.php'; ?>
    </div>
</div>
<?php if (!$canCreate && !$canEdit) { ?>
    <script>
        $(function() {
            toggleFormElements('true');
        });
    </script>
<?php } ?>
<?php
include 'admin_elements/admin_footer.php';
