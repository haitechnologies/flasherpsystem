-- ============================================================================
-- Migration: Add 4 missing module slugs (error log noise fix)
-- Date: 2026-07-28
-- Description: Fixes 4 module slugs that exist in code but not in erp_modules
--   (or have wrong slugs), causing `granted(): Module not found` errors.
--   Modules: account_report_categories, account_report_subcategories,
--   air_tickets, gratuity_settlements.
-- ============================================================================

-- 1. FIX: Update existing module slugs from plural to singular
--    (code uses singular; existing rows have plural slugs)
UPDATE erp_modules SET slug = 'account_report_categories'
WHERE slug = 'accounts_report_categories' AND id = 100;

UPDATE erp_modules SET slug = 'account_report_subcategories'
WHERE slug = 'accounts_report_subcategories' AND id = 101;

-- 2. INSERT missing modules (air_tickets, gratuity_settlements)
INSERT IGNORE INTO erp_modules (slug, module_name, module_type, publish, is_active, created_by, created_at) VALUES
('air_tickets', 'Air Tickets', 'module', 1, 1, 1, NOW()),
('gratuity_settlements', 'Gratuity Settlements', 'module', 1, 1, 1, NOW());

-- 3. INSERT module permission types (view/create/edit/delete) for new modules
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('air_tickets','gratuity_settlements');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('air_tickets','gratuity_settlements');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('air_tickets','gratuity_settlements');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('air_tickets','gratuity_settlements');

-- 4. Grant perms to System Admin (role_id=1) for new modules
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 1, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('air_tickets','gratuity_settlements');

-- 5. Grant perms to Super Admin (role_id=2) for new modules
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 2, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('air_tickets','gratuity_settlements');

-- 6. Also ensure account_report_categories/subcategories have all 4 perm types
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories') AND NOT EXISTS (SELECT 1 FROM erp_module_permissions mp WHERE mp.module_id = m.id AND mp.slug = 'view');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories') AND NOT EXISTS (SELECT 1 FROM erp_module_permissions mp WHERE mp.module_id = m.id AND mp.slug = 'create');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories') AND NOT EXISTS (SELECT 1 FROM erp_module_permissions mp WHERE mp.module_id = m.id AND mp.slug = 'edit');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM erp_modules m WHERE m.slug IN ('account_report_categories','account_report_subcategories') AND NOT EXISTS (SELECT 1 FROM erp_module_permissions mp WHERE mp.module_id = m.id AND mp.slug = 'delete');

-- 7. Grant perms for account_report_categories/subcategories to role 1,2 if missing
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 1, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('account_report_categories','account_report_subcategories');

INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 2, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('account_report_categories','account_report_subcategories');
