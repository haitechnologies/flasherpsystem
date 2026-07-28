-- ============================================================================
-- Migration: Grant Shipping module permissions to Operations role (id=4)
-- Date: 2026-07-28
-- Description: Operations users need access to Shipping modules (advices,
--   invoices, stocks, master data) alongside CRM.
-- ============================================================================

-- 1. Ensure module permission types (view/create/edit/delete) exist for all
--    shipping modules (safe to re-run via INSERT IGNORE)
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'view', 'View', 1, 1, 1, NOW() FROM erp_modules m
WHERE m.slug IN ('shipping_advices','shipping_invoices','shipping_stocks','ports','carriers','consignees','shippers');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'create', 'Create', 1, 1, 1, NOW() FROM erp_modules m
WHERE m.slug IN ('shipping_advices','shipping_invoices','shipping_stocks','ports','carriers','consignees','shippers');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'edit', 'Edit', 1, 1, 1, NOW() FROM erp_modules m
WHERE m.slug IN ('shipping_advices','shipping_invoices','shipping_stocks','ports','carriers','consignees','shippers');
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_by, created_at)
SELECT m.id, 'delete', 'Delete', 1, 1, 1, NOW() FROM erp_modules m
WHERE m.slug IN ('shipping_advices','shipping_invoices','shipping_stocks','ports','carriers','consignees','shippers');

-- 2. Grant view/create/edit/delete to Operations role (id=4) for all shipping modules
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 4, mp.id, mp.module_id, 1, 1, 1, NOW()
FROM erp_module_permissions mp
JOIN erp_modules m ON mp.module_id = m.id
WHERE m.slug IN ('shipping_advices','shipping_invoices','shipping_stocks','ports','carriers','consignees','shippers');
