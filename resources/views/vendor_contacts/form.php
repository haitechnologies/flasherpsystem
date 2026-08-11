<?php

declare(strict_types=1);
/**
 * @var int $vendor_id
 * @var int $contact_id
 * @var string $module
 * @var string $moduleCaption
 * @var int $moduleId
 * @var int $session_user_id
 * @var int $session_role_id
 * @var string $error_message
 * @var string $success_message
 * @var string $action
 * @var string $first_name
 * @var string $last_name
 * @var string $position
 * @var string $email
 * @var string $phone
 * @var string $notes
 * @var bool $canCreate
 * @var bool $canEdit
 * @var bool $canDelete
 */

include 'admin_elements/admin_header.php';

use App\Security\Roles;
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
                                        <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off" enctype="multipart/form-data">
                                            <input type="hidden" name="vendor_id" id="vendor_id" value="<?php echo $vendor_id; ?>" />

                                            <?php if ($contact_id > 0 && ($action === 'edit_vendor_contacts' || $action === 'update_vendor_contacts')): ?>
                                                <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                                                <input type="hidden" name="contact_id" id="contact_id" value="<?php echo $contact_id; ?>" />
                                            <?php else: ?>
                                                <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                                            <?php endif; ?>
                                            <?php echo csrf_field(); ?>

                                            <span class="fw-semibold"><?php echo $moduleCaption; ?></span>

                                            <div class="card-body">
                                                <?php
                                                $field = ['name' => 'first_name', 'label' => 'First Name:*', 'value' => $first_name, 'placeholder' => 'First Name', 'required' => true];
                                                include 'admin_elements/form_field_text.php';

                                                $field = ['name' => 'last_name', 'label' => 'Last Name:*', 'value' => $last_name, 'placeholder' => 'Last Name', 'required' => true];
                                                include 'admin_elements/form_field_text.php';

                                                $field = ['name' => 'position', 'label' => 'Position', 'value' => $position, 'placeholder' => 'Position'];
                                                include 'admin_elements/form_field_text.php';

                                                $field = ['name' => 'email', 'label' => 'Email:*', 'value' => $email, 'placeholder' => 'Email', 'required' => true, 'type' => 'email'];
                                                include 'admin_elements/form_field_text.php';

                                                $field = ['name' => 'phone', 'label' => 'Phone', 'value' => $phone, 'placeholder' => 'Phone', 'help_text' => '<div class="form-text text-muted"><small>+971 50 1234567</small></div>'];
                                                include 'admin_elements/form_field_text.php';

                                                $field = ['name' => 'notes', 'label' => 'Notes', 'value' => $notes, 'placeholder' => 'Notes'];
                                                include 'admin_elements/form_field_textarea.php';
                                                ?>

                                                <div class="row mb-2">
                                                    <label class="col-lg-3 col-form-label">&nbsp;</label>
                                                    <div class="col-lg-9">
                                                        <?php if ($canCreate): ?>
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
    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
