<?php

use App\Security\Roles;
include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Setup';
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

if (!function_exists('setupCanSee')) {
	function setupCanSee(string $moduleSlug): bool
	{
		return has_full_access() || hasModuleAccess($moduleSlug);
	}
}

$setupSections = [
	[
		'title' => 'Platform Administration',
		'icon' => 'ph-gear-six',
		'links' => [
			['href' => 'system_settings.php', 'label' => 'System Settings', 'desc' => 'Core behavior and technical feature settings.', 'icon' => 'ph-cpu', 'visible' => has_full_access()],
			['href' => 'listing_modules.php', 'label' => 'Modules', 'desc' => 'Module catalog and permission-linked module setup.', 'icon' => 'ph-puzzle-piece', 'visible' => has_full_access()],
		],
	],
	[
		'title' => 'Master Data',
		'icon' => 'ph-database',
		'links' => [
			['href' => 'listing_inquiries.php', 'label' => 'Inquiries', 'desc' => 'Customer inquiries and follow-ups.', 'icon' => 'ph-chat-circle-dots', 'visible' => setupCanSee('inquiries')],
			['href' => 'listing_setup_groups.php', 'label' => 'Setup Groups', 'desc' => 'Reusable setup group definitions.', 'icon' => 'ph-sliders', 'visible' => setupCanSee('setup_groups')],
			['href' => 'listing_document_categories.php', 'label' => 'Document Categories', 'desc' => 'Document taxonomy and filing categories.', 'icon' => 'ph-folders', 'visible' => setupCanSee('document_categories')],
			['href' => 'listing_incoterms.php', 'label' => 'Incoterms', 'desc' => 'Trade incoterm definitions.', 'icon' => 'ph-globe-hemisphere-west', 'visible' => setupCanSee('incoterms')],
			['href' => 'listing_units.php', 'label' => 'Units of Measure', 'desc' => 'Measurement units used in pricing and inventory.', 'icon' => 'ph-ruler', 'visible' => setupCanSee('units')],
			['href' => 'listing_storage_types.php', 'label' => 'Storage Types', 'desc' => 'Warehouse storage unit types.', 'icon' => 'ph-stack', 'visible' => setupCanSee('storage_types')],
			['href' => 'listing_storage_subtypes.php', 'label' => 'Storage Subtypes', 'desc' => 'Detailed classifications of storage types.', 'icon' => 'ph-tree-structure', 'visible' => setupCanSee('storage_subtypes')],
			['href' => 'listing_container_types.php', 'label' => 'Container Types', 'desc' => 'Shipping container specifications.', 'icon' => 'ph-cube', 'visible' => setupCanSee('container_types')],
			['href' => 'listing_commodity_types.php', 'label' => 'Commodity Types', 'desc' => 'Product and materials classification.', 'icon' => 'ph-box', 'visible' => setupCanSee('commodity_types')],
			['href' => 'listing_exit_points.php', 'label' => 'Exit Points', 'desc' => 'Customs and shipping exit gate locations.', 'icon' => 'ph-door-open', 'visible' => setupCanSee('exit_points')],
			['href' => 'listing_services.php', 'label' => 'Services', 'desc' => 'Service catalog and service definitions.', 'icon' => 'ph-lightning', 'visible' => setupCanSee('services')],
			['href' => 'listing_job_statuses.php', 'label' => 'Job Statuses', 'desc' => 'Job status definitions for projects.', 'icon' => 'ph-tag', 'visible' => setupCanSee('job_statuses')],
		],
	],
	[
		'title' => 'Catalog & Classification',
		'icon' => 'ph-folder-open',
		'links' => [
			['href' => 'listing_categories.php', 'label' => 'Categories', 'desc' => 'Category taxonomy for organizing items.', 'icon' => 'ph-folder-open', 'visible' => setupCanSee('categories')],
			['href' => 'listing_subcategories.php', 'label' => 'Subcategories', 'desc' => 'Subcategory levels within main categories.', 'icon' => 'ph-folders', 'visible' => setupCanSee('subcategories')],
		],
	],
	[
		'title' => 'Communication & Risk Controls',
		'icon' => 'ph-envelope-simple',
		'links' => [
			['href' => 'listing_disposable_email_domains.php', 'label' => 'Disposable Email Domains', 'desc' => 'Block throwaway domains to reduce abuse.', 'icon' => 'ph-shield-slash', 'visible' => setupCanSee('disposable_email_domains')],
			['href' => 'listing_banned_words.php', 'label' => 'Banned Words', 'desc' => 'Filter prohibited terms across content inputs.', 'icon' => 'ph-prohibit', 'visible' => setupCanSee('banned_words')],
		],
	],
];

$visibleSectionCount = 0;
?>

<div class="content-wrapper">
	<div class="page-header page-header-light shadow carriers-page-header">
		<div class="page-header-content d-lg-flex align-items-center justify-content-between border-top carriers-page-header-content">
			<div class="d-flex align-items-center gap-2 py-2">
				<i class="ph-wrench fs-3 text-primary"></i>
				<div>
					<h5 class="mb-0">Setup Control Center</h5>
					<small class="text-muted">Core configuration and operational setup links for the current organization.</small>
				</div>
			</div>
			<div class="d-flex gap-2 py-2">
				<a href="listing_system_settings.php" class="btn btn-sm btn-primary">System Settings</a>
			</div>
		</div>
	</div>

	<div class="content-area">
		<?php if (Roles::isSuperAdmin($session_role_id)): ?>
			<div class="card mb-3 border-primary-subtle">
				<div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
					<span class="fw-semibold me-1">System Availability:</span>
					<?php
					$labels = ['crm' => 'CRM', 'hr' => 'HR', 'accounting' => 'Accounting', 'shipping' => 'Shipping'];
					foreach ($labels as $key => $label):
						$enabled = dashboardHasSystemAccess($key);
					?>
						<span class="badge <?php echo $enabled ? 'bg-success' : 'bg-secondary'; ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?>: <?php echo $enabled ? 'Enabled' : 'Disabled'; ?></span>
					<?php endforeach; ?>
					<small class="text-muted">Toggle from header menu: Availability</small>
				</div>
			</div>
		<?php endif; ?>

		<div class="row g-3">
			<?php foreach ($setupSections as $section): ?>
				<?php
				$links = array_values(array_filter($section['links'], function ($item) {
					return !empty($item['visible']);
				}));
				if (empty($links)) {
					continue;
				}
				$visibleSectionCount++;
				?>
				<div class="col-12 col-md-6 col-xxl-4">
					<div class="card h-100">
						<div class="card-header d-flex align-items-center gap-2">
							<i class="<?php echo htmlspecialchars($section['icon'], ENT_QUOTES); ?> text-primary"></i>
							<span class="fw-semibold"><?php echo htmlspecialchars($section['title'], ENT_QUOTES); ?></span>
						</div>
						<div class="list-group list-group-flush">
							<?php foreach ($links as $link): ?>
								<a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES); ?>" class="list-group-item list-group-item-action py-3">
									<div class="d-flex align-items-start gap-2">
										<i class="<?php echo htmlspecialchars($link['icon'], ENT_QUOTES); ?> mt-1 text-muted"></i>
										<div>
											<div class="fw-semibold text-body"><?php echo htmlspecialchars($link['label'], ENT_QUOTES); ?></div>
											<small class="text-muted"><?php echo htmlspecialchars($link['desc'], ENT_QUOTES); ?></small>
										</div>
									</div>
								</a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ($visibleSectionCount === 0): ?>
			<div class="alert alert-warning mt-3 mb-0">No setup modules are currently available for your role and active system availability settings.</div>
		<?php endif; ?>
	</div>

	<?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>