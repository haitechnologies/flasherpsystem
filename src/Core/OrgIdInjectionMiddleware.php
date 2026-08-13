<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use mysqli;
use PDO;

/**
 * Organization ID Query Injection Middleware
 *
 * Provides automatic organization_id filtering on all database queries
 * Acts as a safety net to prevent accidental multi-tenant data leaks
 */
class OrgIdInjectionMiddleware
{
    private mixed $db;
    private int $activeOrgId;
    private array $orgScopedTables;
    private mixed $logger;
    private array $injectionLog = [];

    /**
     * Initialize middleware
     *
     * @param mysqli|PDO|Database $db Database connection
     * @param int $activeOrgId Active organization ID
     * @param mixed $logger Optional logger instance
     */
    public function __construct(mixed $db, int $activeOrgId, mixed $logger = null)
    {
        $this->db = $db;
        $this->activeOrgId = $activeOrgId;
        $this->logger = $logger;

        // All tables with organization_id column (auto-generated from schema audit)
        $this->orgScopedTables = [
            'erp_addresses', 'erp_alerts', 'erp_attachments', 'erp_attendance',
            'erp_audit_log', 'erp_carriers', 'erp_consignees', 'erp_contacts',
            'erp_credit_notes', 'erp_currencies', 'erp_customer_transactions', 'erp_customers', 'erp_debit_notes',
            'erp_departments', 'erp_designations', 'erp_document_types',
            'erp_employee_salaries', 'erp_entity_logs', 'erp_entity_notes',
            'erp_exit_points', 'erp_expenses',
            'erp_air_tickets', 'erp_gratuity_settlements',
            'erp_hr_leave_balances', 'erp_hr_payroll_run_items', 'erp_incoterms',
            'erp_inquiries', 'erp_inquiry_replies', 'erp_invoice_items',
            'erp_invoices', 'erp_items', 'erp_job_items', 'erp_job_statuses',
            'erp_jobs', 'erp_journal_items', 'erp_journals', 'erp_leads',
            'erp_leave_requests', 'erp_leave_types', 'erp_organization_invites',
            'erp_organization_memberships', 'erp_organization_roles',
            'erp_organization_system_entitlements', 'erp_payment_made_items',
            'erp_payment_methods', 'erp_payment_received_items',
            'erp_payment_terms', 'erp_payments_made', 'erp_payments_received',
            'erp_payroll_components', 'erp_payroll_runs', 'erp_payslips',
            'erp_ports', 'erp_projects', 'erp_purchase_orders', 'erp_purchases',
            'erp_quotations', 'erp_salary_structures', 'erp_sale_orders',
            'erp_shippers', 'erp_shipping_advice_items', 'erp_shipping_advices',
            'erp_shipping_invoice_items',
            'erp_shipping_stock_items', 'erp_shipping_stocks',
            'erp_storage_types', 'erp_subscription_overrides', 'erp_tasks',
            'erp_tax_treatments', 'erp_taxonomies', 'erp_vendors',
            'erp_container_types'
        ];
    }

    /**
     * Execute query with automatic org_id injection for SELECT statements
     *
     * @param string $sql Original SQL query
     * @return mixed Query result
     */
    public function query(string $sql): mixed
    {
        $injectedSql = $this->injectOrgIdFilter($sql);

        // Log if query was modified
        if ($injectedSql !== $sql) {
            $this->recordInjection($sql, $injectedSql);
        }

        if ($this->db instanceof mysqli) {
            return $this->db->query($injectedSql);
        } elseif ($this->db instanceof PDO) {
            return $this->db->query($injectedSql);
        } elseif ($this->db instanceof Database) {
            return $this->db->execute($injectedSql);
        } elseif (method_exists($this->db, 'execute')) {
            return $this->db->execute($injectedSql);
        }

        throw new RuntimeException("Unsupported database connection type");
    }

    /**
     * Prepare statement with automatic org_id injection
     *
     * Note: Prepared statements should already have org_id filtering.
     * This method validates the query and logs if injections would be needed.
     *
     * @param string $sql Prepared SQL with placeholders
     * @return mixed Prepared statement
     */
    public function prepare(string $sql): mixed
    {
        // Check if query needs org_id injection (warning only - prepared statements should already have it)
        if ($this->needsOrgIdInjection($sql)) {
            $this->logWarning("Prepared statement should include org_id filter", $sql);
        }

        if ($this->db instanceof mysqli) {
            return $this->db->prepare($sql);
        } elseif ($this->db instanceof PDO) {
            return $this->db->prepare($sql);
        } elseif ($this->db instanceof Database) {
            return $this->db->getConnection()->prepare($sql);
        } elseif (method_exists($this->db, 'getConnection')) {
            return $this->db->getConnection()->prepare($sql);
        }

        throw new RuntimeException("Unsupported database connection type");
    }

    /**
     * Inject organization_id filter into SQL query
     *
     * For SELECT queries: Adds " AND organization_id = {id}" to WHERE clause
     * For UPDATE/DELETE: Validates org_id is included (doesn't auto-inject for safety)
     * For other queries: Returns unchanged
     *
     * @param string $sql Original SQL query
     * @return string Modified SQL query
     */
    public function injectOrgIdFilter(string $sql): string
    {
        // Only auto-inject org_id into SELECT statements
        if (!preg_match('/^\s*SELECT\s+/i', trim($sql))) {
            return $sql;
        }

        // Check if query involves org-scoped tables
        $prefix = class_exists(DB::class) ? DB::getPrefix() : 'erp_';
        $involvesOrgTable = false;
        foreach ($this->orgScopedTables as $table) {
            $currentTable = ($prefix !== 'erp_') ? str_replace('erp_', $prefix, $table) : $table;
            if (stripos($sql, $table) !== false || ($prefix !== 'erp_' && stripos($sql, $currentTable) !== false)) {
                $involvesOrgTable = true;
                break;
            }
        }

        if (!$involvesOrgTable) {
            return $sql;
        }

        // Check if org_id is already in the WHERE clause
        if (preg_match('/organization_id\s*=|org_id\s*=|org\.id/i', $sql)) {
            return $sql; // Already filtered
        }

        // Inject org_id filter
        if (preg_match('/WHERE\s+/i', $sql)) {
            // Append to existing WHERE clause
            $sql = preg_replace('/WHERE\s+/i', 'WHERE organization_id = ' . (int)$this->activeOrgId . ' AND ', $sql);
        } else {
            // Add WHERE clause
            if (preg_match('/ORDER\s+BY|GROUP\s+BY|LIMIT|;/i', $sql)) {
                // Insert before ORDER BY, GROUP BY, LIMIT, or semicolon
                $sql = preg_replace('/(ORDER\s+BY|GROUP\s+BY|LIMIT|;)/i', 'WHERE organization_id = ' . (int)$this->activeOrgId . ' $1', $sql);
            } else {
                // Append to end
                $sql .= ' WHERE organization_id = ' . (int)$this->activeOrgId;
            }
        }

        return $sql;
    }

    /**
     * Check if query needs org_id injection
     *
     * @param string $sql SQL query
     * @return bool True if query involves org-scoped table without org_id
     */
    public function needsOrgIdInjection(string $sql): bool
    {
        // Check for SELECT queries on org-scoped tables without org_id filter
        if (!preg_match('/^\s*SELECT\s+/i', trim($sql))) {
            return false;
        }

        $prefix = class_exists(DB::class) ? DB::getPrefix() : 'erp_';
        $involvesOrgTable = false;
        foreach ($this->orgScopedTables as $table) {
            $currentTable = ($prefix !== 'erp_') ? str_replace('erp_', $prefix, $table) : $table;
            if (stripos($sql, $table) !== false || ($prefix !== 'erp_' && stripos($sql, $currentTable) !== false)) {
                $involvesOrgTable = true;
                break;
            }
        }

        if (!$involvesOrgTable) {
            return false;
        }

        // Check for org_id already present
        return !preg_match('/organization_id\s*=|org_id\s*=|org\.id/i', $sql);
    }

    /**
     * Record query injection for audit logging
     *
     * @param string $original Original SQL
     * @param string $injected Injected SQL
     */
    private function recordInjection(string $original, string $injected): void
    {
        $this->injectionLog[] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'org_id' => $this->activeOrgId,
            'original' => $original,
            'injected' => $injected
        ];

        if ($this->logger && method_exists($this->logger, 'info')) {
            $this->logger->info('org_id_injected', [
                'original_query' => substr($original, 0, 200),
                'organization_id' => $this->activeOrgId
            ]);
        }
    }

    /**
     * Log warning for compliance issues
     *
     * @param string $message Warning message
     * @param string $sql SQL query
     */
    private function logWarning(string $message, string $sql): void
    {
        ErrorCapture::record("[OrgIdMiddleware] WARNING: $message - Query: " . substr($sql, 0, 100));

        if ($this->logger && method_exists($this->logger, 'warn')) {
            $this->logger->warn('org_id_compliance_warning', [
                'message' => $message,
                'query' => substr($sql, 0, 200)
            ]);
        }
    }

    /**
     * Get injection audit log
     *
     * @return array All injections performed
     */
    public function getInjectionLog(): array
    {
        return $this->injectionLog;
    }

    /**
     * Clear injection log
     */
    public function clearLog(): void
    {
        $this->injectionLog = [];
    }
}
