<?php
/**
 * Error Alerting System
 * Sends email alerts for critical errors and monitors system health
 * 
 * @package HAI\Monitoring
 * @version 1.0
 * @date March 10, 2026
 */

// Require dependencies
require_once __DIR__ . '/database.php';

/**
 * Send critical error alert via email queue
 * 
 * @param string $errorType Type of error (e.g., 'Database', 'PHP Fatal', 'Security')
 * @param string $message Error message
 * @param array $context Additional context (file, line, trace, etc.)
 * @return bool Success status
 */
function send_critical_error_alert($errorType, $message, $context = []) {
    global $mysqli;
    
    // Get admin email from system settings
    $adminEmail = getTableAttrv('email', DB::SYSTEM_SETTINGS, 'id', '1');
    if (empty($adminEmail)) {
        $adminEmail = 'admin@flasherpsystem.com'; // Fallback
    }
    
    // Check if we've already sent this error recently (throttle duplicate alerts)
    $errorHash = md5($errorType . $message);
    $throttleMinutes = 60; // Don't send same error more than once per hour
    
    $cacheKey = "error_alert_$errorHash";
    if (function_exists('apcu_exists') && apcu_exists($cacheKey)) {
        error_log("[Error Alerting] Throttled duplicate alert: $errorType");
        return false; // Already sent recently
    }
    
    // Prepare email content
    $subject = "[CRITICAL] HAIZON - $errorType Alert";
    
    $body = "<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { background: white; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto; }
        .header { background: #dc3545; color: white; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #dc3545; }
        .label { font-weight: bold; color: #333; }
        .value { color: #666; margin-left: 10px; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2 style='margin: 0;'>ðŸš¨ Critical Error Alert</h2>
            <p style='margin: 5px 0 0 0;'>HAIZON Platform Monitoring</p>
        </div>
        
        <div class='section'>
            <p><span class='label'>Error Type:</span> <span class='value'>$errorType</span></p>
            <p><span class='label'>Timestamp:</span> <span class='value'>" . date('Y-m-d H:i:s') . "</span></p>
            <p><span class='label'>Server:</span> <span class='value'>" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "</span></p>
        </div>
        
        <div class='section'>
            <h3>Error Message</h3>
            <pre>" . htmlspecialchars($message) . "</pre>
        </div>";
    
    // Add context information if available
    if (!empty($context)) {
        $body .= "<div class='section'><h3>Context Details</h3>";
        
        if (isset($context['file'])) {
            $body .= "<p><span class='label'>File:</span> <span class='value'>" . htmlspecialchars($context['file']) . "</span></p>";
        }
        
        if (isset($context['line'])) {
            $body .= "<p><span class='label'>Line:</span> <span class='value'>" . htmlspecialchars($context['line']) . "</span></p>";
        }
        
        if (isset($context['url'])) {
            $body .= "<p><span class='label'>URL:</span> <span class='value'>" . htmlspecialchars($context['url']) . "</span></p>";
        }
        
        if (isset($context['user_id'])) {
            $body .= "<p><span class='label'>User ID:</span> <span class='value'>" . htmlspecialchars($context['user_id']) . "</span></p>";
        }
        
        if (isset($context['trace'])) {
            $body .= "<h4>Stack Trace:</h4><pre>" . htmlspecialchars($context['trace']) . "</pre>";
        }
        
        $body .= "</div>";
    }
    
    // Add recommendations
    $body .= "<div class='section'>
        <h3>Recommended Actions</h3>
        <ul>
            <li>Check error logs: <code>logs/CONSOLIDATED_ERROR_LOG.txt</code></li>
            <li>Monitor database performance and disk space</li>
            <li>Verify backup status and last successful backup</li>
        </ul>
    </div>
    
    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;'>
        <p style='color: #999; font-size: 12px;'>
            This is an automated alert from HAIZON Platform Monitoring System.<br>
            Do not reply to this email.
        </p>
    </div>
    
    </div>
</body>
</html>";
    
    // Send via email queue (high priority)
    try {
        $emailQueue = new EmailQueue();
        $queueId = $emailQueue->enqueue($adminEmail, $subject, $body, [], 1); // Priority 1 (high)
        
        if ($queueId) {
            // Set throttle cache (using APCu if available, or file-based fallback)
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, time(), $throttleMinutes * 60);
            } else {
                // Fallback: use session or database
                $_SESSION["error_alert_$errorHash"] = time();
            }
            
            error_log("[Error Alerting] Critical alert sent: $errorType (Queue ID: $queueId)");
            return true;
        } else {
            error_log("[Error Alerting] Failed to queue alert: $errorType");
            return false;
        }
    } catch (Exception $e) {
        error_log("[Error Alerting] Exception while sending alert: " . $e->getMessage());
        return false;
    }
}

/**
 * Monitor error log size and send alert if exceeds threshold
 * 
 * @param string $logFile Path to log file
 * @param int $maxSizeMB Maximum size in MB before alerting
 * @return void
 */
function monitor_error_log_size($logFile, $maxSizeMB = 50) {
    if (file_exists($logFile)) {
        $sizeMB = filesize($logFile) / 1024 / 1024;
        
        if ($sizeMB > $maxSizeMB) {
            $context = [
                'file' => $logFile,
                'size_mb' => round($sizeMB, 2),
                'threshold_mb' => $maxSizeMB
            ];
            
            send_critical_error_alert(
                'Log File Size Alert',
                "Error log file has exceeded {$maxSizeMB}MB threshold. Current size: " . round($sizeMB, 2) . " MB. Consider log rotation.",
                $context
            );
        }
    }
}

/**
 * Check disk space and alert if running low
 * 
 * @param string $path Path to check
 * @param int $warningThresholdPercent Percentage threshold for warning
 * @return void
 */
function monitor_disk_space($path = __DIR__, $warningThresholdPercent = 90) {
    $freeSpace = disk_free_space($path);
    $totalSpace = disk_total_space($path);
    
    if ($totalSpace > 0) {
        $usagePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
        
        if ($usagePercent >= $warningThresholdPercent) {
            $context = [
                'path' => $path,
                'usage_percent' => round($usagePercent, 2),
                'free_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'total_gb' => round($totalSpace / 1024 / 1024 / 1024, 2)
            ];
            
            send_critical_error_alert(
                'Disk Space Alert',
                "Disk space usage is at " . round($usagePercent, 2) . "% (threshold: {$warningThresholdPercent}%). Free space: " . round($freeSpace / 1024 / 1024 / 1024, 2) . " GB",
                $context
            );
        }
    }
}

/**
 * Custom error handler for critical PHP errors
 * Integrates with existing error logging and adds email alerting
 * 
 * @param int $errno
 * @param string $errstr
 * @param string $errfile
 * @param int $errline
 * @return bool
 */
function critical_error_handler($errno, $errstr, $errfile, $errline) {
    // Only alert on critical errors (not warnings/notices)
    $criticalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    
    if (in_array($errno, $criticalErrors)) {
        $context = [
            'file' => $errfile,
            'line' => $errline,
            'url' => $_SERVER['REQUEST_URI'] ?? 'CLI',
            'user_id' => $_SESSION['user_id'] ?? 'guest'
        ];
        
        send_critical_error_alert('PHP Fatal Error', $errstr, $context);
    }
    
    // Return false to allow normal error handling to continue
    return false;
}

// Register error handler (optional - uncomment to activate)
// set_error_handler('critical_error_handler');

/**
 * Example usage in catch blocks:
 * 
 * try {
 *     // Risky operation
 *     $result = $mysqli->query("SELECT * FROM important_table");
 * } catch (Exception $e) {
 *     send_critical_error_alert(
 *         'Database Error',
 *         $e->getMessage(),
 *         [
 *             'file' => __FILE__,
 *             'line' => __LINE__,
 *             'trace' => $e->getTraceAsString()
 *         ]
 *     );
 * }
 */

