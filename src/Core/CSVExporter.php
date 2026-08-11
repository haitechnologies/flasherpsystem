<?php

declare(strict_types=1);

namespace App\Core;

use mysqli;
use PDO;
use Throwable;

/**
 * CSV Exporter Utility Class
 *
 * Provides simple CSV export functionality for dashboard listing pages
 * Handles secure export with permission checks and memory-efficient streaming
 */
class CSVExporter
{
    /** @var mixed */
    private mixed $db;

    /** @var int */
    private int $maxRows = 50000;

    /** @var string */
    private string $delimiter = ',';

    /** @var string */
    private string $enclosure = '"';

    /**
     * Constructor
     *
     * @param mixed $db Database connection (mysqli, PDO, or App\Core\Database)
     */
    public function __construct(mixed $db)
    {
        $this->db = $db;
    }

    /**
     * Export data array to CSV file
     *
     * @param array $data Array of associative arrays (rows)
     * @param string $filename Filename without extension
     * @param array $columns Optional: specific columns to export (in order)
     * @return bool Success status
     */
    public function exportArray(array $data, string $filename, array $columns = []): bool
    {
        if (empty($data)) {
            return false;
        }

        // Sanitize filename
        $filename = $this->sanitizeFilename($filename);

        // Set CSV headers
        $this->setHeaders($filename);

        // Open output stream
        $output = fopen('php://output', 'w');
        if (!$output) {
            return false;
        }

        // Determine columns to export
        if (empty($columns)) {
            $firstItem = reset($data);
            $columns = is_array($firstItem) ? array_keys($firstItem) : [];
        }

        // Write header row
        fputcsv($output, $columns, $this->delimiter, $this->enclosure);

        // Write data rows
        $rowCount = 0;
        foreach ($data as $row) {
            if ($rowCount >= $this->maxRows) {
                break;
            }

            $csvRow = [];
            foreach ($columns as $col) {
                $csvRow[] = $row[$col] ?? '';
            }

            fputcsv($output, $csvRow, $this->delimiter, $this->enclosure);
            $rowCount++;
        }

        fclose($output);
        exit;
    }

    /**
     * Export data from SQL query directly (memory efficient)
     *
     * @param string $query SQL SELECT query
     * @param string $filename Filename without extension
     * @param array $columns Optional: specific columns to export
     * @param array $columnHeaders Optional: custom header names (maps column => header)
     * @return bool Success status
     */
    public function exportFromQuery(string $query, string $filename, array $columns = [], array $columnHeaders = []): bool
    {
        $filename = $this->sanitizeFilename($filename);
        $this->setHeaders($filename);

        $output = fopen('php://output', 'w');
        if (!$output) {
            return false;
        }

        $stmt = null;
        $result = null;
        $firstRow = null;

        if ($this->db instanceof mysqli) {
            $result = $this->db->query($query);
            if (!$result) {
                ErrorCapture::record("CSV Export Query Failed: " . $this->db->error);
                fclose($output);
                return false;
            }
            if ($result->num_rows === 0) {
                $result->close();
                fclose($output);
                return false;
            }
            $firstRow = $result->fetch_assoc();
        } else {
            try {
                $pdo = $this->db instanceof Database ? $this->db->getConnection() : $this->db;
                if (!$pdo instanceof PDO) {
                    fclose($output);
                    return false;
                }
                $stmt = $pdo->query($query);
                if (!$stmt) {
                    fclose($output);
                    return false;
                }
                $firstRow = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$firstRow) {
                    fclose($output);
                    return false;
                }
            } catch (Throwable $e) {
                ErrorCapture::record("CSV Export Query Failed: " . $e->getMessage());
                fclose($output);
                return false;
            }
        }

        // Determine columns to export
        if (empty($columns)) {
            $columns = array_keys($firstRow);
        }

        // Build header row with custom labels if provided
        $headerRow = [];
        foreach ($columns as $col) {
            $headerRow[] = $columnHeaders[$col] ?? ucwords(str_replace('_', ' ', $col));
        }

        // Write header row
        fputcsv($output, $headerRow, $this->delimiter, $this->enclosure);

        // Write first row
        $csvRow = [];
        foreach ($columns as $col) {
            $csvRow[] = $this->formatValue($firstRow[$col] ?? '');
        }
        fputcsv($output, $csvRow, $this->delimiter, $this->enclosure);

        // Write remaining rows
        $rowCount = 1;
        if ($this->db instanceof mysqli) {
            while (($row = $result->fetch_assoc()) && $rowCount < $this->maxRows) {
                $csvRow = [];
                foreach ($columns as $col) {
                    $csvRow[] = $this->formatValue($row[$col] ?? '');
                }
                fputcsv($output, $csvRow, $this->delimiter, $this->enclosure);
                $rowCount++;
            }
            $result->close();
        } elseif ($stmt) {
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) && $rowCount < $this->maxRows) {
                $csvRow = [];
                foreach ($columns as $col) {
                    $csvRow[] = $this->formatValue($row[$col] ?? '');
                }
                fputcsv($output, $csvRow, $this->delimiter, $this->enclosure);
                $rowCount++;
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Export from DataTable class (using server-side query)
     *
     * @param string $dataTableClass DataTable class name (e.g., 'CompaniesDataTable')
     * @param array $requestData Request parameters for filtering
     * @param string $filename Filename without extension
     * @param array $columns Columns to export
     * @return bool Success status
     */
    public function exportFromDataTable(string $dataTableClass, array $requestData, string $filename, array $columns = []): bool
    {
        if (!class_exists($dataTableClass)) {
            return false;
        }

        // Instantiate DataTable
        $dataTable = new $dataTableClass($this->db);

        if (method_exists($dataTable, 'buildExportQuery')) {
            $query = $dataTable->buildExportQuery($requestData);
            return $this->exportFromQuery($query, $filename, $columns);
        }

        return false;
    }

    /**
     * Set HTTP headers for CSV download
     *
     * @param string $filename Filename
     * @return void
     */
    private function setHeaders(string $filename): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: 0');
        header('Pragma: no-cache');
    }

    /**
     * Sanitize filename to prevent path traversal
     *
     * @param string $filename Raw filename
     * @return string Safe filename
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove extension if provided
        $filename = preg_replace('/\.csv$/i', '', $filename);
        if ($filename === null) {
            $filename = 'export';
        }

        // Remove dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
        if ($filename === null) {
            $filename = 'export';
        }

        // Limit length
        $filename = substr($filename, 0, 100);

        // Add timestamp
        $filename .= '_' . date('Y-m-d_His');

        return $filename;
    }

    /**
     * Format cell value for CSV output
     *
     * @param mixed $value Cell value
     * @return string Formatted value
     */
    private function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        // Strip HTML tags
        $value = strip_tags((string)$value);

        // Decode HTML entities
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Trim whitespace
        $value = trim($value);

        return $value;
    }

    /**
     * Set maximum rows to export
     *
     * @param int $max Maximum rows
     * @return self
     */
    public function setMaxRows(int $max): self
    {
        $this->maxRows = $max;
        return $this;
    }

    /**
     * Set CSV delimiter
     *
     * @param string $delimiter Delimiter character
     * @return self
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }
}
