<?php

use App\Core\DB;
use App\Security\Roles;
include('admin_elements/admin_header.php');

$module = 'accounts';
$module_caption = 'Accounting Dashboard';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();


/*
|--------------------------------------------------------------------------
| FINANCIAL DATA AGGREGATION
|--------------------------------------------------------------------------
*/

// Date ranges
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));
$current_year_start = date('Y-01-01');
$today_date = date('Y-m-d');
$ar_report_from = date('d-m-Y', strtotime($current_year_start));
$ar_report_to = date('d-m-Y', strtotime($today_date));
$ar_report_link = 'report_ar_aging_details.php?action=run_report&date_from=' . $ar_report_from . '&date_to=' . $ar_report_to . '&report_basis=accrual';

// Aging bucket date ranges (for due date filtering)
$due_date_1_15_from = date('d-m-Y', strtotime('-15 days'));  // Due 15 days ago
$due_date_1_15_to = date('d-m-Y', strtotime('-1 day'));      // Due yesterday
$ar_report_link_1_15 = 'report_ar_aging_details.php?action=run_report&date_from=' . $due_date_1_15_from . '&date_to=' . $due_date_1_15_to . '&report_basis=accrual';

$due_date_16_30_from = date('d-m-Y', strtotime('-30 days'));
$due_date_16_30_to = date('d-m-Y', strtotime('-16 days'));
$ar_report_link_16_30 = 'report_ar_aging_details.php?action=run_report&date_from=' . $due_date_16_30_from . '&date_to=' . $due_date_16_30_to . '&report_basis=accrual';

$due_date_31_45_from = date('d-m-Y', strtotime('-45 days'));
$due_date_31_45_to = date('d-m-Y', strtotime('-31 days'));
$ar_report_link_31_45 = 'report_ar_aging_details.php?action=run_report&date_from=' . $due_date_31_45_from . '&date_to=' . $due_date_31_45_to . '&report_basis=accrual';

$due_date_over_45_from = date('d-m-Y', strtotime('-1000 days'));  // Far back in the past
$due_date_over_45_to = date('d-m-Y', strtotime('-46 days'));
$ar_report_link_over_45 = 'report_ar_aging_details.php?action=run_report&date_from=' . $due_date_over_45_from . '&date_to=' . $due_date_over_45_to . '&report_basis=accrual';

// INVOICES METRICS
$total_invoices = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::INVOICES . "`")->fetch_assoc()['count'];
$invoices_this_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::INVOICES . "` WHERE invoice_date BETWEEN '$current_month_start' AND '$current_month_end'")->fetch_assoc()['count'];
$draft_invoices = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::INVOICES . "` WHERE invoice_status='draft'")->fetch_assoc()['count'];
$sent_invoices = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::INVOICES . "` WHERE invoice_status='sent'")->fetch_assoc()['count'];
$paid_invoices = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::INVOICES . "` WHERE invoice_status='paid'")->fetch_assoc()['count'];

// REVENUE CALCULATION
$revenue_query = "
    SELECT 
        SUM(grand_total) as total_revenue,
        SUM(CASE WHEN invoice_date BETWEEN '$current_month_start' AND '$current_month_end' THEN grand_total ELSE 0 END) as current_month_revenue,
        SUM(CASE WHEN invoice_date BETWEEN '$last_month_start' AND '$last_month_end' THEN grand_total ELSE 0 END) as last_month_revenue,
        SUM(CASE WHEN invoice_date >= '$current_year_start' THEN grand_total ELSE 0 END) as year_revenue
    FROM `" . DB::INVOICES . "`
    WHERE invoice_status IN ('sent', 'paid')
";
$revenue_data = $mysqli->query($revenue_query)->fetch_assoc();
$total_revenue = $revenue_data['total_revenue'] ?? 0;
$current_month_revenue = $revenue_data['current_month_revenue'] ?? 0;
$last_month_revenue = $revenue_data['last_month_revenue'] ?? 0;
$year_revenue = $revenue_data['year_revenue'] ?? 0;

// ACCOUNTS RECEIVABLE (Outstanding invoices - payments - credits)
$ar_query = "
    SELECT 
        SUM(grand_total) as total_ar,
        SUM(CASE WHEN DATEDIFF(NOW(), expiry_date) <= 0 THEN grand_total ELSE 0 END) as current_ar,
        SUM(CASE WHEN DATEDIFF(NOW(), expiry_date) BETWEEN 1 AND 15 THEN grand_total ELSE 0 END) as ar_1_15,
        SUM(CASE WHEN DATEDIFF(NOW(), expiry_date) BETWEEN 16 AND 30 THEN grand_total ELSE 0 END) as ar_16_30,
        SUM(CASE WHEN DATEDIFF(NOW(), expiry_date) BETWEEN 31 AND 45 THEN grand_total ELSE 0 END) as ar_31_45,
        SUM(CASE WHEN DATEDIFF(NOW(), expiry_date) > 45 THEN grand_total ELSE 0 END) as ar_over_45
    FROM `" . DB::INVOICES . "`
    WHERE invoice_status IN ('sent', 'partially_paid', 'overdue', 'paid')
";
$ar_data = $mysqli->query($ar_query)->fetch_assoc();
$total_ar = $ar_data['total_ar'] ?? 0;
$current_ar = $ar_data['current_ar'] ?? 0;
$overdue_ar = ($ar_data['ar_1_15'] ?? 0) + ($ar_data['ar_16_30'] ?? 0) + ($ar_data['ar_31_45'] ?? 0) + ($ar_data['ar_over_45'] ?? 0);
$ar_1_15 = $ar_data['ar_1_15'] ?? 0;
$ar_16_30 = $ar_data['ar_16_30'] ?? 0;
$ar_31_45 = $ar_data['ar_31_45'] ?? 0;
$ar_over_45 = $ar_data['ar_over_45'] ?? 0;

// EXPENSES METRICS
$total_expenses = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::EXPENSES . "`")->fetch_assoc()['count'];
$expenses_this_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::EXPENSES . "` WHERE expense_date BETWEEN '$current_month_start' AND '$current_month_end'")->fetch_assoc()['count'];

// EXPENSE AMOUNT CALCULATION
$expense_query = "
    SELECT 
        SUM(grand_total) as total_expenses,
        SUM(CASE WHEN expense_date BETWEEN '$current_month_start' AND '$current_month_end' THEN grand_total ELSE 0 END) as current_month_expenses,
        SUM(CASE WHEN expense_date BETWEEN '$last_month_start' AND '$last_month_end' THEN grand_total ELSE 0 END) as last_month_expenses,
        SUM(CASE WHEN expense_date >= '$current_year_start' THEN grand_total ELSE 0 END) as year_expenses
    FROM `" . DB::EXPENSES . "`
";
$expense_data = $mysqli->query($expense_query)->fetch_assoc();
$total_expense_amount = $expense_data['total_expenses'] ?? 0;
$current_month_expenses = $expense_data['current_month_expenses'] ?? 0;
$last_month_expenses = $expense_data['last_month_expenses'] ?? 0;
$year_expenses = $expense_data['year_expenses'] ?? 0;

// ACCOUNTS PAYABLE (Unpaid expenses)
// Note: Currently showing all expenses as AP since expenses table doesn't track payment status
$ap_query = "
    SELECT 
        SUM(grand_total) as total_ap
    FROM `" . DB::EXPENSES . "`
";
$ap_data = $mysqli->query($ap_query)->fetch_assoc();
$total_ap = $ap_data['total_ap'] ?? 0;
$current_ap = $total_ap; // All approved expenses considered current
$overdue_ap = 0; // No due date tracking for expenses

// PAYMENTS RECEIVED
$payments_received_query = "
    SELECT 
        COUNT(*) as count,
        SUM(total_amount_received) as total_amount,
        SUM(CASE WHEN payment_date BETWEEN '$current_month_start' AND '$current_month_end' THEN total_amount_received ELSE 0 END) as month_amount
    FROM `" . DB::PAYMENTS_RECEIVED . "`
";
$payments_received_data = $mysqli->query($payments_received_query)->fetch_assoc();
$total_payments_received = $payments_received_data['count'] ?? 0;
$total_payments_received_amount = $payments_received_data['total_amount'] ?? 0;
$month_payments_received = $payments_received_data['month_amount'] ?? 0;

// PROFIT/LOSS CALCULATION
$current_month_profit = $current_month_revenue - $current_month_expenses;
$year_profit = $year_revenue - $year_expenses;
$profit_margin = $current_month_revenue > 0 ? round(($current_month_profit / $current_month_revenue) * 100, 1) : 0;

// CUSTOMERS & VENDORS
$total_customers = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::CUSTOMERS . "`")->fetch_assoc()['count'];
$total_vendors = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::VENDORS . "`")->fetch_assoc()['count'];

// BANKS & ACCOUNTS
$total_banks = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::BANKS . "`")->fetch_assoc()['count'];
$total_accounts = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::ACCOUNTS . "`")->fetch_assoc()['count'];

// GROWTH CALCULATIONS
$revenue_growth = $last_month_revenue > 0 ? round((($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100, 1) : 0;
$expense_growth = $last_month_expenses > 0 ? round((($current_month_expenses - $last_month_expenses) / $last_month_expenses) * 100, 1) : 0;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/

?>

<!-- Main content -->
<div class="content-wrapper">

	<!-- Page header -->
	<div class="page-header page-header-light shadow carriers-page-header">
		<div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3">
			<div class="d-flex align-items-center py-3">
				<div class="ms-3">
					<h5 class="mb-0">
						<i class="ph-currency-circle-dollar me-2"></i>
						Accounting & Finance Dashboard
					</h5>
					<span class="text-muted">Financial overview and insights</span>
				</div>
			</div>

			<div class="my-lg-auto ms-lg-auto">
				<div class="d-sm-flex align-items-center mb-3 mb-lg-0 ms-lg-3">
					<a href="invoices.php" class="btn btn-success btn-sm me-2">
						<i class="ph-plus-circle me-1"></i>
						New Invoice
					</a>
					<a href="expenses.php" class="btn btn-danger btn-sm me-2">
						<i class="ph-plus-circle me-1"></i>
						New Expense
					</a>
					<span class="badge bg-primary bg-opacity-20 text-primary">
						<i class="ph-calendar-blank me-1"></i>
						<?php echo date('F Y'); ?>
					</span>
				</div>
			</div>

			<a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
				<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
			</a>
		</div>
	</div>
	<!-- /page header -->

	<!-- Inner content -->
	<div class="content-inner">

		<!-- Content area -->
		<div class="content">

			<?php include('admin_elements/breadcrumb.php'); ?>

			<!-- Dashboard content -->

			<div class="row">
				<div class="col-sm-6 col-xl-3">
					<div class="card card-body border-start border-success border-3">
						<div class="d-flex align-items-center mb-2">
							<h6 class="mb-0 text-muted">Revenue (This Month)</h6>
							<?php if ($revenue_growth != 0): ?>
								<span class="badge <?php echo $revenue_growth > 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $revenue_growth > 0 ? 'text-success' : 'text-danger'; ?> rounded-pill ms-auto">
									<i class="ph-trend-<?php echo $revenue_growth > 0 ? 'up' : 'down'; ?> me-1"></i>
									<?php echo $revenue_growth > 0 ? '+' : ''; ?><?php echo abs($revenue_growth); ?>%
								</span>
							<?php endif; ?>
						</div>
						<h3 class="mb-1"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($current_month_revenue, 2); ?></h3>
						<small class="text-muted">YTD: <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($year_revenue, 2); ?></small>
					</div>
				</div>

				<div class="col-sm-6 col-xl-3">
					<div class="card card-body border-start border-danger border-3">
						<div class="d-flex align-items-center mb-2">
							<h6 class="mb-0 text-muted">Expenses (This Month)</h6>
							<?php if ($expense_growth != 0): ?>
								<span class="badge <?php echo $expense_growth > 0 ? 'bg-danger' : 'bg-success'; ?> bg-opacity-10 <?php echo $expense_growth > 0 ? 'text-danger' : 'text-success'; ?> rounded-pill ms-auto">
									<i class="ph-trend-<?php echo $expense_growth > 0 ? 'up' : 'down'; ?> me-1"></i>
									<?php echo $expense_growth > 0 ? '+' : ''; ?><?php echo abs($expense_growth); ?>%
								</span>
							<?php endif; ?>
						</div>
						<h3 class="mb-1"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($current_month_expenses, 2); ?></h3>
						<small class="text-muted">YTD: <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($year_expenses, 2); ?></small>
					</div>
				</div>

				<div class="col-sm-6 col-xl-3">
					<div class="card card-body border-start <?php echo $current_month_profit >= 0 ? 'border-success' : 'border-danger'; ?> border-3">
						<div class="d-flex align-items-center mb-2">
							<h6 class="mb-0 text-muted">Net Profit (Month)</h6>
							<span class="badge <?php echo $current_month_profit >= 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $current_month_profit >= 0 ? 'text-success' : 'text-danger'; ?> rounded-pill ms-auto">
								<?php echo $current_month_profit >= 0 ? '+' : ''; ?><?php echo $profit_margin; ?>%
							</span>
						</div>
						<h3 class="mb-1"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($current_month_profit, 2); ?></h3>
						<small class="text-muted">Year: <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($year_profit, 2); ?></small>
					</div>
				</div>

				<div class="col-sm-6 col-xl-3">
					<div class="card card-body border-start border-primary border-3">
						<div class="d-flex align-items-center mb-2">
							<h6 class="mb-0 text-muted">Outstanding (AR - AP)</h6>
						</div>
						<h3 class="mb-1"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format($total_ar - $total_ap, 2); ?></h3>
						<small class="text-muted">AR: <?php echo number_format($total_ar, 0); ?> &middot; AP: <?php echo number_format($total_ap, 0); ?></small>
					</div>
				</div>
			</div>

			<div class="row">
				<div class="col-xl-8">

					<div class="row">
						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-wallet me-2"></i>
										Total Receivables
										<i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" title="Outstanding amounts from customers"></i>
									</h6>
									<div class="ms-auto">
										<div class="btn-group">
											<a href="#" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">New</a>
											<div class="dropdown-menu dropdown-menu-end">
												<a href="invoices.php" class="dropdown-item"><i class="ph-plus-circle me-2"></i>New Invoice</a>
												<a href="payments_received.php" class="dropdown-item"><i class="ph-plus-circle me-2"></i>Record Payment</a>
											</div>
										</div>
									</div>
								</div>
								<div class="card-body">
									<div class="row text-center mb-3">
										<div class="col-6">
											<a href="<?php echo $ar_report_link; ?>" class="text-decoration-none">
												<div class="text-info fs-sm mb-1">CURRENT</div>
												<h4 class="mb-0 text-body"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($current_ar, 0); ?></h4>
											</a>
										</div>
										<div class="col-6">
											<a href="<?php echo $ar_report_link_1_15; ?>" class="text-decoration-none">
												<div class="text-danger fs-sm mb-1">OVERDUE</div>
												<h4 class="mb-0 text-danger"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($overdue_ar, 0); ?></h4>
											</a>
										</div>
									</div>
									<div class="border-top pt-3 mb-3">
										<div class="d-flex justify-content-between mb-2">
											<span class="text-muted">Total Invoiced</span>
											<span class="fw-semibold"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($total_revenue, 0); ?></span>
										</div>
										<div class="d-flex justify-content-between">
											<span class="text-muted">Payments Received</span>
											<span class="fw-semibold text-success"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($month_payments_received, 0); ?></span>
										</div>
									</div>
									<div class="border-top pt-3">
										<div class="fs-sm fw-semibold mb-2">Aging Breakdown</div>
										<?php
										$ar_buckets = [
											['label' => '1-15 Days', 'value' => $ar_1_15, 'color' => 'warning', 'link' => $ar_report_link_1_15],
											['label' => '16-30 Days', 'value' => $ar_16_30, 'color' => 'orange', 'link' => $ar_report_link_16_30],
											['label' => '31-45 Days', 'value' => $ar_31_45, 'color' => 'danger', 'link' => $ar_report_link_31_45],
											['label' => '>45 Days', 'value' => $ar_over_45, 'color' => 'dark', 'link' => $ar_report_link_over_45],
										];
										foreach ($ar_buckets as $bucket):
										?>
										<a href="<?php echo $bucket['link']; ?>" class="text-decoration-none">
											<div class="mb-2">
												<div class="d-flex justify-content-between mb-1">
													<small class="text-muted"><?php echo $bucket['label']; ?></small>
													<small class="fw-semibold text-body"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($bucket['value'], 0); ?></small>
												</div>
												<div class="progress" style="height: 6px;">
													<div class="progress-bar bg-<?php echo $bucket['color']; ?>" style="width: <?php echo $total_ar > 0 ? ($bucket['value'] / $total_ar * 100) : 0; ?>%"></div>
												</div>
											</div>
										</a>
										<?php endforeach; ?>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-credit-card me-2"></i>
										Total Payables
										<i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" title="Outstanding amounts to vendors"></i>
									</h6>
									<div class="ms-auto">
										<div class="btn-group">
											<a href="#" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">New</a>
											<div class="dropdown-menu dropdown-menu-end">
												<a href="expenses.php" class="dropdown-item"><i class="ph-plus-circle me-2"></i>New Expense</a>
												<a href="payments_made.php" class="dropdown-item"><i class="ph-plus-circle me-2"></i>Record Payment</a>
											</div>
										</div>
									</div>
								</div>
								<div class="card-body">
									<div class="row text-center mb-3">
										<div class="col-6">
											<div class="text-info fs-sm mb-1">CURRENT</div>
											<h4 class="mb-0"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($current_ap, 0); ?></h4>
										</div>
										<div class="col-6">
											<div class="text-danger fs-sm mb-1">OVERDUE</div>
											<h4 class="mb-0 text-danger"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($overdue_ap, 0); ?></h4>
										</div>
									</div>
									<div class="border-top pt-3">
										<div class="d-flex justify-content-between mb-2">
											<span class="text-muted">Total Expenses</span>
											<span class="fw-semibold"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($total_expense_amount, 0); ?></span>
										</div>
										<div class="d-flex justify-content-between">
											<span class="text-muted">This Month</span>
											<span class="fw-semibold text-danger"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($current_month_expenses, 0); ?></span>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h6 class="mb-0">
								<i class="ph-file-text me-2"></i>
								Recent Invoices
							</h6>
							<div class="ms-auto">
								<a href="listing_invoices.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
							</div>
						</div>
						<div class="card-body p-0">
							<div class="table-responsive">
								<table class="table table-hover">
									<thead class="table-light">
										<tr>
											<th>Invoice #</th>
											<th>Customer</th>
											<th>Date</th>
											<th>Status</th>
											<th class="text-end">Amount</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$result = $mysqli->query("SELECT * FROM `" . DB::INVOICES . "` ORDER BY id DESC LIMIT 8");
										if ($result->num_rows > 0) {
											while ($row = $result->fetch_array()) {
												$customer_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
												$status = $row['invoice_status'];
												$status_class = $status == 'paid' ? 'success' : ($status == 'sent' ? 'warning' : 'secondary');
										?>
												<tr>
													<td class="py-2">
														<a href="invoice_overview.php?invoice_id=<?php echo $row['id']; ?>" class="fw-semibold">
															<?php echo $row['invoice_no']; ?>
														</a>
													</td>
													<td class="py-2"><?php echo $customer_name; ?></td>
													<td class="py-2 text-muted"><?php echo date("d M Y", strtotime($row['invoice_date'])); ?></td>
													<td class="py-2">
														<span class="badge bg-<?php echo $status_class; ?> bg-opacity-20 text-<?php echo $status_class; ?>">
															<?php echo ucfirst($status); ?>
														</span>
													</td>
													<td class="py-2 text-end fw-semibold"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($row['grand_total'], 2); ?></td>
												</tr>
										<?php
											}
										} else {
											echo '<tr><td class="text-center text-muted py-3" colspan="5">No invoices found</td></tr>';
										}
										?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h6 class="mb-0">
								<i class="ph-receipt me-2"></i>
								Recent Expenses
							</h6>
							<div class="ms-auto">
								<a href="listing_expenses.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
							</div>
						</div>
						<div class="card-body p-0">
							<div class="table-responsive">
								<table class="table table-hover">
									<thead class="table-light">
										<tr>
											<th>Expense #</th>
											<th>Vendor</th>
											<th>Date</th>
											<th>Status</th>
											<th class="text-end">Amount</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$result = $mysqli->query("SELECT * FROM `" . DB::EXPENSES . "` ORDER BY id DESC LIMIT 8");
										if ($result->num_rows > 0) {
											while ($row = $result->fetch_array()) {
												$vendor_name = getTableAttr('display_name', DB::VENDORS, $row['vendor_id']);
										$status = 'recorded';
										$status_class = 'info';
												$reference = !empty($row['reference_no']) ? $row['reference_no'] : 'EXP-' . $row['id'];
										?>
												<tr>
													<td class="py-2">
														<a href="expense_overview.php?expense_id=<?php echo $row['id']; ?>" class="fw-semibold">
															<?php echo $reference; ?>
														</a>
													</td>
													<td class="py-2"><?php echo $vendor_name; ?></td>
													<td class="py-2 text-muted"><?php echo date("d M Y", strtotime($row['expense_date'])); ?></td>
													<td class="py-2">
														<span class="badge bg-<?php echo $status_class; ?> bg-opacity-20 text-<?php echo $status_class; ?>">
															<?php echo ucfirst($status); ?>
														</span>
													</td>
													<td class="py-2 text-end fw-semibold"><?php echo BASE_CURRENCY['code']; ?><?php echo number_format($row['grand_total'], 2); ?></td>
												</tr>
										<?php
											}
										} else {
											echo '<tr><td class="text-center text-muted py-3" colspan="5">No expenses found</td></tr>';
										}
										?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

				</div>

				<div class="col-xl-4">

					<div class="row">
						<div class="col-sm-6 col-xl-12 mb-3">
							<a href="listing_invoices.php?status=draft" class="text-decoration-none">
								<div class="card card-body border-start border-secondary border-3">
									<div class="d-flex align-items-center">
										<i class="ph-files ph-2x text-secondary opacity-75 me-3"></i>
										<div class="flex-fill">
											<h3 class="mb-1 text-body"><?php echo $draft_invoices; ?></h3>
											<div class="text-muted">Draft Invoices</div>
										</div>
									</div>
								</div>
							</a>
						</div>

						<div class="col-sm-6 col-xl-12 mb-3">
							<a href="listing_invoices.php?status=sent" class="text-decoration-none">
								<div class="card card-body border-start border-warning border-3">
									<div class="d-flex align-items-center">
										<i class="ph-paper-plane-tilt ph-2x text-warning opacity-75 me-3"></i>
										<div class="flex-fill">
											<h3 class="mb-1 text-body"><?php echo $sent_invoices; ?></h3>
											<div class="text-muted">Sent Invoices</div>
										</div>
									</div>
								</div>
							</a>
						</div>

						<div class="col-sm-6 col-xl-12 mb-3">
							<a href="listing_customers.php" class="text-decoration-none">
								<div class="card card-body border-start border-success border-3">
									<div class="d-flex align-items-center">
										<i class="ph-users ph-2x text-success opacity-75 me-3"></i>
										<div class="flex-fill">
											<h3 class="mb-1 text-body"><?php echo $total_customers; ?></h3>
											<div class="text-muted">Customers</div>
										</div>
									</div>
								</div>
							</a>
						</div>

						<div class="col-sm-6 col-xl-12 mb-3">
							<a href="listing_vendors.php" class="text-decoration-none">
								<div class="card card-body border-start border-primary border-3">
									<div class="d-flex align-items-center">
										<i class="ph-storefront ph-2x text-primary opacity-75 me-3"></i>
										<div class="flex-fill">
											<h3 class="mb-1 text-body"><?php echo $total_vendors; ?></h3>
											<div class="text-muted">Vendors</div>
										</div>
									</div>
								</div>
							</a>
						</div>
					</div>

					<div class="card">
						<div class="card-header">
							<h6 class="mb-0">
								<i class="ph-tree-structure me-2"></i>
								Chart of Accounts
							</h6>
						</div>
						<div class="list-group list-group-flush">
							<a href="listing_accounts.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-database me-3 text-primary"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Accounts</div>
									<div class="fs-sm text-muted">Manage chart of accounts</div>
								</div>
								<span class="badge bg-primary bg-opacity-20 text-primary rounded-pill"><?php echo $total_accounts; ?></span>
							</a>
							<a href="listing_banks.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-bank me-3 text-success"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Banks</div>
									<div class="fs-sm text-muted">Manage bank accounts</div>
								</div>
								<span class="badge bg-success bg-opacity-20 text-success rounded-pill"><?php echo $total_banks; ?></span>
							</a>
							<a href="listing_journals.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-note-pencil me-3 text-info"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Journal Entries</div>
									<div class="fs-sm text-muted">View all transactions</div>
								</div>
								<i class="ph-caret-right"></i>
							</a>
						</div>
					</div>

					<div class="card">
						<div class="card-header">
							<h6 class="mb-0">
								<i class="ph-chart-line me-2"></i>
								Quick Reports
							</h6>
						</div>
						<div class="card-body p-2">
							<div class="row g-2">
								<div class="col-6">
									<a href="report_profit_loss.php" class="card card-body text-center p-3 text-decoration-none border-start border-success border-3">
										<i class="ph-trend-up ph-2x text-success mb-2"></i>
										<div class="fw-semibold small">Profit & Loss</div>
									</a>
								</div>
								<div class="col-6">
									<a href="report_balance_sheet.php" class="card card-body text-center p-3 text-decoration-none border-start border-primary border-3">
										<i class="ph-scales ph-2x text-primary mb-2"></i>
										<div class="fw-semibold small">Balance Sheet</div>
									</a>
								</div>
								<div class="col-6">
									<a href="report_cash_flow.php" class="card card-body text-center p-3 text-decoration-none border-start border-info border-3">
										<i class="ph-arrows-left-right ph-2x text-info mb-2"></i>
										<div class="fw-semibold small">Cash Flow</div>
									</a>
								</div>
								<div class="col-6">
									<a href="<?php echo $ar_report_link; ?>" class="card card-body text-center p-3 text-decoration-none border-start border-warning border-3">
										<i class="ph-clock-countdown ph-2x text-warning mb-2"></i>
										<div class="fw-semibold small">AR Aging</div>
									</a>
								</div>
								<div class="col-6">
									<a href="report_ap_aging_details.php" class="card card-body text-center p-3 text-decoration-none border-start border-danger border-3">
										<i class="ph-clock ph-2x text-danger mb-2"></i>
										<div class="fw-semibold small">AP Aging</div>
									</a>
								</div>
							</div>
						</div>
					</div>

				</div>
			</div>
			<!-- /dashboard content -->

		</div>
		<!-- /content area -->

	</div>
	<!-- /inner content -->

	<?php include('admin_elements/copyright.php'); ?>

</div>
<!-- /main content -->

</div>
<!-- /page content -->
<?php include('admin_elements/admin_footer.php'); ?>