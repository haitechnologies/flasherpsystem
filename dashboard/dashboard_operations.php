<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'leads';
$module_caption = 'Operations Dashboard';
$tbl_name = DB::LEADS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$orgFilter = ' AND organization_id = ' . (int)$activeOrganizationId;

// Leads stats
$totalLeads = (int)($mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::LEADS . "` WHERE 1=1 $orgFilter")->fetch_assoc()['cnt'] ?? 0);
$openLeads = (int)($mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::LEADS . "` WHERE lead_status NOT IN (SELECT id FROM `" . DB::TAXONOMIES . "` WHERE type='lead_status' AND LOWER(value) IN ('closed lost','closed won','junk')) $orgFilter")->fetch_assoc()['cnt'] ?? 0);

// Projects stats
$totalProjects = (int)($mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::PROJECTS . "` WHERE 1=1 $orgFilter")->fetch_assoc()['cnt'] ?? 0);

// Jobs stats
$totalJobs = (int)($mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::JOBS . "` WHERE 1=1 $orgFilter")->fetch_assoc()['cnt'] ?? 0);
$openJobs = (int)($mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::JOBS . "` WHERE job_status NOT IN (SELECT id FROM `" . DB::JOB_STATUSES . "` WHERE LOWER(job_status) IN ('completed','cancelled')) $orgFilter")->fetch_assoc()['cnt'] ?? 0);

?>
<div class="content-wrapper">

    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content d-flex flex-wrap align-items-center">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <a href="dashboard_operations.php" class="text-dark">Operations Dashboard</a>
                    <span class="text-muted fw-normal fs-6">— <?php echo date('l, j F Y'); ?></span>
                </h1>
            </div>
        </div>
    </div>

    <div class="content">

        <?php include('admin_elements/breadcrumb.php'); ?>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-start border-primary border-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-users ph-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Total Leads</h6>
                                <span class="fs-3 fw-bold text-primary"><?php echo $totalLeads; ?></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="listing_leads.php" class="btn btn-outline-primary btn-sm w-100">View Leads</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-warning border-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-progress-bar ph-2x text-warning"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Open Leads</h6>
                                <span class="fs-3 fw-bold text-warning"><?php echo $openLeads; ?></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="listing_leads.php" class="btn btn-outline-warning btn-sm w-100">View Open Leads</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-info border-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-folder ph-2x text-info"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Projects</h6>
                                <span class="fs-3 fw-bold text-info"><?php echo $totalProjects; ?></span>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="listing_projects.php" class="btn btn-outline-info btn-sm w-100">View Projects</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-start border-success border-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="ph-clipboard-text ph-2x text-success"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Jobs</h6>
                                <span class="fs-3 fw-bold text-success"><?php echo $totalJobs; ?></span>
                                <small class="text-muted d-block"><?php echo $openJobs; ?> open</small>
                            </div>
                        </div>
                        <div class="mt-2">
                            <a href="listing_jobs.php" class="btn btn-outline-success btn-sm w-100">View Jobs</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Quick Links</h6>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <a href="leads.php" class="btn btn-outline-primary w-100 text-start d-flex align-items-center gap-2">
                            <i class="ph-plus-circle"></i> New Lead
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="listing_leads.php" class="btn btn-outline-primary w-100 text-start d-flex align-items-center gap-2">
                            <i class="ph-list"></i> All Leads
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="listing_projects.php" class="btn btn-outline-info w-100 text-start d-flex align-items-center gap-2">
                            <i class="ph-list"></i> All Projects
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="listing_jobs.php" class="btn btn-outline-success w-100 text-start d-flex align-items-center gap-2">
                            <i class="ph-list"></i> All Jobs
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
