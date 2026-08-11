<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Core\Database;
use App\Core\DB;
use App\Core\ErrorCapture;

/**
 * Abstract Base Class for DataTable Handlers
 *
 * Provides common functionality for all DataTable modules:
 * - Query building
 * - Search and filtering
 * - Sorting and pagination
 * - Row formatting
 * - Error handling
 */
abstract class BaseDataTable
{
    /** @var int|null */
    protected ?int $userId = null;

    /** @var int|null */
    protected ?int $roleId = null;

    /** @var int|null */
    protected ?int $organizationId = null;

    /** @var string */
    protected $table = '';

    /** @var array */
    protected $searchFields = [];

    /** @var array */
    protected $sortableColumns = [];

    /** @var int */
    protected int $totalRecords = 0;

    /** @var int */
    protected int $filteredRecords = 0;

    /** @var array */
    protected array $data = [];

    /** @var int Current row number (1-based sequential) */
    protected int $rowNumber = 0;

    /** @var array Cache for related data to prevent N+1 queries */
    protected array $relatedDataCache = [];

    /** @var Database */
    protected Database $db;

    /** @var array Bind parameters for query execution */
    protected array $params = [];

    /**
     * Constructor
     *
     * @param mixed $db
     * @param int|null $userId
     * @param int|null $roleId
     * @param int|null $organizationId
     */
    public function __construct(mixed $db, ?int $userId = null, ?int $roleId = null, ?int $organizationId = null)
    {
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->organizationId = $organizationId;

        if ($db instanceof Database) {
            $this->db = $db;
        } else {
            $this->db = new Database();
        }
    }

    /**
     * Main DataTable request processor
     */
    public function process(array $requestData): array
    {
        // Reset query parameters state
        $this->params = [];

        try {
            $draw = (int)($requestData['draw'] ?? 1);
            $start = (int)($requestData['start'] ?? 0);
            $length = (int)($requestData['length'] ?? 10);

            $baseQuery = $this->buildBaseQuery($requestData);
            $this->totalRecords = $this->countRecords($baseQuery);

            $searchClause = $this->buildSearchClause($requestData);
            if ($searchClause !== '') {
                $baseQuery .= ' ' . $searchClause;
            }

            $this->filteredRecords = $this->countRecords($baseQuery);

            $orderClause = $this->buildOrderClause($requestData);
            if ($orderClause !== '') {
                $baseQuery .= ' ' . $orderClause;
            }

            $limitClause = $this->buildLimitClause($requestData, $start, $length);
            if ($limitClause !== '') {
                $baseQuery .= ' ' . $limitClause;
            }

            // Execute query using PDO Database wrapper
            $rows = $this->db->fetchAll($baseQuery, $this->params);

            // Pre-fetch related data for all rows (prevents N+1 queries)
            if (!empty($rows)) {
                $this->prepareRelatedData($rows, $requestData);
            }

            // Format rows using pre-fetched data
            $this->data = [];
            $this->rowNumber = $start;
            foreach ($rows as $row) {
                $this->rowNumber++;
                $this->data[] = $this->formatRow($row, $requestData);
            }

            return [
                'draw' => $draw,
                'recordsTotal' => $this->totalRecords,
                'recordsFiltered' => $this->filteredRecords,
                'data' => $this->data
            ];
        } catch (\Throwable $e) {
            ErrorCapture::record('DataTable Error (' . static::class . ') [' . get_class($e) . ']: ' . $e->getMessage());
            return $this->errorResponse('Error processing request: ' . $e->getMessage());
        }
    }

    /**
     * Hook for subclasses to pre-fetch related data for all rows
     * Override this method to fetch data in bulk instead of per-row
     * Store data in $this->relatedDataCache
     *
     * @param array $rows All rows about to be formatted
     * @param array $requestData Request parameters
     */
    protected function prepareRelatedData(array $rows, array $requestData = []): void
    {
        // Default: no related data needed. Override in subclass if needed.
    }

    /**
     * Build base query for this module
     */
    protected function buildBaseQuery($requestData)
    {
        return "SELECT * FROM `" . $this->table . "` WHERE id > 0" . $this->getOrgIdWhereClause();
    }

    /**
     * Get WHERE clause fragment for organization_id filtering
     * Returns empty string if table doesn't have organization_id or org_id not set
     * Returns " AND organization_id = :active_org_id" if both conditions met
     */
    protected function getOrgIdWhereClause(): string
    {
        if ($this->organizationId === null) {
            return '';
        }

        // Check if table has organization_id column via INFORMATION_SCHEMA (using PDO)
        $hasOrgIdColumn = false;
        try {
            $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :table_name
                    AND COLUMN_NAME = 'organization_id'
                    LIMIT 1";
            $result = $this->db->fetchOne($sql, ['table_name' => $this->table]);
            if ($result !== null) {
                $hasOrgIdColumn = true;
            }
        } catch (\Throwable $e) {
            ErrorCapture::record("BaseDataTable: Error checking org_id column: " . $e->getMessage());
        }

        if (!$hasOrgIdColumn) {
            return ''; // Table doesn't have org_id column
        }

        $this->params['active_org_id'] = (int)$this->organizationId;
        return " AND `organization_id` = :active_org_id";
    }

    /**
     * Format single row of data
     */
    abstract protected function formatRow($row, $requestData = []);

    /**
     * Build search WHERE clause
     */
    protected function buildSearchClause($requestData)
    {
        $searchValue = $requestData['search']['value'] ?? '';
        if (empty($searchValue) || empty($this->searchFields)) {
            return '';
        }

        $conditions = [];
        $searchValue = '%' . $searchValue . '%';

        foreach ($this->searchFields as $i => $field) {
            $key = 'search_val_' . $i;
            $conditions[] = "{$field} LIKE :{$key}";
            $this->params[$key] = $searchValue;
        }

        return 'AND (' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Build ORDER BY clause
     */
    protected function buildOrderClause($requestData)
    {
        $orderColumn = (int)($requestData['order'][0]['column'] ?? 0);
        $orderDir = strtoupper($requestData['order'][0]['dir'] ?? 'ASC');

        if (!in_array($orderDir, ['ASC', 'DESC'], true)) {
            $orderDir = 'ASC';
        }

        $column = $this->sortableColumns[$orderColumn] ?? 'id';
        return 'ORDER BY ' . $column . ' ' . $orderDir;
    }

    /**
     * Build LIMIT clause
     */
    protected function buildLimitClause($requestData, int $start, int $length): string
    {
        return "LIMIT {$length} OFFSET {$start}";
    }

    /**
     * Count total records for a query
     */
    protected function countRecords(string $query): int
    {
        try {
            $countQuery = "SELECT COUNT(*) as total FROM ({$query}) as count_table";
            $row = $this->db->fetchOne($countQuery, $this->params);
            return (int)($row['total'] ?? 0);
        } catch (\Throwable $e) {
            ErrorCapture::record("BaseDataTable: Count query failed: " . $e->getMessage() . " | SQL: " . $query);
            return 0;
        }
    }

    /**
     * Build error response
     */
    protected function errorResponse(string $message): array
    {
        return [
            'draw' => 1,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => $message
        ];
    }



    protected function isGranted(string $action, string $module): bool
    {
        return function_exists('granted_') && granted_($action, $module);
    }

    protected function formatTimeAgo(string $date): string
    {
        return function_exists('timeAgo') ? timeAgo($date) : $date;
    }

    protected function sanitize(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    protected function formatDecimal(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals);
    }

    protected function formatDate(string $date, string $format = 'd M Y'): string
    {
        if ($date === '' || $date === '0000-00-00' || $date === '1970-01-01') {
            return '';
        }
        $ts = strtotime($date);
        return $ts !== false ? date($format, $ts) : $date;
    }

    protected function fetchLookupMap(string $table, array $ids, string $valueField): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, {$valueField} AS value_label FROM `{$table}` WHERE id IN ({$placeholders})";
        $rows = $this->db->fetchAll($sql, $ids);

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = (string)($row['value_label'] ?? '');
        }
        return $map;
    }

    protected function truncateText(string $text, int $length = 100): string
    {
        $text = strip_tags($text);
        if (mb_strlen($text) <= $length) {
            return htmlspecialchars($text);
        }
        return htmlspecialchars(mb_substr($text, 0, $length)) . '&hellip;';
    }
}
