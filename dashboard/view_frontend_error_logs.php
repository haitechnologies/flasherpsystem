<?php

use App\Security\Roles;
/*
|==================================================================================
| FRONTEND ERROR LOG VIEWER - Admin Dashboard
|==================================================================================
| Displays and analyzes FRONTEND_ERROR_LOG.txt from PUBLIC-FACING WEBSITE ONLY
| 
| IMPORTANT: This log contains ONLY frontend/public website errors.
| Dashboard/backend errors are automatically filtered out and should be 
| viewed in CONSOLIDATED_ERROR_LOG.txt instead.
|
| Access: System and Super Admins only
| Features: Pagination, Filtering, Search, Statistics, Export
|==================================================================================
*/

// Buffer output so JSON export can return a clean payload without admin HTML.
ob_start();

// Include admin setup
include('admin_elements/admin_header.php');

// SECURITY CHECK - System and Super Admins only
if (!Roles::hasFullAccess($session_role_id)) {
    echo "<div class='alert alert-danger text-center mt-5'><h3>Access Denied</h3><p>This page is restricted to System Administrators only.</p></div>";
    include('admin_elements/admin_footer.php');
    exit;
}

// Load FrontendErrorLogger class if not already loaded
$logger_class_exists = class_exists(\App\Frontend\FrontendErrorLogger::class);
if (!$logger_class_exists && file_exists(__DIR__ . '/../classes/frontend/ErrorLogger.php')) {
    // Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/frontend/ErrorLogger.php';
    $logger_class_exists = class_exists(\App\Frontend\FrontendErrorLogger::class);
}

// Log file path (frontend)
$log_file = __DIR__ . '/../logs/FRONTEND_ERROR_LOG.txt';
$items_per_page = 25;

// Ensure logs directory exists
$logs_dir = __DIR__ . '/../logs';
if (!is_dir($logs_dir)) {
    mkdir($logs_dir, 0755, true);
}

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_logs') {
    if (file_exists($log_file)) {
        file_put_contents($log_file, '');
        header('Location: view_frontend_error_logs.php?cleared=1');
        exit;
    }
}

$cleared_message = '';
if (isset($_GET['cleared'])) {
    $cleared_message = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ph-check-circle"></i> <strong>Success!</strong> Frontend error logs have been cleared. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Initialize variables
$filters = [
    'severity' => $_GET['severity'] ?? '',
    'file' => $_GET['file'] ?? '',
    'search' => $_GET['search'] ?? '',
    'error_type' => $_GET['error_type'] ?? '', // New: Filter by error type (404, 403, 500, etc.)
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'hide_synthetic' => $_GET['hide_synthetic'] ?? '1'
];

$page = max(1, intval($_GET['page'] ?? 1));
$export = $_GET['export'] ?? '';

function is_synthetic_entry($entry) {
    $message = strtolower((string)($entry['message'] ?? ''));
    $context = strtolower((string)($entry['context'] ?? ''));
    $file = strtolower((string)($entry['file'] ?? ''));
    $requestedUrl = strtolower((string)extract_context_value($entry, 'requested_url'));
    $referrer = strtolower((string)extract_context_value($entry, 'referrer'));
    $userAgent = strtolower((string)extract_context_value($entry, 'user_agent'));
    $method = strtoupper((string)extract_context_value($entry, 'method'));

    // Common crawler/synthetic traffic signatures that flood 404 logs.
    $syntheticUserAgents = [
        'deadlinkchecker',
        'google-adstxt',
        'mediapartners-google',
        'googlebot',
        'bingbot',
        'baiduspider',
        'applebot',
        'petalbot',
        'facebookexternalhit',
        'linkedinbot',
        'photon/1.0',
        'claudebot',
        'chatgpt/',
        'python-httpx/',
        'python-requests/',
        'sogou web spider',
        'tiktokspider',
    ];

    foreach ($syntheticUserAgents as $ua) {
        if (strpos($message, $ua) !== false || strpos($context, $ua) !== false) {
            return true;
        }
    }

    if (has_bot_like_user_agent($userAgent)) {
        return true;
    }

    $syntheticMarkers = [
        'flasherpsystem-manualtester',
        'curl/',
        '/this-page-does-not-exist-404-test',
    ];

    foreach ($syntheticMarkers as $marker) {
        if (strpos($message, $marker) !== false || strpos($context, $marker) !== false || strpos($requestedUrl, $marker) !== false || strpos($userAgent, $marker) !== false) {
            return true;
        }
    }

    // Treat repetitive legacy-content misses from bot traffic as synthetic noise.
    if (is_legacy_404_noise($message, $requestedUrl, $referrer, $userAgent, $method)) {
        return true;
    }

    if (is_static_asset_404_noise($message, $requestedUrl, $referrer, $userAgent)) {
        return true;
    }

    if (is_malformed_404_noise($message, $requestedUrl, $referrer, $userAgent)) {
        return true;
    }

    return false;
}

function extract_404_path($message, $requestedUrl) {
    $path = trim((string)$requestedUrl);

    if ($path === '' && preg_match('#404\s*-\s*page\s+not\s+found:\s*([^\s]+)#i', $message, $matches)) {
        $path = trim((string)($matches[1] ?? ''));
    }

    if ($path === '') {
        return '';
    }

    $parsedPath = parse_url($path, PHP_URL_PATH);
    if (is_string($parsedPath) && $parsedPath !== '') {
        return strtolower($parsedPath);
    }

    return strtolower($path);
}

function extract_context_value($entry, $key) {
    static $decodedContextCache = [];

    if (!is_array($entry)) {
        return '';
    }

    if (isset($entry[$key]) && $entry[$key] !== null && $entry[$key] !== '') {
        return (string)$entry[$key];
    }

    $contextRaw = (string)($entry['context'] ?? '');
    if ($contextRaw === '') {
        return '';
    }

    $cacheKey = sha1($contextRaw);
    if (!array_key_exists($cacheKey, $decodedContextCache)) {
        $decoded = json_decode($contextRaw, true);
        $decodedContextCache[$cacheKey] = is_array($decoded) ? $decoded : [];
    }

    return isset($decodedContextCache[$cacheKey][$key]) ? (string)$decodedContextCache[$cacheKey][$key] : '';
}

function has_bot_like_user_agent($userAgent) {
    if ($userAgent === '') {
        return false;
    }

    $markers = [
        'bot',
        'spider',
        'crawler',
        'feedburner',
        'slurp',
        'bingpreview',
        'yandex',
        'ahrefs',
        'semrush',
        'mj12bot',
        'dotbot',
    ];

    foreach ($markers as $marker) {
        if (strpos($userAgent, $marker) !== false) {
            return true;
        }
    }

    return false;
}

function is_legacy_404_noise($message, $requestedUrl, $referrer, $userAgent, $method) {
    if (strpos($message, '404 - page not found:') === false) {
        return false;
    }

    $path = extract_404_path($message, $requestedUrl);

    if ($path === '') {
        return false;
    }

    $legacyPrefixes = [
        '/article/',
        '/amp/article/',
        '/alphabets-categories/',
        '/alphabets-companies/',
        '/uploads/articles/',
    ];

    $isLegacyPath = false;
    foreach ($legacyPrefixes as $prefix) {
        if (strpos($path, $prefix) === 0) {
            $isLegacyPath = true;
            break;
        }
    }

    if (!$isLegacyPath) {
        return false;
    }

    $directReferrer = ($referrer === '' || $referrer === 'direct');
    $botAgent = has_bot_like_user_agent($userAgent);
    $nonGet = ($method !== '' && $method !== 'GET');

    return ($directReferrer && $botAgent) || $nonGet;
}

function is_static_asset_404_noise($message, $requestedUrl, $referrer, $userAgent) {
    if (strpos($message, '404 - page not found:') === false) {
        return false;
    }

    $path = extract_404_path($message, $requestedUrl);
    if ($path === '') {
        return false;
    }

    $assetPrefixes = [
        '/images/',
        '/assets/images/',
        '/uploads/',
        '/assets/',
    ];

    $isAssetPath = false;
    foreach ($assetPrefixes as $prefix) {
        if (strpos($path, $prefix) === 0) {
            $isAssetPath = true;
            break;
        }
    }

    if (!$isAssetPath) {
        return false;
    }

    $assetExtensions = ['.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.css', '.js'];
    $hasAssetExtension = false;
    foreach ($assetExtensions as $extension) {
        if (substr($path, -strlen($extension)) === $extension) {
            $hasAssetExtension = true;
            break;
        }
    }

    if (!$hasAssetExtension) {
        return false;
    }

    return $referrer === '' || $referrer === 'direct' || has_bot_like_user_agent($userAgent);
}

function is_malformed_404_noise($message, $requestedUrl, $referrer, $userAgent) {
    if (strpos($message, '404 - page not found:') === false) {
        return false;
    }

    $path = extract_404_path($message, $requestedUrl);
    if ($path === '') {
        return false;
    }

    $hasMalformedMarkers =
        strpos($path, '%5b') !== false ||
        strpos($path, '%5d') !== false ||
        strpos($path, '+e') !== false ||
        preg_match('/\+[a-z0-9%\[\]\-_]+\+/i', $path);

    if (!$hasMalformedMarkers) {
        return false;
    }

    return $referrer === '' || $referrer === 'direct' || has_bot_like_user_agent($userAgent);
}

function dedupe_entries(array $entries) {
    $seen = [];
    $deduped = [];

    foreach ($entries as $entry) {
        $key = sha1(
            (string)($entry['timestamp'] ?? '') . '|' .
            (string)($entry['severity'] ?? '') . '|' .
            (string)($entry['file'] ?? '') . '|' .
            (string)($entry['line'] ?? '') . '|' .
            (string)($entry['message'] ?? '') . '|' .
            (string)($entry['context'] ?? '')
        );

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $deduped[] = $entry;
    }

    return $deduped;
}

// Function to apply filters
function apply_filters($entries, $filters) {
    $filtered = $entries;
    
    // Severity filter
    if (!empty($filters['severity'])) {
        $filtered = array_filter($filtered, function($entry) use ($filters) {
            return $entry['severity'] === $filters['severity'];
        });
    }
    
    // File filter
    if (!empty($filters['file'])) {
        $filtered = array_filter($filtered, function($entry) use ($filters) {
            return stripos((string)($entry['file'] ?? ''), (string)$filters['file']) !== false;
        });
    }
    
    // Error type filter (404, 403, 500, etc.) - searches in message for HTTP error codes
    if (!empty($filters['error_type'])) {
        $filtered = array_filter($filtered, function($entry) use ($filters) {
            return stripos($entry['message'] ?? '', $filters['error_type']) !== false;
        });
    }
    
    // Search filter
    if (!empty($filters['search'])) {
        $filtered = array_filter($filtered, function($entry) use ($filters) {
            return (stripos($entry['message'] ?? '', $filters['search']) !== false ||
                    stripos($entry['context'] ?? '', $filters['search']) !== false);
        });
    }
    
    // Date range filter
    if (!empty($filters['date_from'])) {
        $date_from = strtotime($filters['date_from']);
        $filtered = array_filter($filtered, function($entry) use ($date_from) {
            return strtotime($entry['timestamp']) >= $date_from;
        });
    }
    
    if (!empty($filters['date_to'])) {
        $date_to = strtotime($filters['date_to'] . ' 23:59:59');
        $filtered = array_filter($filtered, function($entry) use ($date_to) {
            return strtotime($entry['timestamp']) <= $date_to;
        });
    }
    
    return array_values($filtered);
}

// Function to calculate statistics
function calculate_statistics($entries) {
    $stats = [
        'total' => count($entries),
        'by_severity' => [
            'ERROR' => 0,
            'WARNING' => 0,
            'NOTICE' => 0,
            'INFO' => 0,
            'DEBUG' => 0
        ],
        'by_file' => [],
        'by_error_type' => [],
        'latest_error' => null,
        'errors_404' => 0,
        'errors_403' => 0,
        'errors_500' => 0
    ];
    
    foreach ($entries as $entry) {
        // Count by severity
        $severity = $entry['severity'] ?? 'UNKNOWN';
        if (!isset($stats['by_severity'][$severity])) {
            $stats['by_severity'][$severity] = 0;
        }
        $stats['by_severity'][$severity]++;
        
        // Count by file
        $file = (string)($entry['file'] ?? 'unknown');
        if ($file === '' || $file === null) {
            $file = 'unknown';
        }
        $stats['by_file'][$file] = ($stats['by_file'][$file] ?? 0) + 1;
        
        // Count specific HTTP errors
        if (strpos($entry['message'] ?? '', '404') !== false) {
            $stats['errors_404']++;
            $stats['by_error_type']['404'] = ($stats['by_error_type']['404'] ?? 0) + 1;
        }
        if (strpos($entry['message'] ?? '', '403') !== false) {
            $stats['errors_403']++;
            $stats['by_error_type']['403'] = ($stats['by_error_type']['403'] ?? 0) + 1;
        }
        if (strpos($entry['message'] ?? '', '500') !== false) {
            $stats['errors_500']++;
            $stats['by_error_type']['500'] = ($stats['by_error_type']['500'] ?? 0) + 1;
        }
        
        // Get latest error
        if ($severity === 'ERROR' && $stats['latest_error'] === null) {
            $stats['latest_error'] = $entry;
        }
    }
    
    arsort($stats['by_file']);
    arsort($stats['by_severity']);
    arsort($stats['by_error_type']);
    
    return $stats;
}

function normalize_entries(array $entries) {
    $normalized = [];

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $severity = strtoupper(trim((string)($entry['severity'] ?? 'INFO')));
        if ($severity === '') {
            $severity = 'INFO';
        }

        $normalized[] = [
            'timestamp' => (string)($entry['timestamp'] ?? '-'),
            'severity' => $severity,
            'file' => (string)($entry['file'] ?? 'unknown'),
            'line' => (string)($entry['line'] ?? '0'),
            'message' => (string)($entry['message'] ?? ''),
            'context' => (string)($entry['context'] ?? ''),
        ];
    }

    return $normalized;
}

// Parse log file
$all_entries = [];
$log_file_missing = false;

if (!file_exists($log_file) || filesize($log_file) === 0) {
    $log_file_missing = true;
    $all_entries = [];
} elseif ($logger_class_exists && method_exists(\App\Frontend\FrontendErrorLogger::class, 'parseLogFile')) {
    $all_entries = \App\Frontend\FrontendErrorLogger::parseLogFile($log_file);
    if (!is_array($all_entries)) {
        $all_entries = [];
    }
} else {
    // Fallback manual parsing if class not available
    $log_content = file_get_contents($log_file);
    $log_lines = explode("\n", $log_content);
    $all_entries = [];
    
    foreach ($log_lines as $line) {
        if (empty(trim($line))) continue;
        
        // Simple JSON parsing if log is JSON format
        if (($entry = json_decode($line, true)) && is_array($entry)) {
            $all_entries[] = $entry;
        }
    }
}

$all_entries = normalize_entries($all_entries);
$all_entries = dedupe_entries($all_entries);

if (($filters['hide_synthetic'] ?? '1') === '1') {
    $all_entries = array_values(array_filter($all_entries, function($entry) {
        return !is_synthetic_entry($entry);
    }));
}

$filtered_entries = apply_filters($all_entries, $filters);
$statistics = calculate_statistics($all_entries);

// Export filtered logs as JSON
if ($export === 'json') {
    if (ob_get_length() !== false) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="frontend_error_logs_' . date('Y-m-d_H-i-s') . '.json"');

    echo json_encode([
        'exported_at' => date('c'),
        'log_file' => basename($log_file),
        'filters' => $filters,
        'total_entries' => count($filtered_entries),
        'entries' => array_values($filtered_entries),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Calculate pagination
$total_filtered = count($filtered_entries);
$total_pages = ceil($total_filtered / $items_per_page);
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $items_per_page;
$paginated_entries = array_slice($filtered_entries, $offset, $items_per_page);

// Get unique files
$unique_files = array_unique(array_map(function($entry) {
    $file = (string)($entry['file'] ?? 'unknown');
    return $file !== '' ? $file : 'unknown';
}, $all_entries));
sort($unique_files);

function build_query_params($filters, $overrides = []) {
    $params = [];

    foreach ($filters as $key => $value) {
        if ($value !== '' && $value !== null) {
            $params[$key] = $value;
        }
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return http_build_query($params);
}

$log_file_size = file_exists($log_file) ? round(filesize($log_file) / 1024, 2) . ' KB' : 'No log file';
$latest_ts = !empty($all_entries) ? ($all_entries[0]['timestamp'] ?? '-') : '-';

// PHP native error_log.txt (root-level, captures public site PHP errors)
$php_error_log_file = __DIR__ . '/../error_log.txt';
if (isset($_GET['action']) && $_GET['action'] === 'clear_php_log' && isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    if (file_exists($php_error_log_file)) {
        file_put_contents($php_error_log_file, '');
        header('Location: view_frontend_error_logs.php?cleared=php_log');
        exit;
    }
}

$php_fe_error_lines = [];
$php_fe_error_log_size = 'No file';
if (file_exists($php_error_log_file) && filesize($php_error_log_file) > 0) {
    $php_fe_error_log_size = round(filesize($php_error_log_file) / 1024, 2) . ' KB';
    $raw_fe_lines = @file($php_error_log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($raw_fe_lines) {
        foreach (array_reverse($raw_fe_lines) as $raw_line) {
            if (preg_match('/^\[(\d{2}-[A-Za-z]+-\d{4} \d{2}:\d{2}:\d{2} [A-Z]+)\] (.+)$/', $raw_line, $m)) {
                $php_sev = 'INFO';
                $msg_lower = strtolower($m[2]);
                if (strpos($msg_lower, 'fatal') !== false) $php_sev = 'CRITICAL';
                elseif (strpos($msg_lower, 'error') !== false) $php_sev = 'ERROR';
                elseif (strpos($msg_lower, 'warning') !== false) $php_sev = 'WARNING';
                elseif (strpos($msg_lower, 'notice') !== false) $php_sev = 'NOTICE';
                elseif (strpos($msg_lower, 'deprecated') !== false) $php_sev = 'NOTICE';
                $php_fe_error_lines[] = ['timestamp' => $m[1], 'severity' => $php_sev, 'message' => $m[2]];
                if (count($php_fe_error_lines) >= 200) break;
            }
        }
    }
}
?>

<style>
    .log-shell {
        display: grid;
        gap: 10px;
    }

    .compact-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .stat-strip {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 8px;
    }

    .stat-chip {
        border: 1px solid #dfe6ef;
        border-radius: 8px;
        background: #fff;
        padding: 8px 10px;
        line-height: 1.2;
    }

    .stat-chip strong {
        display: block;
        font-size: 1rem;
    }

    .filters-sticky {
        position: sticky;
        top: 8px;
        z-index: 12;
        border: 1px solid #dbe3ee;
        border-radius: 8px;
        background: #f7fbff;
        padding: 10px;
    }

    .filters-sticky .form-label {
        margin-bottom: 4px;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: #5d6b7b;
        letter-spacing: 0.04em;
    }

    .severity-badge {
        font-weight: 600;
        padding: 0.2rem 0.45rem;
        border-radius: 0.25rem;
        font-size: 0.7rem;
        min-width: 70px;
        display: inline-block;
        text-align: center;
    }

    .severity-critical { background-color: #721c24; color: white; }
    .severity-error { background-color: #dc3545; color: white; }
    .severity-warning { background-color: #ffc107; color: #333; }
    .severity-notice { background-color: #17a2b8; color: white; }
    .severity-info { background-color: #0c5460; color: white; }
    .severity-debug { background-color: #6c757d; color: white; }

    .log-table thead th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #5d6b7b;
        white-space: nowrap;
    }

    .log-table tbody td {
        vertical-align: top;
        font-size: 0.83rem;
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
    }

    .log-msg {
        max-width: 680px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .log-source code {
        font-size: 0.74rem;
    }

    .log-details summary {
        cursor: pointer;
        color: #3b4f68;
        font-size: 0.75rem;
    }

    .log-details pre {
        margin-top: 6px;
        max-height: 160px;
        overflow: auto;
        background: #f6f8fb;
        border: 1px solid #e5ebf2;
        border-radius: 6px;
        padding: 8px;
        font-size: 0.74rem;
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        background: #f8f9fa;
        border-radius: 0.5rem;
    }
</style>

<div class="content-wrapper">
    <div class="content-inner">
        <div class="content">
            <div class="log-shell">

                <div class="compact-header">
                    <div>
                        <h3 class="mb-0"><i class="ph-warning-circle text-warning"></i> Frontend Error Logs</h3>
                        <small class="text-muted">Compact triage view for public-facing website issues only.</small>
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <a href="?<?php echo build_query_params($filters, ['export' => 'json']); ?>" class="btn btn-sm btn-success"><i class="ph-download"></i> JSON</a>
                        <form method="post" action="" id="clearFrontendLogsForm" class="d-inline" onsubmit="return confirm('Are you sure you want to clear all frontend error logs? This action cannot be undone.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="clear_logs">
                            <button type="submit" class="btn btn-sm btn-danger"><i class="ph-trash"></i> Clear</button>
                        </form>
                    </div>
                </div>

                <?php echo $cleared_message; ?>

                <div class="alert alert-info py-2 mb-0">
                    <small>
                        <i class="ph-info"></i>
                        Frontend log only (`pages/`, `includes/`, `classes/frontend/`). Backend errors belong to <code>CONSOLIDATED_ERROR_LOG.txt</code>.
                    </small>
                </div>

                <div class="stat-strip">
                    <a href="view_frontend_error_logs.php" class="stat-chip text-decoration-none<?php if (empty($filters['severity']) && empty($filters['error_type'])) echo ' active'; ?>" title="Show all entries">
                        <small class="text-muted">Total</small><strong><?php echo number_format($statistics['total']); ?></strong>
                    </a>
                    <a href="?severity=ERROR" class="stat-chip text-decoration-none<?php if (($filters['severity'] ?? '') === 'ERROR') echo ' active'; ?>" title="Show only errors">
                        <small class="text-muted">Errors</small><strong class="text-danger"><?php echo number_format($statistics['by_severity']['ERROR'] ?? 0); ?></strong>
                    </a>
                    <a href="?severity=WARNING" class="stat-chip text-decoration-none<?php if (($filters['severity'] ?? '') === 'WARNING') echo ' active'; ?>" title="Show only warnings">
                        <small class="text-muted">Warnings</small><strong class="text-warning"><?php echo number_format($statistics['by_severity']['WARNING'] ?? 0); ?></strong>
                    </a>
                    <a href="?error_type=404" class="stat-chip text-decoration-none<?php if (($filters['error_type'] ?? '') === '404') echo ' active'; ?>" title="Show only 404 errors">
                        <small class="text-muted">404</small><strong class="text-warning"><?php echo number_format($statistics['errors_404']); ?></strong>
                    </a>
                    <a href="?error_type=500" class="stat-chip text-decoration-none<?php if (($filters['error_type'] ?? '') === '500') echo ' active'; ?>" title="Show only 500 errors">
                        <small class="text-muted">500</small><strong class="text-danger"><?php echo number_format($statistics['errors_500']); ?></strong>
                    </a>
                    <a href="?error_type=403" class="stat-chip text-decoration-none<?php if (($filters['error_type'] ?? '') === '403') echo ' active'; ?>" title="Show only 403 errors">
                        <small class="text-muted">403</small><strong class="text-secondary"><?php echo number_format($statistics['errors_403']); ?></strong>
                    </a>
                    <div class="stat-chip"><small class="text-muted">Files</small><strong><?php echo number_format(count($statistics['by_file'])); ?></strong></div>
                    <div class="stat-chip"><small class="text-muted">Log Size</small><strong><?php echo htmlspecialchars($log_file_size); ?></strong></div>
                </div>

                <!-- Removed duplicate filters-sticky button row, now in header -->

                <div class="d-flex justify-content-between align-items-center small text-muted">
                    <div>
                        Showing <strong><?php echo number_format($total_filtered); ?></strong>
                        <?php if ($total_filtered != $statistics['total']): ?>
                            of <strong><?php echo number_format($statistics['total']); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div>Page <?php echo $page; ?> / <?php echo max(1, $total_pages); ?></div>
                </div>

                <?php if (empty($paginated_entries)): ?>
                    <div class="empty-state">
                        <i class="ph-file-search" style="font-size: 3rem; color: #ccc;"></i>
                        <h5 class="mt-2">No log entries found</h5>
                        <p class="text-muted mb-0">
                            <?php if ($log_file_missing || empty($all_entries)): ?>
                                Frontend log is empty or not created yet.
                            <?php else: ?>
                                Adjust filters to widen results.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0 log-table">
                                <thead>
                                    <tr>
                                        <th style="width: 92px;">Level</th>
                                        <th style="width: 185px; white-space:nowrap;">Time</th>
                                        <th style="width: 320px;">Source</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($paginated_entries as $entry): ?>
                                        <tr class="log-row">
                                            <td>
                                                <span class="severity-badge severity-<?php echo strtolower((string)($entry['severity'] ?? 'info')); ?>">
                                                    <?php echo htmlspecialchars((string)($entry['severity'] ?? 'INFO')); ?>
                                                </span>
                                            </td>
                                            <td style="white-space:nowrap;"><small>
                                                <?php
                                                    $ts = $entry['timestamp'] ?? '-';
                                                    if ($ts && $ts !== '-' && strtotime($ts) !== false) {
                                                        echo dd_($ts, 'd M Y g:ia');
                                                    } else {
                                                        echo htmlspecialchars((string)$ts);
                                                    }
                                                ?>
                                            </small></td>
                                            <td class="log-source"><code><?php echo htmlspecialchars((string)($entry['file'] ?? 'unknown')); ?>:<?php echo (int)($entry['line'] ?? 0); ?></code></td>
                                            <td class="log-msg-cell" style="cursor:pointer;">
                                                <div class="log-msg d-flex align-items-center" title="<?php echo htmlspecialchars($entry['message'] ?? 'No message'); ?>">
                                                    <span class="arrow-icon" style="display:inline-block;transition:transform 0.2s; margin-right:6px;">
                                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.5 6L8 9.5L11.5 6" stroke="#888" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                    </span>
                                                    <span><?php echo htmlspecialchars($entry['message'] ?? 'No message'); ?></span>
                                                </div>
                                                <div class="log-details-content" style="display:none;">
                                                      <pre style="margin:8px 0 0 22px; white-space:pre-wrap; overflow-x:hidden;"><?php echo ($entry['context'] !== '' ? htmlspecialchars($entry['context']) : 'No additional details'); ?></pre>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    // Expand/collapse details on message cell click
                                    document.querySelectorAll('td.log-msg-cell').forEach(function(cell) {
                                        cell.addEventListener('click', function(e) {
                                            // Only toggle if not clicking a link or input
                                            if (e.target.closest('a, input, button, textarea, select')) return;
                                            const details = cell.querySelector('.log-details-content');
                                            const arrow = cell.querySelector('.arrow-icon');
                                            if (details) {
                                                const isOpen = details.style.display === 'block';
                                                details.style.display = isOpen ? 'none' : 'block';
                                                if (arrow) arrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
                                            }
                                        });
                                    });
                                });
                                </script>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo build_query_params($filters, ['page' => max(1, $page - 1)]); ?>">Previous</a>
                        </li>

                        <?php if ($page > 3): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo build_query_params($filters, ['page' => 1]); ?>">1</a>
                            </li>
                            <?php if ($page > 4): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                <a class="page-link" href="?<?php echo build_query_params($filters, ['page' => $i]); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages - 2): ?>
                            <?php if ($page < $total_pages - 3): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?php echo build_query_params($filters, ['page' => $total_pages]); ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                            <a class="page-link" href="?<?php echo build_query_params($filters, ['page' => min($total_pages, $page + 1)]); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <div class="alert alert-info mt-3 py-2">
                <small>
                    <i class="ph-info"></i>
                    Log file: <code><?php echo htmlspecialchars($log_file); ?></code> |
                    Last updated: <code><?php echo date('Y-m-d H:i:s'); ?></code> |
                    File size: <code><?php echo htmlspecialchars($log_file_size); ?></code>
                </small>
            </div>

            <!-- PHP Native Error Log (error_log.txt) -->
            <div class="mt-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h5 class="mb-0"><i class="ph-file-text text-secondary"></i> PHP Error Log <small class="text-muted" style="font-size:0.85rem;">(error_log.txt)</small></h5>
                        <small class="text-muted">Native PHP errors from public site &mdash; showing last <?php echo count($php_fe_error_lines); ?> entries &mdash; <?php echo htmlspecialchars($php_fe_error_log_size); ?></small>
                    </div>
                    <a onclick="return confirm('Clear error_log.txt? This cannot be undone.');" href="?action=clear_php_log&confirm=yes" class="btn btn-sm btn-outline-danger"><i class="ph-trash"></i> Clear</a>
                </div>
                <?php if (empty($php_fe_error_lines)): ?>
                    <div class="empty-state">
                        <i class="ph-file-search" style="font-size: 2rem; color:#ccc;"></i>
                        <p class="text-muted mb-0 mt-1">error_log.txt is empty or not found.</p>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-sm table-hover mb-0 log-table">
                                <thead style="position:sticky;top:0;background:#fff;z-index:1;">
                                    <tr>
                                        <th style="width: 92px;">Level</th>
                                        <th style="width: 220px; white-space:nowrap;">Time</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($php_fe_error_lines as $php_entry): ?>
                                        <tr>
                                            <td><span class="severity-badge severity-<?php echo strtolower($php_entry['severity']); ?>"><?php echo htmlspecialchars($php_entry['severity']); ?></span></td>
                                            <td style="white-space:nowrap;"><small><?php echo htmlspecialchars($php_entry['timestamp']); ?></small></td>
                                            <td style="font-size:0.83rem; word-break:break-word;"><?php echo htmlspecialchars($php_entry['message']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>

</div>

<?php include('admin_elements/admin_footer.php'); ?>

