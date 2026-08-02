<?php

use App\Core\DB;
/**
 * ErrorLogger Class - Centralized Error Logging System
 * 
 * Purpose: Consolidate all dashboard errors into a single log file
 * Location: CONSOLIDATED_ERROR_LOG.txt in dashboard directory
 * 
 * Features:
 * - Unified error logging with timestamp, severity, file, line
 * - Automatic log rotation at 5MB
 * - 30-day automatic cleanup of archived logs
 * - Thread-safe file operations
 * - Context data support for debugging
 * 
 * Usage:
 *   log_error('Error message', 'ERROR', __FILE__, __LINE__, ['key' => 'value']);
 * 
 * @author Development Team
 * @version 1.0
 * @date 2026-02-17
 */

if (!class_exists('ErrorLogger')) {
class ErrorLogger {
    
    // Configuration Constants
    private const LOG_DIR = __DIR__ . '/../';
    private const LOG_FILE = 'CONSOLIDATED_ERROR_LOG.txt';
    private const ARCHIVE_DIR = 'logs/archive/';
    private const MAX_LOG_SIZE = 5242880; // 5MB in bytes
    private const DAYS_TO_KEEP = 30;
    private const LOG_LOCK_FILE = 'CONSOLIDATED_ERROR_LOG.lock';
    private static $dbWriteInProgress = false;
    
    // Severity Levels
    private const SEVERITY_LEVELS = [
        'CRITICAL' => 1,
        'ERROR'    => 2,
        'WARNING'  => 3,
        'NOTICE'   => 4,
        'INFO'     => 5,
        'DEBUG'    => 6
    ];
    
    /**
     * Log an error message to the consolidated error log
     * 
     * @param string $message The error message
     * @param string $severity Severity level (CRITICAL, ERROR, WARNING, NOTICE, INFO, DEBUG)
     * @param string $file The file where error occurred
     * @param int $line The line number where error occurred
     * @param array $context Additional context data (non-sensitive)
     * @return bool Success status
     */
    public static function log($message, $severity = 'ERROR', $file = '', $line = '', $context = []) {
        
        // Validate inputs
        if (!is_string($message) || empty(trim($message))) {
            return false;
        }
        
        $severity = strtoupper($severity);
        if (!array_key_exists($severity, self::SEVERITY_LEVELS)) {
            $severity = 'ERROR';
        }

        $context = self::sanitize_context($context);

        // DB write is canonical sink; file write is compatibility sink.
        $db_result = self::write_db_log($message, $severity, $file, $line, $context);
        self::touch_coverage($context, false);
        
        // Format the log entry
        $entry = self::format_entry($severity, $message, $file, $line, $context);
        
        // Write to log file
        $result = self::write_log($entry);
        
        // Check if rotation is needed
        if ($result) {
            self::check_and_rotate();
        }
        
        return $result || $db_result;
    }
    
    /**
     * Format log entry with consistent structure
     * 
     * @return string Formatted log entry
     */
    private static function format_entry($severity, $message, $file = '', $line = '', $context = []) {
        
        $timestamp = date('Y-m-d H:i:s');
        
        // Extract filename only (not full path)
        $filename = !empty($file) ? basename($file) : 'unknown';
        $line_num = !empty($line) ? (int)$line : 0;
        
        // Build the log entry
        $entry = "[{$timestamp}] [{$severity}]";
        
        if (!empty($filename)) {
            $entry .= " [{$filename}:{$line_num}]";
        }
        
        $entry .= "\n";
        $entry .= "Message: " . trim($message) . "\n";
        
        // Add context if provided
        if (!empty($context) && is_array($context)) {
            $entry .= "Context: ";
            
            $context_parts = [];
            foreach ($context as $key => $value) {
                $val = is_array($value)
                    ? self::safe_json_encode($value)
                    : (string)$value;
                $context_parts[] = $key . ": " . substr($val, 0, 100);
            }
            
            $entry .= implode(", ", $context_parts) . "\n";
        }
        
        $entry .= "---\n\n";
        
        return $entry;
    }

    /**
     * Normalize/sanitize context before persistence.
     *
     * @param mixed $context
     * @return array
     */
    private static function sanitize_context($context)
    {
        if (!is_array($context)) {
            return [];
        }

        $sanitized = [];
        foreach ($context as $key => $value) {
            $keyStr = (string)$key;
            $lowerKey = strtolower($keyStr);

            if (
                strpos($lowerKey, 'password') !== false ||
                strpos($lowerKey, 'token') !== false ||
                strpos($lowerKey, 'api_key') !== false ||
                strpos($lowerKey, 'secret') !== false ||
                strpos($lowerKey, 'authorization') !== false ||
                strpos($lowerKey, 'cookie') !== false
            ) {
                $sanitized[$keyStr] = '[REDACTED]';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $sanitized[$keyStr] = substr((string)$value, 0, 1000);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$keyStr] = $value;
                continue;
            }

            $sanitized[$keyStr] = method_exists($value, '__toString')
                ? substr((string)$value, 0, 1000)
                : '[UNSERIALIZABLE_OBJECT]';
        }

        return $sanitized;
    }

    /**
     * Safe JSON encoding with fallback string.
     *
     * @param mixed $value
     * @return string
     */
    private static function safe_json_encode($value)
    {
        $encoded = @json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        return $encoded !== false ? $encoded : '{}';
    }

    /**
     * Resolve an active mysqli connection from known globals.
     *
     * @return mysqli|null
     */
    private static function resolve_db_connection()
    {
        global $mysqli, $conn;

        if (isset($mysqli) && $mysqli instanceof mysqli) {
            return $mysqli;
        }

        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }

        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }

        return null;
    }

    /**
     * Write canonical backend error event to database.
     *
     * @return bool
     */
    private static function write_db_log($message, $severity, $file = '', $line = '', array $context = [])
    {
        if (self::$dbWriteInProgress) {
            return false;
        }

        $db = self::resolve_db_connection();
        if (!$db) {
            return false;
        }
        
        try {
            // Ensure the connection is still open/valid before prepare.
            if (!$db->ping()) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }

        $table = defined('DB::BACKEND_ERROR_LOGS') ? DB::BACKEND_ERROR_LOGS : 'erp_backend_error_logs';
        $meta = self::resolve_request_meta($context, $file);
        $sourceFunction = isset($context['source_function']) ? (string)$context['source_function'] : '';
        $errorCode = isset($context['error_code']) ? (string)$context['error_code'] : '';
        $stackTrace = isset($context['stack_trace']) ? (string)$context['stack_trace'] : '';
        if ($stackTrace === '' && isset($context['trace'])) {
            $stackTrace = is_array($context['trace']) ? self::safe_json_encode($context['trace']) : (string)$context['trace'];
        }

        $contextJson = self::safe_json_encode($context);

        $sql = "INSERT INTO `{$table}` (
            request_id, correlation_id, module_slug, module_id, page_name, page_path,
            entrypoint_type, source_channel, severity, error_code, message, context_json,
            stack_trace, source_file, source_line, source_function, request_uri, request_method,
            user_id, role_id, session_id, ip_address, user_agent, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, NOW()
        )";

        $lineNum = !empty($line) ? (int)$line : null;

        self::$dbWriteInProgress = true;
        try {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                self::$dbWriteInProgress = false;
                return false;
            }

            $stmt->bind_param(
                'sssissssssssssissiissss',
                $meta['request_id'],
                $meta['correlation_id'],
                $meta['module_slug'],
                $meta['module_id'],
                $meta['page_name'],
                $meta['page_path'],
                $meta['entrypoint_type'],
                $meta['source_channel'],
                $severity,
                $errorCode,
                $message,
                $contextJson,
                $stackTrace,
                $file,
                $lineNum,
                $sourceFunction,
                $meta['request_uri'],
                $meta['request_method'],
                $meta['user_id'],
                $meta['role_id'],
                $meta['session_id'],
                $meta['ip_address'],
                $meta['user_agent']
            );

            $ok = $stmt->execute();
            $stmt->close();
            self::$dbWriteInProgress = false;

            if ($ok) {
                self::touch_coverage($context, true);
            }

            return (bool)$ok;
        } catch (Throwable $e) {
            self::$dbWriteInProgress = false;
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            return false;
        }
    }

    /**
     * Record coverage heartbeat for current backend page/module.
     *
     * @param array $context
     * @param bool $isErrorEvent
     * @return bool
     */
    public static function touch_coverage(array $context = [], $isErrorEvent = false)
    {
        $db = self::resolve_db_connection();
        if (!$db) {
            return false;
        }
        
        try {
            if (!$db->ping()) {
                return false;
            }
        } catch (Throwable $e) {
            return false;
        }

        $table = defined('DB::BACKEND_LOG_COVERAGE') ? DB::BACKEND_LOG_COVERAGE : 'erp_backend_log_coverage';
        $meta = self::resolve_request_meta($context);
        $bootstrapIncluded = 1;
        $seenCount = 1;

        $sql = "INSERT INTO `{$table}` (
            module_slug, page_name, page_path, entrypoint_type, source_channel,
            bootstrap_included, first_seen_at, last_seen_at, last_seen_error_at, seen_count
        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            module_slug = VALUES(module_slug),
            page_name = VALUES(page_name),
            source_channel = VALUES(source_channel),
            bootstrap_included = VALUES(bootstrap_included),
            last_seen_at = NOW(),
            last_seen_error_at = IF(VALUES(last_seen_error_at) IS NULL, last_seen_error_at, NOW()),
            seen_count = seen_count + 1";

        $errorTimestamp = $isErrorEvent ? date('Y-m-d H:i:s') : null;

        try {
            $stmt = $db->prepare($sql);
            if (!$stmt) {
                return false;
            }

            $stmt->bind_param(
                'sssssisi',
                $meta['module_slug'],
                $meta['page_name'],
                $meta['page_path'],
                $meta['entrypoint_type'],
                $meta['source_channel'],
                $bootstrapIncluded,
                $errorTimestamp,
                $seenCount
            );
            $ok = $stmt->execute();
            $stmt->close();
            return (bool)$ok;
        } catch (Throwable $e) {
            if (isset($stmt) && $stmt instanceof mysqli_stmt) {
                $stmt->close();
            }
            return false;
        }
    }

    /**
     * Resolve module/page/request metadata for DB categorization.
     *
     * @param array $context
     * @param string $fallbackFile
     * @return array
     */
    private static function resolve_request_meta(array $context = [], $fallbackFile = '')
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string)$_SERVER['SCRIPT_NAME'] : '';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
        $pathFromUri = (string)(parse_url($requestUri, PHP_URL_PATH) ?: '');
        $pagePath = $scriptName !== '' ? $scriptName : $pathFromUri;

        if ($pagePath === '' && $fallbackFile !== '') {
            $pagePath = (string)$fallbackFile;
        }

        $pagePath = str_replace('\\', '/', $pagePath);
        $pagePath = ltrim($pagePath, '/');
        $pageName = basename($pagePath);
        if ($pageName === '' || $pageName === '.' || $pageName === '/') {
            $pageName = 'unknown';
        }

        $entrypointType = 'unknown';
        if (PHP_SAPI === 'cli') {
            $entrypointType = 'cli';
        } elseif (strpos($pagePath, 'datatables_dispatcher.php') !== false) {
            $entrypointType = 'datatable';
        } elseif (strpos($pagePath, '/api/') !== false || strpos($pagePath, 'api/') === 0) {
            $entrypointType = 'api';
        } elseif (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $entrypointType = 'ajax';
        } elseif (strpos($pagePath, '/cron/') !== false || strpos($pagePath, 'cron/') === 0) {
            $entrypointType = 'cron';
        } elseif ($pageName !== 'unknown') {
            $entrypointType = 'page';
        }

        $sourceChannel = $entrypointType === 'cli' ? 'cli_runtime' : 'dashboard_runtime';
        $moduleSlug = self::resolve_module_slug($context, $pageName);
        $moduleId = isset($context['module_id']) ? (int)$context['module_id'] : null;

        $requestId = isset($_SERVER['HTTP_X_REQUEST_ID']) ? (string)$_SERVER['HTTP_X_REQUEST_ID'] : '';
        $correlationId = isset($_SERVER['HTTP_X_CORRELATION_ID']) ? (string)$_SERVER['HTTP_X_CORRELATION_ID'] : '';

        if ($requestId === '') {
            try {
                $requestId = bin2hex(random_bytes(8));
            } catch (Exception $e) {
                $requestId = uniqid('req_', true);
            }
        }

        if ($correlationId === '') {
            $correlationId = $requestId;
        }

        $sessionRoleId = null;
        $sessionUserId = null;
        $projectKey = isset($GLOBALS['project_pre']) ? (string)$GLOBALS['project_pre'] : 'flasherpsystem';
        if (isset($_SESSION[$projectKey]['DASHBOARD']['role_id'])) {
            $sessionRoleId = (int)$_SESSION[$projectKey]['DASHBOARD']['role_id'];
        }
        if (isset($_SESSION[$projectKey]['DASHBOARD']['user_id'])) {
            $sessionUserId = (int)$_SESSION[$projectKey]['DASHBOARD']['user_id'];
        }

        $sessionName = session_name();
        $sessionCookie = ($sessionName !== '' && isset($_COOKIE[$sessionName]))
            ? (string)$_COOKIE[$sessionName]
            : '';

        return [
            'request_id' => substr($requestId, 0, 64),
            'correlation_id' => substr($correlationId, 0, 64),
            'module_slug' => substr($moduleSlug, 0, 120),
            'module_id' => $moduleId,
            'page_name' => substr($pageName, 0, 255),
            'page_path' => substr($pagePath, 0, 500),
            'entrypoint_type' => $entrypointType,
            'source_channel' => $sourceChannel,
            'request_uri' => substr($requestUri, 0, 1000),
            'request_method' => isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : 'CLI',
            'user_id' => $sessionUserId,
            'role_id' => $sessionRoleId,
            'session_id' => $sessionCookie,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 1000) : '',
        ];
    }

    /**
     * Resolve module slug from context first, then from page naming conventions.
     *
     * @param array $context
     * @param string $pageName
     * @return string
     */
    private static function resolve_module_slug(array $context, $pageName)
    {
        if (!empty($context['module']) && is_string($context['module'])) {
            return preg_replace('/[^a-z0-9_\-]/i', '', strtolower($context['module'])) ?: 'unknown';
        }

        if (!empty($context['module_slug']) && is_string($context['module_slug'])) {
            return preg_replace('/[^a-z0-9_\-]/i', '', strtolower($context['module_slug'])) ?: 'unknown';
        }

        $base = strtolower((string)$pageName);
        $base = preg_replace('/\.php$/', '', $base);

        if (strpos($base, 'listing_') === 0) {
            return substr($base, 8) ?: 'unknown';
        }

        if (strpos($base, 'dashboard_') === 0) {
            return substr($base, 10) ?: 'dashboard';
        }

        if ($base !== '' && $base !== 'index') {
            return $base;
        }

        return 'dashboard';
    }
    
    /**
     * Write log entry to file with file locking
     * 
     * @return bool Success status
     */
    private static function write_log($entry) {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        
        // Ensure directory exists
        if (!is_dir(self::LOG_DIR)) {
            @mkdir(self::LOG_DIR, 0755, true);
        }
        
        // Acquire lock to prevent concurrent writes
        $lock_path = self::LOG_DIR . self::LOG_LOCK_FILE;
        $lock = @fopen($lock_path, 'c');
        
        if ($lock === false) {
            return false;
        }
        
        // Acquire exclusive lock
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return false;
        }
        
        try {
            // Write to log file
            $result = @file_put_contents($log_path, $entry, FILE_APPEND | LOCK_EX);
            
            if ($result === false) {
                flock($lock, LOCK_UN);
                fclose($lock);
                return false;
            }
            
            flock($lock, LOCK_UN);
            fclose($lock);
            return true;
            
        } catch (Exception $e) {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
            return false;
        }
    }
    
    /**
     * Check if log file size exceeds limit and rotate if needed
     * 
     * @return void
     */
    private static function check_and_rotate() {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        
        // Check if log file exists and get size
        if (!file_exists($log_path)) {
            return;
        }
        
        $log_size = filesize($log_path);
        
        // If size exceeds limit, rotate the log
        if ($log_size > self::MAX_LOG_SIZE) {
            self::rotate_log();
        }
        
        // Cleanup old logs (daily)
        self::cleanup_old_logs();
    }
    
    /**
     * Rotate the current log file to archive directory
     * 
     * @return bool Success status
     */
    private static function rotate_log() {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        $archive_dir = self::LOG_DIR . self::ARCHIVE_DIR;
        
        // Create archive directory if it doesn't exist
        if (!is_dir($archive_dir)) {
            @mkdir($archive_dir, 0755, true);
        }
        
        // Generate archive filename with timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $archive_file = $archive_dir . 'ERROR_LOG_' . $timestamp . '.txt';
        
        // Move current log to archive
        if (file_exists($log_path)) {
            $moved = @rename($log_path, $archive_file);
            
            if ($moved) {
                // Log rotation event
                $rotation_msg = "[" . date('Y-m-d H:i:s') . "] [INFO] Log rotated to: {$archive_file}\n---\n\n";
                @file_put_contents($log_path, $rotation_msg, FILE_APPEND);
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Clean up log files older than DAYS_TO_KEEP
     * Called automatically during check_and_rotate()
     * 
     * @return int Number of files deleted
     */
    private static function cleanup_old_logs() {
        
        $archive_dir = self::LOG_DIR . self::ARCHIVE_DIR;
        
        // Don't attempt cleanup if archive dir doesn't exist
        if (!is_dir($archive_dir)) {
            return 0;
        }
        
        $cutoff_time = time() - (self::DAYS_TO_KEEP * 24 * 60 * 60);
        $deleted_count = 0;
        
        try {
            $files = @scandir($archive_dir);
            
            if ($files === false) {
                return 0;
            }
            
            foreach ($files as $file) {
                
                // Skip . and ..
                if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                    continue;
                }
                
                $file_path = $archive_dir . $file;
                
                // Only delete error log files
                if (!preg_match('/^ERROR_LOG_.*\.txt$/', $file) || !is_file($file_path)) {
                    continue;
                }
                
                // Check file modification time
                $file_time = @filemtime($file_path);
                
                if ($file_time !== false && $file_time < $cutoff_time) {
                    if (@unlink($file_path)) {
                        $deleted_count++;
                    }
                }
            }
            
            return $deleted_count;
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get all logs (main and archived) for admin interface
     * 
     * @param string $severity_filter Filter by severity level
     * @param int $limit Number of entries to return
     * @return array Array of log entries
     */
    public static function get_logs($severity_filter = '', $limit = 100) {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        $logs = [];
        
        // Read main log file
        if (file_exists($log_path)) {
            $content = @file_get_contents($log_path);
            if ($content) {
                $entries = explode("---\n\n", $content);
                $logs = array_merge($logs, $entries);
            }
        }
        
        // Apply filters and limiting
        if (!empty($severity_filter)) {
            $logs = array_filter($logs, function($entry) use ($severity_filter) {
                return stripos($entry, "[{$severity_filter}]") !== false;
            });
        }
        
        // Reverse to show latest first
        $logs = array_reverse($logs);
        
        // Limit results
        $logs = array_slice($logs, 0, $limit);
        
        return array_filter($logs);
    }
    
    /**
     * Clear all logs (admin function - use with caution)
     * 
     * @return bool Success status
     */
    public static function clear_logs() {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        
        try {
            if (file_exists($log_path)) {
                @unlink($log_path);
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get statistics about errors (for dashboard)
     * 
     * @return array Statistics array
     */
    public static function get_statistics() {
        
        $log_path = self::LOG_DIR . self::LOG_FILE;
        $stats = [
            'total_errors' => 0,
            'by_severity' => [
                'CRITICAL' => 0,
                'ERROR'    => 0,
                'WARNING'  => 0,
                'NOTICE'   => 0,
                'INFO'     => 0,
                'DEBUG'    => 0
            ],
            'by_file' => [],
            'last_error' => null
        ];
        
        if (!file_exists($log_path)) {
            return $stats;
        }
        
        $content = @file_get_contents($log_path);
        if (!$content) {
            return $stats;
        }
        
        $entries = explode("---\n\n", $content);
        $stats['total_errors'] = count(array_filter($entries));
        
        // Count by severity and file
        foreach ($entries as $entry) {
            if (empty(trim($entry))) {
                continue;
            }
            
            // Extract severity
            if (preg_match('/\[(CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG)\]/', $entry, $matches)) {
                $severity = $matches[1];
                $stats['by_severity'][$severity]++;
            }
            
            // Extract filename
            if (preg_match('/\[([\w\-\.]+):(\d+)\]/', $entry, $matches)) {
                $file = $matches[1];
                $stats['by_file'][$file] = ($stats['by_file'][$file] ?? 0) + 1;
            }
            
            // Get last error (first in reversed list)
            if ($stats['last_error'] === null) {
                $lines = explode("\n", trim($entry));
                if (!empty($lines[0])) {
                    $stats['last_error'] = $lines[0];
                }
            }
        }
        
        // Sort by file frequency
        arsort($stats['by_file']);
        
        return $stats;
    }
}
}

/**
 * Helper function for easy error logging throughout the application
 * 
 * @param string $message Error message
 * @param string $severity Severity level
 * @param string $file File location
 * @param int $line Line number
 * @param array $context Context data
 * @return bool Success status
 */
if (!function_exists('log_error')) {
function log_error($message, $severity = 'ERROR', $file = __FILE__, $line = __LINE__, $context = []) {
    return ErrorLogger::log($message, $severity, $file, $line, $context);
}
}

/**
 * Record request coverage heartbeat even when no error occurs.
 *
 * @param array $context
 * @return bool
 */
if (!function_exists('backend_log_coverage_heartbeat')) {
function backend_log_coverage_heartbeat($context = []) {
    if (!is_array($context)) {
        $context = [];
    }

    return ErrorLogger::touch_coverage($context, false);
}
}

/**
 * Build backend logging context with module details when available.
 *
 * @param array $context
 * @return array
 */
if (!function_exists('backend_runtime_log_context')) {
function backend_runtime_log_context($context = []) {
    if (!is_array($context)) {
        $context = [];
    }

    $module = '';
    if (isset($GLOBALS['module']) && is_string($GLOBALS['module'])) {
        $module = strtolower(trim((string)$GLOBALS['module']));
    }

    if ($module !== '') {
        $context['module'] = $module;
        $context['module_slug'] = $module;
    }

    return $context;
}
}

/**
 * Custom PHP error handler - catches all PHP warnings, notices, etc.
 * 
 * @param int $errno Error number
 * @param string $errstr Error string
 * @param string $errfile Error file
 * @param int $errline Error line
 * @return bool
 */
if (!function_exists('custom_error_handler')) {
function custom_error_handler($errno, $errstr, $errfile, $errline) {
    
    // Don't log if error reporting is disabled for this error
    if (!(error_reporting() & $errno)) {
        return true;
    }
    
    // Map error codes to severity levels
    $severity_map = [
        E_ERROR             => 'ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'CRITICAL',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CRITICAL',
        E_CORE_WARNING      => 'WARNING',
        E_COMPILE_ERROR     => 'CRITICAL',
        E_COMPILE_WARNING   => 'WARNING',
        E_USER_ERROR        => 'ERROR',
        E_USER_WARNING      => 'WARNING',
        E_USER_NOTICE       => 'NOTICE',
        E_STRICT            => 'NOTICE',
        E_DEPRECATED        => 'NOTICE',
        E_USER_DEPRECATED   => 'NOTICE'
    ];
    
    $severity = $severity_map[$errno] ?? 'ERROR';
    
    // Get error type name
    $error_types = [
        E_ERROR             => 'PHP Error',
        E_WARNING           => 'PHP Warning',
        E_PARSE             => 'Parse Error',
        E_NOTICE            => 'PHP Notice',
        E_DEPRECATED        => 'Deprecated',
    ];
    
    $error_type = $error_types[$errno] ?? 'PHP Error';
    
    $context = backend_runtime_log_context(['errno' => $errno]);

    // Log the error
    log_error(
        "{$error_type}: {$errstr}",
        $severity,
        $errfile,
        $errline,
        $context
    );
    
    // Return false to let PHP handle the error as well (for display)
    return false;
}
}

/**
 * Custom exception handler
 * 
 * @param Throwable $exception
 * @return void
 */
if (!function_exists('custom_exception_handler')) {
function custom_exception_handler($exception) {
    $context = backend_runtime_log_context([
        'exception_class' => get_class($exception),
        'trace_depth' => count($exception->getTrace())
    ]);
    
    log_error(
        'Exception: ' . $exception->getMessage(),
        'ERROR',
        $exception->getFile(),
        $exception->getLine(),
        $context
    );
    
    // Display user-friendly error
    if (!headers_sent()) {
        @http_response_code(500);
    }
    echo "An error occurred. Please try again later.";
}
}

/**
 * Handle fatal/parse errors that can't be caught by set_error_handler
 * 
 * @return void
 */
if (!function_exists('handle_fatal_error')) {
function handle_fatal_error() {
    
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $context = backend_runtime_log_context(['error_type' => $error['type']]);
        
        log_error(
            'Fatal Error: ' . $error['message'],
            'CRITICAL',
            $error['file'],
            $error['line'],
            $context
        );
    }
}
}

?>
