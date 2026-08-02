<?php
http_response_code(404);

// Log 404 error for dashboard
require_once __DIR__ . '/../config/logging.php';

if (isset($GLOBALS['frontendLogger'])) {
    $GLOBALS['frontendLogger']->log404(
        $_SERVER['REQUEST_URI'] ?? 'unknown',
        $_SERVER['HTTP_REFERER'] ?? 'direct'
    );
}

// Detect base path based on server environment
$is_local = (in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1']) || strpos($_SERVER['HTTP_HOST'], 'localhost:') === 0 || strpos($_SERVER['HTTP_HOST'], '127.0.0.1:') === 0);

if ($is_local) {
	$base_path = '/flasherpsystem/dashboard/';
} else {
    $base_path = '/dashboard/';
}

include('admin_elements/admin_header.php');
?>

<div class="content-wrapper">

	<!-- Page header -->
	<div class="page-header page-header-light shadow carriers-page-header">
		<div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content">
			<div class="d-flex">
				<div class="breadcrumb py-2">
					<a href="index.php" class="breadcrumb-item"><i class="ph-house"></i></a>
					<a href="index.php" class="breadcrumb-item">Home</a>
					<span class="breadcrumb-item active">404 - Page Not Found</span>
				</div>
			</div>
		</div>
	</div>
	<!-- /page header -->

	<div class="content-inner">
		<div class="content">

			<!-- Error page -->
			<div class="row">
				<div class="col-lg-12">
					<div class="card">
						<div class="card-body text-center">

							<div class="py-5">
								<!-- Error icon -->
								<div class="mb-4">
									<i class="ph-warning-circle text-warning" style="font-size: 96px;"></i>
								</div>

								<!-- Error message -->
								<h1 class="display-4 fw-semibold mb-3">404</h1>
								<h5 class="mb-3">Oops! Page Not Found</h5>
								<p class="text-muted mb-4">
									The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
								</p>

								<!-- Navigation options -->
								<div class="d-flex justify-content-center gap-2 mb-4">
									<a href="index.php" class="btn btn-primary">
										<i class="ph-house me-2"></i>
										Go to Dashboard
									</a>
									<a href="javascript:history.back()" class="btn btn-light">
										<i class="ph-arrow-left me-2"></i>
										Go Back
									</a>
								</div>

								<!-- Additional help -->
								<div class="text-muted">
									<p class="mb-2">If you believe this is an error, please contact your system administrator.</p>
									<p class="mb-0">
										<i class="ph-info me-1"></i>
										Error Code: 404 - Resource Not Found
									</p>
								</div>

							</div>

						</div>
					</div>
				</div>
			</div>
			<!-- /error page -->

		</div>

		<?php include('admin_elements/copyright.php'); ?>
	</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
