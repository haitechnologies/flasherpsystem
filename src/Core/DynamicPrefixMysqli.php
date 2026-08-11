<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use mysqli_result;
use mysqli_stmt;

/**
 * Dynamic Table Prefix MySQLi Subclass
 *
 * Intercepts MySQLi query execution and statement preparation to rewrite table prefixes at runtime.
 */
class DynamicPrefixMysqli extends mysqli
{
    /**
     * Enable strict/exception reporting so every query failure surfaces as a
     * mysqli_sql_exception (caught by the dashboard error handler and written
     * to erp_backend_error_logs) instead of silently returning false.
     */
    public function __construct(
        ?string $hostname = null,
        ?string $username = null,
        ?string $password = null,
        ?string $database = null,
        ?int $port = null,
        ?string $socket = null
    ) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        parent::__construct($hostname, $username, $password, $database, $port, $socket);
    }

    /**
     * @param string $query
     * @param int $resultMode
     * @return mysqli_result|bool
     */
    #[\ReturnTypeWillChange]
    public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT)
    {
        $query = DynamicPrefixRewriter::rewrite($query);
        return parent::query($query, $resultMode);
    }

    /**
     * @param string $query
     * @return mysqli_stmt|false
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query)
    {
        $query = DynamicPrefixRewriter::rewrite($query);
        return parent::prepare($query);
    }
}
