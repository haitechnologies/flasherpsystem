<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $consigneeName
 * @var string $addressLine1
 * @var string $addressLine2
 * @var string $city
 * @var string $zipcode
 * @var string $province
 * @var int $country
 * @var string $email
 * @var string $telephone
 * @var string $mobile
 * @var string $fax
 * @var array $countriesList
 * @var int $publish
 * @var string $moduleCaption
 * @var string $module
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo $id > 0 ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
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
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="is_active" value="1">
                <?php if ($id > 0) { ?>
                    <input type="hidden" name="action" value="update_<?php echo $module; ?>">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                <?php } else { ?>
                    <input type="hidden" name="action" value="add_<?php echo $module; ?>">
                <?php } ?>
                <div class="card col-lg-6">
                    <div class="card-body clearfix">
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label"><span class="text-danger">Consignee Name: <span class="text-danger">*</span></span></label>
                            <div class="col-lg-9">
                                <input required type="text" name="consignee_name" value="<?php echo htmlspecialchars($consigneeName); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Address Line 1:</label>
                            <div class="col-lg-9">
                                <input type="text" name="address_line1" value="<?php echo htmlspecialchars($addressLine1); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Address Line 2:</label>
                            <div class="col-lg-9">
                                <input type="text" name="address_line2" value="<?php echo htmlspecialchars($addressLine2); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">City:</label>
                            <div class="col-lg-9">
                                <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Zip Code:</label>
                            <div class="col-lg-9">
                                <input type="text" name="zipcode" value="<?php echo htmlspecialchars($zipcode); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Province:</label>
                            <div class="col-lg-9">
                                <input type="text" name="province" value="<?php echo htmlspecialchars($province); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Country:</label>
                            <div class="col-lg-9">
                                <select name="country" class="form-select">
                                    <option value="0">Please select</option>
                                    <?php foreach ($countriesList as $row): ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo (string)$country === (string)$row['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['country']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Email:</label>
                            <div class="col-lg-9">
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Telephone:</label>
                            <div class="col-lg-9">
                                <input type="text" name="telephone" value="<?php echo htmlspecialchars($telephone); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Mobile:</label>
                            <div class="col-lg-9">
                                <input type="text" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" class="form-control">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <label class="col-lg-3 col-form-label">Fax:</label>
                            <div class="col-lg-9">
                                <input type="text" name="fax" value="<?php echo htmlspecialchars($fax); ?>" class="form-control">
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
