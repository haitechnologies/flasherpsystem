<?php

declare(strict_types=1);

namespace App\Frontend;

/**
 * Frontend Error Logger
 *
 * Logs FRONTEND/PUBLIC-FACING errors ONLY to logs/FRONTEND_ERROR_LOG.txt
 *
 * IMPORTANT: This logger automatically filters out dashboard/backend errors.
 * It will NOT log errors from:
 *   - dashboard/ directory
 *   - admin_elements/ directory
 *   - Any request with /dashboard/ in the URI
 *
 * Dashboard errors should be logged to dashboard/CONSOLIDATED_ERROR_LOG.txt instead.
 *
 * Uses same format as dashboard CONSOLIDATED_ERROR_LOG.txt for consistency
 *
 * Format: [TIMESTAMP] [SEVERITY] [FILE:LINE]
 *         Message: {message}
 *         Context: {json context}
 *         ---
 */

class FrontendErrorLogger
{
    private $logFile;
    private $environment;
    private static $requestLogSignatures = [];

    public const LEVEL_ERROR = 'ERROR';
    public const LEVEL_WARNING = 'WARNING';
    public const LEVEL_NOTICE = 'NOTICE';
    public const LEVEL_DEBUG = 'DEBUG';
    public const LEVEL_INFO = 'INFO';

    public function __construct($logFile = null, $env = 'development')
    {
        $this->logFile = $logFile ?: __DIR__ . '/../../logs/FRONTEND_ERROR_LOG.txt';
        $this->environment = $env;

        // Create directory if doesn't exist
        $this->ensureDirectoryExists(dirname($this->logFile));
    }

    /**
     * Log an error
     */
    public function error($message, $context = [], $file = null, $line = null)
    {
        $this->log(self::LEVEL_ERROR, $message, $context, $file, $line);
    }

    /**
     * Log a warning
     */
    public function warning($message, $context = [], $file = null, $line = null)
    {
        $this->log(self::LEVEL_WARNING, $message, $context, $file, $line);
    }

    /**
     * Log a notice
     */
    public function notice($message, $context = [], $file = null, $line = null)
    {
        $this->log(self::LEVEL_NOTICE, $message, $context, $file, $line);
    }

    /**
     * Log info message
     */
    public function info($message, $context = [], $file = null, $line = null)
    {
        $this->log(self::LEVEL_INFO, $message, $context, $file, $line);
    }

    /**
     * Track 404 Not Found error page hit
     *
     * @param string $requestedUrl The URL that was requested
     * @param string $referrer The referrer URL
     */
    public function log404($requestedUrl = null, $referrer = null)
    {
        $requestedUrl = $requestedUrl ?: ($_SERVER['REQUEST_URI'] ?? 'unknown');
        $referrer = $referrer ?: ($_SERVER['HTTP_REFERER'] ?? 'direct');
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        if ($this->shouldSkipSyntheticProbe($requestedUrl, $userAgent)) {
            return;
        }

        $context = [
            'error_type' => '404',
            'error_name' => 'Not Found',
            'requested_url' => $requestedUrl,
            'referrer' => $referrer,
            'user_agent' => $userAgent,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        $this->warning("404 - Page Not Found: $requestedUrl", $context);
    }

    /**
     * Track 403 Forbidden error page hit
     *
     * @param string $requestedUrl The URL that was requested
     * @param string $reason Optional reason for denial
     */
    public function log403($requestedUrl = null, $reason = null)
    {
        $requestedUrl = $requestedUrl ?: ($_SERVER['REQUEST_URI'] ?? 'unknown');
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        if ($this->shouldSkipSyntheticProbe($requestedUrl, $userAgent)) {
            return;
        }

        $context = [
            'error_type' => '403',
            'error_name' => 'Forbidden',
            'requested_url' => $requestedUrl,
            'reason' => $reason ?: 'Access Denied',
            'user_agent' => $userAgent,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (isset($_SESSION['project_pre']['FRONTEND']['user_id'])) {
            $context['user_id'] = $_SESSION['project_pre']['FRONTEND']['user_id'];
            $context['username'] = $_SESSION['project_pre']['FRONTEND']['username'] ?? 'unknown';
        }

        $this->warning("403 - Access Forbidden: $requestedUrl", $context);
    }

    /**
     * Track 500 Server Error page hit
     *
     * @param string $errorMessage The error message or description
     * @param string $errorCode Optional error code or reference ID
     */
    public function log500($errorMessage = null, $errorCode = null)
    {
        $errorMessage = $errorMessage ?: 'Internal Server Error';
        $requestedUrl = (string)($_SERVER['REQUEST_URI'] ?? 'unknown');
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        if ($this->shouldSkipSyntheticProbe($requestedUrl, $userAgent)) {
            return;
        }

        $context = [
            'error_type' => '500',
            'error_name' => 'Internal Server Error',
            'error_message' => $errorMessage,
            'error_code' => $errorCode ?: uniqid('ERR_' . date('YmdHis') . '_'),
            'requested_url' => $requestedUrl,
            'user_agent' => $userAgent,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'timestamp' => date('Y-m-d H:i:s')
        ];

        if (isset($_SESSION['project_pre']['FRONTEND']['user_id'])) {
            $context['user_id'] = $_SESSION['project_pre']['FRONTEND']['user_id'];
            $context['username'] = $_SESSION['project_pre']['FRONTEND']['username'] ?? 'unknown';
        }

        $this->error("500 - Server Error", $context);
    }

    /**
     * Log debug info (development only)
     */
    public function debug($message, $context = [], $file = null, $line = null)
    {
        if ($this->environment !== 'production') {
            $this->log(self::LEVEL_DEBUG, $message, $context, $file, $line);
        }
    }

    /**
     * Core logging function - writes in dashboard format
     */
    private function log($level, $message, $context = [], $file = null, $line = null)
    {
        // Get file/line info if not provided
        if (!$file || !$line) {
            $trace = debug_backtrace();
            // Skip our own frames
            foreach ($trace as $frame) {
                if (isset($frame['class']) && $frame['class'] === __CLASS__) {
                    continue;
                }
                if (!$file) {
                    $file = $frame['file'] ?? 'unknown';
                }
                if (!$line) {
                    $line = $frame['line'] ?? 0;
                }
                break;
            }
        }

        // Ensure $file is not null before calling basename
        $file = $file ?? 'unknown';

        // FILTER: Skip logging if error is from dashboard/backend
        // This log is ONLY for frontend/public-facing errors
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $filePath = is_string($file) ? $file : '';
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';

        // Skip if request is from dashboard or file is in dashboard directory
        // Also skip if the referrer is a dashboard page (e.g., 404 from dashboard page)
        if (
            stripos($requestUri, '/dashboard/') !== false ||
            stripos($filePath, 'dashboard') !== false ||
            stripos($filePath, 'admin_elements') !== false ||
            stripos($referrer, '/dashboard/') !== false
        ) {
            return; // Don't log backend/dashboard errors to frontend log
        }

        // Clean up file path
        $file = basename($file);

        // Build entry in dashboard format: [TIMESTAMP] [SEVERITY] [FILE:LINE]
        $timestamp = date('Y-m-d H:i:s');
        $uri = (string)($_SERVER['REQUEST_URI'] ?? 'N/A');
        $method = (string)($_SERVER['REQUEST_METHOD'] ?? 'N/A');
        $signature = sha1($level . '|' . $this->sanitize($message) . '|' . $uri . '|' . $method);
        if (isset(self::$requestLogSignatures[$signature])) {
            return;
        }
        self::$requestLogSignatures[$signature] = true;

        $entry = "[$timestamp] [$level] [$file:$line]\n";
        $entry .= "Message: " . $this->sanitize($message) . "\n";

        // Add context with IP and URI
        $contextData = $context;
        $contextData['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $contextData['uri'] = $uri;
        $contextData['method'] = $method;

        $entry .= "Context: " . json_encode($contextData) . "\n";
        $entry .= "---\n";

        // Write with file locking
        $this->writeLog($entry);
    }

    /**
     * Write log entry with File locking for safety
     */
    private function writeLog($entry)
    {
        $fp = @fopen($this->logFile, 'a');
        if (!$fp) {
            return; // Silently fail - don't break the application
        }

        // Try to acquire exclusive lock
        if (@flock($fp, LOCK_EX)) {
            fwrite($fp, $entry);
            @flock($fp, LOCK_UN);
        }

        fclose($fp);
    }

    /**
     * Sanitize message to prevent issues
     */
    private function sanitize($message)
    {
        return strip_tags((string)$message);
    }

    /**
     * Skip synthetic/manual probing errors so production triage stays actionable.
     */
    private function shouldSkipSyntheticProbe($requestedUrl, $userAgent)
    {
        $url = strtolower((string)$requestedUrl);
        $agent = strtolower((string)$userAgent);

        $isSyntheticAgent = (strpos($agent, 'flasherpsystem-manualtester') !== false || strpos($agent, 'curl/') !== false);
        if (!$isSyntheticAgent) {
            return false;
        }

        $syntheticPaths = [
            '/this-page-does-not-exist-404-test',
            '/pages/403.php',
            '/pages/500.php',
            '/manual-test-links',
        ];

        foreach ($syntheticPaths as $path) {
            if (strpos($url, $path) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ensure directory exists and create .htaccess
     */
    private function ensureDirectoryExists($dir)
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        // Create .htaccess to block web access
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }
    }

    /**
     * Register PHP error handler
     */
    public function registerErrorHandler()
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            // Skip if error is from dashboard/backend
            if (
                stripos($errfile, 'dashboard') !== false ||
                stripos($_SERVER['REQUEST_URI'] ?? '', '/dashboard/') !== false
            ) {
                return false; // Let default handler process it
            }

            $level = $this->mapErrorLevel($errno);

            $this->log(
                $level,
                $errstr,
                ['error_code' => $errno],
                $errfile,
                $errline
            );

            // Don't prevent default PHP error handling
            return false;
        });
    }

    /**
     * Register exception handler
     */
    public function registerExceptionHandler()
    {
        set_exception_handler(function ($exception) {
            static $isHandlingException = false;
            if ($isHandlingException) {
                http_response_code(500);
                echo "An unexpected error occurred. Our team has been notified.";
                exit;
            }
            $isHandlingException = true;

            // Skip if exception is from dashboard/backend
            $exceptionFile = $exception->getFile();
            if (
                stripos($exceptionFile, 'dashboard') !== false ||
                stripos($_SERVER['REQUEST_URI'] ?? '', '/dashboard/') !== false
            ) {
                // Let dashboard's error handler deal with it
                http_response_code(500);
                echo "An unexpected error occurred. Our team has been notified.";
                exit;
            }

            $this->error(
                'Uncaught Exception: ' . $exception->getMessage(),
                [
                    'class' => get_class($exception),
                    'code' => $exception->getCode(),
                    'trace' => $exception->getTraceAsString()
                ],
                $exception->getFile(),
                $exception->getLine()
            );

            // Show frontend 500 page (safe fallback to plain text)
            $projectRoot = dirname(__DIR__, 2);
            $errorPage = $projectRoot . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '500.php';

            if (is_file($errorPage)) {
                http_response_code(500);
                include $errorPage;
                exit;
            }

            http_response_code(500);
            echo "An unexpected error occurred. Our team has been notified.";
            exit;
        });
    }

    /**
     * Map PHP error codes to log levels
     */
    private function mapErrorLevel($errno)
    {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                return self::LEVEL_ERROR;

            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return self::LEVEL_WARNING;

            case E_NOTICE:
            case E_USER_NOTICE:
                return self::LEVEL_NOTICE;

            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return self::LEVEL_WARNING;

            default:
                return self::LEVEL_DEBUG;
        }
    }

    /**
     * Parse log file into entries (for viewing)
     */
    public static function parseLogFile($logFile)
    {
        $entries = [];

        if (!file_exists($logFile)) {
            return $entries;
        }

        $content = file_get_contents($logFile);
        $blocks = explode("---\n", $content);

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            $entry = [];

            // Parse: [2026-02-20 14:35:22] [ERROR] [file.php:45] or [unknown:]
            // Make line number optional to handle [unknown:] format
            if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[([A-Z]+)\] \[([^:]+):(\d*)\]/', $block, $matches)) {
                $entry['timestamp'] = $matches[1];
                $entry['severity'] = $matches[2];
                $entry['file'] = $matches[3];
                $entry['line'] = !empty($matches[4]) ? $matches[4] : '0';

                // Parse message and context
                if (preg_match('/Message: (.+?)\nContext: (.+?)(?:\n|$)/s', $block, $msgMatches)) {
                    $entry['message'] = trim($msgMatches[1]);
                    $entry['context'] = trim($msgMatches[2]);
                } elseif (preg_match('/Message: (.+?)(?:\n|$)/s', $block, $msgMatches)) {
                    // Handle cases where there might not be a Context line
                    $entry['message'] = trim($msgMatches[1]);
                    $entry['context'] = '';
                }

                $entries[] = $entry;
            }
        }

        return array_reverse($entries); // Latest first
    }

    /**
     * Get log file path
     */
    public function getLogFile()
    {
        return $this->logFile;
    }
}
