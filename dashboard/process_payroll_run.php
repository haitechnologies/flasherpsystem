<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'payroll_runs';
$tbl_name = DB::PAYROLL_RUNS;

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

$payrollRunId = (int)($_GET['id'] ?? 0);

if ($payrollRunId <= 0) {
    $_SESSION['error_message'] = 'Invalid payroll run ID.';
    header('Location: listing_payroll_runs.php');
    exit;
}

if (!granted_('edit', $module)) {
    $_SESSION['error_message'] = 'You do not have permission to generate payslips.';
    header('Location: listing_payroll_runs.php');
    exit;
}

$runResult = $mysqli->query("
    SELECT id, status, total_gross
    FROM `" . DB::PAYROLL_RUNS . "`
    WHERE id = $payrollRunId
");
$run = $runResult->fetch_assoc();

if (!$run) {
    $_SESSION['error_message'] = 'Payroll run not found.';
    header('Location: listing_payroll_runs.php');
    exit;
}

if ($run['status'] !== 'draft' || (float)$run['total_gross'] > 0) {
    $_SESSION['error_message'] = 'Payslips can only be generated for a draft run with no existing payslips.';
    header("Location: view_payroll_run.php?id=$payrollRunId");
    exit;
}

$existingResult = $mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::PAYSLIPS . "` WHERE payroll_run_id = $payrollRunId");
$existingCount = (int)$existingResult->fetch_assoc()['cnt'];
if ($existingCount > 0) {
    $_SESSION['error_message'] = 'Payslips already exist for this payroll run. Delete them first to regenerate.';
    header("Location: view_payroll_run.php?id=$payrollRunId");
    exit;
}

$employeesResult = $mysqli->query("
    SELECT DISTINCT ss.employee_id, u.full_name
    FROM `" . DB::SALARY_STRUCTURES . "` ss
    INNER JOIN `" . DB::USERS . "` u ON u.id = ss.employee_id
    WHERE u.organization_id = " . (int)$activeOrganizationId . "
    AND u.is_active = 1
    AND u.id > 1
    ORDER BY u.full_name ASC
");

$generatedCount = 0;
$totalGross = 0;
$totalDeductions = 0;
$totalNet = 0;
$errors = [];

$mysqli->begin_transaction();

try {
    while ($emp = $employeesResult->fetch_assoc()) {
        $employeeId = (int)$emp['employee_id'];

        $componentsResult = $mysqli->query("
            SELECT pc.component_type, SUM(ss.amount) as total_amount
            FROM `" . DB::SALARY_STRUCTURES . "` ss
            INNER JOIN `" . DB::PAYROLL_COMPONENTS . "` pc ON pc.id = ss.component_id
            WHERE ss.employee_id = $employeeId
            GROUP BY pc.component_type
        ");

        $gross = 0;
        $deductions = 0;

        while ($comp = $componentsResult->fetch_assoc()) {
            $amount = (float)($comp['total_amount'] ?? 0);
            if ($comp['component_type'] === 'earning') {
                $gross += $amount;
            } else {
                $deductions += $amount;
            }
        }

        $net = $gross - $deductions;
        if ($net < 0) $net = 0;

        $totalGross += $gross;
        $totalDeductions += $deductions;
        $totalNet += $net;

        $status = 'generated';

        $insertResult = $mysqli->query("
            INSERT INTO `" . DB::PAYSLIPS . "`
                (payroll_run_id, employee_id, gross, deductions, net, status, created_at)
            VALUES
                ($payrollRunId, $employeeId, $gross, $deductions, $net, '$status', NOW())
        ");

        if ($insertResult) {
            $generatedCount++;
        } else {
            $errors[] = 'Failed to generate payslip for ' . htmlspecialchars($emp['full_name'] ?? "Employee #$employeeId");
        }
    }

    if ($generatedCount > 0) {
        // Update payroll run totals and mark as approved
        $updateResult = $mysqli->query("
            UPDATE `" . DB::PAYROLL_RUNS . "`
            SET total_gross = $totalGross,
                total_deductions = $totalDeductions,
                total_net = $totalNet,
                status = 'approved'
            WHERE id = $payrollRunId
        ");

        if (!$updateResult) {
            throw new \RuntimeException('Failed to update payroll run totals.');
        }

        // Insert payroll run items
        $itemsResult = $mysqli->query("
            SELECT id, gross, deductions, net
            FROM `" . DB::PAYSLIPS . "`
            WHERE payroll_run_id = $payrollRunId
        ");

        while ($item = $itemsResult->fetch_assoc()) {
            $itemGross = (float)$item['gross'];
            $itemDed = (float)$item['deductions'];
            $itemNet = (float)$item['net'];
            $itemPayslipId = (int)$item['id'];

            $mysqli->query("
                INSERT INTO `" . DB::HR_PAYROLL_RUN_ITEMS . "`
                    (payroll_run_id, payslip_id, gross, deductions, net, created_at)
                VALUES
                    ($payrollRunId, $itemPayslipId, $itemGross, $itemDed, $itemNet, NOW())
            ");
        }
    }

    $mysqli->commit();

    if ($generatedCount > 0) {
        $_SESSION['success_message'] = "Successfully generated $generatedCount payslip(s).";
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode(' ', $errors);
        }
    } else {
        $_SESSION['error_message'] = 'No employees found with salary structures. Please create salary structures first.';
    }

} catch (\Throwable $e) {
    $mysqli->rollback();
    log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'payroll_runs', 'action' => 'generate_payslips', 'payroll_run_id' => $payrollRunId]);
    $_SESSION['error_message'] = 'Error generating payslips: ' . $e->getMessage();
}

header("Location: view_payroll_run.php?id=$payrollRunId");
exit;
