<?php

include('admin_elements/admin_header.php');
$module_caption = 'Select Organization';

$organizations = dashboardGetAccessibleOrganizations();
$activeOrganizationId = dashboardGetActiveOrganizationId(false);
$requestedOrganizationId = (int)($_GET['organization_id'] ?? 0);

if ($requestedOrganizationId > 0) {
    if (dashboardSetActiveOrganization($requestedOrganizationId)) {
        flash_success('Organization switched successfully.');
        header('Location:index.php');
        exit;
    }

    flash_error('You do not have access to that organization.');
    header('Location:select_organization.php');
    exit;
}

?>

<div class="content-wrapper">
        <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <?php if (isset($module) && !empty($module)): ?>
                    <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                        <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a>

                    </h1>
                <?php else: ?>
                    <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                        <?php echo !empty($module_caption) ? htmlspecialchars($module_caption) : 'Dashboard'; ?>

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
    <!-- /page header -->

    <div class="content">
        <?php include('admin_elements/breadcrumb.php'); ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Select Organization</h5>
                <?php if (function_exists('dashboardCanCreateOrganizations') && dashboardCanCreateOrganizations()) { ?>
                    <a href="organizations.php" class="btn btn-primary btn-sm">Create Organization</a>
                <?php } ?>
            </div>

            <div class="list-group list-group-flush">
                <?php if (empty($organizations)) { ?>
                    <div class="list-group-item py-4 text-center text-muted">
                        No organizations are assigned to your account yet.
                    </div>
                <?php } else { ?>
                    <?php foreach ($organizations as $organization) { ?>
                        <?php $organizationId = (int)($organization['id'] ?? 0); ?>
                        <?php $isActive = $organizationId === $activeOrganizationId; ?>
                        <div class="list-group-item d-flex flex-column flex-md-row align-items-md-center gap-3 py-3">
                            <div class="flex-fill">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fw-semibold"><?php echo htmlspecialchars((string)($organization['warehouse_name'] ?? 'Organization'), ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($isActive) { ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php } ?>
                                </div>
                                <div class="text-muted small mt-1">
                                    ID: <?php echo $organizationId; ?>
                                    <?php if (!empty($organization['slug'])) { ?>
                                        | Slug: <?php echo htmlspecialchars((string)$organization['slug'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php } ?>
                                    <?php if (!empty($organization['status'])) { ?>
                                        | Status: <?php echo htmlspecialchars((string)$organization['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php } ?>
                                </div>
                            </div>
                            <div>
                                <?php if ($isActive) { ?>
                                    <a href="organizations.php?action=edit_organizations&id=<?php echo $organizationId; ?>" class="btn btn-light btn-sm">Manage</a>
                                <?php } else { ?>
                                    <a href="select_organization.php?organization_id=<?php echo $organizationId; ?>" class="btn btn-primary btn-sm">Switch</a>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>