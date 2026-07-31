-- ============================================================================
-- Migration: Grant customer address modules to Accounts role (id=5)
-- Date: 2026-07-31
-- Description: Accounts users need access to Customer Billing & Shipping
--   Address forms (customer_billing_addresses.php, customer_shipping_addresses.php)
-- ============================================================================

-- 1. Ensure modules exist in erp_modules
INSERT IGNORE INTO erp_modules (slug, module_name, module_type, publish, is_active, created_by, created_at) VALUES
('customer_billing_addresses', 'Customer Billing Addresses', 'module', 1, 1, 1, NOW()),
('customer_shipping_addresses', 'Customer Shipping Addresses', 'module', 1, 1, 1, NOW());

-- 2. Ensure module permission types (view/create/edit/delete) exist
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');

-- 3. Grant view/create/edit/delete to Accounts role (id=5) for both modules
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 5, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');

-- 4. Also ensure System Admin (1) and Super Admin (2) have access (safe guard)
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 1, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');

INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 2, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('customer_billing_addresses','customer_shipping_addresses');
