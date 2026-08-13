<?php

use App\Core\DB;
use App\Security\Roles;
include('admin_elements/admin_header.php');

$module = 'shipping_advices';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Dashboard';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();


/*
|--------------------------------------------------------------------------
| DATA AGGREGATION FOR DASHBOARD
|--------------------------------------------------------------------------
*/

// Date range for current month
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$last_month_start = date('Y-m-01', strtotime('-1 month'));
$last_month_end = date('Y-m-t', strtotime('-1 month'));

// QUOTATIONS METRICS
$total_quotations = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::QUOTATIONS . "`")->fetch_assoc()['count'];
$quotations_this_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::QUOTATIONS . "` WHERE quotation_date BETWEEN '$current_month_start' AND '$current_month_end'")->fetch_assoc()['count'];
$quotations_last_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::QUOTATIONS . "` WHERE quotation_date BETWEEN '$last_month_start' AND '$last_month_end'")->fetch_assoc()['count'];
$quotations_pending = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::QUOTATIONS . "` WHERE quotation_status='1'")->fetch_assoc()['count'];
$quotations_approved = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::QUOTATIONS . "` WHERE quotation_status='2'")->fetch_assoc()['count'];

// SALE ORDERS METRICS
$total_sale_orders = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SALE_ORDERS . "`")->fetch_assoc()['count'];
$sale_orders_this_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SALE_ORDERS . "` WHERE sale_order_date BETWEEN '$current_month_start' AND '$current_month_end'")->fetch_assoc()['count'];
$sale_orders_confirmed = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SALE_ORDERS . "` WHERE sale_order_status='3'")->fetch_assoc()['count'];
$sale_orders_pending = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SALE_ORDERS . "` WHERE sale_order_status IN ('1','2')")->fetch_assoc()['count'];

// JOBS METRICS
$total_jobs = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::JOBS . "`")->fetch_assoc()['count'];
$jobs_active = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::JOBS . "` WHERE job_status NOT IN ('5','6')")->fetch_assoc()['count'];
$jobs_this_month = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::JOBS . "` WHERE created_at BETWEEN '$current_month_start' AND '$current_month_end'")->fetch_assoc()['count'];

// SHIPPING STOCK METRICS
$total_shipping_advices = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SHIPPING_ADVICES . "`")->fetch_assoc()['count'];
$total_shipping_stocks = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::SHIPPING_STOCKS . "`")->fetch_assoc()['count'];

// HS CODES & REFERENCE DATA
$total_hscodes = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::HS_CODES . "`")->fetch_assoc()['count'];
$total_ports = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::PORTS . "`")->fetch_assoc()['count'];
$total_carriers = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::CARRIERS . "`")->fetch_assoc()['count'];
$total_containers = $mysqli->query("SELECT COUNT(*) as count FROM `" . DB::CONTAINER_TYPES . "`")->fetch_assoc()['count'];

// TOP HS CODES (most recently added)
$top_hscodes_query = "
	SELECT h.code, te.description as hscode_name 
	FROM `" . DB::HS_CODES . "` h
	LEFT JOIN `" . DB::HS_CODE_TEXTS . "` te ON h.id = te.hs_code_id AND te.lang = 'en'
	WHERE h.is_active = 1
    ORDER BY h.id DESC
    LIMIT 10
";
$top_hscodes = $mysqli->query($top_hscodes_query);

// TOP DESTINATIONS
$top_destinations_query = "
    SELECT DISTINCT p.port_name, p.port_code, c.country,
           (SELECT COUNT(*) FROM `" . DB::QUOTATIONS . "` q WHERE q.destination_port = p.id) +
           (SELECT COUNT(*) FROM `" . DB::SALE_ORDERS . "` s WHERE s.destination_port = p.id) as shipment_count
    FROM `" . DB::PORTS . "` p
    LEFT JOIN `" . DB::GEO_COUNTRIES . "` c ON p.country_id = c.id
    WHERE p.is_active = 1
    ORDER BY shipment_count DESC
    LIMIT 5
";
$top_destinations = $mysqli->query($top_destinations_query);

// REVENUE CALCULATION (from sale orders)
$revenue_query = "
    SELECT 
        SUM(grand_total) as total_revenue,
        SUM(CASE WHEN sale_order_date BETWEEN '$current_month_start' AND '$current_month_end' THEN grand_total ELSE 0 END) as current_month_revenue,
        SUM(CASE WHEN sale_order_date BETWEEN '$last_month_start' AND '$last_month_end' THEN grand_total ELSE 0 END) as last_month_revenue
    FROM `" . DB::SALE_ORDERS . "`
    WHERE sale_order_status = '3'
";
$revenue_data = $mysqli->query($revenue_query)->fetch_assoc();
$total_revenue = $revenue_data['total_revenue'] ?? 0;
$current_month_revenue = $revenue_data['current_month_revenue'] ?? 0;
$last_month_revenue = $revenue_data['last_month_revenue'] ?? 0;

// Calculate growth percentages
$quotations_growth = $quotations_last_month > 0 ? round((($quotations_this_month - $quotations_last_month) / $quotations_last_month) * 100, 1) : 0;
$revenue_growth = $last_month_revenue > 0 ? round((($current_month_revenue - $last_month_revenue) / $last_month_revenue) * 100, 1) : 0;

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<!-- Main content -->
<div class="content-wrapper">

	<!-- Page header -->
	<div class="page-header page-header-light shadow">
		<div class="page-header-content d-lg-flex border-top py-2 px-3">
			<div class="d-flex align-items-center py-3">
				<div class="ms-3">
					<h5 class="mb-0">
						<i class="ph-package me-2"></i>
						Shipping & Logistics Dashboard
					</h5>
					<span class="text-muted">Real-time operations overview</span>
				</div>
			</div>

			<div class="my-lg-auto ms-lg-auto">
				<div class="d-sm-flex align-items-center gap-2 mb-3 mb-lg-0 ms-lg-3">
					<div class="dropdown">
						<button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
							<i class="ph-plus me-1"></i> New
						</button>
						<div class="dropdown-menu">
							<a class="dropdown-item" href="add_quotation.php"><i class="ph-file-text me-2"></i>Quotation</a>
							<a class="dropdown-item" href="add_sale_order.php"><i class="ph-shopping-cart me-2"></i>Sale Order</a>
							<a class="dropdown-item" href="add_job.php"><i class="ph-briefcase me-2"></i>Job</a>
							<a class="dropdown-item" href="import_shipping_advices.php"><i class="ph-truck me-2"></i>Delivery Advice</a>
							<a class="dropdown-item" href="add_shipping_stock.php"><i class="ph-package me-2"></i>Shipping Stock</a>
						</div>
					</div>
					<span class="badge bg-success bg-opacity-10 text-success">
						<i class="ph-check-circle me-1"></i>
						<?php echo $jobs_active; ?> Active Jobs
					</span>
					<span class="badge bg-primary bg-opacity-10 text-primary">
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
			
			<!-- KPI Summary Cards -->
			<div class="row">
				<!-- Quotations Card -->
				<div class="col-sm-6 col-xl-3">
					<a href="listing_quotations.php" class="text-decoration-none">
						<div class="card card-body border-start border-primary border-3">
							<div class="d-flex align-items-center mb-2">
								<div class="flex-fill"><h6 class="mb-0 text-muted">Quotations</h6></div>
								<?php if ($quotations_growth != 0): ?>
									<span class="badge <?php echo $quotations_growth > 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $quotations_growth > 0 ? 'text-success' : 'text-danger'; ?> rounded-pill">
										<i class="ph-trend-<?php echo $quotations_growth > 0 ? 'up' : 'down'; ?>"></i>
										<?php echo abs($quotations_growth); ?>%
									</span>
								<?php endif; ?>
							</div>
							<h3 class="mb-1"><?php echo $quotations_this_month; ?> <small class="text-muted fs-sm">/ <?php echo $total_quotations; ?> total</small></h3>
							<small class="text-muted"><?php echo $quotations_approved; ?> approved, <?php echo $quotations_pending; ?> pending</small>
						</div>
					</a>
				</div>

				<!-- Sale Orders Card -->
				<div class="col-sm-6 col-xl-3">
					<a href="listing_sale_orders.php" class="text-decoration-none">
						<div class="card card-body border-start border-success border-3">
							<div class="d-flex align-items-center mb-2">
								<div class="flex-fill"><h6 class="mb-0 text-muted">Sale Orders</h6></div>
							</div>
							<h3 class="mb-1"><?php echo $sale_orders_this_month; ?> <small class="text-muted fs-sm">/ <?php echo $total_sale_orders; ?> total</small></h3>
							<small class="text-muted"><?php echo $sale_orders_confirmed; ?> confirmed, <?php echo $sale_orders_pending; ?> pending</small>
						</div>
					</a>
				</div>

				<!-- Jobs Card -->
				<div class="col-sm-6 col-xl-3">
					<a href="listing_jobs.php" class="text-decoration-none">
						<div class="card card-body border-start border-info border-3">
							<div class="d-flex align-items-center mb-2">
								<div class="flex-fill"><h6 class="mb-0 text-muted">Active Jobs</h6></div>
							</div>
							<h3 class="mb-1"><?php echo $jobs_active; ?> <small class="text-muted fs-sm">/ <?php echo $total_jobs; ?> total</small></h3>
							<small class="text-muted"><?php echo $jobs_this_month; ?> created this month</small>
						</div>
					</a>
				</div>

				<!-- Revenue Card -->
				<div class="col-sm-6 col-xl-3">
					<a href="listing_sale_orders.php" class="text-decoration-none">
						<div class="card card-body border-start border-warning border-3">
							<div class="d-flex align-items-center mb-2">
								<div class="flex-fill"><h6 class="mb-0 text-muted">Revenue</h6></div>
								<?php if ($revenue_growth != 0): ?>
									<span class="badge <?php echo $revenue_growth > 0 ? 'bg-success' : 'bg-danger'; ?> bg-opacity-10 <?php echo $revenue_growth > 0 ? 'text-success' : 'text-danger'; ?> rounded-pill">
										<i class="ph-trend-<?php echo $revenue_growth > 0 ? 'up' : 'down'; ?>"></i>
										<?php echo abs($revenue_growth); ?>%
									</span>
								<?php endif; ?>
							</div>
							<h3 class="mb-1"><?php echo number_format($current_month_revenue, 0); ?> <small class="text-muted fs-sm">/ <?php echo number_format($total_revenue, 0); ?> total</small></h3>
							<small class="text-muted">Confirmed sale orders revenue</small>
						</div>
					</a>
				</div>
			</div>

			<!-- Main Content Row -->
			<div class="row">
				<!-- Left Column: Activity & Lists -->
				<div class="col-xl-8">
					
					<!-- Recent Quotations & Sale Orders -->
					<div class="row">
						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-file-text me-2"></i>
										Recent Quotations
									</h6>
									<div class="ms-auto">
										<a href="listing_quotations.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
									</div>
								</div>
								<div class="card-body p-0">
									<div class="table-responsive">
										<table class="table table-hover">
											<tbody>
												<?php
												$result = $mysqli->query("SELECT * FROM `" . DB::QUOTATIONS . "` ORDER BY id DESC LIMIT 5");
												if ($result->num_rows > 0) {
													while ($row = $result->fetch_array()) {
														$customer_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
														$status_class = $row['quotation_status'] == '2' ? 'success' : ($row['quotation_status'] == '1' ? 'warning' : 'secondary');
														$status_text = $row['quotation_status'] == '2' ? 'Approved' : ($row['quotation_status'] == '1' ? 'Pending' : 'Draft');
												?>
													<tr>
														<td class="py-2">
															<a href="quotation_overview.php?id=<?php echo $row['id']; ?>" class="fw-semibold text-body">
																<?php echo $row['quotation_no']; ?>
															</a>
															<div class="text-muted fs-sm"><?php echo $customer_name; ?></div>
														</td>
														<td class="py-2 text-end">
															<span class="badge bg-<?php echo $status_class; ?> bg-opacity-20 text-<?php echo $status_class; ?>">
																<?php echo $status_text; ?>
															</span>
															<div class="text-muted fs-sm"><?php echo date("d M", strtotime($row['quotation_date'])); ?></div>
														</td>
													</tr>
												<?php 
													}
												} else {
													echo '<tr><td class="text-center text-muted py-3" colspan="2">No quotations found</td></tr>';
												}
												?>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-shopping-cart me-2"></i>
										Recent Sale Orders
									</h6>
									<div class="ms-auto">
										<a href="listing_sale_orders.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
									</div>
								</div>
								<div class="card-body p-0">
									<div class="table-responsive">
										<table class="table table-hover">
											<tbody>
												<?php
												$result = $mysqli->query("SELECT * FROM `" . DB::SALE_ORDERS . "` ORDER BY id DESC LIMIT 5");
												if ($result->num_rows > 0) {
													while ($row = $result->fetch_array()) {
														$customer_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
														$status_class = $row['sale_order_status'] == '3' ? 'success' : ($row['sale_order_status'] == '2' ? 'warning' : 'info');
														$status_text = $row['sale_order_status'] == '3' ? 'Confirmed' : ($row['sale_order_status'] == '2' ? 'Waiting' : 'Requested');
												?>
													<tr>
														<td class="py-2">
															<a href="sale_order_overview.php?id=<?php echo $row['id']; ?>" class="fw-semibold text-body">
																<?php echo $row['sale_order_no']; ?>
															</a>
															<div class="text-muted fs-sm"><?php echo $customer_name; ?></div>
														</td>
														<td class="py-2 text-end">
															<span class="badge bg-<?php echo $status_class; ?> bg-opacity-20 text-<?php echo $status_class; ?>">
																<?php echo $status_text; ?>
															</span>
															<div class="text-muted fs-sm"><?php echo date("d M", strtotime($row['sale_order_date'])); ?></div>
														</td>
													</tr>
												<?php 
													}
												} else {
													echo '<tr><td class="text-center text-muted py-3" colspan="2">No sale orders found</td></tr>';
												}
												?>
											</tbody>
										</table>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Active Jobs List -->
					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h6 class="mb-0">
								<i class="ph-briefcase me-2"></i>
								Active Jobs
							</h6>
							<div class="ms-auto">
								<a href="listing_jobs.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
							</div>
						</div>
						<div class="card-body p-0">
							<div class="table-responsive">
								<table class="table table-hover">
									<thead class="table-light">
										<tr>
											<th>Job ID</th>
											<th>Customer</th>
											<th>Date</th>
											<th>Status</th>
											<th class="text-end">Actions</th>
										</tr>
									</thead>
									<tbody>
										<?php
										$result = $mysqli->query("SELECT * FROM `" . DB::JOBS . "` WHERE job_status NOT IN ('5','6') ORDER BY id DESC LIMIT 8");
										if ($result->num_rows > 0) {
											while ($row = $result->fetch_array()) {
												$customer_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
												$status_name = getTableAttr('job_status', DB::JOB_STATUSES, $row['job_status']);
										?>
											<tr>
												<td class="py-2">
													<a href="view_job.php?id=<?php echo $row['id']; ?>" class="fw-semibold">
														#<?php echo $row['id']; ?>
													</a>
												</td>
												<td class="py-2"><?php echo $customer_name; ?></td>
												<td class="py-2 text-muted"><?php echo date("d M Y", strtotime($row['created_at'])); ?></td>
												<td class="py-2">
													<span class="badge bg-info bg-opacity-20 text-info">
														<?php echo $status_name; ?>
													</span>
												</td>
												<td class="py-2 text-end">
													<a href="view_job.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-light">
														<i class="ph-eye"></i>
													</a>
												</td>
											</tr>
										<?php 
											}
										} else {
											echo '<tr><td class="text-center text-muted py-3" colspan="5">No active jobs found</td></tr>';
										}
										?>
									</tbody>
								</table>
							</div>
						</div>
					</div>

					<!-- Stock IN/OUT -->
					<div class="row">
						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-arrow-down-left text-success me-2"></i>
										Stock-IN (Delivery Advices)
									</h6>
									<div class="ms-auto">
										<a href="listing_shipping_advices.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
									</div>
								</div>
								<div class="card-body">
									<?php
									$result = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICES . "` ORDER BY id DESC LIMIT 5");
									if ($result->num_rows > 0) {
										while ($rows = $result->fetch_array()) {
											$invoice_date = date("d M Y", strtotime($rows['invoice_date']));
									?>
										<div class="d-flex align-items-start mb-3 pb-3 border-bottom">
											<div class="flex-fill">
												<a href="view_shipping_advice.php?id=<?php echo $rows['id']; ?>" class="fw-semibold d-block">
													<?php echo $rows['invoice_no']; ?>
												</a>
												<small class="text-muted">AWB: <?php echo $rows['awb_no']; ?></small>
											</div>
											<div class="text-end ms-3">
												<div class="fs-sm"><?php echo $invoice_date; ?></div>
											</div>
										</div>
									<?php 
										}
									} else {
										echo '<p class="text-muted text-center">No delivery advices found</p>';
									}
									?>
								</div>
							</div>
						</div>

						<div class="col-lg-6">
							<div class="card">
								<div class="card-header d-flex align-items-center">
									<h6 class="mb-0">
										<i class="ph-arrow-up-right text-danger me-2"></i>
										Stock-OUT
									</h6>
									<div class="ms-auto">
										<a href="listing_shipping_stocks.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
									</div>
								</div>
								<div class="card-body">
									<?php
									$result = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_STOCKS . "` ORDER BY invoice_date DESC LIMIT 5");
									if ($result->num_rows > 0) {
										while ($rows = $result->fetch_array()) {
											$consignee_name = getTableAttr("consignee_name", DB::CONSIGNEES, $rows['consignee_id']);
											$destination_port_name = getTableAttr("port_name", DB::PORTS, $rows['destination_port']);
											$invoice_date = date("d M Y", strtotime($rows['invoice_date']));
									?>
										<div class="d-flex align-items-start mb-3 pb-3 border-bottom">
											<div class="flex-fill">
												<a href="view_shipping_stocks.php?id=<?php echo $rows['id']; ?>" class="fw-semibold d-block">
													<?php echo $consignee_name; ?>
												</a>
												<small class="text-muted"><?php echo $destination_port_name; ?></small>
											</div>
											<div class="text-end ms-3">
												<div class="fs-sm"><?php echo $invoice_date; ?></div>
											</div>
										</div>
									<?php 
										}
									} else {
										echo '<p class="text-muted text-center">No shipping stocks found</p>';
									}
									?>
								</div>
							</div>
						</div>
					</div>

				</div>

				<!-- Right Column: Analytics & Insights -->
				<div class="col-xl-4">
					
					<!-- Quick Stats Grid -->
					<div class="row">
						<div class="col-sm-6 col-xl-12">
							<a href="listing_shipping_advices.php" class="text-decoration-none">
								<div class="card card-body border-start border-success border-3">
									<div class="d-flex align-items-center">
										<div class="flex-fill">
											<h3 class="mb-1"><?php echo $total_shipping_advices; ?></h3>
											<div class="text-muted">Delivery Advices</div>
										</div>
									</div>
								</div>
							</a>
						</div>

						<div class="col-sm-6 col-xl-12">
							<a href="listing_hscodes.php" class="text-decoration-none">
								<div class="card card-body border-start border-primary border-3">
									<div class="d-flex align-items-center">
										<div class="flex-fill">
											<h3 class="mb-1"><?php echo $total_hscodes; ?></h3>
											<div class="text-muted">HS Codes</div>
										</div>
									</div>
								</div>
							</a>
						</div>
					</div>

					<!-- Top HS Codes -->
					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h6 class="mb-0">
								<i class="ph-barcode me-2"></i>
								Recently Added HS Codes
							</h6>
							<div class="ms-auto">
								<a href="listing_hscodes.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
							</div>
						</div>
						<div class="card-body">
							<?php
							if ($top_hscodes->num_rows > 0) {
								while ($row = $top_hscodes->fetch_array()) {
							?>
								<div class="d-flex align-items-center mb-3 pb-2 border-bottom">
									<div class="me-2">
										<i class="ph-hash text-primary"></i>
									</div>
									<div class="flex-fill">
										<div class="fw-semibold"><?php echo htmlspecialchars((string)($row['code'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
										<div class="text-muted fs-sm">
											<?php 
											$hscode_name = (string)($row['hscode_name'] ?? '');
											echo htmlspecialchars(substr($hscode_name, 0, 45), ENT_QUOTES, 'UTF-8');
											echo strlen($hscode_name) > 45 ? '...' : '';
											?>
										</div>
									</div>
								</div>
							<?php 
								}
							} else {
								echo '<p class="text-muted text-center">No HS codes found</p>';
							}
							?>
						</div>
					</div>

					<!-- Top Destinations -->
					<div class="card">
						<div class="card-header d-flex align-items-center">
							<h6 class="mb-0">
								<i class="ph-airplane-takeoff me-2"></i>
								Top Destinations
							</h6>
							<div class="ms-auto">
								<a href="listing_ports.php" class="text-body">View all <i class="ph-arrow-right ms-1"></i></a>
							</div>
						</div>
						<div class="card-body">
							<?php
							if ($top_destinations->num_rows > 0) {
								while ($row = $top_destinations->fetch_array()) {
									if ($row['shipment_count'] > 0) {
							?>
								<div class="d-flex align-items-center mb-3">
									<div class="me-2">
										<i class="ph-map-pin text-primary"></i>
									</div>
									<div class="flex-fill">
										<div class="fw-semibold"><?php echo $row['port_code']; ?> - <?php echo $row['port_name']; ?></div>
										<div class="text-muted fs-sm"><?php echo $row['country']; ?></div>
									</div>
									<div class="ms-3">
										<span class="badge bg-info rounded-pill"><?php echo $row['shipment_count']; ?></span>
									</div>
								</div>
							<?php 
									}
								}
							} else {
								echo '<p class="text-muted text-center">No destination data found</p>';
							}
							?>
						</div>
					</div>

					<!-- Reference Data Summary -->
					<div class="card">
						<div class="card-header">
							<h6 class="mb-0">
								<i class="ph-database me-2"></i>
								Reference Data
							</h6>
						</div>
						<div class="list-group list-group-flush">
							<a href="listing_ports.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-anchor me-3 text-primary"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Ports</div>
								</div>
								<span class="badge bg-primary bg-opacity-10 text-primary rounded-pill"><?php echo $total_ports; ?></span>
							</a>
							<a href="listing_carriers.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-truck me-3 text-success"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Carriers</div>
								</div>
								<span class="badge bg-success bg-opacity-10 text-success rounded-pill"><?php echo $total_carriers; ?></span>
							</a>
							<a href="listing_container_types.php" class="list-group-item list-group-item-action d-flex align-items-center">
								<i class="ph-cube me-3 text-info"></i>
								<div class="flex-fill">
									<div class="fw-semibold">Container Types</div>
								</div>
								<span class="badge bg-info bg-opacity-10 text-info rounded-pill"><?php echo $total_containers; ?></span>
							</a>
						</div>
					</div>

				</div>
			</div>
			<!-- /dashboard content -->

		</div>
		<!-- /content area -->

		<?php include('admin_elements/copyright.php'); ?>

	</div>
	<!-- /inner content -->

</div>
<!-- /main content -->

</div>
<!-- /page content -->
<?php include('admin_elements/admin_footer.php'); ?>
