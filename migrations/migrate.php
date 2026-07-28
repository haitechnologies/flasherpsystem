<?php

/**
 * Database Migration CLI
 *
 * Usage:
 *   php migrations/migrate.php                # Run all pending migrations
 *   php migrations/migrate.php --status       # Show migration status
 *   php migrations/migrate.php --verify       # Dry-run verification (no recording)
 *
 * Migrations are PHP files in the migrations/ directory that return a callable:
 *
 *   return function (mysqli $conn): void {
 *       // your migration SQL here
 *   };
 *
 *   // Or with metadata:
 *   return [
 *       'description' => 'Add timestamps to tables',
 *       'up' => function (mysqli $conn): void { ... },
 *   ];
 */

declare(strict_types=1);

// Ensure CLI only
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

$projectRoot = dirname(__DIR__);

require_once $projectRoot . '/vendor/autoload.php';
require_once $projectRoot . '/config/database.php';

use App\Database\MigrationRunner;

$migrationsDir = $projectRoot . '/migrations';
$runner = new MigrationRunner($mysqli, $migrationsDir);

// Parse CLI arguments
$args = array_slice($argv, 1);
$command = $args[0] ?? '--run';

switch ($command) {
    case '--status':
        $runner->printStatus();
        break;

    case '--verify':
        echo "[migrate] Running in DRY-RUN mode (migrations will NOT be recorded)...\n\n";
        $result = $runner->runPending(dryRun: true);
        echo "\n[migrate] Dry-run complete.\n";
        echo "  Ran: " . count($result['ran']) . "\n";
        echo "  Skipped: " . count($result['skipped']) . "\n";
        echo "  Errors: " . count($result['errors']) . "\n";
        if (!empty($result['errors'])) {
            exit(1);
        }
        break;

    case '--run':
    default:
        $pending = $runner->getPendingMigrations();
        if (empty($pending)) {
            echo "[migrate] Nothing to migrate. All migrations are up to date.\n";
            break;
        }

        echo "[migrate] Found " . count($pending) . " pending migration(s).\n\n";

        $result = $runner->runPending();

        echo "\n[migrate] Migration complete.\n";
        echo "  Ran: " . count($result['ran']) . "\n";
        echo "  Skipped: " . count($result['skipped']) . "\n";
        echo "  Errors: " . count($result['errors']) . "\n";

        if (!empty($result['errors'])) {
            exit(1);
        }
        break;
}

echo "\n";
