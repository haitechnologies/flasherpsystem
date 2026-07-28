<?php

declare(strict_types=1);

namespace App\Database;

use App\Core\DB;
use mysqli;

/**
 * Migration Helper Functions
 *
 * Idempotent schema inspection utilities for use within migration files.
 * All methods check state before acting, making migrations safe to re-run.
 */
class MigrationHelpers
{
    /**
     * Check if a table exists.
     */
    public static function tableExists(mysqli $conn, string $table): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Check if a column exists on a table.
     */
    public static function columnExists(mysqli $conn, string $table, string $column): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Check if an index exists on a table.
     */
    public static function indexExists(mysqli $conn, string $table, string $indexName): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $table, $indexName);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Check if a foreign key constraint exists.
     */
    public static function foreignKeyExists(mysqli $conn, string $table, string $constraintName): bool
    {
        $stmt = $conn->prepare(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY' LIMIT 1"
        );
        $stmt->bind_param('ss', $table, $constraintName);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    /**
     * Get the current column type for a given column.
     *
     * @return string|null The COLUMN_TYPE (e.g., 'int', 'varchar(100)') or null if column doesn't exist
     */
    public static function getColumnType(mysqli $conn, string $table, string $column): ?string
    {
        $stmt = $conn->prepare(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['COLUMN_TYPE'] : null;
    }

    /**
     * Get the current DATA_TYPE for a given column.
     *
     * @return string|null The DATA_TYPE (e.g., 'int', 'varchar', 'datetime') or null
     */
    public static function getColumnDataType(mysqli $conn, string $table, string $column): ?string
    {
        $stmt = $conn->prepare(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['DATA_TYPE'] : null;
    }

    /**
     * Get the storage engine for a table.
     */
    public static function getTableEngine(mysqli $conn, string $table): ?string
    {
        $stmt = $conn->prepare(
            "SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['ENGINE'] : null;
    }

    /**
     * Get the collation for a table.
     */
    public static function getTableCollation(mysqli $conn, string $table): ?string
    {
        $stmt = $conn->prepare(
            "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1"
        );
        $stmt->bind_param('s', $table);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row ? $row['TABLE_COLLATION'] : null;
    }

    /**
     * Execute a SQL statement with error logging.
     *
     * @throws \RuntimeException on failure
     */
    public static function exec(mysqli $conn, string $sql, string $label = ''): void
    {
        if (!$conn->query($sql)) {
            $msg = $label ? "[{$label}] " : '';
            throw new \RuntimeException("{$msg}SQL Error: {$conn->error}\nSQL: {$sql}");
        }
        if ($label) {
            echo "    ✓ {$label}\n";
        }
    }

    /**
     * Add a column if it doesn't already exist.
     */
    public static function addColumnIfNotExists(
        mysqli $conn,
        string $table,
        string $column,
        string $definition,
        string $after = ''
    ): void {
        if (self::columnExists($conn, $table, $column)) {
            echo "    · {$table}.{$column} already exists\n";
            return;
        }

        $afterClause = $after ? " AFTER `{$after}`" : '';
        self::exec($conn, "ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}{$afterClause}", "Add {$table}.{$column}");
    }

    /**
     * Add an index if it doesn't already exist.
     */
    public static function addIndexIfNotExists(
        mysqli $conn,
        string $table,
        string $indexName,
        string $columns,
        bool $unique = false
    ): void {
        if (self::indexExists($conn, $table, $indexName)) {
            echo "    · Index {$indexName} already exists on {$table}\n";
            return;
        }

        $type = $unique ? 'UNIQUE KEY' : 'KEY';
        self::exec($conn, "ALTER TABLE `{$table}` ADD {$type} `{$indexName}` ({$columns})", "Add index {$indexName} on {$table}");
    }

    /**
     * Drop an index if it exists.
     */
    public static function dropIndexIfExists(mysqli $conn, string $table, string $indexName): void
    {
        if (!self::indexExists($conn, $table, $indexName)) {
            echo "    · Index {$indexName} does not exist on {$table}\n";
            return;
        }

        self::exec($conn, "ALTER TABLE `{$table}` DROP INDEX `{$indexName}`", "Drop index {$indexName} from {$table}");
    }

    /**
     * Modify a column type (only if current type doesn't match target).
     */
    public static function modifyColumnIfNeeded(
        mysqli $conn,
        string $table,
        string $column,
        string $newDefinition,
        string $targetDataType
    ): void {
        $currentType = self::getColumnDataType($conn, $table, $column);
        if ($currentType === null) {
            echo "    · {$table}.{$column} does not exist — skipping modify\n";
            return;
        }

        if (strtolower($currentType) === strtolower($targetDataType)) {
            echo "    · {$table}.{$column} already {$targetDataType}\n";
            return;
        }

        self::exec($conn, "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` {$newDefinition}", "Modify {$table}.{$column} → {$targetDataType}");
    }

    /**
     * Add a foreign key constraint if it doesn't already exist.
     */
    public static function addForeignKeyIfNotExists(
        mysqli $conn,
        string $table,
        string $constraintName,
        string $column,
        string $refTable,
        string $refColumn = 'id',
        string $onDelete = 'RESTRICT',
        string $onUpdate = 'CASCADE'
    ): void {
        if (self::foreignKeyExists($conn, $table, $constraintName)) {
            echo "    · FK {$constraintName} already exists on {$table}\n";
            return;
        }

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` "
            . "FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`) "
            . "ON DELETE {$onDelete} ON UPDATE {$onUpdate}";
        self::exec($conn, $sql, "Add FK {$constraintName} on {$table}");
    }

    /**
     * Resolve a table name using the dynamic prefix.
     */
    public static function table(string $baseName): string
    {
        return DB::getPrefix() . $baseName;
    }
}
