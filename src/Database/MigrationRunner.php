<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\DB;
use mysqli;

/**
 * Database Migration Runner
 *
 * Tracks and executes versioned, idempotent schema migrations.
 * Each migration is a PHP file in the migrations directory that returns
 * a callable accepting a mysqli connection.
 *
 * Usage:
 *   php database/migrate.php                # Run pending migrations
 *   php database/migrate.php --status       # Show migration status
 *   php database/migrate.php --verify       # Dry-run to verify idempotency
 *   php database/migrate.php --rollback     # (future) Rollback last batch
 */
class MigrationRunner
{
    private mysqli $conn;
    private string $migrationsDir;
    private string $trackingTable;

    public function __construct(mysqli $conn, string $migrationsDir, string $trackingTable = '')
    {
        $this->conn = $conn;
        $this->migrationsDir = rtrim($migrationsDir, '/\\');

        // Resolve tracking table name using DB class prefix
        if ($trackingTable !== '') {
            $this->trackingTable = $trackingTable;
        } else {
            $this->trackingTable = DB::getPrefix() . 'schema_migrations';
        }

        $this->ensureTrackingTable();
    }

    /**
     * Create the migrations tracking table if it doesn't exist.
     */
    private function ensureTrackingTable(): void
    {
        $table = $this->conn->real_escape_string($this->trackingTable);
        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `version` VARCHAR(200) NOT NULL,
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `batch` INT NOT NULL DEFAULT 1,
            `executed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Tracks executed database schema migrations'";

        if (!$this->conn->query($sql)) {
            throw new \RuntimeException("Failed to create tracking table: {$this->conn->error}");
        }
    }

    /**
     * Get all already-executed migration versions.
     *
     * @return array<string, array{version: string, batch: int, executed_at: string}>
     */
    public function getExecutedMigrations(): array
    {
        $table = $this->conn->real_escape_string($this->trackingTable);
        $result = $this->conn->query("SELECT version, batch, executed_at FROM `{$table}` ORDER BY id ASC");
        $executed = [];
        while ($row = $result->fetch_assoc()) {
            $executed[$row['version']] = $row;
        }
        return $executed;
    }

    /**
     * Get all migration files from the migrations directory.
     *
     * @return string[] Sorted list of migration filenames (without path)
     */
    public function getAvailableMigrations(): array
    {
        $files = glob($this->migrationsDir . '/*.php');
        if ($files === false) {
            return [];
        }

        $migrations = [];
        foreach ($files as $file) {
            $basename = basename($file, '.php');
            $migrations[] = $basename;
        }
        sort($migrations);
        return $migrations;
    }

    /**
     * Get pending (not yet executed) migrations.
     *
     * @return string[]
     */
    public function getPendingMigrations(): array
    {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        return array_filter($available, fn(string $m) => !isset($executed[$m]));
    }

    /**
     * Get the next batch number.
     */
    private function getNextBatch(): int
    {
        $table = $this->conn->real_escape_string($this->trackingTable);
        $result = $this->conn->query("SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM `{$table}`");
        $row = $result->fetch_assoc();
        return (int) $row['next_batch'];
    }

    /**
     * Run all pending migrations.
     *
     * @param bool $dryRun If true, run but don't record as executed
     * @return array{ran: string[], skipped: string[], errors: array<string, string>}
     */
    public function runPending(bool $dryRun = false): array
    {
        $pending = $this->getPendingMigrations();
        $batch = $this->getNextBatch();
        $ran = [];
        $skipped = [];
        $errors = [];

        foreach ($pending as $version) {
            $file = $this->migrationsDir . '/' . $version . '.php';
            if (!is_file($file)) {
                $skipped[] = $version;
                continue;
            }

            echo "[migrate] Running: {$version}\n";

            try {
                $migration = require $file;

                if (is_callable($migration)) {
                    $migration($this->conn);
                } elseif (is_array($migration) && isset($migration['up']) && is_callable($migration['up'])) {
                    $migration['up']($this->conn);
                } else {
                    throw new \RuntimeException("Migration file must return a callable or ['up' => callable]");
                }

                if (!$dryRun) {
                    $this->recordMigration($version, $migration['description'] ?? '', $batch);
                }

                $ran[] = $version;
                echo "[migrate] ✓ Completed: {$version}\n";
            } catch (\Throwable $e) {
                $errors[$version] = $e->getMessage();
                echo "[migrate] ✗ FAILED: {$version} — {$e->getMessage()}\n";
                // Stop on first error
                break;
            }
        }

        return ['ran' => $ran, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Record a migration as executed.
     */
    private function recordMigration(string $version, string $description, int $batch): void
    {
        $table = $this->conn->real_escape_string($this->trackingTable);
        $stmt = $this->conn->prepare(
            "INSERT INTO `{$table}` (`version`, `description`, `batch`) VALUES (?, ?, ?)"
        );
        if (!$stmt) {
            throw new \RuntimeException("Failed to prepare migration record: {$this->conn->error}");
        }
        $stmt->bind_param('ssi', $version, $description, $batch);
        if (!$stmt->execute()) {
            throw new \RuntimeException("Failed to record migration: {$stmt->error}");
        }
        $stmt->close();
    }

    /**
     * Print migration status report.
     */
    public function printStatus(): void
    {
        $executed = $this->getExecutedMigrations();
        $available = $this->getAvailableMigrations();

        echo "\n=== Migration Status ===\n\n";
        echo "Tracking table: {$this->trackingTable}\n";
        echo "Migrations dir: {$this->migrationsDir}\n\n";

        if (empty($available)) {
            echo "No migration files found.\n";
            return;
        }

        echo str_pad('Migration', 60) . str_pad('Status', 12) . str_pad('Batch', 8) . "Executed At\n";
        echo str_repeat('-', 110) . "\n";

        foreach ($available as $version) {
            if (isset($executed[$version])) {
                $info = $executed[$version];
                echo str_pad($version, 60)
                    . str_pad('✓ Ran', 12)
                    . str_pad((string) $info['batch'], 8)
                    . $info['executed_at'] . "\n";
            } else {
                echo str_pad($version, 60) . "⏳ Pending\n";
            }
        }

        $pending = count($available) - count(array_intersect_key(
            array_flip($available),
            $executed
        ));
        echo "\nTotal: " . count($available) . " | Executed: " . (count($available) - $pending) . " | Pending: {$pending}\n";
    }
}
