<?php

use App\Core\DB;
$module = 'payslips';
$module_caption = 'View Payslip';

include('admin_elements/admin_header.php');
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$payslip_id = intval($_GET['id'] ?? 0);

if (empty($payslip_id)) {
    header("Location:listing_payslips.php");
    exit;
}

// Get payslip details
$payslip_query = $mysqli->query("
    SELECT
        ps.*,
        u.full_name,
        u.email,
        d.department,
        pr.period_start,
        pr.period_end,
        pr.status as run_status
    FROM `" . DB::PAYSLIPS . "` ps
    INNER JOIN `" . DB::USERS . "` u ON ps.employee_id = u.id
    LEFT JOIN `" . DB::DEPARTMENTS . "` d ON u.department_id = d.id
    INNER JOIN `" . DB::PAYROLL_RUNS . "` pr ON ps.payroll_run_id = pr.id
    WHERE ps.id = $payslip_id
");

if (!$payslip_query || $payslip_query->num_rows == 0) {
    header("Location:listing_payslips.php");
    exit;
}

$payslip = $payslip_query->fetch_assoc();

// Get salary breakdown (earnings and deductions)
$breakdown_query = $mysqli->query("
    SELECT
        pc.component_name,
        pc.component_type,
        ss.amount
    FROM `" . DB::SALARY_STRUCTURES . "` ss
    INNER JOIN `" . DB::PAYROLL_COMPONENTS . "` pc ON ss.component_id = pc.id
    WHERE ss.employee_id = " . $payslip['employee_id'] . "
    AND (ss.effective_from IS NULL OR YEAR(ss.effective_from) = 0 OR ss.effective_from <= '" . $payslip['period_end'] . "')
    AND (ss.effective_to IS NULL OR YEAR(ss.effective_to) = 0 OR ss.effective_to >= '" . $payslip['period_start'] . "')
    ORDER BY pc.component_type DESC, pc.component_name ASC
");

$earnings = [];
$deductions = [];

while ($item = $breakdown_query->fetch_assoc()) {
    if ($item['component_type'] == 'earning') {
        $earnings[] = $item;
    } else {
        $deductions[] = $item;
    }
}
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

            <!-- Payslip Header -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="ph-file-text me-2"></i>Payslip #<?php echo str_pad($payslip_id, 6, '0', STR_PAD_LEFT); ?>
                    </h5>
                    <div>
                        <a href="listing_payslips.php" class="btn btn-light btn-sm">
                            <i class="ph-arrow-left me-2"></i>Back to Payslips
                        </a>
                        <?php if ($payslip['status'] == 'paid') { ?>
                            <a href="mark_payslip_paid.php?action=mark_unpaid&id=<?php echo $payslip_id; ?>"
                               class="btn btn-warning btn-sm"
                               onclick="return confirm('Mark this payslip as unpaid?')">
                                <i class="ph-arrow-counter-clockwise me-2"></i>Mark as Unpaid
                            </a>
                        <?php } else { ?>
                            <a href="mark_payslip_paid.php?action=mark_paid&id=<?php echo $payslip_id; ?>"
                               class="btn btn-success btn-sm"
                               onclick="return confirm('Mark this payslip as paid?')">
                                <i class="ph-check-circle me-2"></i>Mark as Paid
                            </a>
                        <?php } ?>
                        <button onclick="window.print()" class="btn btn-primary btn-sm">
                            <i class="ph-printer me-2"></i>Print Payslip
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payslip Content -->
            <div class="card" id="printable-payslip">
                <div class="card-body">
                    <!-- Company & Employee Info -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h4 class="mb-3">Flash Logistics</h4>
                            <p class="text-muted mb-1">Salary Slip</p>
                            <p class="text-muted mb-0">
                                For the period: <strong><?php echo processDateYtoD($payslip['period_start']); ?></strong> to <strong><?php echo processDateYtoD($payslip['period_end']); ?></strong>
                            </p>
                        </div>
                        <div class="col-md-6 text-end">
                            <h5>Payslip #<?php echo str_pad($payslip_id, 6, '0', STR_PAD_LEFT); ?></h5>
                            <p class="mb-1">
                                <?php if ($payslip['status'] == 'generated') { ?>
                                    <span class="badge bg-info">Generated</span>
                                <?php } elseif ($payslip['status'] == 'submitted') { ?>
                                    <span class="badge bg-success">Submitted</span>
                                <?php } elseif ($payslip['status'] == 'paid') { ?>
                                    <span class="badge bg-primary">Paid</span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary"><?php echo ucfirst($payslip['status']); ?></span>
                                <?php } ?>
                            </p>
                            <p class="text-muted mb-0">Generated: <?php echo processDateYtoD($payslip['created_at']); ?></p>
                        </div>
                    </div>

                    <hr>

                    <!-- Employee Details -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Employee Information</h6>
                            <div class="table-responsive">
<table class="table table-sm table-borderless">
                                <tr>
                                    <td width="40%" class="text-muted">Employee Name:</td>
                                    <td><strong><?php echo s__($payslip['full_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Employee ID:</td>
                                    <td><?php echo $payslip['employee_id']; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Department:</td>
                                    <td><?php echo s__($payslip['department']) ?: '-'; ?></td>
                                </tr>
                            </table>
</div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Payment Information</h6>
                            <div class="table-responsive">
<table class="table table-sm table-borderless">
                                <tr>
                                    <td width="40%" class="text-muted">Pay Period:</td>
                                    <td>
                                        <?php
                                        $start = new DateTime($payslip['period_start']);
                                        $end = new DateTime($payslip['period_end']);
                                        echo $start->format('M d, Y') . ' - ' . $end->format('M d, Y');
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Date:</td>
                                    <td><?php echo (!empty($payslip['paid_at']) && $payslip['paid_at'] != '0000-00-00') ? processDateYtoD($payslip['paid_at']) : 'Not yet paid'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Payment Status:</td>
                                    <td>
                                        <?php if ($payslip['status'] == 'paid') { ?>
                                            <span class="text-success"><i class="ph-check-circle"></i> Paid</span>
                                        <?php } else { ?>
                                            <span class="text-warning"><i class="ph-clock"></i> Pending</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            </table>
</div>
                        </div>
                    </div>

                    <hr>

                    <!-- Salary Breakdown -->
                    <div class="row mb-4">
                        <!-- Earnings -->
                        <div class="col-md-6">
                            <h6 class="text-success mb-3">
                                <i class="ph-plus-circle me-1"></i>Earnings
                            </h6>
                            <div class="table-responsive">
<table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component</th>
                                        <th class="text-end">Amount (AED)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_earnings = 0;
                                    if (count($earnings) == 0) {
                                        echo '<tr><td colspan="2" class="text-center text-muted">No earnings</td></tr>';
                                    } else {
                                        foreach ($earnings as $earning) {
                                            $total_earnings += $earning['amount'];
                                    ?>
                                        <tr>
                                            <td><?php echo s__($earning['component_name']); ?></td>
                                            <td class="text-end"><?php echo number_format($earning['amount'], 2); ?></td>
                                        </tr>
                                    <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                                <tfoot class="table-success">
                                    <tr>
                                        <th>Total Earnings</th>
                                        <th class="text-end"><?php echo number_format($payslip['gross'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
</div>
                        </div>

                        <!-- Deductions -->
                        <div class="col-md-6">
                            <h6 class="text-danger mb-3">
                                <i class="ph-minus-circle me-1"></i>Deductions
                            </h6>
                            <div class="table-responsive">
<table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Component</th>
                                        <th class="text-end">Amount (AED)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_deductions = 0;
                                    if (count($deductions) == 0) {
                                        echo '<tr><td colspan="2" class="text-center text-muted">No deductions</td></tr>';
                                    } else {
                                        foreach ($deductions as $deduction) {
                                            $total_deductions += $deduction['amount'];
                                    ?>
                                        <tr>
                                            <td><?php echo s__($deduction['component_name']); ?></td>
                                            <td class="text-end"><?php echo number_format($deduction['amount'], 2); ?></td>
                                        </tr>
                                    <?php
                                        }
                                    }
                                    ?>
                                </tbody>
                                <tfoot class="table-danger">
                                    <tr>
                                        <th>Total Deductions</th>
                                        <th class="text-end"><?php echo number_format($payslip['deductions'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
</div>
                        </div>
                    </div>

                    <hr>

                    <!-- Net Salary -->
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h5 class="mb-1">Net Salary</h5>
                                    <small>Total Earnings - Total Deductions</small>
                                </div>
                                <div class="col-md-6 text-end">
                                    <h2 class="mb-0">AED <?php echo number_format($payslip['net'], 2); ?></h2>
                                    <small>(<?php echo convertNumberToWords($payslip['net']); ?> Dirhams Only)</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Note -->
                    <div class="mt-4 pt-4 border-top">
                        <p class="text-muted small mb-0">
                            <i class="ph-info me-1"></i>
                            This is a computer-generated payslip and does not require a signature. For any queries, please contact the HR department.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<!-- Print Styles -->
<style>
@media print {
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }
    .card-header,
    .btn,
    .breadcrumb,
    .copyright,
    .sidebar,
    .navbar {
        display: none !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    #printable-payslip {
        page-break-inside: avoid;
    }
}
</style>

<?php
// Helper function to convert number to words
function convertNumberToWords($number) {
    $number = intval($number);
    $words = array(
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety'
    );

    if ($number < 21) {
        return $words[$number];
    } elseif ($number < 100) {
        $tens = intval($number / 10) * 10;
        $units = $number % 10;
        return $words[$tens] . ($units ? ' ' . $words[$units] : '');
    } elseif ($number < 1000) {
        $hundreds = intval($number / 100);
        $remainder = $number % 100;
        return $words[$hundreds] . ' Hundred' . ($remainder ? ' and ' . convertNumberToWords($remainder) : '');
    } elseif ($number < 1000000) {
        $thousands = intval($number / 1000);
        $remainder = $number % 1000;
        return convertNumberToWords($thousands) . ' Thousand' . ($remainder ? ' ' . convertNumberToWords($remainder) : '');
    } else {
        return number_format($number);
    }
}
?>

<?php include('admin_elements/admin_footer.php'); ?>
