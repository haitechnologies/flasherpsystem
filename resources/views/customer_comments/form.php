<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var int $moduleId
 * @var int $session_user_id
 * @var int $session_role_id
 * @var string $error_message
 * @var string $success_message
 * @var int $customer_id
 * @var string $comments
 * @var int $commentId
 * @var string $action
 * @var array $allNotes
 * @var array $userNames
 * @var bool $canCreate
 * @var bool $canEdit
 * @var bool $canDelete
 */
include 'admin_elements/admin_header.php';
?>
<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <?php include('admin_elements/sidebar_customer.php'); ?>
</aside>

<div class="content-wrapper">
    <div class="content-inner">
        <?php include('admin_elements/page_header_customer.php'); ?>

        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">
                <div class="col-xl-10">
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-12">

                                    <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off" enctype="multipart/form-data">
                                        <input type="hidden" name="customer_id" id="customer_id" value="<?php echo $customer_id; ?>" />

                                        <?php if (!empty($success_message)): ?>
                                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                                <?php echo htmlspecialchars($success_message); ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($error_message)): ?>
                                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                                <?php echo htmlspecialchars($error_message); ?>
                                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($customer_id)): ?>
                                            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                                            <input type="hidden" name="comment_id" id="comment_id" value="<?php echo $commentId; ?>" />
                                        <?php else: ?>
                                            <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                                        <?php endif; ?>
                                        <?php echo csrf_field(); ?>

                                        <div class="row">
                                            <div class="col-lg-10">
                                                <?php
                                                $field = ['name' => 'comments', 'label' => 'Comments', 'value' => $comments, 'rows' => 3];
                                                include 'admin_elements/form_field_textarea.php';
                                                ?>

                                                <div class="d-flex justify-content-end">
                                                    <button type="submit" class="btn btn-light btn-sm my-1">
                                                        Add Comments
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>

                                    <div class="row">
                                        <div class="col-lg-10">
                                            <span class="small text-muted">
                                                ALL COMMENTS
                                                <span class="badge bg-primary rounded-pill ms-auto">
                                                    <?php echo count($allNotes); ?>
                                                </span>
                                            </span>

                                            <div class="comment-timeline p-4">
                                                <?php foreach ($allNotes as $note): ?>
                                                    <?php $noteId = $note->id; ?>
                                                    <?php $userName = $userNames[$note->createdBy] ?? 'Unknown'; ?>
                                                    <div class="d-flex mb-4 position-relative">
                                                        <div class="position-absolute border-start h-100" style="left: 15px; top: 30px; z-index: 0; width: 2px; border-color: #e9ecef !important;"></div>

                                                        <div class="bg-white border rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 32px; height: 32px; z-index: 1;">
                                                            <i class="ph-chat-centered-text text-primary"></i>
                                                        </div>

                                                        <div class="ms-3 flex-grow-1">
                                                            <div class="d-flex align-items-center mb-1">
                                                                <span class="fw-bold me-2"><?php echo htmlspecialchars($userName); ?></span>
                                                                <span class="text-muted small">• <?php echo htmlspecialchars($note->createdAt ?? ''); ?></span>
                                                            </div>
                                                            <div class="bg-light rounded p-3 d-flex justify-content-between align-items-center">
                                                                <span class="text-dark"><?php echo htmlspecialchars($note->notes ?? ''); ?></span>

                                                                <div class="d-flex">
                                                                    <?php if ($canEdit): ?>
                                                                    <a href="customer_comments.php?action=edit_customer_comments&comment_id=<?php echo $noteId; ?>&customer_id=<?php echo $customer_id; ?>"
                                                                       class="btn btn-link text-muted p-0 me-2" title="Edit">
                                                                        <i class="ph-pencil-simple"></i>
                                                                    </a>
                                                                    <?php endif; ?>
                                                                    <?php if ($canDelete): ?>
                                                                    <form method="post" action="customer_comments.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this comment?')">
                                                                        <input type="hidden" name="action" value="delete_customer_comments">
                                                                        <input type="hidden" name="comment_id" value="<?php echo $noteId; ?>">
                                                                        <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                                                                        <?php echo csrf_field(); ?>
                                                                        <button type="submit" class="btn btn-link text-muted p-0" title="Delete">
                                                                            <i class="ph-trash"></i>
                                                                        </button>
                                                                    </form>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
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
