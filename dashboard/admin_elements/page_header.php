    <div class="page-header page-header-light">
        <div class="page-header-content d-flex align-items-center justify-content-between border-top py-2 px-3">
            <div class="my-1">
                <?php if (isset($module) && !empty($module)): ?>
                    <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                        <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>
                    </h1>
                <?php else: ?>
                    <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                        Dashboard
                    </h1>
                <?php endif; ?>
            </div>

            <div class="my-1">
                <?php if (empty($hide_add_button) && isset($module_id) && isset($module) && granted('create', $module_id)) { ?>
                    <a href="<?php echo $module; ?>.php" class="btn btn-primary btn-sm d-inline-flex align-items-center">
                        <i class="ph-plus ph-sm me-2 opacity-75"></i>New
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>