<?php

use App\Core\DB;
$module = 'report_hr';
$module_caption = 'HR Reports';
$error_message = '';
$success_message = '';

require_once __DIR__ . '/bootstrap.php';
csrf_token();

include('admin_elements/permissions.php');
include('admin_elements/admin_header.php');

$orgId = (int)dashboardRequireActiveOrganization();
$orgFilter = " AND u.organization_id = $orgId AND u.id > 1";
$today = date('Y-m-d');

// Total employees
$total_employees = (int)$mysqli->query("SELECT COUNT(*) as total FROM `" . DB::USERS . "` u WHERE u.id > 1 AND u.is_active = 1 AND u.organization_id = $orgId")->fetch_assoc()['total'];

// Headcount by department
$dept_query = $mysqli->query("
    SELECT d.department, COUNT(u.id) as employee_count
    FROM `" . DB::DEPARTMENTS . "` d
    LEFT JOIN `" . DB::USERS . "` u ON d.id = u.department_id AND u.is_active = 1 AND u.id > 1 " . ($orgId > 0 ? "AND u.organization_id = $orgId" : "") . "
    GROUP BY d.id, d.department ORDER BY employee_count DESC
");

// Payroll summary
$payroll_row = $mysqli->query("
    SELECT COUNT(DISTINCT ss.employee_id) as employees_with_salary,
        COALESCE(SUM(CASE WHEN pc.component_type = 'earning' THEN ss.amount ELSE 0 END), 0) as total_gross,
        COALESCE(SUM(CASE WHEN pc.component_type = 'deduction' THEN ss.amount ELSE 0 END), 0) as total_deductions
    FROM `" . DB::SALARY_STRUCTURES . "` ss
    INNER JOIN `" . DB::PAYROLL_COMPONENTS . "` pc ON ss.component_id = pc.id
    WHERE (ss.effective_to IS NULL OR CAST(ss.effective_to AS CHAR) = '0000-00-00' OR ss.effective_to >= CURDATE())
    AND (ss.effective_from IS NULL OR CAST(ss.effective_from AS CHAR) = '0000-00-00' OR ss.effective_from <= CURDATE())
    AND ss.organization_id = $orgId
")->fetch_assoc();
$total_gross = (float)($payroll_row['total_gross'] ?? 0);
$total_deductions = (float)($payroll_row['total_deductions'] ?? 0);
$employees_with_salary = (int)($payroll_row['employees_with_salary'] ?? 0);

// Leave summary
$leave_summary = $mysqli->query("
    SELECT lt.leave_type, COUNT(lr.id) as request_count,
        SUM(CASE WHEN lr.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN lr.status = 'pending' THEN 1 ELSE 0 END) as pending_count
    FROM `" . DB::LEAVE_TYPES . "` lt
    LEFT JOIN `" . DB::LEAVE_REQUESTS . "` lr ON lt.id = lr.leave_type_id " . ($orgId > 0 ? "AND lr.organization_id = $orgId" : "") . "
    GROUP BY lt.id, lt.leave_type ORDER BY request_count DESC
");

// Expiring documents (next 30 days)
$expiring_docs = $mysqli->query("
    SELECT COUNT(*) as cnt FROM `" . DB::USER_DOCUMENTS . "` ud
    JOIN `" . DB::USERS . "` u ON u.id = ud.attachable_id
    WHERE ud.attachable_type = 'EmployeeDoc' AND ud.expiry_date != '1970-01-01' AND ud.expiry_date IS NOT NULL
    AND ud.expiry_date <= DATE_ADD('$today', INTERVAL 30 DAY) $orgFilter
")->fetch_assoc()['cnt'] ?? 0;

// Pending air tickets
$pending_tickets = (int)$mysqli->query("
    SELECT COUNT(*) as cnt FROM `" . DB::AIR_TICKETS . "` at
    JOIN `" . DB::USERS . "` u ON u.id = at.employee_id
    WHERE at.status IN ('pending','payable') $orgFilter
")->fetch_assoc()['cnt'];

// Pending gratuity settlements
$pending_gratuity = (int)$mysqli->query("
    SELECT COUNT(*) as cnt FROM `" . DB::GRATUITY_SETTLEMENTS . "` gs
    JOIN `" . DB::USERS . "` u ON u.id = gs.employee_id
    WHERE gs.status IN ('calculated','approved') AND gs.organization_id = $orgId
")->fetch_assoc()['cnt'];

// Pending leave requests
$pending_leaves = (int)$mysqli->query("
    SELECT COUNT(*) as cnt FROM `" . DB::LEAVE_REQUESTS . "` lr
    JOIN `" . DB::USERS . "` u ON u.id = lr.employee_id
    WHERE lr.status = 'pending' $orgFilter
")->fetch_assoc()['cnt'];
?>

<div class="content-wrapper">
    <?php  ?>

    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h1 class="h5 mb-0 d-inline-flex align-items-center gap-2">
                    <span class="text-dark">HR Reports</span>
                </h1>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <!-- KPI Cards -->
            <div class="row mb-3 g-3">
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-primary border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $total_employees; ?></h3>
                                <span class="text-muted small">Employees</span>
                            </div>
                            <div class="ms-3 text-primary"><i class="ph-users ph-2x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-warning border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $pending_leaves; ?></h3>
                                <span class="text-muted small">Pending Leaves</span>
                            </div>
                            <div class="ms-3 text-warning"><i class="ph-calendar-x ph-2x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-info border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $pending_tickets; ?></h3>
                                <span class="text-muted small">Pending Air Tickets</span>
                            </div>
                            <div class="ms-3 text-info"><i class="ph-airplane ph-2x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-danger border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $expiring_docs; ?></h3>
                                <span class="text-muted small">Expiring Docs</span>
                            </div>
                            <div class="ms-3 text-danger"><i class="ph-files ph-2x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-primary border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $employees_with_salary; ?></h3>
                                <span class="text-muted small">On Payroll</span>
                            </div>
                            <div class="ms-3 text-primary"><i class="ph-money ph-2x"></i></div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-xl-2">
                    <div class="card card-body border-start border-secondary border-3">
                        <div class="d-flex align-items-center">
                            <div class="flex-fill">
                                <h3 class="mb-0"><?php echo $pending_gratuity; ?></h3>
                                <span class="text-muted small">Pending Gratuity</span>
                            </div>
                            <div class="ms-3 text-secondary"><i class="ph-coins ph-2x"></i></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Headcount + Leave Summary -->
            <div class="row g-3 mb-3">
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <h5 class="mb-0"><i class="ph-chart-bar me-2"></i>Headcount by Department</h5>
                            <div class="ms-auto">
                                <a href="listing_departments.php" class="btn btn-sm btn-outline-primary">Manage</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr><th>#</th><th>Department</th><th>Count</th><th>%</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php $c = 1; while ($dept = $dept_query->fetch_array()):
                                            $pct = $total_employees > 0 ? ($dept['employee_count'] / $total_employees * 100) : 0; ?>
                                        <tr>
                                            <td><?php echo $c++; ?></td>
                                            <td class="fw-semibold"><?php echo s__($dept['department']); ?></td>
                                            <td><?php echo $dept['employee_count']; ?></td>
                                            <td>
                                                <div class="progress" style="height:18px">
                                                    <div class="progress-bar bg-primary" style="width:<?php echo $pct; ?>%"><?php echo number_format($pct, 1); ?>%</div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <h5 class="mb-0"><i class="ph-calendar-blank me-2"></i>Leave Requests Summary</h5>
                            <div class="ms-auto">
                                <a href="listing_leave_requests.php" class="btn btn-sm btn-outline-primary">Manage</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover mb-0">
                                    <thead class="table-light">
                                        <tr><th>#</th><th>Leave Type</th><th>Total</th><th>Approved</th><th>Pending</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php $c = 1; if ($leave_summary->num_rows == 0): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">No leave requests found.</td></tr>
                                        <?php else: while ($leave = $leave_summary->fetch_array()): ?>
                                        <tr>
                                            <td><?php echo $c++; ?></td>
                                            <td class="fw-semibold"><?php echo s__($leave['leave_type']); ?></td>
                                            <td><?php echo $leave['request_count']; ?></td>
                                            <td><span class="badge bg-success bg-opacity-20 text-success"><?php echo $leave['approved_count']; ?></span></td>
                                            <td><span class="badge bg-warning bg-opacity-20 text-warning"><?php echo $leave['pending_count']; ?></span></td>
                                        </tr>
                                        <?php endwhile; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Payroll Summary + Document Status -->
            <div class="row g-3 mb-3">
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <h5 class="mb-0"><i class="ph-money me-2"></i>Payroll Summary</h5>
                            <div class="ms-auto">
                                <a href="listing_payroll_runs.php" class="btn btn-sm btn-outline-primary">View Runs</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-4">
                                    <label class="text-muted d-block small">Gross</label>
                                    <h4 class="text-success mb-0">AED <?php echo number_format($total_gross, 0); ?></h4>
                                </div>
                                <div class="col-4">
                                    <label class="text-muted d-block small">Deductions</label>
                                    <h4 class="text-danger mb-0">AED <?php echo number_format($total_deductions, 0); ?></h4>
                                </div>
                                <div class="col-4">
                                    <label class="text-muted d-block small">Net</label>
                                    <h4 class="text-primary mb-0">AED <?php echo number_format($total_gross - $total_deductions, 0); ?></h4>
                                </div>
                            </div>
                            <hr>
                            <p class="text-muted small mb-0"><i class="ph-info me-1"></i>Based on active salary structures for <?php echo $employees_with_salary; ?> employee<?php echo $employees_with_salary != 1 ? 's' : ''; ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="card h-100">
                        <div class="card-header d-flex align-items-center">
                            <h5 class="mb-0"><i class="ph-files me-2"></i>Document Expiry Status</h5>
                            <div class="ms-auto">
                                <a href="listing_user_documents.php" class="btn btn-sm btn-outline-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <div class="flex-fill">
                                    <h2 class="mb-0 text-danger"><?php echo $expiring_docs; ?></h2>
                                    <span class="text-muted small">Documents expiring within 30 days</span>
                                </div>
                                <i class="ph-warning-circle ph-3x text-danger opacity-50"></i>
                            </div>
                            <div class="d-flex align-items-center">
                                <div class="flex-fill">
                                    <h2 class="mb-0 text-warning"><?php echo $pending_tickets; ?></h2>
                                    <span class="text-muted small">Pending Air Tickets</span>
                                </div>
                                <i class="ph-airplane ph-3x text-warning opacity-50"></i>
                            </div>
                            <hr>
                            <div class="d-flex align-items-center">
                                <div class="flex-fill">
                                    <h2 class="mb-0 text-secondary"><?php echo $pending_gratuity; ?></h2>
                                    <span class="text-muted small">Pending Gratuity Settlements</span>
                                </div>
                                <i class="ph-coins ph-3x text-secondary opacity-50"></i>
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
