-- ============================================================================
-- Migration: Grant setup modules for Accounts role (role_id=5)
-- Date: 2026-07-28
-- Description: Add module permission types for categories and subcategories,
--              then grant view/create/edit/delete for role_id=5 on
--              categories (119), subcategories (120), and units (196).
-- ============================================================================

-- 1. Module permission types for categories (119)
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_at) VALUES
(119, 'view', 'View', 1, 1, NOW()),
(119, 'create', 'Create', 1, 1, NOW()),
(119, 'edit', 'Edit', 1, 1, NOW()),
(119, 'delete', 'Delete', 1, 1, NOW());

-- 2. Module permission types for subcategories (120)
INSERT IGNORE INTO erp_module_permissions (module_id, slug, permission_name, publish, is_active, created_at) VALUES
(120, 'view', 'View', 1, 1, NOW()),
(120, 'create', 'Create', 1, 1, NOW()),
(120, 'edit', 'Edit', 1, 1, NOW()),
(120, 'delete', 'Delete', 1, 1, NOW());

-- 3. Grant all perms for categories (119) to Accounts role
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 5, mp.id, 119, 1, 1, 1, NOW()
FROM erp_module_permissions mp
WHERE mp.module_id = 119 AND mp.slug IN ('view', 'create', 'edit', 'delete');

-- 4. Grant all perms for subcategories (120) to Accounts role
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 5, mp.id, 120, 1, 1, 1, NOW()
FROM erp_module_permissions mp
WHERE mp.module_id = 120 AND mp.slug IN ('view', 'create', 'edit', 'delete');

-- 5. Grant all perms for units (196) to Accounts role
INSERT IGNORE INTO erp_permissions (role_id, permission_id, module_id, publish, is_active, created_by, created_at)
SELECT 5, mp.id, 196, 1, 1, 1, NOW()
FROM erp_module_permissions mp
WHERE mp.module_id = 196 AND mp.slug IN ('view', 'create', 'edit', 'delete');
