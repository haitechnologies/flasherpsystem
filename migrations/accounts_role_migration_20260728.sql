-- ============================================================================
-- Migration: Accounts Role Scoping (role_id=5)
-- Date: 2026-07-28
-- Description: Remove all non-accounting module permissions for the Accounts
--              role. Keep only accounting-related modules + projects/jobs.
-- ============================================================================

DELETE p FROM erp_permissions p
JOIN erp_modules m ON m.id = p.module_id
WHERE p.role_id = 5
  AND m.slug NOT IN (
    -- Accounting core
    'items', 'banks',
    -- Customers
    'customers', 'customer_addresses', 'customer_contacts', 'customer_notes', 'customer_files', 'customer_documents',
    -- Sales
    'quotations', 'sale_orders', 'invoices', 'recurring_invoices', 'payments_received', 'credit_notes',
    -- Vendors/Suppliers
    'vendors', 'vendor_addresses', 'vendor_contacts', 'vendor_notes', 'vendor_files', 'vendor_credits',
    -- Purchases
    'expenses', 'purchase_orders', 'purchases', 'recurring_purchases', 'payments_made', 'debit_notes',
    -- Accounting setup & reporting
    'journals', 'accounts', 'accounts_report_categories', 'accounts_report_subcategories',
    'tax_treatments', 'payment_terms', 'currencies', 'payment_methods', 'account_types',
    -- General
    'email_history',
    -- Projects/Jobs (sidebar visibility)
    'projects', 'jobs', 'job_statuses'
  );
