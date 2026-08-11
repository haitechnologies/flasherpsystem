<?php

declare(strict_types=1);
/**
 * @var int    $vendor_id
 * @var string $module
 * @var string $moduleCaption
 * @var int    $moduleId
 * @var int    $session_user_id
 * @var int    $session_role_id
 * @var string $error_message
 * @var string $success_message
 * @var string $attention
 * @var string $country
 * @var string $address_line1
 * @var string $address_line2
 * @var string $city
 * @var string $state
 * @var string $zipcode
 * @var string $phone
 * @var string $fax
 * @var string $addressType
 * @var bool   $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <?php include('admin_elements/sidebar_vendor.php'); ?>
</aside>

<div class="content-wrapper">
    <div class="content-inner">
        <?php include('admin_elements/page_header_vendor.php'); ?>

        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">
                <div class="col-lg-6 col-xl-12">
                    <div class="card">
                        <div class="tab-content card-body">
                            <div class="tab-pane fade show active" id="tab-overview" role="tabpanel">
                                <div class="row">
                                    <?php include('admin_elements/sidebar_vendor_overview.php'); ?>

                                    <div class="col-lg-8">
                                        <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off" enctype="multipart/form-data" novalidate>
                                            <input type="hidden" name="vendor_id" id="vendor_id" value="<?php echo $vendor_id; ?>" />
                                            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                                            <?php echo csrf_field(); ?>

                                            <span class="fw-semibold"><?php echo $moduleCaption; ?></span>

                                            <div class="card-body">
                                                <?php
                                                $field = ['name' => 'attention', 'label' => 'Attention:', 'value' => $attention];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <div class="row mb-2">
                                                    <label class="col-lg-3 col-form-label">Country:<span class="text-danger">*</span></label>
                                                    <div class="col-lg-9">
                                                        <select required class="form-select" name="country" id="country" aria-required="true">
                                                            <option value="0">Please select</option>
                                                            <?php echo getUAECountryDropdown($country); ?>
                                                        </select>
                                                        <div class="invalid-feedback">Please select a country</div>
                                                    </div>
                                                </div>

                                                <?php
                                                $field = ['name' => 'address_line1', 'label' => 'Address Line 1:', 'value' => $address_line1, 'required' => true];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'address_line2', 'label' => 'Address Line 2:', 'value' => $address_line2];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'city', 'label' => 'City:', 'value' => $city, 'required' => true];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'state', 'label' => 'State:', 'value' => $state];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'zipcode', 'label' => 'Zip Code:', 'value' => $zipcode];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'phone', 'label' => 'Phone:', 'value' => $phone];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <?php
                                                $field = ['name' => 'fax', 'label' => 'Fax Number:', 'value' => $fax];
                                                include 'admin_elements/form_field_text.php';
                                                ?>

                                                <div class="row mb-2">
                                                    <label class="col-lg-3 col-form-label">&nbsp;</label>
                                                    <div class="col-lg-9">
                                                        <?php if ($canEdit): ?>
                                                            <button type="submit" class="btn btn-primary btn-sm my-1 me-2">Save</button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
