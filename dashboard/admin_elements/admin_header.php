<?php declare(strict_types=1);

use App\Core\DB;
use App\Security\Roles;
require_once __DIR__ . '/../bootstrap.php';

// Initialize CSRF token for all forms
csrf_token();

global $session_role_id, $session_user_id, $session_full_name, $session_email, $admin_base_url, $mysqli, $base_url, $current_page, $project_pre, $headerAlertsCount, $headerOrganizationCount, $activeOrganizationName;

/*
|--------------------------------------------------------------------------
|     HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

/**
 * Check user access to module (prevent redeclaration if sidebar.php loaded first)
 */
if (!function_exists('hasModuleAccess')) {
	function hasModuleAccess($module)
	{
		return granted_('view', $module) || granted_('create', $module) ||
			granted_('edit', $module) || granted_('delete', $module);
	}
}

if (!function_exists('fetchAccountsDropdown')) {
	function fetchAccountsDropdown($accountType = null, $prefix = '', $selected = null)
	{
		global $mysqli;

		if (!$mysqli instanceof mysqli) {
			return '';
		}

		if ($accountType === null || $accountType === '') {
			return '';
		}

		$accountTypes = is_array($accountType) ? $accountType : [$accountType];

		// Legacy callers pass numeric type codes (1=Assets, 2=Liability, 3=Equity, 4=Income, 5=Expense)
		$typeMap = ['1' => 'Assets', '2' => 'Liability', '3' => 'Equity', '4' => 'Income', '5' => 'Expense'];

		// Escape string values for IN clause
		$escaped = [];
		foreach ($accountTypes as $t) {
			$t = (string)$t;
			if (isset($typeMap[$t])) {
				$t = $typeMap[$t];
			}
			if ($t !== '') {
				$escaped[] = "'" . $mysqli->real_escape_string($t) . "'";
			}
		}
		if (empty($escaped)) {
			return '';
		}

		$typeList = implode(',', $escaped);
		$sql = "SELECT id, parent_id, account_name, account_code, account_type, level
		        FROM `" . DB::ACCOUNTS . "`
		        WHERE account_type IN ($typeList) AND (parent_id IS NULL OR parent_id = 0)
		        ORDER BY account_name ASC";
		$result = $mysqli->query($sql);
		if (!$result) {
			return '';
		}

		$html = '';
		while ($row = $result->fetch_assoc()) {
			$id = (int)($row['id'] ?? 0);
			if ($id <= 0) continue;
			$label = htmlspecialchars((string)($row['account_name'] ?? ''));
			$selectedAttr = ($id === (int)$selected) ? ' selected' : '';
			$html .= '<option value="' . $id . '"' . $selectedAttr . ' class="fw-semibold text-black">' . $prefix . $label . '</option>';
			$html .= fetchAccountsDropdownChildren($id, $prefix . '- ', $selected);
		}
		$result->free();
		return $html;
	}
}

if (!function_exists('fetchAccountsDropdownChildren')) {
	function fetchAccountsDropdownChildren($parentId, $prefix = '', $selected = null)
	{
		global $mysqli;
		if (!$mysqli instanceof mysqli) return '';

		$parentId = (int)$parentId;
		if ($parentId <= 0) return '';

		$sql = "SELECT id, account_name, account_code, level
		        FROM `" . DB::ACCOUNTS . "`
		        WHERE parent_id = $parentId
		        ORDER BY account_name ASC";
		$result = $mysqli->query($sql);
		if (!$result || $result->num_rows === 0) return '';

		$html = '';
		while ($row = $result->fetch_assoc()) {
			$id = (int)$row['id'];
			if ($id <= 0) continue;
			$label = htmlspecialchars((string)($row['account_name'] ?? ''));
			$selectedAttr = ($id === (int)$selected) ? ' selected' : '';
			$html .= '<option value="' . $id . '"' . $selectedAttr . '>' . $prefix . $label . '</option>';
			$html .= fetchAccountsDropdownChildren($id, $prefix . '- ', $selected);
		}
		$result->free();
		return $html;
	}
}

/*
|--------------------------------------------------------------------------
|     COUNT UNREAD ERROR LOGS
|--------------------------------------------------------------------------
| Count new error log entries (runtime errors) since last read
*/
if (!function_exists('getUnreadErrorLogsCount')) {
	function getUnreadErrorLogsCount()
	{
		global $mysqli;

		if (!$mysqli instanceof mysqli) {
			return 0;
		}

		$backendLogTableExists = false;
		$coverageTableExists = false;

		$tableCheckSql = "SELECT TABLE_NAME FROM information_schema.TABLES
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME IN (?, ?)";
		$tableCheckStmt = $mysqli->prepare($tableCheckSql);
		if ($tableCheckStmt) {
			$backendLogTable = DB::BACKEND_ERROR_LOGS;
			$coverageTable = DB::BACKEND_LOG_COVERAGE;
			$tableCheckStmt->bind_param('ss', $backendLogTable, $coverageTable);
			if ($tableCheckStmt->execute()) {
				$result = $tableCheckStmt->get_result();
				while ($row = $result ? $result->fetch_assoc() : null) {
					if (($row['TABLE_NAME'] ?? '') === DB::BACKEND_ERROR_LOGS) {
						$backendLogTableExists = true;
					}
					if (($row['TABLE_NAME'] ?? '') === DB::BACKEND_LOG_COVERAGE) {
						$coverageTableExists = true;
					}
				}
			}
			$tableCheckStmt->close();
		}

		if ($backendLogTableExists) {
			$lastReadTimestamp = '1970-01-01 00:00:00';
			$statusResult = $mysqli->query("SELECT last_read_timestamp FROM `" . DB::ERROR_LOG_STATUS . "` WHERE id = 1 LIMIT 1");
			if ($statusResult instanceof mysqli_result) {
				$statusRow = $statusResult->fetch_assoc();
				if (!empty($statusRow['last_read_timestamp'])) {
					$lastReadTimestamp = (string)$statusRow['last_read_timestamp'];
				}
				$statusResult->free();
			}

			$countStmt = $mysqli->prepare(
				"SELECT COUNT(*) AS unread_count
				 FROM `" . DB::BACKEND_ERROR_LOGS . "`
				 WHERE created_at > ?
				   AND severity NOT IN ('INFO', 'DEBUG')"
			);
			if ($countStmt) {
				$countStmt->bind_param('s', $lastReadTimestamp);
				if ($countStmt->execute()) {
					$countResult = $countStmt->get_result();
					$countRow = $countResult ? $countResult->fetch_assoc() : null;
					$countStmt->close();
					return (int)($countRow['unread_count'] ?? 0);
				}
				$countStmt->close();
			}
		}

		// Try consolidated error log first, then fallback to Apache error_log
		$error_log_file = __DIR__ . '/../CONSOLIDATED_ERROR_LOG.txt';

		// If consolidated log exists, trust it even if empty
		if (!file_exists($error_log_file)) {
			$error_log_file = __DIR__ . '/../error_log.txt';
		}

		if (!file_exists($error_log_file) || filesize($error_log_file) == 0) {
			return 0; // No error log file
		}

		$unread_count = 0;
		$log_content = file_get_contents($error_log_file);
		$log_lines = explode("\n", $log_content);

		foreach ($log_lines as $line) {
			if (empty(trim($line))) continue;

			// Only count lines that are actual runtime errors
			$is_error = preg_match('/\b(PHP|PHP Fatal|Fatal error|parse error|Warning|Notice|Strict|Deprecated|Error|Exception|Undefined|Syntax Error|Type Error)\b/i', $line);
			if (!$is_error) continue;

			// Try to extract timestamp - supports multiple formats
			$log_timestamp = null;

			// Format 1: [18-Feb-2026 19:53:17 Europe/Berlin]
			if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
				$parsedTime = strtotime($matches[1]);
				if ($parsedTime !== false) {
					$log_timestamp = date('Y-m-d H:i:s', $parsedTime);
				}
			}

			if ($log_timestamp === null && preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
				$log_timestamp = $matches[1];
			}

			if ($log_timestamp !== null) {
				$lastReadTimestamp = '1970-01-01 00:00:00';
				$statusResult = $mysqli->query("SELECT last_read_timestamp FROM `" . DB::ERROR_LOG_STATUS . "` WHERE id = 1 LIMIT 1");
				if ($statusResult instanceof mysqli_result) {
					$statusRow = $statusResult->fetch_assoc();
					if (!empty($statusRow['last_read_timestamp'])) {
						$lastReadTimestamp = (string)$statusRow['last_read_timestamp'];
					}
					$statusResult->free();
				}

				if (strtotime($log_timestamp) > strtotime($lastReadTimestamp)) {
					$unread_count++;
				}
			}
		}

		return $unread_count;
	}
}

if (!function_exists('resolveFrontendErrorLogPath')) {
	function resolveFrontendErrorLogPath()
	{
		$candidates = [
			__DIR__ . '/../../logs/FRONTEND_ERROR_LOG.txt',
			__DIR__ . '/../logs/FRONTEND_ERROR_LOG.txt',
		];

		foreach ($candidates as $candidate) {
			if (file_exists($candidate)) {
				return $candidate;
			}
		}

		return $candidates[0];
	}
}

/*
|--------------------------------------------------------------------------
|     COUNT FRONTEND ERROR LOGS
|--------------------------------------------------------------------------
| Count frontend error log entries from logs/FRONTEND_ERROR_LOG.txt
*/
if (!function_exists('getFrontendErrorLogsCount')) {
	function getFrontendErrorLogsCount()
	{
		// Frontend error log file
		$error_log_file = resolveFrontendErrorLogPath();

		if (!file_exists($error_log_file) || filesize($error_log_file) == 0) {
			return 0; // No frontend error log file
		}

		$error_count = 0;
		$log_content = file_get_contents($error_log_file);
		$log_lines = explode("\n", $log_content);

		foreach ($log_lines as $line) {
			if (empty(trim($line))) continue;

			// Count lines that contain error indicators (severity level, exception, etc.)
			$is_error = preg_match('/\b(ERROR|CRITICAL|FATAL|EXCEPTION|WARNING)\b/i', $line);
			if ($is_error) {
				$error_count++;
			}
		}

		return $error_count;
	}
}



// Normalize page URL for conditional assets and helpers.
$page_url = $_SERVER['REQUEST_URI'] ?? '';
$current_page = basename(parse_url($page_url, PHP_URL_PATH) ?? '');
$base_url = $base_url ?? ($admin_base_url ?? '');

$pageHelpData = [];

$emailModuleLinks = [
	[
		'module' => 'email_queue',
		'href' => 'listing_email_queue.php',
		'label' => 'Queue',
		'icon' => 'ph-list-numbers',
	],
	[
		'module' => 'email_history',
		'href' => 'listing_email_history.php',
		'label' => 'History',
		'icon' => 'ph-archive',
	],
];

$visibleEmailLinks = array_values(array_filter($emailModuleLinks, function ($item) {
	return hasModuleAccess($item['module']);
}));

$isEmailRelatedPage = preg_match('/^(listing_)?email_[a-z0-9_]*\.php$/i', $current_page) === 1;
$currentRoleId = Roles::getCurrentRoleId() ?? $session_role_id ?? null;
$currentRoleName = strtolower(trim((string) Roles::getName($currentRoleId)));
$activeOrganizationName = function_exists('dashboardGetActiveOrganizationName') ? dashboardGetActiveOrganizationName() : '';
$headerAccessibleOrganizations = function_exists('dashboardGetAccessibleOrganizations') ? (array)dashboardGetAccessibleOrganizations() : [];
$headerOrganizationCount = count($headerAccessibleOrganizations);
$headerAlertsCount = function_exists('getUnreadErrorLogsCount') ? (int)getUnreadErrorLogsCount() : 0;

if (!function_exists('dashboardSystemSettingSlug')) {
	function dashboardSystemSettingSlug(string $system): string
	{
		$map = [
			'crm' => 'system_suite_crm_enabled',
			'hr' => 'system_suite_hr_enabled',
			'accounting' => 'system_suite_accounting_enabled',
			'shipping' => 'system_suite_shipping_enabled',
		];

		$key = strtolower(trim($system));
		return $map[$key] ?? '';
	}
}

if (!function_exists('dashboardIsSystemEnabled')) {
	function dashboardIsSystemEnabled(string $system): bool
	{
		$slug = dashboardSystemSettingSlug($system);
		if ($slug === '') {
			return true;
		}

		$raw = strtolower(trim((string)getSystemSetting($slug, '1')));
		return in_array($raw, ['1', 'true', 'yes', 'on'], true);
	}
}

if (!function_exists('dashboardHasSystemAccess')) {
	function dashboardHasSystemAccess(string $system): bool
	{
		return dashboardIsSystemEnabled($system);
	}
}

if (!function_exists('dashboardUpdateSystemAvailability')) {
	function dashboardUpdateSystemAvailability(mysqli $mysqli, string $system, bool $enabled): bool
	{
		$slug = dashboardSystemSettingSlug($system);
		if ($slug === '') {
			return false;
		}

		$value = $enabled ? '1' : '0';
		$labelMap = [
			'crm' => 'CRM Enabled',
			'hr' => 'HR Enabled',
			'accounting' => 'Accounting Enabled',
			'shipping' => 'Shipping Enabled',
		];
		$name = $labelMap[strtolower($system)] ?? strtoupper($system) . ' Enabled';

		$stmt = $mysqli->prepare(
			"INSERT INTO `" . DB::SYSTEM_SETTINGS . "` (setting_slug, setting_name, setting_value, hint)
			 VALUES (?, ?, ?, '1=enabled, 0=disabled')
			 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_name = VALUES(setting_name)"
		);
		if (!$stmt) {
			return false;
		}

		$stmt->bind_param('sss', $slug, $name, $value);
		$ok = $stmt->execute();
		$stmt->close();

		if ($ok) {
			$GLOBALS['SYSTEM_SETTINGS'][$slug] = $value;
		}

		return $ok;
	}
}

$systemToggleStatus = (string)($_GET['system_toggle_status'] ?? '');

if (
	$_SERVER['REQUEST_METHOD'] === 'POST'
	&& (string)($_POST['action'] ?? '') === 'toggle_system_suite'
	&& Roles::isSuperAdmin($session_role_id)
) {
	if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
		$systemToggleStatus = 'csrf_failed';
	} else {
		$targetSystem = strtolower(trim((string)($_POST['target_system'] ?? '')));
		$targetValue = strtolower(trim((string)($_POST['enabled'] ?? '1')));
		$enabled = in_array($targetValue, ['1', 'true', 'yes', 'on'], true);
		$ok = ($mysqli instanceof mysqli) ? dashboardUpdateSystemAvailability($mysqli, $targetSystem, $enabled) : false;
		$systemToggleStatus = $ok ? 'updated' : 'failed';
	}

	$redirectPath = strtok((string)($_SERVER['REQUEST_URI'] ?? 'admin_elements/admin_header.php'), '?');
	$redirectQuery = $_GET;
	unset($redirectQuery['system_toggle_status']);
	$redirectQuery['system_toggle_status'] = $systemToggleStatus;
	$qs = http_build_query($redirectQuery);
	header('Location: ' . $redirectPath . ($qs !== '' ? '?' . $qs : ''));
	exit;
}

$systemSuites = [
	'crm' => [
		'label' => 'CRM',
		'icon' => 'ph-users-three',
		'enabled' => dashboardIsSystemEnabled('crm'),
	],
	'hr' => [
		'label' => 'HR',
		'icon' => 'ph-identification-card',
		'enabled' => dashboardIsSystemEnabled('hr'),
	],
	'accounting' => [
		'label' => 'Accounting',
		'icon' => 'ph-currency-circle-dollar',
		'enabled' => dashboardIsSystemEnabled('accounting'),
	],
	'shipping' => [
		'label' => 'Shipping',
		'icon' => 'ph-package',
		'enabled' => dashboardIsSystemEnabled('shipping'),
	],
];

$adminHeaderQuickAccessSections = [
	[
		'title' => 'System',
		'icon' => 'ph-gear-six',
		'links' => [
			['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'ph-house'],
			['href' => 'global_settings.php', 'label' => 'Global Settings', 'icon' => 'ph-sliders-horizontal'],
			['href' => 'listing_users.php', 'label' => 'System Users', 'icon' => 'ph-users'],
			['href' => 'listing_roles.php', 'label' => 'Roles & Permissions', 'icon' => 'ph-lock-key'],
			['href' => 'listing_authentication_activity.php', 'label' => 'Security Logs', 'icon' => 'ph-shield-check'],
		],
	],
	[
		'title' => 'CRM',
		'icon' => 'ph-users-three',
		'dashboard' => 'dashboard_crm.php',
		'links' => [
			['href' => 'listing_leads.php', 'label' => 'Leads', 'icon' => 'ph-target', 'perm' => 'leads'],
			['href' => 'listing_customers.php', 'label' => 'Customers', 'icon' => 'ph-user-circle', 'perm' => 'customers'],
			['href' => 'listing_projects.php', 'label' => 'Projects', 'icon' => 'ph-briefcase', 'perm' => 'projects'],
			['href' => 'listing_jobs.php', 'label' => 'Jobs', 'icon' => 'ph-suitcase-simple', 'perm' => 'jobs'],
		],
	],
	[
		'title' => 'Accounting',
		'icon' => 'ph-currency-circle-dollar',
		'dashboard' => 'dashboard_accounting.php',
		'links' => [
            ['href' => 'listing_items.php', 'label' => 'Items', 'icon' => 'ph-cube', 'perm' => 'items'],
			['href' => 'listing_quotations.php', 'label' => 'Quotations', 'icon' => 'ph-file-text', 'perm' => 'quotations'],
			['href' => 'listing_sale_orders.php', 'label' => 'Sale Orders', 'icon' => 'ph-shopping-cart', 'perm' => 'sale_orders'],
			['href' => 'listing_invoices.php', 'label' => 'Invoices', 'icon' => 'ph-receipt', 'perm' => 'invoices'],
			['href' => 'listing_payments_received.php', 'label' => 'Payments Received', 'icon' => 'ph-arrow-circle-down', 'perm' => 'payments_received'],
			['href' => 'listing_credit_notes.php', 'label' => 'Credit Notes', 'icon' => 'ph-file-minus', 'perm' => 'credit_notes'],
			['href' => 'listing_recurring_invoices.php', 'label' => 'Recurring Invoices', 'icon' => 'ph-repeat', 'perm' => 'recurring_invoices'],
			['href' => 'listing_customers.php', 'label' => 'Customers', 'icon' => 'ph-user-circle', 'perm' => 'customers'],
			['href' => 'listing_vendors.php', 'label' => 'Vendors', 'icon' => 'ph-truck', 'perm' => 'vendors'],
			['href' => 'listing_expenses.php', 'label' => 'Expenses', 'icon' => 'ph-wallet', 'perm' => 'expenses'],
			['href' => 'listing_purchase_orders.php', 'label' => 'Purchase Orders', 'icon' => 'ph-clipboard-text', 'perm' => 'purchase_orders'],
			['href' => 'listing_purchases.php', 'label' => 'Purchases', 'icon' => 'ph-shopping-bag', 'perm' => 'purchases'],
			['href' => 'listing_payments_made.php', 'label' => 'Payments Made', 'icon' => 'ph-arrow-circle-up', 'perm' => 'payments_made'],
			['href' => 'listing_debit_notes.php', 'label' => 'Debit Notes', 'icon' => 'ph-file-plus', 'perm' => 'debit_notes'],
			['href' => 'listing_banks.php', 'label' => 'Banks', 'icon' => 'ph-bank', 'perm' => 'banks'],
			['href' => 'listing_journals.php', 'label' => 'Journals', 'icon' => 'ph-notebook', 'perm' => 'journals'],
		],
	],
	[
		'title' => 'HR',
		'icon' => 'ph-identification-card',
		'dashboard' => 'dashboard_hr.php',
		'links' => [
			['group' => 'ORGANIZATION'],
			['href' => 'listing_departments.php', 'label' => 'Departments', 'icon' => 'ph-building', 'perm' => 'departments'],
			['href' => 'listing_designations.php', 'label' => 'Designations', 'icon' => 'ph-identification-badge', 'perm' => 'designations'],
			['group' => 'TIME & ATTENDANCE'],
			['href' => 'listing_attendance.php', 'label' => 'Attendance', 'icon' => 'ph-clock', 'perm' => 'attendance'],
			['href' => 'listing_leave_requests.php', 'label' => 'Leave Requests', 'icon' => 'ph-calendar-check', 'perm' => 'leave_requests'],
			['href' => 'listing_leave_types.php', 'label' => 'Leave Types', 'icon' => 'ph-calendar-blank', 'perm' => 'leave_types'],
			['group' => 'PAYROLL'],
			['href' => 'listing_payroll_components.php', 'label' => 'Payroll Components', 'icon' => 'ph-puzzle-piece', 'perm' => 'payroll_components'],
			['href' => 'listing_salary_structures.php', 'label' => 'Salary Structures', 'icon' => 'ph-currency-circle-dollar', 'perm' => 'salary_structures'],
			['href' => 'listing_employee_salaries.php', 'label' => 'Employee Salaries', 'icon' => 'ph-wallet', 'perm' => 'employee_salaries'],
			['href' => 'listing_payroll_runs.php', 'label' => 'Payroll Runs', 'icon' => 'ph-money', 'perm' => 'payroll_runs'],
			['href' => 'listing_payslips.php', 'label' => 'Payslips', 'icon' => 'ph-receipt', 'perm' => 'payslips'],
			['group' => 'DOCUMENTS & REPORTS'],
			['href' => 'listing_user_documents.php', 'label' => 'User Documents', 'icon' => 'ph-folder-open', 'perm' => 'user_documents'],
			['href' => 'report_hr.php', 'label' => 'HR Report', 'icon' => 'ph-chart-line-up', 'perm' => 'report_hr'],
		],
	],
	[
		'title' => 'Shipping',
		'icon' => 'ph-package',
		'dashboard' => 'dashboard_shipping.php',
		'links' => [
			['href' => 'listing_shipping_advices.php', 'label' => 'Shipping Advices', 'icon' => 'ph-note-pencil', 'perm' => 'shipping_advices'],
			['href' => 'listing_shipping_stocks.php', 'label' => 'Shipping Stocks', 'icon' => 'ph-stack', 'perm' => 'shipping_stocks'],
			['href' => 'listing_ports.php', 'label' => 'Ports', 'icon' => 'ph-map-pin-line', 'perm' => 'ports'],
			['href' => 'listing_carriers.php', 'label' => 'Carriers', 'icon' => 'ph-truck', 'perm' => 'carriers'],
		],
	],
];

$quickAccessSystemMap = [
	'CRM' => 'crm',
	'HR' => 'hr',
	'Accounting' => 'accounting',
	'Shipping' => 'shipping',
];

// For role_id=5 (Accounts), show only the Accounting section in mega menu
// For role_id=4 (Operations), show only CRM and Shipping sections in mega menu
$currentRoleId = $session_role_id;
if ((int)$currentRoleId === 5) {
	$adminHeaderQuickAccessSections = array_values(array_filter($adminHeaderQuickAccessSections, function ($section) {
		return strcasecmp((string)($section['title'] ?? ''), 'Accounting') === 0;
	}));
} elseif ((int)$currentRoleId === 4) {
	$adminHeaderQuickAccessSections = array_values(array_filter($adminHeaderQuickAccessSections, function ($section) {
		$title = (string)($section['title'] ?? '');
		return $title === 'CRM' || $title === 'Shipping';
	}));
} else {
	$adminHeaderQuickAccessSections = array_values(array_filter($adminHeaderQuickAccessSections, function ($section) use ($quickAccessSystemMap) {
		$title = (string)($section['title'] ?? '');
		$mapped = $quickAccessSystemMap[$title] ?? null;
		if ($mapped === null) {
			return true;
		}

		// Check subscription-level access
		if (!dashboardHasSystemAccess($mapped)) {
			return false;
		}

		// Check user has view permission for at least one module in this section
		$links = $section['links'] ?? [];
		foreach ($links as $link) {
			$perm = $link['perm'] ?? null;
			if ($perm && function_exists('granted_') && granted_('view', $perm)) {
				return true;
			}
		}

		return false;
	}));
}

$systemsMegaSections = array_values(array_filter($adminHeaderQuickAccessSections, function ($section) {
	return strcasecmp((string)($section['title'] ?? ''), 'System') !== 0;
}));

$adminMegaSections = array_values(array_filter($adminHeaderQuickAccessSections, function ($section) {
	return strcasecmp((string)($section['title'] ?? ''), 'System') === 0;
}));


if (!function_exists('renderEmailQuickbar')) {
	function renderEmailQuickbar($visibleEmailLinks, $current_page)
	{
		if (empty($visibleEmailLinks)) {
			return;
		}
?>
		<div class="email-quickbar" aria-label="Email management quick links">
			<div class="email-quickbar-inner">
				<span class="email-quickbar-title">
					<i class="ph-envelope me-1"></i>
					Email Modules
				</span>
				<?php foreach ($visibleEmailLinks as $emailLink): ?>
					<?php $isActiveEmailLink = $current_page === basename($emailLink['href']); ?>
					<a href="<?php echo htmlspecialchars($emailLink['href'], ENT_QUOTES); ?>" class="email-quickbar-link <?php echo $isActiveEmailLink ? 'active' : ''; ?>">
						<i class="<?php echo htmlspecialchars($emailLink['icon'], ENT_QUOTES); ?>"></i>
						<?php echo htmlspecialchars($emailLink['label'], ENT_QUOTES); ?>
					</a>
				<?php endforeach; ?>
			</div>
		</div>
<?php
	}
}

/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>Admin Panel - Dashboard</title>
	<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
	<?php
	// Dynamic favicon from system settings
	$favicon = getSystemSetting('favicon', '');
	if (!empty($favicon) && file_exists(__DIR__ . '/../../uploads/system_settings/' . $favicon)) {
		echo '<link rel="shortcut icon" href="../uploads/system_settings/' . htmlspecialchars($favicon) . '" type="image/x-icon" />';
		echo '<link rel="icon" href="../uploads/system_settings/' . htmlspecialchars($favicon) . '" type="image/x-icon" />';
	} else {
		echo '<link rel="shortcut icon" href="../favicon.ico" type="image/x-icon" />';
	}
	?>

	<link href="<?php echo $base_url; ?>/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css">
	<link href="<?php echo $admin_base_url; ?>/assets/fonts/inter/inter.css" rel="stylesheet" type="text/css">
	<link href="<?php echo $admin_base_url; ?>/assets/icons/phosphor/styles.min.css" rel="stylesheet" type="text/css">
	<link href="<?php echo $admin_base_url; ?>/assets/assets_custom/css/all.min.css" id="stylesheet" rel="stylesheet" type="text/css">
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
	<link href="<?php echo $admin_base_url; ?>/assets/assets_custom/css/haipulse-dashboard-compat.css?v=<?php echo @filemtime(__DIR__ . '/../assets/assets_custom/css/haipulse-dashboard-compat.css'); ?>" rel="stylesheet" type="text/css">
	<!-- /global stylesheets -->

	<!-- Core JS files -->
	<script src="<?php echo $admin_base_url; ?>/assets/assets_custom/js/ui-preferences.js"></script>
	<script src="<?php echo $base_url; ?>/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
	<!-- /core JS files -->

	<!-- Google Charts Library -->
	<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/pickers/datepicker.min.js"></script>

	<link rel="stylesheet" href="<?php echo $admin_base_url; ?>/assets/custom_css/jquery-ui.css">
	<script src="<?php echo $base_url; ?>/assets/vendor/jquery/jquery.min.js"></script>
	<script src="<?php echo $admin_base_url; ?>/assets/custom_js/jquery-ui.js"></script>
	<script src="<?php echo $admin_base_url; ?>/assets/custom_js/jquery.inputmask.bundle.js"></script>

	<script>
		window.HAI_CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;

		(function(jq) {
			if (!jq || typeof jq.ajaxSetup !== 'function') {
				return;
			}

			var csrfToken = window.HAI_CSRF_TOKEN || '';

			jq.ajaxSetup({
				beforeSend: function(xhr, settings) {
					var method = (settings.type || settings.method || 'GET').toUpperCase();
					if (method !== 'POST') {
						return;
					}

					if (typeof settings.data === 'string') {
						if (!/(^|&)csrf_token=/.test(settings.data)) {
							settings.data += (settings.data ? '&' : '') + 'csrf_token=' + encodeURIComponent(csrfToken);
						}
					} else if (settings.data instanceof FormData) {
						if (!settings.data.has('csrf_token')) {
							settings.data.append('csrf_token', csrfToken);
						}
					} else if (typeof settings.data === 'object' && settings.data !== null) {
						if (typeof settings.data.csrf_token === 'undefined') {
							settings.data.csrf_token = csrfToken;
						}
					} else if (typeof settings.data === 'undefined') {
						settings.data = {
							csrf_token: csrfToken
						};
					}
				}
			});
		})(window.jQuery);
	</script>

	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/tables/datatables/datatables.min.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/vendor/tables/datatables/datatables.min.js'); ?>"></script>
	<link rel="stylesheet" type="text/css" href="<?php echo $admin_base_url; ?>/assets/vendor/datatables/dataTables.bootstrap5.min.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $admin_base_url; ?>/assets/vendor/datatables-responsive/responsive.dataTables.min.css">
	<script type="text/javascript" src="<?php echo $admin_base_url; ?>/assets/vendor/datatables-responsive/dataTables.responsive.min.js"></script>

	<!-- DataTables Standardization - Badges, Action Buttons, Table Styling -->
	<link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>/assets/css/datatables-unified.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/datatables-unified.css'); ?>">
	<!-- dashboard-listing-pages.css removed — pages module decommissioned -->
	<link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>/assets/css/badges.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>/assets/css/action-buttons.css">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>/assets/css/datatable-error-handler.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/datatable-error-handler.css'); ?>">
	<link rel="stylesheet" type="text/css" href="<?php echo $base_url; ?>/assets/css/responsive-bootstrap-redesign.css?v=<?php echo @filemtime(__DIR__ . '/../../assets/css/responsive-bootstrap-redesign.css'); ?>">
	<script src="<?php echo $base_url; ?>/assets/js/dashboard-datatable-standard.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/dashboard-datatable-standard.js'); ?>"></script>
	<script src="<?php echo $base_url; ?>/assets/js/datatable-error-handler.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/datatable-error-handler.js'); ?>"></script>
	<script src="<?php echo $base_url; ?>/assets/js/dashboard-datatable-initializer.js?v=<?php echo @filemtime(__DIR__ . '/../../assets/js/dashboard-datatable-initializer.js'); ?>"></script>
	<script>
		// Apply global DataTables defaults so legacy direct DataTable() pages
		// follow the same listing_companies-style layout contract.
		(function(win, $) {
			if (!$ || !$.fn || !$.fn.dataTable) {
				return;
			}

			var defaultDom = (win.HAIDataTable && win.HAIDataTable.defaultDom) ?
				win.HAIDataTable.defaultDom :
				"<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>";

			$.extend(true, $.fn.dataTable.defaults, {
				autoWidth: false,
				responsive: true,
				pageLength: 10,
				lengthMenu: [
					[10, 25, 50, 100],
					[10, 25, 50, 100]
				],
				dom: defaultDom
			});
		})(window, window.jQuery);
	</script>

	<!-- Centralized Event Bindings (Phase 2 refactoring) -->
	<script src="<?php echo $admin_base_url; ?>/assets_custom/js/event-bindings.js?v=<?php echo @filemtime(__DIR__ . '/../assets_custom/js/event-bindings.js'); ?>"></script>

	<!-- Form Input - Count Max Lenghts -->
	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/forms/inputs/maxlength.min.js"></script>


	<!-- // Add the new slick-theme.css if you want the default styling -->
	<link rel="stylesheet" type="text/css" href="<?php echo $admin_base_url; ?>/assets/custom_css/slick.min.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $admin_base_url; ?>/assets/custom_css/slick-theme.min.css" />
	<link rel="stylesheet" type="text/css" href="<?php echo $admin_base_url; ?>/assets/assets_custom/css/dashboard-inline.css" />
	<script type="text/javascript" src="<?php echo $admin_base_url; ?>/assets/custom_js/slick.min.js"></script>

	<style>
		<?php
		// Generate dynamic color CSS variables from database settings
		$headerColors = array_merge([
			'background' => '#0f172a',
			'text' => '#ffffff',
			'accent' => '#38bdf8',
		], (array)getAdminHeaderColors());
		$sidebarColors = array_merge([
			'background' => '#1e293b',
			'text' => '#e2e8f0',
			'hover_background' => '#334155',
			'active_background' => '#475569',
			'active_text' => '#ffffff',
		], (array)getSidebarColors());
		$loginColors = (array)getLoginPageColors();
		?>:root {
			/* Admin Header Colors */
			--admin-header-bg: <?php echo $headerColors['background']; ?>;
			--admin-header-text: <?php echo $headerColors['text']; ?>;
			--admin-header-accent: <?php echo $headerColors['accent']; ?>;

			/* Sidebar Colors */
			--sidebar-bg: <?php echo $sidebarColors['background']; ?>;
			--sidebar-text: <?php echo $sidebarColors['text']; ?>;
			--sidebar-hover-bg: <?php echo $sidebarColors['hover_background']; ?>;
			--sidebar-active-bg: <?php echo $sidebarColors['active_background']; ?>;
			--sidebar-active-text: <?php echo $sidebarColors['active_text']; ?>;
		}

		.navbar-expand-lg .navbar-nav .nav-link {
			color: var(--admin-header-text) !important;
		}

		.navbar-expand-lg .navbar-nav .nav-link:hover {
			color: var(--admin-header-accent) !important;
		}

		.sidebar-main {
			background-color: var(--sidebar-bg) !important;
			color: var(--sidebar-text) !important;
		}

		.sidebar-main .nav-link {
			color: var(--sidebar-text) !important;
		}

		.sidebar-main .nav-link.active {
			background-color: var(--sidebar-active-bg) !important;
			color: var(--sidebar-active-text) !important;
		}

		.sidebar-main .nav-link:hover {
			background-color: var(--sidebar-hover-bg) !important;
		}

		.page-header-light {
			border-bottom: 1px solid #ccc;
		}

		.page-header-content {
			--bg-opacity: 0.1;
			/* background-color: rgba(var(--primary-rgb), var(--bg-opacity)); */
		}

		.dropdown-menu>.dropdown-submenu>.dropdown-item:after {
			content: none;
		}

		.nav .dropdown-toggle::after,
		.navbar-nav .dropdown-toggle::after,
		.dropdown-toggle::after {
			display: none !important;
			content: none !important;
			border: 0 !important;
		}
	</style>


	<link rel="stylesheet" href="<?php echo $admin_base_url; ?>/assets/custom_css/jquery-ui-timepicker-addon.min.css" />
	<link rel="stylesheet" href="<?php echo $admin_base_url; ?>/assets/custom_css/jquery-ui.min.css" />
	<script src="<?php echo $admin_base_url; ?>/assets/custom_js/jquery-ui-timepicker-addon.min.js"></script>
	<!-- Time Picker -->

	<!-- Note: Most datepicker configurations have been extracted to external files.
         See: assets_custom/js/datepicker-config.js -->

	<script>
		/*
        |--------------------------------------------------------------------------|
        |---------------------- BOOKING MODULE (PHP-DEPENDENT) --------------------|
        |--------------------------------------------------------------------------|
        */
		$(function() {
			// Dynamic input masks for requested dates (PHP loop - must stay inline)
			<?php for ($i = 1; $i <= 30; $i++) { ?>
				$("#requested_date<?php echo $i; ?>").inputmask();
				$('input[id$="requested_time<?php echo $i; ?>"]').inputmask("hh:mm", {
					placeholder: "hh:mm",
					insertMode: false,
					showMaskOnHover: false,
					hourFormat: "24"
				});
			<?php } ?>

			// Booking module specific time masks
			$('input[id$="arrival_time"]').inputmask("hh:mm", {
				placeholder: "hh:mm",
				insertMode: false,
				showMaskOnHover: false,
				hourFormat: "24"
			});

			$('input[id$="departure_time"]').inputmask("hh:mm", {
				placeholder: "hh:mm",
				insertMode: false,
				showMaskOnHover: false,
				hourFormat: "24"
			});

			// Booking module datepickers
			$("#date_arrival").datepicker({
				dateFormat: 'dd-mm-yy',
				changeMonth: true,
				changeYear: true
			});

			$("#date_departure").datepicker({
				dateFormat: 'dd-mm-yy',
				changeMonth: true,
				changeYear: true
			});

			$("#dob").datepicker({
				dateFormat: 'dd-mm-yy',
				changeMonth: true,
				changeYear: true,
				yearRange: '1950:2005'
			});

			$("#date_of_joining").datepicker({
				dateFormat: 'dd-mm-yy',
				changeMonth: true,
				changeYear: true,
				yearRange: '2020:2035'
			});
		});
	</script>


	<!-- ========== EXTRACTED JAVASCRIPT FILES ========== -->
	<!-- Datepicker configurations for all modules -->
	<script src="<?php echo $admin_base_url; ?>/assets_custom/js/datepicker-config.js"></script>

	<!-- Input mask configurations -->
	<script src="<?php echo $admin_base_url; ?>/assets_custom/js/input-masks.js"></script>

	<!-- Keyboard navigation and shortcuts -->
	<script src="<?php echo $admin_base_url; ?>/assets_custom/js/keyboard-shortcuts.js"></script>

	<!-- Form validation with Bootstrap -->
	<script src="<?php echo $admin_base_url; ?>/assets_custom/js/form-validation.js"></script>
	<!-- ================================================= -->

	<!-- Theme JS files -->
	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/visualization/d3/d3.min.js"></script>
	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/visualization/d3/d3_tooltip.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

	<!-- MY Custom JS files -->
	<script src="<?php echo $admin_base_url; ?>/assets/assets_custom/js/app.js"></script>


	<script src="<?php echo $admin_base_url; ?>/assets/js/vendor/forms/selects/select2.min.js"></script>
	<!-- /theme JS files -->


	<script src="<?php echo $admin_base_url; ?>/assets/custom_js/ajax.js?v=<?php echo @filemtime(__DIR__ . '/../assets/custom_js/ajax.js'); ?>"></script>
	<script src="<?php echo $admin_base_url; ?>/assets/custom_js/site.js"></script>

	<style>
		.navbar {
			--navbar-padding-y: 2px;
		}

		.navbar-dark {
			/* background-color: #182A3E; */
			/* background-color: #21263c; */
			background-color: #21263c;
			/* background-color: #F9AD1B; */
			/* background: rgb(187, 25, 0);
            background: linear-gradient(90deg, rgba(187, 25, 0, 1) 0%, rgba(253, 111, 1, 1) 100%); */

			/* background: linear-gradient(90deg, rgba(255, 95, 109, 1), rgba(255, 195, 113, 1));
            -webkit-background: linear-gradient(90deg, rgba(255, 95, 109, 1), rgba(255, 195, 113, 1));
            -moz-background: linear-gradient(90deg, rgba(255, 95, 109, 1), rgba(255, 195, 113, 1)); */

			/* background: rgb(131, 58, 180);
            background: linear-gradient(90deg, rgba(131, 58, 180, 1) 0%, rgba(253, 29, 29, 1) 50%, rgba(252, 176, 69, 1) 100%); */
		}

		.page-title {
			padding: 20px 32px 15px 0 !important;
		}

		.sidebar {
			/* width: 15.75rem; */
			/* width: 220px; */
			text-decoration: none;
		}

		/* .sidebar.nav-link:hover {
            font-weight: 100;
            color: #fff !important;
        } */

		/* .nav-sidebar .nav-link {
            height: 2.5em;
            font-weight: 400;
            padding: 0.4em 1em 1em 1em;
            font-size: 1em;
        } */

		/* More specific version */
		.nav-sidebar .nav-item .nav-link {
			font-weight: 400;
			padding-top: 0.3em;
			padding-bottom: 0.3em;
		}

		.nav-sidebar .nav-item .nav-link.active {
			background-color: #408dfb;
			color: #ffffff;
			/* font-weight: 400; */
		}


		/* .nav-sidebar .nav-group-sub .nav-link {
            padding: 0.4em 0 0 5m;
        } */


		/* colorful sidebar
        .nav-sidebar .nav-item a {
            color: #ccc;
            font-weight: 300;
        }

        .nav-sidebar .nav-item-open>.nav-link:not(.disabled):not(:active),
        .nav-sidebar>.nav-item-expanded>.nav-link:not(:active) .nav-sidebar .nav-item a:hover {
            color: #fff;
            background-color: #122F74;
            font-weight: 300;
        }

        .nav-sidebar .nav-item a:hover {
            background-color: #122F74;
        }

        .nav-item-open .nav-item-open {
            color: #fff;
            background-color: #0F2A69;
            font-weight: 300;
        } */

		.ph-star {
			animation: blinkSlow 10s infinite ease-in-out;
		}

		.email-quickbar {
			background: #f7f9fc;
			border-bottom: 1px solid #dbe3ef;
			padding: 8px 12px;
		}

		.email-quickbar-inner {
			display: flex;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}

		.email-quickbar-title {
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: .06em;
			font-weight: 700;
			color: #55657f;
			margin-right: 6px;
		}

		.email-quickbar-link {
			display: inline-flex;
			align-items: center;
			gap: 5px;
			padding: 5px 10px;
			font-size: 12px;
			text-decoration: none;
			border: 1px solid #dbe3ef;
			border-radius: 8px;
			background: #fff;
			color: #33435d;
		}

		.email-quickbar-link:hover {
			background: #edf3ff;
			color: #203d6f;
		}

		.email-quickbar-link.active {
			background: #2e6ddf;
			border-color: #2e6ddf;
			color: #fff;
		}

		/* Systems mega menu */
		#importantSystemsMenuLeft.systems-mega-menu {
			width: min(94vw, 1080px);
			min-width: 780px;
			max-width: 1080px;
			padding: 0;
			overflow: hidden;
			z-index: 1080;
		}

		#quickAccessMenu.admin-mega-menu {
			width: min(92vw, 460px);
			min-width: 360px;
			max-width: 460px;
			padding: 0;
			overflow: hidden;
			z-index: 1080;
		}

		#importantSystemsMenuLeft .systems-mega-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
			max-height: 70vh;
			overflow: auto;
		}

		#quickAccessMenu .systems-mega-grid {
			display: grid;
			grid-template-columns: 1fr;
			max-height: 70vh;
			overflow: auto;
		}

		#importantSystemsMenuLeft .systems-col {
			border-right: 1px solid #eef2f7;
			border-bottom: 1px solid #eef2f7;
		}

		#importantSystemsMenuLeft .systems-col:last-child {
			border-right: 0;
		}

		@media (max-width: 991.98px) {
			#importantSystemsMenuLeft.systems-mega-menu {
				width: 94vw;
				min-width: 320px;
			}

			#quickAccessMenu.admin-mega-menu {
				width: 94vw;
				min-width: 300px;
			}

			#importantSystemsMenuLeft .systems-mega-grid {
				grid-template-columns: 1fr;
				max-height: 60vh;
			}

			#quickAccessMenu .systems-mega-grid {
				grid-template-columns: 1fr;
				max-height: 60vh;
			}

			#importantSystemsMenuLeft .systems-col,
			#importantSystemsMenuLeft .systems-col:last-child {
				border-right: 0;
			}

			#quickAccessMenu .systems-col,
			#quickAccessMenu .systems-col:nth-child(3n) {
				border-right: 0;
			}
		}

		/* Multi-column for single-section mega menu (e.g. Accounts role) */
		.mega-cols-4 {
			column-count: 4;
			column-gap: 1.5rem;
		}
		.mega-cols-4 .list-group-item {
			break-inside: avoid;
			-webkit-column-break-inside: avoid;
		}

		@keyframes blinkSlow {

			0%,
			100% {
				opacity: 1;
			}

			50% {
				opacity: 0.3;
			}
		}
	</style>

	<!-- Custom Light Box Images Asset -->
	<link href="<?php echo $admin_base_url; ?>/assets/assets_custom/css/lightbox.css" rel="stylesheet" type="text/css">




	<!--
    |--------------------------------------------------------------------------|
    |-------------------- ON PRESS ENTER MOVE TO NEXT -------------------------|
    |--------------------------------------------------------------------------|
    -->
	<?php if (preg_match('/pricing_matrix.php/', $page_url)) { ?>
		<script>
			// register jQuery extension
			jQuery.extend(jQuery.expr[':'], {
				focusable: function(el, index, selector) {
					return $(el).is('a, button, :input, [tabindex]');
				}
			});
			$(document).on('keypress', 'input,select', function(e) {
				if (e.which == 13) {
					e.preventDefault();
					// Get all focusable elements on the page
					var $canfocus = $(':focusable');
					var index = $canfocus.index(this) + 1;
					if (index >= $canfocus.length) index = 0;
					$canfocus.eq(index).focus();
				}
			});
		</script>
	<?php } ?>
	<!-- https://copyprogramming.com/howto/how-to-move-focus-on-next-field-when-enter-is-pressed -->






	<?php if (preg_match('/calendar.php/', $page_url)) { ?>

		<link rel="stylesheet" href="https://pro.fontawesome.com/releases/v5.10.0/css/all.css" integrity="sha384-AYmEC3Yw5cVb3ZcuHtOA93w35dYTsvhLPVnYs9eStHfGJvOvKxVfELGroGkvsg+p" crossorigin="anonymous" />
		<!-- <link rel="stylesheet" href="<?php echo $admin_base_url; ?>/assets/custom_css/bootstrap.min.css"> -->
		<link rel="stylesheet" href="<?php echo $admin_base_url; ?>/fullcalendar/lib/main.min.css">
		<!-- <script src="<?php echo $admin_base_url; ?>/assets/custom_js/jquery-3.6.0.min.js"></script> -->
		<script src="<?php echo $admin_base_url; ?>/assets/custom_js/bootstrap.min.js"></script>
		<script src="<?php echo $admin_base_url; ?>/fullcalendar/lib/main.min.js"></script>
		<style>
			/* :root {
            --bs-success-rgb: 71, 222, 152 !important;
        } */

			/* html,
        body {
            height: 100%;
            width: 100%;
            font-family: 'Roboto', Apple Chancery, cursive;
        } */

			/* .btn-info.text-light:hover,
        .btn-info.text-light:focus {
            background: #000;
        } */

			table,
			tbody,
			td,
			tfoot,
			th,
			thead,
			tr {
				border-color: #ededed !important;
				border-style: solid;
				border-width: 1px !important;
			}
		</style>
	<?php } // if
	?>


	<?php if (in_array($current_page, ['dashboard_shipping.php', 'dashboard_crm.php', 'dashboard_hr.php'], true)) { ?>
		<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
	<?php } ?>


	<style>
		/* Fix Caret Dropdown spacing/white box issues on Navbar dropdowns */
		.navbar .dropdown-toggle::after,
		.navbar-nav .dropdown-toggle::after,
		.main-header .dropdown-toggle::after {
			background: transparent !important;
			background-color: transparent !important;
			width: 0 !important;
			height: 0 !important;
			display: inline-block !important;
			line-height: 0 !important;
			border-top: 0.3em solid !important;
			border-right: 0.3em solid transparent !important;
			border-left: 0.3em solid transparent !important;
			border-bottom: 0 !important;
		}
	</style>
</head>

<!-- <body style="background-color: #E8EAED;"> -->

<?php $dashboardBodyClass = trim((string)($dashboardBodyClass ?? '')); ?>

<body class="hai-dashboard responsive-bootstrap-redesign<?php echo $dashboardBodyClass !== '' ? ' ' . htmlspecialchars($dashboardBodyClass, ENT_QUOTES) : ''; ?>" data-session-user-id="<?php echo $session_user_id; ?>" data-session-role-id="<?php echo $session_role_id; ?>">

	<!-- Main header wrapper -->
	<header class="main-header">
		<!-- Main navbar -->
		<nav class="navbar navbar-dark navbar-expand-lg navbar-static border-bottom border-bottom-white border-opacity-10" role="navigation" aria-label="Main navigation">
		<!-- <div class="navbar navbar-expand-lg navbar-static border-bottom border-bottom-black border-opacity-10" style="background-color: #F0F0F0;"> -->
		<div class="container-fluid">
			<div class="d-flex d-lg-none me-2">
				<button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-mobile-main-toggle rounded-pill" aria-label="Toggle sidebar">
					<i class="ph-list" aria-hidden="true"></i>
				</button>
			</div>

			<div class="navbar-brand flex-1 flex-lg-0">
				<a href="index.php" class="d-inline-flex align-items-center text-white fw-semibold">
					<!-- <img src="assets/images/logo_text_light.svg" class="d-none d-sm-inline-block h-16px ms-3" alt=""> -->

					<?php
					$software_name        = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="software_name"');
					echo s__($software_name);
					?>
				</a>
			</div>

			<ul class="nav flex-row align-items-center">

				<!-- Sidebar Toggle -->
				<li class="nav-item">
					<button type="button" class="btn btn-flat-white btn-icon btn-sm rounded-pill border-transparent sidebar-control sidebar-main-resize d-none d-lg-inline-flex" aria-label="Toggle sidebar">
						<i class="ph-arrows-left-right" aria-hidden="true"></i>
					</button>
				</li>

				<!-- <li class="nav-item mt-2 ms-lg-2">
					<a href="sitemaps.php" class="text-white">
						&nbsp; Sitemaps
					</a>
				</li> -->

				<!-- <li class="nav-item mt-2 ms-lg-2">
					<a href="seo_health_check.php" class="text-white">
						&nbsp; SEO
					</a>
				</li> -->


				<?php if (!empty($systemsMegaSections) && Roles::hasFullAccess($session_role_id)): ?>
					<li class="nav-item nav-item-dropdown-lg dropdown ms-lg-2">
						<a href="#" class="navbar-nav-link rounded-pill px-2 py-1 d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false" aria-controls="importantSystemsMenuLeft" title="Systems">
							<i class="ph-diamonds-four me-1" aria-hidden="true"></i>
							<span class="d-none d-xl-inline">Systems</span>
						</a>
						<div class="dropdown-menu dropdown-menu-start systems-mega-menu" id="importantSystemsMenuLeft">
							<div class="d-flex align-items-center justify-content-between border-bottom p-3">
								<div>
									<h6 class="mb-0">Systems Navigation</h6>
									<small class="text-muted">All links grouped under their main systems.</small>
								</div>
								<a href="setup.php" class="btn btn-sm btn-light">Tools</a>
							</div>
							<div class="systems-mega-grid">
								<?php foreach ($systemsMegaSections as $section):
									$hasGroups = false;
									foreach ($section['links'] as $link) {
										if (isset($link['group'])) { $hasGroups = true; break; }
									}
									if ($hasGroups):
										$sectionGroups = [];
										$currentGroup = [];
										foreach ($section['links'] as $link) {
											if (isset($link['group'])) {
												if (!empty($currentGroup)) { $sectionGroups[] = $currentGroup; }
												$currentGroup = ['header' => $link['group'], 'links' => []];
											} else {
												$currentGroup['links'][] = $link;
											}
										}
										if (!empty($currentGroup)) { $sectionGroups[] = $currentGroup; }
										foreach ($sectionGroups as $group):
									?>
									<div class="systems-col">
										<div class="p-3 h-100">
											<div class="d-flex align-items-center gap-2 mb-2">
												<i class="<?php echo htmlspecialchars($section['icon'], ENT_QUOTES); ?> fs-5 text-primary"></i>
												<span class="fw-semibold"><?php echo htmlspecialchars($group['header'], ENT_QUOTES); ?></span>
											</div>
											<div class="list-group list-group-flush">
												<?php foreach ($group['links'] as $link): ?>
													<?php if (empty($link['perm']) || function_exists('granted_') && granted_('view', $link['perm'])): ?>
														<?php $isSystemMenuActive = $current_page === basename($link['href']); ?>
														<a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES); ?>" class="list-group-item list-group-item-action border-0 px-0 py-1<?php echo $isSystemMenuActive ? ' active' : ''; ?>">
															<i class="<?php echo htmlspecialchars($link['icon'], ENT_QUOTES); ?> me-2"></i>
															<?php echo htmlspecialchars($link['label'], ENT_QUOTES); ?>
														</a>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
									<?php endforeach; else: ?>
									<div class="systems-col">
										<div class="p-3 h-100">
											<div class="d-flex align-items-center gap-2 mb-2">
												<i class="<?php echo htmlspecialchars($section['icon'], ENT_QUOTES); ?> fs-5 text-primary"></i>
												<a href="<?php echo htmlspecialchars($section['dashboard'] ?? '#', ENT_QUOTES); ?>" class="fw-semibold text-dark text-decoration-none"><?php echo htmlspecialchars($section['title'], ENT_QUOTES); ?></a>
											</div>
											<div class="list-group list-group-flush<?php echo (int)$session_role_id === 5 ? ' mega-cols-4' : ''; ?>">
												<?php foreach ($section['links'] as $link): ?>
													<?php if (isset($link['group'])): ?>
														<div class="list-group-item border-0 px-0 pt-2 pb-0 fw-semibold text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px;"><?php echo htmlspecialchars($link['group'], ENT_QUOTES); ?></div>
													<?php elseif (empty($link['perm']) || function_exists('granted_') && granted_('view', $link['perm'])): ?>
														<?php $isSystemMenuActive = $current_page === basename($link['href']); ?>
														<a href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES); ?>" class="list-group-item list-group-item-action border-0 px-0 py-1<?php echo $isSystemMenuActive ? ' active' : ''; ?>">
															<i class="<?php echo htmlspecialchars($link['icon'], ENT_QUOTES); ?> me-2"></i>
															<?php echo htmlspecialchars($link['label'], ENT_QUOTES); ?>
														</a>
													<?php endif; ?>
												<?php endforeach; ?>
											</div>
										</div>
									</div>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					</li>
				<?php endif; ?>

			</ul>

			<div class="navbar-collapse justify-content-center flex-lg-1 order-2 order-lg-1 collapse" id="navbar_search">
				<div class="navbar-search flex-fill position-relative mt-2 mt-lg-0 mx-lg-3"></div>
			</div>


			<ul class="nav flex-row justify-content-end align-items-center order-1 order-lg-2">

				<?php if (Roles::hasFullAccess($session_role_id)) { ?>

					<?php if (Roles::isSuperAdmin($session_role_id)): ?>
						<li class="nav-item nav-item-dropdown-lg dropdown ms-lg-2">
							<a href="#" class="navbar-nav-link rounded-pill px-2 py-1 d-flex align-items-center" data-bs-toggle="dropdown" aria-expanded="false" aria-controls="systemAvailabilityMenu" title="System Availability">
								<i class="ph-toggle-left me-1" aria-hidden="true"></i>
								<span class="d-none d-xl-inline">Availability</span>
							</a>
							<div class="dropdown-menu dropdown-menu-end p-2" id="systemAvailabilityMenu" style="min-width: 280px;">
								<div class="px-2 pt-1 pb-2 border-bottom">
									<div class="fw-semibold">System Availability</div>
									<small class="text-muted">Control what appears to registered users.</small>
								</div>
								<?php foreach ($systemSuites as $suiteKey => $suite): ?>
									<form method="post" action="" class="d-flex align-items-center justify-content-between gap-2 px-2 py-2 border-bottom">
										<?php echo csrf_field(); ?>
										<input type="hidden" name="action" value="toggle_system_suite">
										<input type="hidden" name="target_system" value="<?php echo htmlspecialchars($suiteKey, ENT_QUOTES); ?>">
										<div class="d-flex align-items-center gap-2">
											<i class="<?php echo htmlspecialchars((string)$suite['icon'], ENT_QUOTES); ?>"></i>
											<span><?php echo htmlspecialchars((string)$suite['label'], ENT_QUOTES); ?></span>
										</div>
										<select name="enabled" class="form-select form-select-sm" style="width: 96px;" onchange="this.form.submit()">
											<option value="1" <?php echo !empty($suite['enabled']) ? 'selected' : ''; ?>>Enabled</option>
											<option value="0" <?php echo empty($suite['enabled']) ? 'selected' : ''; ?>>Disabled</option>
										</select>
									</form>
								<?php endforeach; ?>
								<div class="px-2 pt-2 pb-1">
									<small class="text-muted">Changes apply to sidebar and quick navigation.</small>
								</div>
							</div>
						</li>
					<?php endif; ?>




					<?php if (Roles::isSystemAdmin($session_role_id)): ?>
						<li class="nav-item ms-lg-2 position-relative">
							<a href="view_backend_error_logs.php" class="navbar-nav-link rounded-pill position-relative d-inline-flex align-items-center" title="Error Logs">
								<i class="ph-warning-circle me-1"></i>Admin Logs
								<?php if ($headerAlertsCount > 0): ?>
									<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px;min-width:18px;line-height:1.2;">
										<?php echo (int)$headerAlertsCount; ?>
									</span>
								<?php endif; ?>
							</a>
						</li>
					<?php endif; ?>


					<li class="nav-item dropdown ms-lg-2">
						<a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="System Settings">
							<i class="ph-gear-six"></i>
						</a>
						<ul class="dropdown-menu dropdown-menu-end">
							<li>
								<a href="global_settings.php" class="dropdown-item<?php echo ($current_page === 'global_settings.php') ? ' active' : ''; ?>">
									<i class="ph-sliders-horizontal me-2"></i> Global Settings
								</a>
							</li>
							<li>
								<a href="setup.php" class="dropdown-item<?php echo ($current_page === 'setup.php') ? ' active' : ''; ?>">
									<i class="ph-wrench me-2"></i> System Tools
								</a>
							</li>
							<li>
								<a href="run_coverage_sweep.php" class="dropdown-item<?php echo ($current_page === 'run_coverage_sweep.php') ? ' active' : ''; ?>">
									<i class="ph-rocket-launch me-2"></i> QA Test
								</a>
							</li>
							<li>
								<a href="listing_email_providers.php" class="dropdown-item<?php echo ($current_page === 'listing_email_providers.php') ? ' active' : ''; ?>">
									<i class="ph-envelope-simple me-2"></i> Email Providers
								</a>
							</li>
							<?php if (hasModuleAccess('email_queue')): ?>
							<li>
								<a href="listing_email_queue.php" class="dropdown-item<?php echo ($current_page === 'listing_email_queue.php') ? ' active' : ''; ?>">
									<i class="ph-envelope-simple-open me-2"></i> Email Queue
								</a>
							</li>
							<?php endif; ?>
							<li>
								<a href="listing_cron_jobs.php" class="dropdown-item<?php echo ($current_page === 'listing_cron_jobs.php') ? ' active' : ''; ?>">
									<i class="ph-clock-clockwise me-2"></i> Cron Jobs
								</a>
							</li>
						</ul>
					</li>

				<?php } ?>

				<li class="nav-item ms-lg-2">
					<a href="email_history.php" class="navbar-nav-link navbar-nav-link-icon rounded-pill position-relative d-inline-flex align-items-center" title="Email History">
						<i class="ph-envelope-simple"></i>
					</a>
				</li>

				<!-- User Profile Divider -->
				<div class="vr flex-shrink-0 my-1 mx-3"></div>




				<?php if (Roles::currentUserHasFullAccess()): ?>
				<li class="nav-item ms-lg-2 position-relative">
					<a href="#" class="navbar-nav-link rounded-pill d-inline-flex align-items-center" data-bs-toggle="offcanvas" data-bs-target="#organizations" title="Organizations">
						<i class="ph-buildings me-1" aria-hidden="true"></i>
						<span class="d-none d-xl-inline"><?php echo htmlspecialchars($activeOrganizationName !== '' ? $activeOrganizationName : 'Organizations', ENT_QUOTES, 'UTF-8'); ?></span>
					</a>
				</li>
				<?php endif; ?>

				<?php if (Roles::isSuperAdmin($session_role_id)): ?>
					<li class="nav-item ms-lg-2 position-relative">
						<a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill position-relative" data-bs-toggle="offcanvas" data-bs-target="#notifications" title="Alerts">
							<i class="ph-bell"></i>
							<?php if ($headerAlertsCount > 0): ?>
								<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:10px;min-width:18px;line-height:1.2;">
									<?php echo (int)$headerAlertsCount; ?>
								</span>
							<?php endif; ?>
						</a>
					</li>
				<?php endif; ?>

				<li class="nav-item dropdown ms-lg-2">
					<a href="#" class="navbar-nav-link d-flex align-items-center rounded-pill p-1 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="Account">
						<?php
						// -- Profile Photo --
						$pp = getTableAttr('photo', DB::USERS, $session_user_id);

						if (!empty($pp) && file_exists('../uploads/users/thumbs/' . $pp)) {
							$pp = $base_url . '/uploads/users/thumbs/' . $pp;
						} else {
							$pp = $base_url . '/images/no-image-profile-photo.png';
						}
						?>

						<div class="status-indicator-container">
							<img src="<?php echo $pp; ?>" alt="" class="w-32px h-32px rounded-pill">
							<span class="status-indicator bg-success"></span>
						</div>
					</a>
					<div class="dropdown-menu dropdown-menu-end" style="min-width: 260px;">
						<div class="px-3 py-2 border-bottom d-flex align-items-center gap-3">
							<img src="<?php echo $pp; ?>" alt="Profile" class="rounded-pill" style="width:40px;height:40px;object-fit:cover;">
							<div>
								<div class="fw-semibold text-truncate" style="max-width: 170px;"><?php echo htmlspecialchars($session_full_name ?? 'User', ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="small text-muted">Admin Account</div>
							</div>
						</div>

						<?php if (Roles::isSystemAdmin($session_role_id)): ?>
							<div class="px-3 py-2 border-bottom">
								<div class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 6px;">Admin Options</div>
								<div class="d-grid gap-1">
									<a href="listing_modules.php" class="dropdown-item px-2 py-1 rounded">
										<i class="ph-list-numbers me-2"></i> Modules
									</a>
									<a href="global_settings.php" class="dropdown-item px-2 py-1 rounded">
										<i class="ph-sliders-horizontal me-2"></i> Global Settings
									</a>
									<a href="setup.php" class="dropdown-item px-2 py-1 rounded">
										<i class="ph-wrench me-2"></i> System Tools
									</a>
								</div>
							</div>
						<?php endif; ?>

						<?php if (Roles::hasFullAccess($session_role_id)): ?>
						<div class="px-3 py-2 border-bottom">
							<div class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 6px;">User Management</div>
							<div class="d-grid gap-1">
								<a href="listing_users.php" class="dropdown-item px-2 py-1 rounded">
									<i class="ph-users me-2"></i> Users
								</a>
								<a href="listing_roles.php" class="dropdown-item px-2 py-1 rounded">
									<i class="ph-user-check me-2"></i> Roles & Permissions
								</a>
							</div>
						</div>
						<?php endif; ?>

						<div class="px-3 py-2 border-bottom">
							<div class="text-muted text-uppercase" style="font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; margin-bottom: 6px;">Profile & Security</div>
							<div class="d-grid gap-1">
								<a href="profile.php" class="dropdown-item px-2 py-1 rounded">
									<i class="ph-user-circle me-2"></i> My Profile
								</a>
								<a href="change_password.php" class="dropdown-item px-2 py-1 rounded">
									<i class="ph-key me-2"></i> Change Password
								</a>
							</div>
						</div>

						<div class="p-2">
							<a href="logout.php" class="dropdown-item px-3 py-2 text-danger rounded d-flex align-items-center">
								<i class="ph-sign-out me-2"></i> Logout
							</a>
						</div>
					</div>
				</li>

				<li class="nav-item ms-lg-2">
					<a href="#" class="navbar-nav-link navbar-nav-link-icon rounded-pill" data-bs-toggle="offcanvas" data-bs-target="#systemsPanel" title="Systems">
						<i class="ph-squares-four" aria-hidden="true"></i>
					</a>
				</li>

			</ul>
		</div>
	</nav>
	<!-- /main navbar -->
	</header>


	<!-- Page content -->






	<!-- Page content -->
	<main class="page-content" role="main">

		<?php
		include_once('admin_elements/sidebar.php');
		?>
