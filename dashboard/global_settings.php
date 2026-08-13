<?php

use App\Core\DB;
use App\Core\Session;
use App\Core\Database;
use App\Security\Roles;
use App\Security\ImageUploadHandler;
use App\Service\GlobalSettingsService;
include('admin_elements/admin_header.php');
Roles::requireAdminAccess();

$module 		= 'system_settings';
$module_caption = 'Global Settings';
$tbl_name		= $tbl_prefix . $module;

$photo_upload_path = '../uploads/' . $module . '/';

$error_message = '';
$success_message = '';

$settingsService = new GlobalSettingsService(new Database());

$logoHandler = new ImageUploadHandler($photo_upload_path, 5, ImageUploadHandler::MIME_IMAGES_COMMON);
$faviconHandler = new ImageUploadHandler($photo_upload_path, 5, ImageUploadHandler::MIME_IMAGES_WITH_ICO);
$ampLogoHandler = new ImageUploadHandler($photo_upload_path, 5, ImageUploadHandler::MIME_IMAGES_COMMON);
$loginLogoHandler = new ImageUploadHandler($photo_upload_path, 5, ImageUploadHandler::MIME_IMAGES_COMMON);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in global_settings.php', 'WARNING', __FILE__, __LINE__);
    }
}

// Handle delete requests for images
if (isset($_REQUEST['delete_photo']) && $_REQUEST['delete_photo'] == 1) {
    $logo = $settingsService->getSettingValue('logo');
    if (!empty($logo)) {
        $logoHandler->delete($logo);
        $settingsService->deleteSettingValue('logo');
        $success_message = 'Logo Deleted Successfully.';
    }
}
if (isset($_REQUEST['delete_favicon']) && $_REQUEST['delete_favicon'] == 1) {
    $favicon = $settingsService->getSettingValue('favicon');
    if (!empty($favicon)) {
        $faviconHandler->delete($favicon);
        $settingsService->deleteSettingValue('favicon');
        $success_message = 'Favicon Deleted Successfully.';
    }
}
if (isset($_REQUEST['delete_amp_logo']) && $_REQUEST['delete_amp_logo'] == 1) {
    $amp_logo = $settingsService->getSettingValue('amp_logo');
    if (!empty($amp_logo)) {
        $ampLogoHandler->delete($amp_logo);
        $settingsService->deleteSettingValue('amp_logo');
        $success_message = 'AMP Logo Deleted Successfully.';
    }
}
if (isset($_REQUEST['delete_login_logo']) && $_REQUEST['delete_login_logo'] == 1) {
    $login_logo = $settingsService->getSettingValue('login_logo');
    if (!empty($login_logo)) {
        $loginLogoHandler->delete($login_logo);
        $settingsService->deleteSettingValue('login_logo');
        $success_message = 'Login Logo Deleted Successfully.';
    }
}

$system_settings_arr = array();
$result_system_settings = $mysqli->query("SELECT setting_slug, setting_name, setting_value, hint FROM `" . DB::SYSTEM_SETTINGS . "`");
while ($row_system_settings = $result_system_settings->fetch_array()) {
    $GLOBALS['SYS_SLUG'][$row_system_settings["setting_slug"]] = $row_system_settings["setting_slug"];
    $GLOBALS['SYS_NAME'][$row_system_settings["setting_slug"]] = $row_system_settings["setting_name"];
    $GLOBALS['SYS_VALUE'][$row_system_settings["setting_slug"]] = $row_system_settings["setting_value"];
    $GLOBALS['SYS_HINT'][$row_system_settings["setting_slug"]] = $row_system_settings["hint"];
}

if ($action == "update_$module") {
    try {
        $updated_count = $settingsService->updateSettings($_POST);
        $success_message = "Settings updated successfully! ({$updated_count} settings saved)";
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'settings', 'action' => 'update_settings']);
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}



$company_name			= (empty($company_name) 	? 'Flash Logistics FZCO' : $company_name);
$phone					= (empty($phone) 			? '' : $phone);
$email					= (empty($email) 			? '' : $email);
$website				= (empty($website) 			? 'https://flashlogistics.com/' : $website);
$pobox					= (empty($pobox) 			? '' : $pobox);
$trn					= (empty($trn) 				? '' : $trn);

$bank_name				= (empty($bank_name) 		? '' : $bank_name);
$beneficiary			= (empty($beneficiary) 		? 'Flash Logistics FZCO' : $beneficiary);
$account_number			= (empty($account_number) 	? '' : $account_number);
$iban					= (empty($iban) 			? '' : $iban);

/*
|--------------------------------------------------------------------------
| UI DESIGN SETTINGS - Color Management
|--------------------------------------------------------------------------
*/





















// Handle color POST submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ui_colors'])) {
    try {
        $updated_count = $settingsService->updateColors($_POST);
        if ($updated_count > 0) {
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'settings', 'action' => 'save_ui_colors']);
        $error_message = "Error saving UI colors: " . htmlspecialchars($e->getMessage());
    }
}

// Get current color settings
$headerColors = getAdminHeaderColors();
$sidebarColors = getSidebarColors();
$loginColors = getLoginPageColors();


// ============================================================================
// UPDATE SECTION (Legacy code - archived for future reference)
// ============================================================================

/*
// Old update section - kept for reference
// if ($action == "update_$module") { ... }
*/

// ============================================================================
// EDIT SECTION (Retrieve current settings)
// ============================================================================

$software_name		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="software_name"'));
$company_name		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));
$phone				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="phone"'));
$email				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="email"'));
$website			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="website"'));
$trn				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="trn"'));

$street1			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="street1"'));
$street2			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="street2"'));
$city				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="city"'));
$pobox				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="pobox"'));
$country			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="country"'));

$social_fb			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="social_fb"'));
$social_x			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="social_x"'));
$social_insta		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="social_insta"'));
$social_gmb			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="social_gmb"'));

// New fields
$global_settings	= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="global_settings"'));
$login_captcha_threshold = (int) s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="login_captcha_threshold"'));
$login_captcha_threshold = ($login_captcha_threshold >= 1 && $login_captcha_threshold <= 10) ? $login_captcha_threshold : 3;
$amp_logo			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="amp_logo"'));
$sitemap_root		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_root"'));
$sitemap_enabled	= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_enabled"'));
$ai_sitemap_enabled = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="ai_sitemap_enabled"'));
$sitemap_companies	= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_companies"'));
$sitemap_blogs		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_blogs"'));
$sitemap_categories = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_categories"'));
$sitemap_hs_codes	= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_hs_codes"'));
$sitemap_amp		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="sitemap_amp"'));
$seo_hsts_required = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_hsts_required"'));
$seo_ai_policy_mode = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_ai_policy_mode"'));
$seo_ai_policy_mode = in_array($seo_ai_policy_mode, ['allow', 'block', 'inherit'], true) ? $seo_ai_policy_mode : 'inherit';

// SEO Settings for Public Website
$seo_meta_title				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_meta_title"'));
$seo_meta_description		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_meta_description"'));
$seo_meta_keywords			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_meta_keywords"'));
$seo_og_title				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_og_title"'));
$seo_og_description			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_og_description"'));
$seo_og_image				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_og_image"'));
$seo_og_type				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_og_type"'));
$seo_og_url					= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_og_url"'));
$seo_twitter_card			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_twitter_card"'));
$seo_twitter_site			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_twitter_site"'));
$seo_twitter_creator		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_twitter_creator"'));
$seo_google_analytics		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_google_analytics"'));
$seo_google_tag_manager		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_google_tag_manager"'));
$seo_google_site_verification = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_google_site_verification"'));
$seo_bing_verification		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_bing_verification"'));
$seo_robots_meta			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_robots_meta"'));
$seo_canonical_url			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_canonical_url"'));
$seo_schema_organization	= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="seo_schema_organization"'));

$bank_name			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="bank_name"'));
$beneficiary		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="beneficiary"'));
$account_number		= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="account_number"'));
$iban				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="iban"'));

$photo				= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"'));
$favicon			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="favicon"'));
$login_logo			= s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="login_logo"'));


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<style>
	/* Codebase Color Variables */
	:root {
		--primary-color: #0c83ff;
		--primary-light: #e3f2fd;
		--primary-dark: #0962bf;
		--info-color: #049aad;
		--secondary-color: #247297;
		--light-bg: #F9FAFB;
		--light-border: #E5E7EB;
		--gray-text: #6B7280;
		--dark-text: #1F2937;
		--white: #fff;
	}

	.settings-hero {
		background: linear-gradient(135deg, var(--primary-color) 0%, var(--info-color) 100%);
		color: white;
		padding: 2rem 0;
		margin-bottom: 1.5rem;
		border-radius: var(--border-radius-lg, 8px);
		position: relative;
		overflow: hidden;
		box-shadow: 0 2px 8px rgba(12, 131, 255, 0.15);
	}

	.settings-hero::before {
		content: '';
		position: absolute;
		top: -50%;
		right: -10%;
		width: 400px;
		height: 400px;
		background: rgba(255, 255, 255, 0.08);
		border-radius: 50%;
	}

	.settings-hero .hero-content {
		position: relative;
		z-index: 1;
	}

	.settings-hero h1 {
		font-size: 2rem;
		font-weight: 700;
		margin-bottom: 0.5rem;
		display: flex;
		align-items: center;
		gap: 0.75rem;
	}

	.settings-hero p {
		font-size: 0.95rem;
		opacity: 0.95;
		margin: 0;
	}

	.settings-tabs {
		border-bottom: 2px solid var(--light-border);
		margin-bottom: 1.5rem;
		flex-wrap: wrap;
	}

	.settings-tabs .nav-link {
		color: var(--gray-text);
		border: none;
		border-bottom: 3px solid transparent;
		font-weight: 500;
		padding: 0.875rem 1rem 0.875rem 0;
		margin-right: 1.5rem;
		transition: all var(--transition-base-timer, 0.15s) ease;
		display: flex;
		align-items: center;
		gap: 0.5rem;
	}

	.settings-tabs .nav-link:hover {
		color: var(--primary-color);
		border-bottom-color: var(--primary-color);
	}

	.settings-tabs .nav-link.active {
		color: var(--primary-color);
		border-bottom-color: var(--primary-color);
	}

	.settings-card {
		background: var(--white);
		border: 1px solid var(--light-border);
		border-radius: var(--border-radius-lg, 8px);
		margin-bottom: 1.5rem;
		transition: all var(--transition-base-timer, 0.15s) ease;
		overflow: hidden;
		box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
	}

	.settings-card:hover {
		box-shadow: 0 4px 12px rgba(12, 131, 255, 0.12);
		border-color: var(--primary-color);
	}

	.settings-card-header {
		background: linear-gradient(135deg, var(--light-bg) 0%, #F3F4F6 100%);
		padding: 1.25rem;
		border-bottom: 1px solid var(--light-border);
		display: flex;
		align-items: center;
		gap: 1rem;
	}

	.settings-card-icon {
		width: 40px;
		height: 40px;
		background: var(--primary-color);
		color: white;
		border-radius: 6px;
		display: flex;
		align-items: center;
		justify-content: center;
		font-size: 1.25rem;
	}

	.settings-card h3 {
		font-size: 1.15rem;
		font-weight: 600;
		margin: 0;
		color: var(--dark-text);
	}

	.settings-card-body {
		padding: 1.25rem;
	}

	.form-section {
		margin-bottom: 1.25rem;
	}

	.form-section:last-child {
		margin-bottom: 0;
	}

	.form-label {
		font-weight: 500;
		color: var(--dark-text);
		margin-bottom: 0.5rem;
		font-size: 0.9rem;
	}

	.form-control, .form-select {
		border: 1px solid var(--light-border);
		border-radius: var(--border-radius, 4px);
		padding: 0.625rem 0.875rem;
		font-size: 0.875rem;
		transition: all var(--transition-base-timer, 0.15s) ease;
		background-color: var(--white);
	}

	.form-control:focus, .form-select:focus {
		border-color: var(--primary-color);
		box-shadow: 0 0 0 3px rgba(12, 131, 255, 0.1);
		outline: none;
	}

	.form-text {
		font-size: 0.8rem;
		color: var(--gray-text);
		margin-top: 0.4rem;
	}

	.image-upload-preview {
		background: var(--light-bg);
		border: 2px dashed var(--light-border);
		border-radius: var(--border-radius-lg, 8px);
		padding: 2rem;
		text-align: center;
		transition: all var(--transition-base-timer, 0.15s) ease;
		margin-top: 1rem;
	}

	.image-upload-preview.has-image {
		background: var(--light-bg);
		border: 1px solid var(--light-border);
		padding: 1rem;
	}

	.image-upload-preview:hover {
		border-color: var(--primary-color);
		background: var(--primary-light);
	}

	.image-preview-wrapper {
		position: relative;
		display: inline-block;
	}

	.image-preview-wrapper img {
		max-width: 100%;
		height: auto;
		border-radius: 6px;
		box-shadow: var(--box-shadow-sm, 0 1px 2px rgba(0, 0, 0, 0.1));
	}

	.delete-image-btn {
		position: absolute;
		top: -10px;
		right: -10px;
		background: #EF4444;
		color: white;
		border: none;
		border-radius: 50%;
		width: 32px;
		height: 32px;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: pointer;
		transition: all var(--transition-base-timer, 0.15s) ease;
		font-size: 1rem;
	}

	.delete-image-btn:hover {
		background: #DC2626;
		transform: scale(1.1);
	}

	.btn-modern {
		border: none;
		border-radius: var(--border-radius, 4px);
		padding: 0.625rem 1.25rem;
		font-weight: 500;
		transition: all var(--transition-base-timer, 0.15s) ease;
		display: inline-flex;
		align-items: center;
		gap: 0.5rem;
		font-size: 0.875rem;
	}

	.btn-primary-modern {
		background: var(--primary-color);
		color: white;
	}

	.btn-primary-modern:hover {
		background: var(--primary-dark);
		transform: translateY(-1px);
		box-shadow: 0 4px 12px rgba(12, 131, 255, 0.3);
	}

	.btn-secondary-modern {
		background: #F3F4F6;
		color: var(--dark-text);
		border: 1px solid var(--light-border);
	}

	.btn-secondary-modern:hover {
		background: #E5E7EB;
		transform: translateY(-1px);
	}

	.btn-danger-modern {
		background: #EF4444;
		color: white;
	}

	.btn-danger-modern:hover {
		background: #DC2626;
	}

	.settings-group {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 1.25rem;
	}

	@media (max-width: 768px) {
		.settings-group {
			grid-template-columns: 1fr;
		}
		.settings-hero h1 {
			font-size: 1.5rem;
		}
		.settings-tabs .nav-link {
			margin-right: 1rem;
		}
	}

	.form-divider {
		border-top: 1px solid var(--light-border);
		margin: 1.25rem 0;
		padding-top: 1.25rem;
	}

	.success-banner, .error-banner {
		border-radius: var(--border-radius-lg, 8px);
		padding: 1rem 1.25rem;
		margin-bottom: 1.25rem;
		display: flex;
		align-items: center;
		gap: 1rem;
		animation: slideIn 0.3s ease;
		font-size: 0.9rem;
	}

	.success-banner {
		background: #D1FAE5;
		color: #065F46;
		border: 1px solid #A7F3D0;
	}

	.error-banner {
		background: #FEE2E2;
		color: #7F1D1D;
		border: 1px solid #FECACA;
	}

	@keyframes slideIn {
		from {
			opacity: 0;
			transform: translateY(-10px);
		}
		to {
			opacity: 1;
			transform: translateY(0);
		}
	}

	.section-description {
		color: var(--gray-text);
		font-size: 0.85rem;
		margin-top: 0.5rem;
	}
</style>

<div class="content-wrapper">

	<form method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="global_settings.php" enctype="multipart/form-data" novalidate>
		<input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
		<?php echo csrf_field(); ?>

		<div class="content-inner">
			<div class="content">

				<!-- Hero Section -->
				<div class="settings-hero">
					<div class="hero-content">
						<div class="row align-items-center p-2">
							<div class="col-md-8">
								<h1><i class="ph-gear"></i> Global Settings</h1>
								<p>Manage your application settings, branding, and configuration</p>
							</div>
							<div class="col-md-4 text-md-end">
								<button type="submit" class="btn btn-modern btn-primary-modern me-2">
									<i class="ph-floppy-disk"></i> Save Changes
								</button>
							</div>
						</div>
					</div>
				</div>

				<!-- Status Messages -->
				<?php if (!empty($success_message)) { ?>
					<div class="success-banner">
						<i class="ph-check-circle fs-5"></i>
						<span><?php echo $success_message; ?></span>
					</div>
				<?php } ?>
				<?php if (!empty($error_message)) { ?>
					<div class="error-banner">
						<i class="ph-warning-circle fs-5"></i>
						<span><?php echo $error_message; ?></span>
					</div>
				<?php } ?>

				<!-- Tabs Navigation -->
				<ul class="nav settings-tabs" role="tablist">
					<li class="nav-item" role="presentation">
						<button class="nav-link active" id="contact-tab" data-bs-toggle="tab" data-bs-target="#contact" role="tab">
							<i class="ph-address-book"></i> Contact
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="address-tab" data-bs-toggle="tab" data-bs-target="#address" role="tab">
							<i class="ph-map-pin"></i> Location
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="social-tab" data-bs-toggle="tab" data-bs-target="#social" role="tab">
							<i class="ph-share-network"></i> Social
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" role="tab">
							<i class="ph-bank"></i> Banking
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="branding-tab" data-bs-toggle="tab" data-bs-target="#branding" role="tab">
							<i class="ph-palette"></i> Branding
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="seo-tab" data-bs-toggle="tab" data-bs-target="#seo" role="tab">
							<i class="ph-magnifying-glass"></i> SEO
						</button>
					</li>
					<li class="nav-item" role="presentation">
						<button class="nav-link" id="ui-design-tab" data-bs-toggle="tab" data-bs-target="#ui-design" role="tab">
							<i class="ph-palette"></i> UI Design
						</button>
					</li>
				</ul>

				<!-- Tab Content -->
				<div class="tab-content">
					<!-- Branding Tab -->
					<div class="tab-pane fade" id="branding" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-palette"></i>
								</div>
								<div>
									<h3>System Branding</h3>
									<p class="section-description mb-0">Customize your application branding and logos</p>
								</div>
							</div>
							<div class="settings-card-body">
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-text"></i> Software Name</label>
										<input type="text" class="form-control" name="software_name" id="software_name" value="<?php echo $software_name; ?>" placeholder="e.g., FLASH ERP SYSTEM">
										<div class="form-text">Name displayed throughout the application</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-image"></i> Logo Upload</label>
										<input type="file" name="photo" id="photo" class="form-control" accept="image/*">
										<div class="form-text">360x360px â€¢ WebP, JPG, PNG, GIF â€¢ Max 5MB</div>
										<?php if (!empty($photo) && file_exists('../uploads/system_settings/thumbs/' . $photo)) { ?>
											<div class="image-upload-preview has-image mt-3">
												<div class="image-preview-wrapper">
													<img src="<?php echo $photo_upload_path . '/thumbs/' . $photo; ?>" alt="Logo" width="100">
													<a href="global_settings.php?delete_photo=1" class="delete-image-btn" title="Delete logo">
														<i class="ph-x"></i>
													</a>
												</div>
											</div>
										<?php } else { ?>
											<div class="image-upload-preview">
												<i class="ph-image-plus fs-4" style="color: #adb5bd;"></i>
												<p class="text-muted mt-2 mb-0">Drop logo here or click to upload</p>
											</div>
										<?php } ?>
									</div>
								</div>

								<div class="form-divider"></div>

								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-image"></i> Favicon</label>
										<input type="file" name="favicon" id="favicon" class="form-control" accept="image/*">
										<div class="form-text">Browser tab icon â€¢ 16x16 or 32x32px â€¢ ICO/PNG</div>
										<?php if (!empty($favicon) && file_exists('../uploads/system_settings/thumbs/' . $favicon)) { ?>
											<div class="image-upload-preview has-image mt-3">
												<div class="image-preview-wrapper">
													<img src="<?php echo $photo_upload_path . '/thumbs/' . $favicon; ?>" alt="Favicon" width="50">
													<a href="global_settings.php?delete_favicon=1" class="delete-image-btn" title="Delete favicon">
														<i class="ph-x"></i>
													</a>
												</div>
											</div>
										<?php } else { ?>
											<div class="image-upload-preview">
												<i class="ph-image-plus fs-4" style="color: #adb5bd;"></i>
												<p class="text-muted mt-2 mb-0">Drop favicon here or click to upload</p>
											</div>
										<?php } ?>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-phone-light"></i> AMP Logo</label>
										<input type="file" name="amp_logo" id="amp_logo" class="form-control" accept="image/*">
										<div class="form-text">Mobile AMP version â€¢ Optimized layout</div>
										<?php if (!empty($amp_logo) && file_exists('../uploads/system_settings/thumbs/' . $amp_logo)) { ?>
											<div class="image-upload-preview has-image mt-3">
												<div class="image-preview-wrapper">
													<img src="<?php echo $photo_upload_path . '/thumbs/' . $amp_logo; ?>" alt="AMP Logo" width="100">
													<a href="global_settings.php?delete_amp_logo=1" class="delete-image-btn" title="Delete AMP logo">
														<i class="ph-x"></i>
													</a>
												</div>
											</div>
										<?php } else { ?>
											<div class="image-upload-preview">
												<i class="ph-image-plus fs-4" style="color: #adb5bd;"></i>
												<p class="text-muted mt-2 mb-0">Drop AMP logo here or click to upload</p>
											</div>
										<?php } ?>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-sign-in"></i> Login Page Logo</label>
										<input type="file" name="login_logo" id="login_logo" class="form-control" accept="image/*">
										<div class="form-text">Dashboard login page logo â€¢ 360x360px â€¢ WebP, JPG, PNG, GIF â€¢ Max 5MB</div>
										<?php if (!empty($login_logo) && file_exists('../uploads/system_settings/thumbs/' . $login_logo)) { ?>
											<div class="image-upload-preview has-image mt-3">
												<div class="image-preview-wrapper">
													<img src="<?php echo $photo_upload_path . '/thumbs/' . $login_logo; ?>" alt="Login Logo" width="100">
													<a href="global_settings.php?delete_login_logo=1" class="delete-image-btn" title="Delete login logo">
														<i class="ph-x"></i>
													</a>
												</div>
											</div>
										<?php } else { ?>
											<div class="image-upload-preview">
												<i class="ph-image-plus fs-4" style="color: #adb5bd;"></i>
												<p class="text-muted mt-2 mb-0">Drop login logo here or click to upload</p>
											</div>
										<?php } ?>
									</div>
								</div>

								<div class="form-divider"></div>

								<div class="form-section">
									<label class="form-label"><i class="ph-code"></i> Global Settings (JSON)</label>
									<textarea class="form-control" name="global_settings" id="global_settings" rows="4" placeholder='{"setting": "value"}'><?php echo $global_settings; ?></textarea>
									<div class="form-text">Advanced JSON configuration for application-wide settings</div>
								</div>

								<div class="form-section">
									<label class="form-label"><i class="ph-shield-check"></i> Login CAPTCHA Threshold</label>
									<input type="number" class="form-control" name="login_captcha_threshold" id="login_captcha_threshold" min="1" max="10" value="<?php echo (int)$login_captcha_threshold; ?>">
									<div class="form-text">Show CAPTCHA on public login after this many failed attempts from the same IP (1-10).</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Contact Tab -->
					<div class="tab-pane fade show active" id="contact" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-address-book"></i>
								</div>
								<div>
									<h3>Contact Information</h3>
									<p class="section-description mb-0">Primary business contact details</p>
								</div>
							</div>
							<div class="settings-card-body">
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-buildings"></i> <?php echo $GLOBALS['SYS_NAME']['company_name']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['company_name']; ?>" value="<?php echo $company_name; ?>" placeholder="Your company name">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['company_name']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-phone"></i> <?php echo $GLOBALS['SYS_NAME']['phone']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['phone']; ?>" value="<?php echo $phone; ?>" placeholder="+971 XX XXX XXXX">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['phone']; ?></div>
									</div>
								</div>

								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-envelope"></i> <?php echo $GLOBALS['SYS_NAME']['email']; ?></label>
										<input type="email" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['email']; ?>" value="<?php echo $email; ?>" placeholder="contact@example.com">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['email']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-globe"></i> <?php echo $GLOBALS['SYS_NAME']['website']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['website']; ?>" value="<?php echo $website; ?>" placeholder="https://example.com">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['website']; ?></div>
									</div>
								</div>

								<div class="form-section">
									<label class="form-label"><i class="ph-identification-card"></i> TRN#</label>
									<input type="text" class="form-control" name="trn" id="trn" value="<?php echo $trn; ?>" placeholder="Trade Registration Number">
									<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['trn']; ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Address Tab -->
					<div class="tab-pane fade" id="address" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-map-pin"></i>
								</div>
								<div>
									<h3>Location Details</h3>
									<p class="section-description mb-0">Physical address information</p>
								</div>
							</div>
							<div class="settings-card-body">
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-road"></i> <?php echo $GLOBALS['SYS_NAME']['street1']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['street1']; ?>" value="<?php echo $street1; ?>" placeholder="Street address line 1">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['street1']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-road"></i> <?php echo $GLOBALS['SYS_NAME']['street2']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['street2']; ?>" value="<?php echo $street2; ?>" placeholder="Street address line 2 (optional)">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['street2']; ?></div>
									</div>
								</div>

								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-map"></i> <?php echo $GLOBALS['SYS_NAME']['city']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['city']; ?>" value="<?php echo $city; ?>" placeholder="City">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['city']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-envelope"></i> <?php echo $GLOBALS['SYS_NAME']['pobox']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['pobox']; ?>" value="<?php echo $pobox; ?>" placeholder="P.O. Box">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['pobox']; ?></div>
									</div>
								</div>

								<div class="form-section">
									<label class="form-label"><i class="ph-globe"></i> <?php echo $GLOBALS['SYS_NAME']['country']; ?></label>
									<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['country']; ?>" value="<?php echo $country; ?>" placeholder="United Arab Emirates">
									<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['country']; ?></div>
								</div>
							</div>
						</div>
					</div>

					<!-- Social Media Tab -->
					<div class="tab-pane fade" id="social" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-share-network"></i>
								</div>
								<div>
									<h3>Social Media Links</h3>
									<p class="section-description mb-0">Connect your social media profiles</p>
								</div>
							</div>
							<div class="settings-card-body">
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-facebook-logo"></i> <?php echo $GLOBALS['SYS_NAME']['social_fb']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['social_fb']; ?>" value="<?php echo $social_fb; ?>" placeholder="https://facebook.com/yourpage">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['social_fb']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-x-logo"></i> <?php echo $GLOBALS['SYS_NAME']['social_x']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['social_x']; ?>" value="<?php echo $social_x; ?>" placeholder="https://x.com/yourpage">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['social_x']; ?></div>
									</div>
								</div>

								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-instagram-logo"></i> <?php echo $GLOBALS['SYS_NAME']['social_insta']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['social_insta']; ?>" value="<?php echo $social_insta; ?>" placeholder="https://instagram.com/yourpage">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['social_insta']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-map"></i> <?php echo $GLOBALS['SYS_NAME']['social_gmb']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['social_gmb']; ?>" value="<?php echo $social_gmb; ?>" placeholder="Google Business Profile URL">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['social_gmb']; ?></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Banking Tab -->
					<div class="tab-pane fade" id="banking" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-bank"></i>
								</div>
								<div>
									<h3>Banking Details</h3>
									<p class="section-description mb-0">Company bank account information</p>
								</div>
							</div>
							<div class="settings-card-body">
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-bank"></i> <?php echo $GLOBALS['SYS_NAME']['bank_name']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['bank_name']; ?>" value="<?php echo $bank_name; ?>" placeholder="Bank name">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['bank_name']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-user"></i> <?php echo $GLOBALS['SYS_NAME']['beneficiary']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['beneficiary']; ?>" value="<?php echo $beneficiary; ?>" placeholder="Account beneficiary name">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['beneficiary']; ?></div>
									</div>
								</div>

								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-hash"></i> <?php echo $GLOBALS['SYS_NAME']['account_number']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['account_number']; ?>" value="<?php echo $account_number; ?>" placeholder="Account number">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['account_number']; ?></div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-identification-card"></i> <?php echo $GLOBALS['SYS_NAME']['iban']; ?></label>
										<input type="text" class="form-control" name="<?php echo $GLOBALS['SYS_SLUG']['iban']; ?>" value="<?php echo $iban; ?>" placeholder="IBAN">
										<div class="form-text"><?php echo $GLOBALS['SYS_HINT']['iban']; ?></div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- SEO Tab -->
					<div class="tab-pane fade" id="seo" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-magnifying-glass"></i>
								</div>
								<div>
									<h3>SEO Settings for Public Website</h3>
									<p class="section-description mb-0">Comprehensive search engine optimization settings</p>
								</div>
							</div>
							<div class="settings-card-body">
								
								<!-- Basic Meta Tags -->
								<h4 class="settings-group-title"><i class="ph-tag"></i> Basic Meta Tags</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-text-t"></i> Meta Title</label>
										<input type="text" class="form-control" name="seo_meta_title" value="<?php echo $seo_meta_title; ?>" placeholder="Flash Logistics FZCO - Logistics & Supply Chain">
										<div class="form-text">Default page title for SEO (50-60 characters recommended)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-article"></i> Meta Description</label>
										<textarea class="form-control" name="seo_meta_description" rows="3" placeholder="Flash Logistics FZCO - Logistics, freight forwarding & supply chain solutions"><?php echo $seo_meta_description; ?></textarea>
										<div class="form-text">Brief description for search results (150-160 characters recommended)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-key"></i> Meta Keywords</label>
										<input type="text" class="form-control" name="seo_meta_keywords" value="<?php echo $seo_meta_keywords; ?>" placeholder="logistics, freight forwarding, supply chain, UAE logistics">
										<div class="form-text">Comma-separated keywords (optional, less important for modern SEO)</div>
									</div>
								</div>

								<!-- Open Graph Tags -->
								<h4 class="settings-group-title"><i class="ph-share-network"></i> Open Graph (Facebook/LinkedIn)</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-text-t"></i> OG Title</label>
										<input type="text" class="form-control" name="seo_og_title" value="<?php echo $seo_og_title; ?>" placeholder="Flash Logistics FZCO - Logistics & Supply Chain">
										<div class="form-text">Title when shared on Facebook/LinkedIn (leave empty to use Meta Title)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-article"></i> OG Description</label>
										<textarea class="form-control" name="seo_og_description" rows="2" placeholder="Flash Logistics FZCO provides logistics, freight forwarding & supply chain solutions"><?php echo $seo_og_description; ?></textarea>
										<div class="form-text">Description for social media shares (leave empty to use Meta Description)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-image"></i> OG Image URL</label>
										<input type="text" class="form-control" name="seo_og_image" value="<?php echo $seo_og_image; ?>" placeholder="https://yoursite.com/images/og-image.jpg">
										<div class="form-text">Full URL to default sharing image (1200x630px recommended)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-file"></i> OG Type</label>
										<input type="text" class="form-control" name="seo_og_type" value="<?php echo $seo_og_type; ?>" placeholder="website">
										<div class="form-text">Usually "website" for most pages</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-link"></i> OG URL</label>
										<input type="text" class="form-control" name="seo_og_url" value="<?php echo $seo_og_url; ?>" placeholder="https://yoursite.com">
										<div class="form-text">Canonical URL for your website</div>
									</div>
								</div>

								<!-- Twitter Card Tags -->
								<h4 class="settings-group-title"><i class="ph-twitter-logo"></i> Twitter Cards</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-card"></i> Twitter Card Type</label>
										<select class="form-select" name="seo_twitter_card">
											<option value="summary" <?php echo ($seo_twitter_card == 'summary') ? 'selected' : ''; ?>>Summary</option>
											<option value="summary_large_image" <?php echo ($seo_twitter_card == 'summary_large_image') ? 'selected' : ''; ?>>Summary Large Image</option>
											<option value="app" <?php echo ($seo_twitter_card == 'app') ? 'selected' : ''; ?>>App</option>
											<option value="player" <?php echo ($seo_twitter_card == 'player') ? 'selected' : ''; ?>>Player</option>
										</select>
										<div class="form-text">Type of Twitter card to display (summary_large_image recommended)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-at"></i> Twitter Site Handle</label>
										<input type="text" class="form-control" name="seo_twitter_site" value="<?php echo $seo_twitter_site; ?>" placeholder="@yoursite">
										<div class="form-text">Your website's Twitter username (include @)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-user"></i> Twitter Creator Handle</label>
										<input type="text" class="form-control" name="seo_twitter_creator" value="<?php echo $seo_twitter_creator; ?>" placeholder="@creator">
										<div class="form-text">Content creator's Twitter username (optional)</div>
									</div>
								</div>

								<!-- Analytics & Tracking -->
								<h4 class="settings-group-title"><i class="ph-chart-line"></i> Analytics & Tracking</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-google-logo"></i> Google Analytics ID</label>
										<input type="text" class="form-control" name="seo_google_analytics" value="<?php echo $seo_google_analytics; ?>" placeholder="G-XXXXXXXXXX or UA-XXXXXXXXX-X">
										<div class="form-text">Your Google Analytics tracking ID</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-tag"></i> Google Tag Manager ID</label>
										<input type="text" class="form-control" name="seo_google_tag_manager" value="<?php echo $seo_google_tag_manager; ?>" placeholder="GTM-XXXXXXX">
										<div class="form-text">Your Google Tag Manager container ID</div>
									</div>
								</div>

								<!-- Verification Codes -->
								<h4 class="settings-group-title"><i class="ph-shield-check"></i> Search Console Verification</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-google-logo"></i> Google Site Verification</label>
										<input type="text" class="form-control" name="seo_google_site_verification" value="<?php echo $seo_google_site_verification; ?>" placeholder="aBcDeFgHiJkLmNoPqRsTuVwXyZ">
										<div class="form-text">Google Search Console verification code (content value only)</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-microsoft-logo"></i> Bing Webmaster Verification</label>
										<input type="text" class="form-control" name="seo_bing_verification" value="<?php echo $seo_bing_verification; ?>" placeholder="123456789ABCDEF">
										<div class="form-text">Bing Webmaster Tools verification code (content value only)</div>
									</div>
								</div>

								<!-- Advanced SEO -->
								<h4 class="settings-group-title"><i class="ph-gear"></i> Advanced SEO Settings</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-robot"></i> Robots Meta Tag</label>
										<input type="text" class="form-control" name="seo_robots_meta" value="<?php echo $seo_robots_meta; ?>" placeholder="index, follow">
										<div class="form-text">Default robots directive (e.g., "index, follow" or "noindex, nofollow")</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-link"></i> Canonical URL</label>
										<input type="text" class="form-control" name="seo_canonical_url" value="<?php echo $seo_canonical_url; ?>" placeholder="https://yoursite.com">
										<div class="form-text">Preferred domain for canonical URLs</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-code"></i> Schema.org Organization JSON</label>
										<textarea class="form-control" name="seo_schema_organization" rows="8" placeholder='{"@context":"https://schema.org","@type":"Organization","name":"Your Company"...}'><?php echo $seo_schema_organization; ?></textarea>
										<div class="form-text">JSON-LD structured data for your organization (optional, advanced)</div>
									</div>
								</div>

								<!-- Sitemap Settings (Existing) -->
								<h4 class="settings-group-title"><i class="ph-sitemap"></i> Sitemap Configuration</h4>
								<div class="settings-group">
									<div class="form-section">
										<label class="form-label"><i class="ph-link"></i> Sitemap Root URL</label>
										<input type="text" class="form-control" name="sitemap_root" value="<?php echo $sitemap_root; ?>" placeholder="https://yoursite.com">
										<div class="form-text">Base URL for sitemap generation</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-toggle-right"></i> Sitemap Master Toggles</label>
										<div class="form-check form-switch" style="padding-top: 0.3rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_enabled" value="1" <?php echo ($sitemap_enabled == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Enable Main Sitemap</label>
										</div>
										<div class="form-check form-switch" style="padding-top: 0.4rem;">
											<input type="checkbox" class="form-check-input" name="ai_sitemap_enabled" value="1" <?php echo ($ai_sitemap_enabled == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Enable AI Sitemap</label>
										</div>
										<div class="form-text">Master control for public and AI XML sitemap endpoints</div>
									</div>
								</div>

								<div class="settings-group mt-3">
									<div class="form-section">
										<label class="form-label"><i class="ph-list-checks"></i> Sitemap Content Inclusion</label>
										<div class="form-check form-switch" style="padding-top: 0.3rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_companies" value="1" <?php echo ($sitemap_companies == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Include Companies</label>
										</div>
										<div class="form-check form-switch" style="padding-top: 0.35rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_blogs" value="1" <?php echo ($sitemap_blogs == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Include Blogs</label>
										</div>
										<div class="form-check form-switch" style="padding-top: 0.35rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_categories" value="1" <?php echo ($sitemap_categories == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Include Categories</label>
										</div>
										<div class="form-check form-switch" style="padding-top: 0.35rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_hs_codes" value="1" <?php echo ($sitemap_hs_codes == 1 || $sitemap_hs_codes === '') ? 'checked' : ''; ?>>
											<label class="form-check-label">Include HS Codes Sitemap in Index</label>
										</div>
										<div class="form-check form-switch" style="padding-top: 0.35rem;">
											<input type="checkbox" class="form-check-input" name="sitemap_amp" value="1" <?php echo ($sitemap_amp == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Enable AMP Sitemap (when handler exists)</label>
										</div>
										<div class="form-text">These toggles drive `dashboard/sitemaps.php` and `dashboard/seo_health_check.php` checks.</div>
									</div>

									<div class="form-section">
										<label class="form-label"><i class="ph-robot"></i> AI Crawlers Policy Mode</label>
										<select class="form-select" name="seo_ai_policy_mode">
											<option value="allow" <?php echo ($seo_ai_policy_mode === 'allow') ? 'selected' : ''; ?>>Allow (recommended for AI discoverability)</option>
											<option value="inherit" <?php echo ($seo_ai_policy_mode === 'inherit') ? 'selected' : ''; ?>>Inherit from User-agent: *</option>
											<option value="block" <?php echo ($seo_ai_policy_mode === 'block') ? 'selected' : ''; ?>>Block AI training crawlers</option>
										</select>
										<div class="form-check form-switch" style="padding-top: 0.7rem;">
											<input type="checkbox" class="form-check-input" name="seo_hsts_required" value="1" <?php echo ($seo_hsts_required == 1) ? 'checked' : ''; ?>>
											<label class="form-check-label">Require HSTS in production HTTPS</label>
										</div>
										<div class="form-text">Used by SEO health diagnostics to evaluate AI crawler policy and HSTS severity.</div>
									</div>
								</div>

								<div class="alert alert-info mt-3 mb-0">
									<i class="ph-link me-2"></i>
									<strong>Connected Dashboards:</strong>
									<a href="system_settings.php" class="ms-2">System Settings</a>
									<span class="mx-2">|</span>
									<a href="sitemaps.php" class="ms-2">Sitemap Status</a>
									<span class="mx-2">|</span>
									<a href="seo_health_check.php">SEO Health Check</a>
								</div>

							</div>
						</div>
					</div>

					<!-- UI Design Tab -->
					<div class="tab-pane fade" id="ui-design" role="tabpanel">
						<div class="settings-card">
							<div class="settings-card-header">
								<div class="settings-card-icon">
									<i class="ph-palette"></i>
								</div>
								<div>
									<h3>UI Color Settings</h3>
									<p class="section-description mb-0">Customize colors for admin interface, buttons, and alerts</p>
								</div>
							</div>
							<div class="settings-card-body">
								<input type="hidden" name="save_ui_colors" value="1">
								
								<!-- Admin Header Colors -->
								<div class="settings-group">
									<h5 style="margin-bottom: 15px;"><i class="ph-desktop"></i> Admin Header</h5>
									<!-- Professional Color Palettes -->
									<div class="mb-3">
										<label class="form-label">Professional Palettes:</label>
										<div class="d-flex flex-wrap gap-2 align-items-center">
											<!-- System Default Reset Palette -->
											<div class="palette-swatch" id="header-reset-default" data-bg="#0f172a" data-text="#ffffff" data-accent="#38bdf8" style="background: linear-gradient(90deg,#0f172a 60%,#38bdf8 100%); border:2px dashed #38bdf8; cursor:pointer; width:64px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative; gap:4px;" title="Reset to System Default">
												<i class="ph-arrow-counter-clockwise" style="font-size:13px; color:#38bdf8;"></i>
												<span style="font-size:9px; color:#38bdf8; font-weight:700; letter-spacing:.3px;">Default</span>
											</div>
											<!-- Current/Primary Palette (saved DB colors) -->
											<div class="palette-swatch palette-default" data-bg="<?php echo htmlspecialchars($headerColors['background']); ?>" data-text="<?php echo htmlspecialchars($headerColors['text']); ?>" data-accent="<?php echo htmlspecialchars($headerColors['accent']); ?>" style="background: linear-gradient(90deg,<?php echo htmlspecialchars($headerColors['background']); ?> 60%,<?php echo htmlspecialchars($headerColors['accent']); ?> 100%); border:2px solid <?php echo htmlspecialchars($headerColors['background']); ?>; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Current Saved Colors">
												<span style="width:16px; height:16px; background:<?php echo htmlspecialchars($headerColors['text']); ?>; border-radius:50%; border:2px solid <?php echo htmlspecialchars($headerColors['accent']); ?>; display:inline-block;"></span>
												<span style="position:absolute; bottom:2px; right:4px; font-size:9px; color:#aaa; font-weight:600;">Saved</span>
											</div>
											<div class="palette-swatch" data-bg="#0C83FF" data-text="#FFFFFF" data-accent="#FFD600" style="background: linear-gradient(90deg,#0C83FF 60%,#FFD600 100%); border:2px solid #0C83FF; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Blue/Gold">
												<span style="width:16px; height:16px; background:#FFFFFF; border-radius:50%; border:2px solid #FFD600; display:inline-block;"></span>
											</div>
											<div class="palette-swatch" data-bg="#22223B" data-text="#F2E9E4" data-accent="#9A8C98" style="background: linear-gradient(90deg,#22223B 60%,#9A8C98 100%); border:2px solid #22223B; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Charcoal/Lavender">
												<span style="width:16px; height:16px; background:#F2E9E4; border-radius:50%; border:2px solid #9A8C98; display:inline-block;"></span>
											</div>
											<div class="palette-swatch" data-bg="#1B263B" data-text="#E0E1DD" data-accent="#415A77" style="background: linear-gradient(90deg,#1B263B 60%,#415A77 100%); border:2px solid #1B263B; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Navy/Steel">
												<span style="width:16px; height:16px; background:#E0E1DD; border-radius:50%; border:2px solid #415A77; display:inline-block;"></span>
											</div>
											<div class="palette-swatch" data-bg="#006D77" data-text="#EDF6F9" data-accent="#83C5BE" style="background: linear-gradient(90deg,#006D77 60%,#83C5BE 100%); border:2px solid #006D77; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Teal/Mint">
												<span style="width:16px; height:16px; background:#EDF6F9; border-radius:50%; border:2px solid #83C5BE; display:inline-block;"></span>
											</div>
											<div class="palette-swatch" data-bg="#232946" data-text="#FFFFFE" data-accent="#EEBBC3" style="background: linear-gradient(90deg,#232946 60%,#EEBBC3 100%); border:2px solid #232946; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Indigo/Pink">
												<span style="width:16px; height:16px; background:#FFFFFE; border-radius:50%; border:2px solid #EEBBC3; display:inline-block;"></span>
											</div>
										</div>
										<small class="text-muted">Click a palette to apply to Admin Header colors below. <strong class="text-primary">Default</strong> resets to system factory colors.</small>
									</div>
									<div class="row">
										<div class="col-md-4 mb-3">
											<label class="form-label">Background Color</label>
											<div class="input-group">
												<input type="color" name="admin_header_bg_color" class="form-control form-control-color admin-header-bg-color" value="<?php echo htmlspecialchars($headerColors['background']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="admin_header_bg_color" class="form-control form-control-hex admin-header-bg-color" value="<?php echo htmlspecialchars($headerColors['background']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Text Color</label>
											<div class="input-group">
												<input type="color" name="admin_header_text_color" class="form-control form-control-color admin-header-text-color" value="<?php echo htmlspecialchars($headerColors['text']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="admin_header_text_color" class="form-control form-control-hex admin-header-text-color" value="<?php echo htmlspecialchars($headerColors['text']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Accent Color</label>
											<div class="input-group">
												<input type="color" name="admin_header_accent_color" class="form-control form-control-color admin-header-accent-color" value="<?php echo htmlspecialchars($headerColors['accent']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="admin_header_accent_color" class="form-control form-control-hex admin-header-accent-color" value="<?php echo htmlspecialchars($headerColors['accent']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
									</div>
								</div>

								<div class="form-divider"></div>

								<!-- Sidebar Colors -->
								<div class="settings-group">
									<h5 style="margin-bottom: 15px;"><i class="ph-list"></i> Sidebar</h5>
									<!-- Sidebar Color Palettes -->
									<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
										<!-- System Default Reset Palette -->
										<div class="sidebar-palette-swatch" id="sidebar-reset-default" data-bg="#1e293b" data-text="#e2e8f0" data-active-bg="#475569" data-active-text="#ffffff" data-hover-bg="#334155" style="background: linear-gradient(90deg,#1e293b 60%,#475569 100%); border:2px dashed #e2e8f0; cursor:pointer; width:64px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative; gap:4px;" title="Reset to System Default">
											<i class="ph-arrow-counter-clockwise" style="font-size:13px; color:#e2e8f0;"></i>
											<span style="font-size:9px; color:#e2e8f0; font-weight:700; letter-spacing:.3px;">Default</span>
										</div>
										<div class="sidebar-palette-swatch" data-bg="#1e293b" data-text="#e2e8f0" data-active-bg="#475569" data-active-text="#ffffff" data-hover-bg="#334155" style="background: linear-gradient(90deg,#1e293b 60%,#475569 100%); border:2px solid #1e293b; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Slate Blue">
											<span style="width:16px; height:16px; background:#e2e8f0; border-radius:50%; border:2px solid #475569; display:inline-block;"></span>
										</div>
										<div class="sidebar-palette-swatch" data-bg="#22223b" data-text="#f2e9e4" data-active-bg="#9a8c98" data-active-text="#ffffff" data-hover-bg="#4a4e69" style="background: linear-gradient(90deg,#22223b 60%,#9a8c98 100%); border:2px solid #22223b; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Charcoal/Lavender">
											<span style="width:16px; height:16px; background:#f2e9e4; border-radius:50%; border:2px solid #9a8c98; display:inline-block;"></span>
										</div>
										<div class="sidebar-palette-swatch" data-bg="#232946" data-text="#fffffe" data-active-bg="#eeebc3" data-active-text="#232946" data-hover-bg="#b8c1ec" style="background: linear-gradient(90deg,#232946 60%,#eeebc3 100%); border:2px solid #232946; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Indigo/Pink">
											<span style="width:16px; height:16px; background:#fffffe; border-radius:50%; border:2px solid #eeebc3; display:inline-block;"></span>
										</div>
										<div class="sidebar-palette-swatch" data-bg="#006d77" data-text="#edf6f9" data-active-bg="#83c5be" data-active-text="#006d77" data-hover-bg="#b2f7ef" style="background: linear-gradient(90deg,#006d77 60%,#83c5be 100%); border:2px solid #006d77; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Teal/Mint">
											<span style="width:16px; height:16px; background:#edf6f9; border-radius:50%; border:2px solid #83c5be; display:inline-block;"></span>
										</div>
										<div class="sidebar-palette-swatch" data-bg="#f7f7fe" data-text="#2f3b4f" data-active-bg="#e7f0ee" data-active-text="#3ba1ff" data-hover-bg="#f0f0f7" style="background: linear-gradient(90deg,#f7f7fe 60%,#e7f0ee 100%); border:2px solid #f7f7fe; cursor:pointer; width:56px; height:32px; border-radius:6px; display:flex; align-items:center; justify-content:center; position:relative;" title="Default (Light)">
											<span style="width:16px; height:16px; background:#2f3b4f; border-radius:50%; border:2px solid #e7f0ee; display:inline-block;"></span>
										</div>
									</div>
									<small class="text-muted">Click a palette to apply to Sidebar colors below. <strong class="text-primary">Default</strong> resets to system factory colors.</small>
									<div class="row">
										<div class="col-md-4 mb-3">
											<label class="form-label">Background</label>
											<div class="input-group">
												<input type="color" name="sidebar_bg_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebarColors['background']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="sidebar_bg_color" class="form-control form-control-hex" value="<?php echo htmlspecialchars($sidebarColors['background']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Text Color</label>
											<div class="input-group">
												<input type="color" name="sidebar_text_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebarColors['text']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="sidebar_text_color" class="form-control form-control-hex" value="<?php echo htmlspecialchars($sidebarColors['text']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Active Background</label>
											<div class="input-group">
												<input type="color" name="sidebar_active_bg_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebarColors['active_bg']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="sidebar_active_bg_color" class="form-control form-control-hex" value="<?php echo htmlspecialchars($sidebarColors['active_bg']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Active Text</label>
											<div class="input-group">
												<input type="color" name="sidebar_active_text_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebarColors['active_text']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="sidebar_active_text_color" class="form-control form-control-hex" value="<?php echo htmlspecialchars($sidebarColors['active_text']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
										<div class="col-md-4 mb-3">
											<label class="form-label">Hover Background</label>
											<div class="input-group">
												<input type="color" name="sidebar_hover_bg_color" class="form-control form-control-color" value="<?php echo htmlspecialchars($sidebarColors['hover_bg']); ?>" oninput="this.nextElementSibling.value = this.value">
												<input type="text" name="sidebar_hover_bg_color" class="form-control form-control-hex" value="<?php echo htmlspecialchars($sidebarColors['hover_bg']); ?>" maxlength="7" pattern="#?[0-9A-Fa-f]{6}" oninput="this.previousElementSibling.value = this.value">
											</div>
										</div>
									</div>
								</div>
						</div>
					</div>
				</div>
				<!-- /Tab Content -->
			</div>
		</div>
	</form>
	<?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>

<script>
// Palette click handler for Admin Header
document.addEventListener('DOMContentLoaded', function() {
	document.querySelectorAll('.palette-swatch').forEach(function(swatch) {
		swatch.addEventListener('click', function() {
			var bg = swatch.getAttribute('data-bg');
			var text = swatch.getAttribute('data-text');
			var accent = swatch.getAttribute('data-accent');
			// Set color pickers and hex fields for Admin Header
			document.querySelectorAll('.admin-header-bg-color').forEach(function(input) {
				input.value = bg;
			});
			document.querySelectorAll('.admin-header-text-color').forEach(function(input) {
				input.value = text;
			});
			document.querySelectorAll('.admin-header-accent-color').forEach(function(input) {
				input.value = accent;
			});
		});
	});
	// Palette click handler for Sidebar
	document.querySelectorAll('.sidebar-palette-swatch').forEach(function(swatch) {
		swatch.addEventListener('click', function() {
			var bg = swatch.getAttribute('data-bg');
			var text = swatch.getAttribute('data-text');
			var activeBg = swatch.getAttribute('data-active-bg');
			var activeText = swatch.getAttribute('data-active-text');
			var hoverBg = swatch.getAttribute('data-hover-bg');
			document.querySelectorAll('input[name="sidebar_bg_color"]').forEach(function(input) {
				input.value = bg;
			});
			document.querySelectorAll('input[name="sidebar_text_color"]').forEach(function(input) {
				input.value = text;
			});
			document.querySelectorAll('input[name="sidebar_active_bg_color"]').forEach(function(input) {
				input.value = activeBg;
			});
			document.querySelectorAll('input[name="sidebar_active_text_color"]').forEach(function(input) {
				input.value = activeText;
			});
			document.querySelectorAll('input[name="sidebar_hover_bg_color"]').forEach(function(input) {
				input.value = hoverBg;
			});
		});
	});
});
	// ============================================================================
	// BOOTSTRAP 5 TAB INITIALIZATION
	// ============================================================================
	
	document.addEventListener('DOMContentLoaded', function() {
		// Initialize all Bootstrap tabs
		const triggerTabList = document.querySelectorAll('.settings-tabs button[data-bs-toggle="tab"]');
		triggerTabList.forEach(triggerEl => {
			const tabTrigger = new bootstrap.Tab(triggerEl);
			
			// Add click event to ensure tab switching works
			triggerEl.addEventListener('click', function (event) {
				event.preventDefault();
				tabTrigger.show();
			});
		});

		// Allow direct links like global_settings.php?tab=seo, with localStorage fallback.
		const urlParams = new URLSearchParams(window.location.search);
		const requestedTab = urlParams.get('tab');
		if (requestedTab) {
			const targetSelector = `#${requestedTab}`;
			const tabElement = document.querySelector(`button[data-bs-target="${targetSelector}"]`);
			if (tabElement) {
				const tab = new bootstrap.Tab(tabElement);
				tab.show();
			}
		} else {
			// Restore last active tab from localStorage
			const lastActiveTab = localStorage.getItem('globalSettingsActiveTab');
			if (lastActiveTab) {
				const tabElement = document.querySelector(`button[data-bs-target="${lastActiveTab}"]`);
				if (tabElement) {
					const tab = new bootstrap.Tab(tabElement);
					tab.show();
				}
			}
		}

		// Save active tab to localStorage
		triggerTabList.forEach(triggerEl => {
			triggerEl.addEventListener('shown.bs.tab', function (event) {
				const targetTab = event.target.getAttribute('data-bs-target');
				localStorage.setItem('globalSettingsActiveTab', targetTab);
			});
		});
	});

	// ============================================================================
	// FORM ENHANCEMENTS
	// ============================================================================
	
	// Auto-submit form functionality
	document.querySelectorAll('.form-control, .form-select').forEach(input => {
		input.addEventListener('change', function() {
			// Add visual feedback when inputs change
			this.classList.add('is-dirty');
		});
	});

	// File input styling and preview
	document.querySelectorAll('input[type="file"]').forEach(input => {
		input.addEventListener('change', function() {
			if (this.files.length > 0) {
				this.parentElement.classList.add('has-file');
				
				// Show file name
				const fileName = this.files[0].name;
				const fileSize = (this.files[0].size / 1024 / 1024).toFixed(2);
				
				// Update form text to show selected file
				const formText = this.parentElement.querySelector('.form-text');
				if (formText) {
					formText.innerHTML = `<i class="ph-check-circle text-success"></i> Selected: ${fileName} (${fileSize} MB)`;
				}
			}
		});
	});

	// ============================================================================
	// DELETE CONFIRMATION
	// ============================================================================
	
	document.querySelectorAll('.delete-image-btn').forEach(btn => {
		btn.addEventListener('click', function(e) {
			if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
				e.preventDefault();
				return false;
			}
		});
	});

	// ============================================================================
	// FORM VALIDATION
	// ============================================================================
	
	document.getElementById('frmsystem_settings').addEventListener('submit', function(e) {
		let isValid = true;
		const requiredFields = this.querySelectorAll('[required]');
		
		requiredFields.forEach(field => {
			if (!field.value.trim()) {
				isValid = false;
				field.classList.add('is-invalid');
			} else {
				field.classList.remove('is-invalid');
			}
		});
		
		if (!isValid) {
			e.preventDefault();
			alert('Please fill in all required fields.');
			return false;
		}
	});

	// ============================================================================
	// AUTO-SAVE DRAFT (OPTIONAL)
	// ============================================================================
	
	let autoSaveTimer;
	document.querySelectorAll('.form-control, .form-select, input[type="checkbox"]').forEach(input => {
		input.addEventListener('input', function() {
			clearTimeout(autoSaveTimer);
			autoSaveTimer = setTimeout(() => {
				// Save to localStorage as draft
				const formData = new FormData(document.getElementById('frmsystem_settings'));
				const draftData = {};
				for (let [key, value] of formData.entries()) {
					draftData[key] = value;
				}
				localStorage.setItem('globalSettingsDraft', JSON.stringify(draftData));
				
				// Show saved indicator
				showAutoSaveIndicator();
			}, 2000); // Auto-save after 2 seconds of inactivity
		});
	});

	function showAutoSaveIndicator() {
		const indicator = document.createElement('div');
		indicator.className = 'auto-save-indicator';
		indicator.innerHTML = '<i class="ph-check-circle"></i> Draft saved';
		indicator.style.cssText = 'position: fixed; top: 80px; right: 20px; background: #10B981; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; z-index: 9999; animation: slideInRight 0.3s ease;';
		document.body.appendChild(indicator);
		
		setTimeout(() => {
			indicator.style.animation = 'slideOutRight 0.3s ease';
			setTimeout(() => indicator.remove(), 300);
		}, 2000);
	}

	// ============================================================================
	// KEYBOARD SHORTCUTS
	// ============================================================================
	
	document.addEventListener('keydown', function(e) {
		// Ctrl/Cmd + S to save
		if ((e.ctrlKey || e.metaKey) && e.key === 's') {
			e.preventDefault();
			document.getElementById('frmsystem_settings').submit();
		}
		
		// Ctrl/Cmd + 1-7 to switch tabs
		if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '7') {
			e.preventDefault();
			const tabIndex = parseInt(e.key) - 1;
			const tabs = document.querySelectorAll('.settings-tabs button[data-bs-toggle="tab"]');
			if (tabs[tabIndex]) {
				const tab = new bootstrap.Tab(tabs[tabIndex]);
				tab.show();
			}
		}
	});

	console.log('âœ… Global Settings page initialized');
	console.log('ðŸ’¡ Keyboard shortcuts: Ctrl+S (Save), Ctrl+1-7 (Switch tabs)');

	// Color picker value display update
	document.querySelectorAll('input[type="color"]').forEach(colorInput => {
		colorInput.addEventListener('input', function() {
			const display = this.nextElementSibling;
			if (display && display.classList.contains('color-display')) {
				display.textContent = this.value.toUpperCase();
			}
		});
	});
</script>

<style>
	@keyframes slideInRight {
		from {
			opacity: 0;
			transform: translateX(100%);
		}
		to {
			opacity: 1;
			transform: translateX(0);
		}
	}
	
	@keyframes slideOutRight {
		from {
			opacity: 1;
			transform: translateX(0);
		}
		to {
			opacity: 0;
			transform: translateX(100%);
		}
	}

	.is-dirty {
		border-color: #FFA500 !important;
		box-shadow: 0 0 0 2px rgba(255, 165, 0, 0.1) !important;
	}

	.is-invalid {
		border-color: #EF4444 !important;
		box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.1) !important;
	}

	/* UI Design Color Settings Styling */
	.form-divider {
		height: 1px;
		background: linear-gradient(to right, transparent, #ddd, transparent);
		margin: 25px 0 20px 0;
		border: none;
	}

	.settings-group {
		margin-bottom: 5px;
	}

	.settings-group h5 {
		font-weight: 600;
		color: #2c3e50;
		display: flex;
		align-items: center;
		gap: 8px;
	}

	.settings-group h6 {
		font-weight: 500;
		margin-top: 15px;
		padding-bottom: 10px;
		border-bottom: 1px solid #ecf0f1;
	}

	.color-display {
		font-family: 'Monaco', 'Courier New', monospace;
		font-size: 12px;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 1px;
		background: #f8f9fa;
		color: #2c3e50;
		border: 1px solid #ddd;
		min-width: 85px;
		text-align: center;
		cursor: default;
	}

	.form-control-color {
		height: 42px;
		padding: 3px;
		cursor: pointer;
		border-radius: 4px;
		transition: all 0.2s ease;
	}

	.form-control-color:hover {
		transform: scale(1.02);
		box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
	}

	.input-group .form-control-color {
		flex: 0 0 60px;
	}
</style>
	
