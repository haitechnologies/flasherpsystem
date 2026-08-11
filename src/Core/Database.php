<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Core Database Wrapper
 *
 * Provides a secure, strict PDO connection with prepared statement helpers.
 * Adheres to PSR.md specifications: no string interpolation, strict types, and PDO exceptions.
 */
class Database
{
    private ?PDO $pdo = null;
    private string $host;
    private string $db;
    private string $user;
    private string $password;
    private string $charset;
    private int $savepointDepth = 0;

    /**
     * Initialize connection settings from environment or fallback database configs
     */
    public function __construct(
        ?string $host = null,
        ?string $db = null,
        ?string $user = null,
        ?string $password = null,
        string $charset = 'utf8mb4'
    ) {
        $this->host = $host ?? $GLOBALS['DB']['HOSTNAME'] ?? $_ENV['DB_HOSTNAME'] ?? 'localhost';
        $this->db = $db ?? $GLOBALS['DB']['DATABASE'] ?? $_ENV['DB_DATABASE'] ?? '';
        $this->user = $user ?? $GLOBALS['DB']['USERNAME'] ?? $_ENV['DB_USERNAME'] ?? '';
        $this->password = $password ?? $GLOBALS['DB']['PASSWORD'] ?? $_ENV['DB_PASSWORD'] ?? '';
        $this->charset = $charset;
    }

    /**
     * Retrieve the active PDO connection instance
     *
     * Establishes the connection if not already active.
     *
     * @return PDO The active PDO connection
     * @throws RuntimeException if connection establishment fails
     */
    public function getConnection(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $dsn = "mysql:host={$this->host};dbname={$this->db};charset={$this->charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new DynamicPrefixPdo($dsn, $this->user, $this->password, $options);
            return $this->pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Database Connection Failure: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Execute a SQL query with prepared parameters
     *
     * @param string $sql The SQL query containing placeholders
     * @param array $params Parameter bindings for the query
     * @return PDOStatement The executed statement
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $pdo = $this->getConnection();
        try {
            $params = DynamicPrefixRewriter::rewriteParams($params);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Query Execution Failure: " . $e->getMessage() . " | SQL: " . $sql,
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Fetch all matching rows of a query
     *
     * @param string $sql The SQL query containing placeholders
     * @param array $params Parameter bindings for the query
     * @return array The list of matched rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->execute($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single matching row of a query
     *
     * @param string $sql The SQL query containing placeholders
     * @param array $params Parameter bindings for the query
     * @return array|null The matched row or null if not found
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->execute($sql, $params)->fetch();
        return is_array($result) ? $result : null;
    }

    /**
     * Execute an INSERT query and return the last insert ID
     *
     * @param string $sql The INSERT query containing placeholders
     * @param array $params Parameter bindings for the query
     * @return string The generated auto-increment ID
     */
    public function insert(string $sql, array $params = []): string
    {
        $pdo = $this->getConnection();
        try {
            $params = DynamicPrefixRewriter::rewriteParams($params);
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Insert Execution Failure: " . $e->getMessage(),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * Start a new transaction
     *
     * Supports nesting via SAVEPOINTs so services that begin their own
     * transactions may safely call others (e.g. JournalService::createJournal)
     * which also begin transactions.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        if ($this->getConnection()->inTransaction()) {
            $this->savepointDepth++;
            $savepoint = 'sp' . $this->savepointDepth;
            $this->getConnection()->exec("SAVEPOINT {$savepoint}");
            return true;
        }
        return $this->getConnection()->beginTransaction();
    }

    /**
     * Commit the active transaction
     *
     * @return bool
     */
    public function commit(): bool
    {
        if ($this->savepointDepth > 0) {
            $savepoint = 'sp' . $this->savepointDepth;
            $this->getConnection()->exec("RELEASE SAVEPOINT {$savepoint}");
            $this->savepointDepth--;
            return true;
        }
        return $this->getConnection()->commit();
    }

    /**
     * Rollback the active transaction
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        if ($this->savepointDepth > 0) {
            $savepoint = 'sp' . $this->savepointDepth;
            $this->getConnection()->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            $this->getConnection()->exec("RELEASE SAVEPOINT {$savepoint}");
            $this->savepointDepth--;
            return true;
        }
        return $this->getConnection()->rollBack();
    }

    /**
     * Check if a transaction is currently active
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getConnection()->inTransaction();
    }
}
