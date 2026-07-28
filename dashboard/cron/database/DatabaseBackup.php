<?php
require_once dirname(dirname(__DIR__)) . '/admin_elements/error_handler_init.php';

/**
 * Database Backup Cron Job
 * 
 * Automated database backups with:
 * - Full database dump using mysqldump
 * - Compression (gzip)
 * - Retention management (delete old backups)
 * - Error logging and notifications
 * 
 * Run via cron: 0 1 * * * /usr/bin/php /path/to/cron/database/DatabaseBackup.php
 */

require_once __DIR__ . '/../../../config/globals.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../CronJobBase.php';

class DatabaseBackup extends CronJobBase {
    
    /**
     * Backup retention in days
     */
    private $retentionDays = 30;
    
    /**
     * Backup directory
     */
    private $backupDir;
    
    /**
     * Constructor
     * 
     * @param mysqli $mysqli Database connection
     */
    public function __construct($mysqli) {
        parent::__construct($mysqli);
        $this->backupDir = __DIR__ . '/../../backups';
        $this->ensureBackupDirectory();
    }
    
    /**
     * Ensure backup directory exists
     */
    private function ensureBackupDirectory() {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
            $this->log("Created backup directory: {$this->backupDir}", 'INFO');
        }
    }
    
    /**
     * Get job name for logging
     * 
     * @return string
     */
    protected function getJobName() {
        return 'database_backup';
    }
    
    /**
     * Execute database backup
     */
    public function execute() {
        $this->log('Starting database backup', 'INFO');
        
        // Create backup
        $backupFile = $this->createBackup();
        
        if ($backupFile) {
            // Compress backup
            $this->compressBackup($backupFile);
            
            // Clean old backups
            $this->cleanOldBackups();
            
            // Verify backup integrity
            $this->verifyBackup($backupFile . '.gz');
            
            $this->log('Database backup completed successfully', 'SUCCESS');
        } else {
            $this->log('Database backup failed', 'ERROR');
            $this->incrementErrors();
        }
    }
    
    /**
     * Create database backup using mysqldump
     * 
     * @return string|false Backup file path on success, false on failure
     */
    private function createBackup() {
        global $db_host, $db_user, $db_pass, $db_name;
        
        $timestamp = date('Y-m-d_His');
        $backupFile = $this->backupDir . '/backup_' . $timestamp . '.sql';
        
        $this->log("Creating backup: " . basename($backupFile), 'INFO');
        
        // Build mysqldump command
        // Use --single-transaction for InnoDB tables (no table locks)
        // Add --routines to backup stored procedures
        // Add --triggers to backup triggers
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
            $this->getMysqldumpPath(),
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($backupFile)
        );
        
        // Execute mysqldump
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            $size = filesize($backupFile);
            $sizeMB = round($size / 1024 / 1024, 2);
            
            $this->log("Backup created successfully: " . basename($backupFile) . " ({$sizeMB} MB)", 'SUCCESS');
            $this->incrementProcessed();
            
            return $backupFile;
        } else {
            $this->log("Backup failed with code $returnCode", 'ERROR');
            if (!empty($output)) {
                $this->log("Error output: " . implode("\n", $output), 'ERROR');
            }
            return false;
        }
    }
    
    /**
     * Get mysqldump executable path
     * 
     * @return string Path to mysqldump
     */
    private function getMysqldumpPath() {
        // Try XAMPP path on Windows
        if (DIRECTORY_SEPARATOR === '\\' && file_exists('G:\\xampp\\mysql\\bin\\mysqldump.exe')) {
            return '"G:\\xampp\\mysql\\bin\\mysqldump.exe"';
        }
        
        // Try common Linux paths
        $paths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/lampp/bin/mysqldump',
        ];
        
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Default to system PATH
        return 'mysqldump';
    }
    
    /**
     * Compress backup file using gzip
     * 
     * @param string $backupFile Backup file path
     * @return bool True on success
     */
    private function compressBackup($backupFile) {
        if (!file_exists($backupFile)) {
            return false;
        }
        
        $this->log('Compressing backup...', 'INFO');
        
        // Use gzip if available
        if ($this->commandExists('gzip')) {
            exec('gzip "' . $backupFile . '"', $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($backupFile . '.gz')) {
                $originalSize = filesize($backupFile);
                $compressedSize = filesize($backupFile . '.gz');
                $ratio = round((1 - ($compressedSize / $originalSize)) * 100, 1);
                
                $this->log("Backup compressed successfully (compression: {$ratio}%)", 'SUCCESS');
                return true;
            }
        } else {
            // Fallback: PHP gzip compression
            $this->log('gzip not available, using PHP compression', 'WARNING');
            
            $data = file_get_contents($backupFile);
            $gzData = gzencode($data, 9); // Maximum compression
            
            if (file_put_contents($backupFile . '.gz', $gzData)) {
                unlink($backupFile); // Remove uncompressed file
                $this->log('Backup compressed using PHP', 'SUCCESS');
                return true;
            }
        }
        
        $this->log('Compression failed', 'WARNING');
        return false;
    }
    
    /**
     * Clean old backup files
     */
    private function cleanOldBackups() {
        $this->log("Cleaning backups older than {$this->retentionDays} days...", 'INFO');
        
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $cutoff = strtotime("-{$this->retentionDays} days");
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                    $this->log("Deleted old backup: " . basename($file), 'INFO');
                }
            }
        }
        
        if ($deleted > 0) {
            $this->log("Deleted $deleted old backup(s)", 'SUCCESS');
        } else {
            $this->log('No old backups to delete', 'INFO');
        }
    }
    
    /**
     * Verify backup file integrity
     * 
     * @param string $backupFile Compressed backup file path
     * @return bool True if valid
     */
    private function verifyBackup($backupFile) {
        if (!file_exists($backupFile)) {
            $this->log('Backup file not found for verification', 'ERROR');
            return false;
        }
        
        $this->log('Verifying backup integrity...', 'INFO');
        
        // Check if file is a valid gzip file
        $fp = fopen($backupFile, 'rb');
        if ($fp) {
            $header = fread($fp, 2);
            fclose($fp);
            
            // Gzip magic number: 1f 8b
            if ($header === "\x1f\x8b") {
                $size = filesize($backupFile);
                $sizeMB = round($size / 1024 / 1024, 2);
                
                $this->log("Backup verified: {$sizeMB} MB", 'SUCCESS');
                return true;
            }
        }
        
        $this->log('Backup verification failed', 'ERROR');
        $this->incrementErrors();
        return false;
    }
    
    /**
     * Check if command exists in system
     * 
     * @param string $command Command name
     * @return bool True if command exists
     */
    private function commandExists($command) {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows
            $result = shell_exec("where $command 2>nul");
        } else {
            // Linux/Unix
            $result = shell_exec("which $command 2>/dev/null");
        }
        
        return !empty($result);
    }
    
    /**
     * Get current backup statistics
     * 
     * @return array Backup statistics
     */
    private function getBackupStats() {
        $files = glob($this->backupDir . '/backup_*.sql.gz');
        $totalSize = 0;
        $oldest = null;
        $newest = null;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            $mtime = filemtime($file);
            
            if ($oldest === null || $mtime < $oldest) {
                $oldest = $mtime;
            }
            if ($newest === null || $mtime > $newest) {
                $newest = $mtime;
            }
        }
        
        return [
            'count' => count($files),
            'total_size_mb' => round($totalSize / 1024 / 1024, 2),
            'oldest' => $oldest ? date('Y-m-d H:i:s', $oldest) : 'N/A',
            'newest' => $newest ? date('Y-m-d H:i:s', $newest) : 'N/A'
        ];
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $mysqli = $GLOBALS['DB']['MSQLI'];
    $backup = new DatabaseBackup($mysqli);
    $backup->run();
} else {
    http_response_code(403);
    die('CLI only');
}
