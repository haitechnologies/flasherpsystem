<?php
require_once dirname(__DIR__) . '/admin_elements/error_handler_init.php';

/**
 * Centralized Cron Job Scheduler
 * 
 * Single entry point for all cron jobs with:
 * - Job registry and management
 * - Command-line interface
 * - Individual job execution
 * - Job listing and status
 * 
 * Usage:
 *   php CronJobScheduler.php --list              # List all registered jobs
 *   php CronJobScheduler.php --job=email:queue   # Run specific job
 *   php CronJobScheduler.php --help              # Show help
 * 
 * @author Development Team  
 * @date 2026-02-18
 */

require_once __DIR__ . '/../../config/globals.php';
require_once __DIR__ . '/../../config/database.php';

class CronJobScheduler {
    
    /**
     * Registry of all available cron jobs
     * Format: 'job:name' => ['class' => 'ClassName', 'path' => 'path/to/file.php', 'schedule' => 'cron expression']
     */
    private $jobs = [
        // Email jobs
        'email:queue' => [
            'class' => 'EmailQueueWorker',
            'path' => 'email/EmailQueueWorker.php',
            'schedule' => '*/5 * * * *',
            'description' => 'Process email marketing queue'
        ],
        'email:cleanup' => [
            'class' => 'EmailQueueCleanup',
            'path' => 'email/EmailQueueCleanup.php',
            'schedule' => '0 2 * * *',
            'description' => 'Clean up old email queue items'
        ],
        
        // Document jobs
        'documents:expiry' => [
            'class' => 'DocumentExpiryCron',
            'path' => 'documents/DocumentExpiryCron.php',
            'schedule' => '0 7 * * *',
            'description' => 'Check expiring employee documents and send notifications'
        ],

        // Database jobs
        'db:backup' => [
            'class' => 'DatabaseBackup',
            'path' => 'database/DatabaseBackup.php',
            'schedule' => '0 1 * * *',
            'description' => 'Create database backup'
        ],
        'db:cleanup' => [
            'class' => 'DatabaseCleanup',
            'path' => 'database/DatabaseCleanup.php',
            'schedule' => '0 4 * * 0',
            'description' => 'Clean up old database records'
        ],
    ];
    
    /**
     * Database connection
     */
    private $mysqli;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->mysqli = $GLOBALS['DB']['MSQLI'];
        $this->validateCLI();
        $this->bootstrapErrorLogger();
    }

    /**
     * Load central dashboard error logger when available.
     */
    protected function bootstrapErrorLogger() {
        $loggerPath = __DIR__ . '/../admin_elements/error_logger.php';
        if (file_exists($loggerPath)) {
            require_once $loggerPath;
            if (function_exists('custom_error_handler')) {
                set_error_handler('custom_error_handler');
            }
            if (function_exists('custom_exception_handler')) {
                set_exception_handler('custom_exception_handler');
            }
            if (function_exists('handle_fatal_error')) {
                register_shutdown_function('handle_fatal_error');
            }
        }
    }
    
    /**
     * Ensure CLI-only execution
     */
    private function validateCLI() {
        if (php_sapi_name() !== 'cli') {
            http_response_code(403);
            die('CLI only - This script can only be run from command line');
        }
    }
    
    /**
     * List all available jobs
     */
    public function listJobs() {
        echo "\n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "                          AVAILABLE CRON JOBS                                  \n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "\n";
        
        printf("%-20s %-15s %s\n", "JOB NAME", "SCHEDULE", "DESCRIPTION");
        echo str_repeat('-', 79) . "\n";
        
        foreach ($this->jobs as $name => $config) {
            printf("%-20s %-15s %s\n", $name, $config['schedule'], $config['description']);
        }
        
        echo "\n";
        echo "CRON SCHEDULE FORMAT:\n";
        echo "  */5 * * * *  = Every 5 minutes\n";
        echo "  0 * * * *    = Every hour\n";
        echo "  0 2 * * *    = Daily at 2:00 AM\n";
        echo "  0 4 * * 0    = Weekly on Sunday at 4:00 AM\n";
        echo "\n";
        echo "USAGE:\n";
        echo "  php CronJobScheduler.php --job=email:queue    # Run specific job\n";
        echo "  php CronJobScheduler.php --list               # List all jobs\n";
        echo "\n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "\n";
    }
    
    /**
     * Run a specific job
     * 
     * @param string $jobName Job identifier (e.g., 'email:queue')
     * @return int Exit code (0 = success, 1 = failure)
     */
    public function runJob($jobName) {
        if (!isset($this->jobs[$jobName])) {
            echo "ERROR: Job '$jobName' not found\n";
            echo "Run with --list to see available jobs\n";
            return 1;
        }
        
        $job = $this->jobs[$jobName];
        $jobFile = __DIR__ . '/' . $job['path'];
        
        if (!file_exists($jobFile)) {
            echo "ERROR: Job file not found: {$jobFile}\n";
            return 1;
        }
        
        echo "\n";
        echo "â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• \n";
        echo "Running job: $jobName\n";
        echo "Class: {$job['class']}\n";
        echo "File: {$job['path']}\n";
        echo "Schedule: {$job['schedule']}\n";
        echo "â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• \n";
        echo "\n";
        
        // Include and run the job
        try {
            require_once $jobFile;
            
            $className = $job['class'];
            if (!class_exists($className)) {
                echo "ERROR: Class '$className' not found in {$jobFile}\n";
                return 1;
            }
            
            $instance = new $className($this->mysqli);
            $instance->run();
            
            echo "\n";
            echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
            echo "Job completed successfully\n";
            echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
            echo "\n";
            
            return 0;
        } catch (Exception $e) {
            echo "\n";
            echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
            echo "Job failed with error:\n";
            echo $e->getMessage() . "\n";
            echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
            echo "\n";
            
            return 1;
        }
    }
    
    /**
     * Show help message
     */
    public function showHelp() {
        echo "\n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "                       CRON JOB SCHEDULER - HELP                               \n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "\n";
        echo "USAGE:\n";
        echo "  php CronJobScheduler.php [OPTIONS]\n";
        echo "\n";
        echo "OPTIONS:\n";
        echo "  --list              List all available cron jobs\n";
        echo "  --job=<name>        Run a specific job (e.g., --job=email:queue)\n";
        echo "  --help              Show this help message\n";
        echo "\n";
        echo "EXAMPLES:\n";
        echo "  php CronJobScheduler.php --list\n";
        echo "  php CronJobScheduler.php --job=email:queue\n";
        echo "  php CronJobScheduler.php --job=db:backup\n";
        echo "\n";
        echo "CRONTAB EXAMPLES:\n";
        echo "  # Email queue worker (every 5 minutes)\n";
        echo "  */5 * * * * cd /path/to/cron && php CronJobScheduler.php --job=email:queue\n";
        echo "\n";
        echo "  # Database backup (daily at 1 AM)\n";
        echo "  0 1 * * * cd /path/to/cron && php CronJobScheduler.php --job=db:backup\n";
        echo "\n";
        echo "  # Email bounce processor (hourly)\n";
        echo "  0 * * * * cd /path/to/cron && php CronJobScheduler.php --job=email:bounce\n";
        echo "\n";
        echo "AVAILABLE JOBS:\n";
        foreach ($this->jobs as $name => $config) {
            echo "  - $name\n";
        }
        echo "\n";
        echo "â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "\n";
    }
    
    /**
     * Generate crontab file
     */
    public function generateCrontab() {
        echo "# â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• \n";
        echo "# FLASH ERP SYSTEM - Cron Jobs Configuration\n";
        echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
        echo "# â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• â• \n";
        echo "\n";
        echo "# Set PHP path (adjust as needed)\n";
        echo "PHP=/usr/bin/php\n";
        echo "CRON_DIR=/var/www/flasherpsystem/dashboard/cron\n";
        echo "LOG_DIR=/var/log/flasherpsystem\n";
        echo "\n";
        
        $categories = [
            'Email Jobs' => ['email:queue', 'email:bounce', 'email:cleanup', 'email:stats'],
            'Database Jobs' => ['db:backup', 'db:cleanup']
        ];
        
        foreach ($categories as $category => $jobNames) {
            echo "# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€\n";
            echo "# $category\n";
            echo "# â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€\n";
            echo "\n";
            
            foreach ($jobNames as $jobName) {
                if (isset($this->jobs[$jobName])) {
                    $job = $this->jobs[$jobName];
                    echo "# {$job['description']}\n";
                    echo "{$job['schedule']} cd \$CRON_DIR && \$PHP CronJobScheduler.php --job=$jobName >> \$LOG_DIR/cron.log 2>&1\n";
                    echo "\n";
                }
            }
        }
        
        echo "# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
        echo "# End of cron jobs configuration\n";
        echo "# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•\n";
    }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CLI Execution
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

if (php_sapi_name() === 'cli') {
    $scheduler = new CronJobScheduler();
    
    // Parse command line options
    $options = getopt('', ['job:', 'list', 'help', 'generate-crontab']);
    
    if (isset($options['list'])) {
        $scheduler->listJobs();
        exit(0);
    } elseif (isset($options['job'])) {
        $exitCode = $scheduler->runJob($options['job']);
        exit($exitCode);
    } elseif (isset($options['generate-crontab'])) {
        $scheduler->generateCrontab();
        exit(0);
    } elseif (isset($options['help'])) {
        $scheduler->showHelp();
        exit(0);
    } else {
        $scheduler->showHelp();
        exit(0);
    }
} else {
    http_response_code(403);
    die('CLI only');
}


