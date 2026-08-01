-- ============================================================================
-- Migration: Add 5 missing module slugs (error log noise fix)
-- Date: 2026-08-01
-- Fixes: customer_statement, customer_transactions, customer_comments,
--        customer_billing_addresses, carriers
-- All produce "granted(): Module not found" in error logs
-- ============================================================================

INSERT IGNORE INTO `erp_modules` (slug, module_name, module_type, publish, is_active, created_by, created_at) VALUES
('customer_statement', 'Customer Statement', 'module', 1, 1, 1, NOW()),
('customer_transactions', 'Customer Transactions', 'module', 1, 1, 1, NOW()),
('customer_comments', 'Customer Comments', 'module', 1, 1, 1, NOW()),
('customer_billing_addresses', 'Customer Billing Addresses', 'module', 1, 1, 1, NOW()),
('carriers', 'Carriers', 'module', 1, 1, 1, NOW());

-- Grant all 4 permissions for each new module
INSERT IGNORE INTO `erp_module_permissions` (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM `erp_modules` m WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');
INSERT IGNORE INTO `erp_module_permissions` (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM `erp_modules` m WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');
INSERT IGNORE INTO `erp_module_permissions` (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM `erp_modules` m WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');
INSERT IGNORE INTO `erp_module_permissions` (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM `erp_modules` m WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');

-- Grant perms to System Admin (role_id=1) and Super Admin (role_id=2)
INSERT IGNORE INTO `erp_permissions` (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 1, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM `erp_module_permissions` mp
JOIN `erp_modules` m ON mp.module_id = m.id
WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');

INSERT IGNORE INTO `erp_permissions` (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 2, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM `erp_module_permissions` mp
JOIN `erp_modules` m ON mp.module_id = m.id
WHERE m.slug IN ('customer_statement','customer_transactions','customer_comments','customer_billing_addresses','carriers');
