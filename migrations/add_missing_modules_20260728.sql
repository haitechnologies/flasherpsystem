-- ============================================================================
-- Migration: Add 4 missing module slugs (error log noise fix)
-- Date: 2026-07-28
-- Description: INSERTS 4 module slugs that exist in code but not in erp_modules,
--              causing 2191 `granted(): Module not found` errors per session.
--              Modules: account_report_categories, account_report_subcategories,
--              air_tickets, gratuity_settlements.
--              Grants all perms to System Admin (role_id=1) and Super Admin (role_id=2).
-- ============================================================================

-- 1. INSERT missing modules into erp_modules
INSERT INTO erp_modules (slug, module_name, module_type, publish, is_active, created_by, created_at) VALUES
('account_report_categories', 'Account Report Categories', 'module', 1, 1, 1, NOW()),
('account_report_subcategories', 'Account Report Subcategories', 'module', 1, 1, 1, NOW()),
('air_tickets', 'Air Tickets', 'module', 1, 1, 1, NOW()),
('gratuity_settlements', 'Gratuity Settlements', 'module', 1, 1, 1, NOW());

-- 2. INSERT module permission types (view/create/edit/delete) for all 4 modules
INSERT INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');
INSERT INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');
INSERT INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');
INSERT INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');

-- 3. Grant all perms to System Admin (role_id=1)
INSERT INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 1, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');

-- 4. Grant all perms to Super Admin (role_id=2)
INSERT INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 2, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('account_report_categories','account_report_subcategories','air_tickets','gratuity_settlements');
