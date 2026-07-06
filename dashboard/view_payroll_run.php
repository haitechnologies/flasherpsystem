<?php

use App\Core\DB;
$module = 'payroll_runs';
$module_caption = 'View Payroll Run';

include('admin_elements/admin_header.php');
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$payroll_run_id = intval($_GET['id'] ?? 0);

if (empty($payroll_run_id)) {
    header("Location:listing_payroll_runs.php");
    exit;
}

// Get payroll run details
$run_result = $mysqli->query("SELECT * FROM `" . DB::PAYROLL_RUNS . "` WHERE id=$payroll_run_id");
$run = $run_result->fetch_array();

if (!$run) {
    header("Location:listing_payroll_runs.php");
    exit;
}

$period_start = $run['period_start'];
$period_end = $run['period_end'];
$status = $run['status'];
$total_gross = $run['total_gross'];
$total_deductions = $run['total_deductions'];
$total_net = $run['total_net'];
?>

<div class="content-wrapper">
    <?php  ?>

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

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <!-- Payroll Run Summary -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Payroll Run #<?php echo $payroll_run_id; ?> - Summary</h5>
                    <div>
                        <?php if ($status == 'draft') { ?>
                            <span class="badge bg-secondary">Draft</span>
                        <?php } elseif ($status == 'approved') { ?>
                            <span class="badge bg-success">Approved</span>
                        <?php } elseif ($status == 'posted') { ?>
                            <span class="badge bg-primary">Posted</span>
                        <?php } ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="text-muted d-block mb-1">Period Start</label>
                                <strong><?php echo processDateYtoD($period_start); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="text-muted d-block mb-1">Period End</label>
                                <strong><?php echo processDateYtoD($period_end); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="text-muted d-block mb-1">Total Gross</label>
                                <strong class="text-success">AED <?php echo number_format($total_gross, 2); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="text-muted d-block mb-1">Total Deductions</label>
                                <strong class="text-danger">AED <?php echo number_format($total_deductions, 2); ?></strong>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="text-muted d-block mb-1">Total Net</label>
                                <strong class="text-primary">AED <?php echo number_format($total_net, 2); ?></strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="listing_payroll_runs.php" class="btn btn-light">
                            <i class="ph-arrow-left me-2"></i>Back to Payroll Runs
                        </a>
                        <?php if ($status == 'draft' && $total_gross == 0) { ?>
                            <a href="process_payroll_run.php?id=<?php echo $payroll_run_id; ?>"
                               class="btn btn-success"
                               onclick="return confirm('Generate payslips for this payroll run?')">
                                <i class="ph-play me-2"></i>Generate Payslips
                            </a>
                        <?php } ?>
                        <?php if ($status == 'draft') { ?>
                            <a href="payroll_runs.php?action=edit_payroll_runs&id=<?php echo $payroll_run_id; ?>"
                               class="btn btn-primary">
                                <i class="ph-pencil me-2"></i>Edit Run
                            </a>
                        <?php } ?>
                        <?php
                        // Check if there are unpaid payslips
                        if ($total_gross > 0) {
                            $unpaid_count = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::PAYSLIPS . "` WHERE payroll_run_id=$payroll_run_id AND status != 'paid'")->fetch_assoc()['count'];
                            if ($unpaid_count > 0) {
                        ?>
                            <a href="mark_payslip_paid.php?action=mark_all_paid&payroll_run_id=<?php echo $payroll_run_id; ?>"
                               class="btn btn-success"
                               onclick="return confirm('Mark all <?php echo $unpaid_count; ?> payslip(s) in this run as paid?')">
                                <i class="ph-check-circle me-2"></i>Mark All as Paid (<?php echo $unpaid_count; ?>)
                            </a>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- Employee Payslips -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Employee Payslips</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Gross Salary</th>
                                    <th>Deductions</th>
                                    <th>Net Salary</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $payslips_query = $mysqli->query("
                                    SELECT
                                        ps.id,
                                        ps.employee_id,
                                        ps.gross,
                                        ps.deductions,
                                        ps.net,
                                        ps.status,
                                        u.full_name,
                                        d.department
                                    FROM `" . DB::PAYSLIPS . "` ps
                                    INNER JOIN `" . DB::USERS . "` u ON ps.employee_id = u.id
                                    LEFT JOIN `" . DB::DEPARTMENTS . "` d ON u.department_id = d.id
                                    WHERE ps.payroll_run_id = $payroll_run_id
                                    ORDER BY u.full_name ASC
                                ");

                                $counter = 1;

                                if ($payslips_query->num_rows == 0) {
                                    echo '<tr><td colspan="8" class="text-center text-muted py-4">No payslips generated yet. Click "Generate Payslips" to process this payroll run.</td></tr>';
                                } else {
                                    while ($payslip = $payslips_query->fetch_array()) {
                                ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td class="fw-semibold"><?php echo s__($payslip['full_name']); ?></td>
                                        <td><?php echo s__($payslip['department']) ?: '-'; ?></td>
                                        <td class="text-success fw-semibold">AED <?php echo number_format($payslip['gross'], 2); ?></td>
                                        <td class="text-danger">AED <?php echo number_format($payslip['deductions'], 2); ?></td>
                                        <td class="text-primary fw-bold">AED <?php echo number_format($payslip['net'], 2); ?></td>
                                        <td>
                                            <?php if ($payslip['status'] == 'generated') { ?>
                                                <span class="badge bg-info">Generated</span>
                                            <?php } elseif ($payslip['status'] == 'submitted') { ?>
                                                <span class="badge bg-success">Submitted</span>
                                            <?php } elseif ($payslip['status'] == 'paid') { ?>
                                                <span class="badge bg-primary">Paid</span>
                                            <?php } else { ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($payslip['status']); ?></span>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <a href="view_payslip.php?id=<?php echo $payslip['id']; ?>"
                                               class="btn btn-sm btn-info"
                                               title="View payslip">
                                                <i class="ph-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php
                                    }
                                }
                                ?>
                            </tbody>
                            <?php if ($payslips_query->num_rows > 0) { ?>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3">TOTAL</th>
                                    <th class="text-success">AED <?php echo number_format($total_gross, 2); ?></th>
                                    <th class="text-danger">AED <?php echo number_format($total_deductions, 2); ?></th>
                                    <th class="text-primary">AED <?php echo number_format($total_net, 2); ?></th>
                                    <th colspan="2"></th>
                                </tr>
                            </tfoot>
                            <?php } ?>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
