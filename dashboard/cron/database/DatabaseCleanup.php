<?php
require_once dirname(dirname(__DIR__)) . '/admin_elements/error_handler_init.php';

/**
 * Database Cleanup Cron Job
 * 
 * Cleans up old database records to maintain performance: 
 * - Deletes old error logs
 * - Deletes old user action logs
 * - Removes expired sessions
 * - Cleans temporary data
 * - Optimizes tables after cleanup
 * 
 * Run via cron: 0 4 * * 0 /usr/bin/php /path/to/cron/database/DatabaseCleanup.php
 */

require_once __DIR__ . '/../../../config/globals.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../CronJobBase.php';

class DatabaseCleanup extends CronJobBase {
    
    /**
     * Retention periods in days for different data types
     */
    private $retention = [
        'error_logs' => 90,      // Keep error logs for 90 days
        'sessions' => 7,         // Keep expired sessions for 7 days
        'temp_data' => 1,        // Keep temp data for 1 day
        'login_attempts' => 30,  // Keep failed login attempts for 30 days
    ];
    
    /**
     * Get job name for logging
     * 
     * @return string
     */
    protected function getJobName() {
        return 'database_cleanup';
    }
    
    /**
     * Execute database cleanup
     */
    public function execute() {
        $this->log('Starting database cleanup', 'INFO');
        
        $totalDeleted = 0;
        
        // Clean error logs
        $totalDeleted += $this->cleanErrorLogs();
        
        // Clean expired sessions
        $totalDeleted += $this->cleanSessions();
        
        // Clean failed login attempts
        $totalDeleted += $this->cleanLoginAttempts();
        
        // Clean temporary data
        $totalDeleted += $this->cleanTempData();
        
        // Optimize tables
        $this->optimizeTables();
        
        $this->log("Total records deleted: $totalDeleted", 'SUCCESS');
        $this->log('Database cleanup complete', 'SUCCESS');
    }
    
    /**
     * Clean old error logs
     * 
     * @return int Number of records deleted
     */
    private function cleanErrorLogs() {
        $this->log("Cleaning error logs (retention: {$this->retention['error_logs']} days)...", 'INFO');
        
        // Check if error logs table exists
        $tableExists = $this->safeQuery(
            "SHOW TABLES LIKE 'erp_error_logs'"
        );
        
        if (!$tableExists || $tableExists->num_rows === 0) {
            $this->log('Error logs table does not exist', 'INFO');
            return 0;
        }
        
        $result = $this->safeQuery(
            "DELETE FROM `erp_error_logs` 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL {$this->retention['error_logs']} DAY)"
        );
        
        $deleted = $result ? $this->mysqli->affected_rows : 0;
        
        if ($deleted > 0) {
            $this->log("Deleted $deleted old error log record(s)", 'SUCCESS');
            $this->incrementProcessed($deleted);
        } else {
            $this->log('No old error logs to delete', 'INFO');
        }
        
        return $deleted;
    }
    
    /**
     * Clean expired sessions
     * 
     * @return int Number of records deleted
     */
    private function cleanSessions() {
        $this->log("Cleaning expired sessions (retention: {$this->retention['sessions']} days)...", 'INFO');
        
        // Check if sessions table exists
        $tableExists = $this->safeQuery(
            "SHOW TABLES LIKE 'erp_sessions'"
        );
        
        if (!$tableExists || $tableExists->num_rows === 0) {
            $this->log('Sessions table does not exist', 'INFO');
            return 0;
        }
        
        // Delete sessions that expired more than retention days ago
        $result = $this->safeQuery(
            "DELETE FROM `erp_sessions` 
            WHERE expires_at < DATE_SUB(NOW(), INTERVAL {$this->retention['sessions']} DAY)"
        );
        
        $deleted = $result ? $this->mysqli->affected_rows : 0;
        
        if ($deleted > 0) {
            $this->log("Deleted $deleted expired session(s)", 'SUCCESS');
            $this->incrementProcessed($deleted);
        } else {
            $this->log('No expired sessions to delete', 'INFO');
        }
        
        return $deleted;
    }
    
    /**
     * Clean old failed login attempts
     * 
     * @return int Number of records deleted
     */
    private function cleanLoginAttempts() {
        $this->log("Cleaning failed login attempts (retention: {$this->retention['login_attempts']} days)...", 'INFO');
        
        // Check if login attempts table exists  
        $tableExists = $this->safeQuery(
            "SHOW TABLES LIKE 'erp_login_attempts'"
        );
        
        if (!$tableExists || $tableExists->num_rows === 0) {
            $this->log('Login attempts table does not exist', 'INFO');
            return 0;
        }
        
        $result = $this->safeQuery(
            "DELETE FROM `erp_login_attempts` 
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL {$this->retention['login_attempts']} DAY)"
        );
        
        $deleted = $result ? $this->mysqli->affected_rows : 0;
        
        if ($deleted > 0) {
            $this->log("Deleted $deleted old login attempt(s)", 'SUCCESS');
            $this->incrementProcessed($deleted);
        } else {
            $this->log('No old login attempts to delete', 'INFO');
        }
        
        return $deleted;
    }
    
    /**
     * Clean temporary data
     * 
     * @return int Number of records deleted
     */
    private function cleanTempData() {
        $this->log("Cleaning temporary data (retention: {$this->retention['temp_data']} days)...", 'INFO');
        
        // Check if temp data table exists
        $tableExists = $this->safeQuery(
            "SHOW TABLES LIKE 'erp_temp_data'"
        );
        
        if (!$tableExists || $tableExists->num_rows === 0) {
            $this->log('Temp data table does not exist', 'INFO');
            return 0;
        }
        
        $result = $this->safeQuery(
            "DELETE FROM `erp_temp_data` 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL {$this->retention['temp_data']} DAY)"
        );
        
        $deleted = $result ? $this->mysqli->affected_rows : 0;
        
        if ($deleted > 0) {
            $this->log("Deleted $deleted old temp data record(s)", 'SUCCESS');
            $this->incrementProcessed($deleted);
        } else {
            $this->log('No old temp data to delete', 'INFO');
        }
        
        return $deleted;
    }
    
    /**
     * Optimize database tables
     */
    private function optimizeTables() {
        $this->log('Optimizing database tables...', 'INFO');
        
        // Get all tables in database
        $tables = $this->safeQuery("SHOW TABLES");
        
        if (!$tables) {
            return;
        }
        
        $optimized = 0;
        $failed = 0;
        
        while ($row = $tables->fetch_array(MYSQLI_NUM)) {
            $tableName = $row[0];
            
            // Skip views
            $isView = $this->safeQuery(
                "SELECT TABLE_TYPE FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = '$tableName' 
                AND TABLE_TYPE = 'VIEW'"
            );
            
            if ($isView && $isView->num_rows > 0) {
                continue;
            }
            
            // Optimize table
            $result = $this->safeQuery("OPTIMIZE TABLE `$tableName`");
            
            if ($result) {
                $optimized++;
            } else {
                $failed++;
                $this->incrementErrors();
            }
        }
        
        $this->log("Optimized $optimized table(s)" . ($failed > 0 ? ", $failed failed" : ""), 
                   $failed > 0 ? 'WARNING' : 'SUCCESS');
    }
    
    /**
     * Get database statistics
     * 
     * @return array Database statistics
     */
    private function getDatabaseStats() {
        $stats = [];
        
        // Get total database size
        $sizeResult = $this->safeQuery(
            "SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()"
        );
        
        if ($sizeResult) {
            $row = $sizeResult->fetch_array(MYSQLI_ASSOC);
            $stats['total_size_mb'] = $row['size_mb'];
        }
        
        // Get table count
        $tableResult = $this->safeQuery(
            "SELECT COUNT(*) as count 
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() 
            AND TABLE_TYPE = 'BASE TABLE'"
        );
        
        if ($tableResult) {
            $row = $tableResult->fetch_array(MYSQLI_ASSOC);
            $stats['table_count'] = $row['count'];
        }
        
        return $stats;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $mysqli = $GLOBALS['DB']['MSQLI'];
    $cleanup = new DatabaseCleanup($mysqli);
    $cleanup->run();
} else {
    http_response_code(403);
    die('CLI only');
}
