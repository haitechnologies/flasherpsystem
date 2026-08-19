-- ============================================================================
-- Flash ERP System — LIVE DEPLOYMENT FIX (all-in-one) — 2026-08-18
-- ============================================================================
-- Resolves the 2026-08-18 live error batch reported from flasherpsystem.com.
--
--   1. Adds missing erp_invoices.cbm column (fixes "Unknown column 'cbm'").
--      Note: this normally runs via DatabaseSchemaInitializer::ensureInvoiceCbmColumns(),
--      but on live the DB user may lack ALTER or the initializer is not reached.
--   2. Creates erp_setup_banks + registers setup_banks module/permissions
--      (fixes "Table 'erp_setup_banks' doesn't exist" and setup_banks
--      "Unauthorized module access attempt" / "granted(): Module not found").
--   3. Grants Accounts role (role_id=5) view/create/edit/delete on setup_banks.
--
-- Fully idempotent: all DDL guarded via information_schema, all INSERTs
-- guarded with NOT EXISTS. Safe to run even if steps were already applied.
--
-- BACKUP FIRST:
--   mysqldump -u <user> -p <db_name> > backup_$(date +%Y%m%d_%H%M%S).sql
--
-- RUN:
--   mysql -u <user> -p <db_name> < migrations/2026_08_18_live_cbm_setup_banks_perms.sql
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. ADD erp_invoices.cbm (decimal(10,3) NOT NULL DEFAULT 0.000, AFTER volume)
--    Guarded: skipped when the column already exists.
-- ============================================================================

SELECT COUNT(*) INTO @has_cbm
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'erp_invoices'
  AND COLUMN_NAME = 'cbm';

SET @cbm_sql := IF(@has_cbm > 0,
  'SELECT 1',
  'ALTER TABLE `erp_invoices` ADD COLUMN `cbm` DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `volume`');

PREPARE stmt FROM @cbm_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 2. CREATE erp_setup_banks (bank institutions master data) in its final
--    simplified form, then drop obsolete columns if a legacy full-form table
--    already existed.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `erp_setup_banks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `institution_name` VARCHAR(255) NOT NULL,
  `head_office` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_org` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bank institutions master data (simplified)';

SELECT COUNT(*) INTO @has_register_no
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'erp_setup_banks'
  AND COLUMN_NAME = 'register_no';

SET @simplify_sql := IF(@has_register_no > 0,
  'ALTER TABLE `erp_setup_banks` DROP COLUMN `register_no`, DROP COLUMN `license_type`, DROP COLUMN `license_category`, DROP COLUMN `identification_number`',
  'SELECT 1');

PREPARE stmt FROM @simplify_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- 3. SEED 61 bank institutions (skipped if the table already has rows) and
--    register the setup_banks module + permissions.
-- ============================================================================

INSERT INTO `erp_setup_banks`
(`organization_id`, `institution_name`, `head_office`, `is_active`)
SELECT * FROM (
    SELECT 1 AS organization_id, 'HSBC Bank Middle East Limited' AS institution_name, 'Dubai' AS head_office, 1 AS is_active
    UNION ALL SELECT 1, 'Standard Chartered Bank', 'Dubai', 1
    UNION ALL SELECT 1, 'Emirates NBD Bank P.J.S.C', 'Dubai', 1
    UNION ALL SELECT 1, 'CitiBank N.A.', 'Dubai', 1
    UNION ALL SELECT 1, 'Mashreq Bank P.S.C.', 'Dubai', 1
    UNION ALL SELECT 1, 'Habib Bank Ltd.', 'Dubai', 1
    UNION ALL SELECT 1, 'United Bank Ltd.', 'Dubai', 1
    UNION ALL SELECT 1, 'First Abu Dhabi Bank P.J.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Bank Saderat Iran', 'Dubai', 1
    UNION ALL SELECT 1, 'Bank of Dubai P.J.S.C', 'Dubai', 1
    UNION ALL SELECT 1, 'Al Ahli Bank of Kuwait', 'Dubai', 1
    UNION ALL SELECT 1, 'Bank Melli Iran', 'Dubai', 1
    UNION ALL SELECT 1, 'Arab African International Bank', 'Dubai', 1
    UNION ALL SELECT 1, 'Banque Misr', 'Dubai', 1
    UNION ALL SELECT 1, 'Bank of Sharjah P.J.S.C', 'Sharjah', 1
    UNION ALL SELECT 1, 'Arab Bank PLC', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'BNP Paribas', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Al Khaliji (France) S.A.', 'Dubai', 1
    UNION ALL SELECT 1, 'Rafidain Bank', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Bank of Baroda', 'Dubai', 1
    UNION ALL SELECT 1, 'Janata Bank PLC', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Habib Bank A.G Zurich', 'Dubai', 1
    UNION ALL SELECT 1, 'Banorient France', 'Dubai', 1
    UNION ALL SELECT 1, 'Dubai Islamic Bank P.J.S.C', 'Dubai', 1
    UNION ALL SELECT 1, 'Sharjah Islamic Bank P.J.S.C.', 'Sharjah', 1
    UNION ALL SELECT 1, 'United Arab Bank P.J.S.C', 'Sharjah', 1
    UNION ALL SELECT 1, 'InvestBank P.J.S.C', 'Sharjah', 1
    UNION ALL SELECT 1, 'Credit Agricole-Corporate and Investment Bank', 'Dubai', 1
    UNION ALL SELECT 1, 'Arab Bank for Inv.& Foreign Trade', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Emirates Islamic Bank P.J.S.C.', 'Dubai', 1
    UNION ALL SELECT 1, 'National Bank of R.A.K P.J.S.C', 'R.A.K', 1
    UNION ALL SELECT 1, 'Emirates Investment Bank (PJSC)', 'Dubai', 1
    UNION ALL SELECT 1, 'El Nilein Bank', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Banque of Oman S.A.O.G.', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'National Bank of U.A.Q PSC', 'U.A.Q', 1
    UNION ALL SELECT 1, 'National Bank of Bahrain', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'National Bank of Fujairah PSC', 'Fujairah', 1
    UNION ALL SELECT 1, 'Abu Dhabi Commercial Bank P.J.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Commercial Bank International P.J.S.C', 'Dubai', 1
    UNION ALL SELECT 1, 'Abu Dhabi Islamic Bank P.J.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Al Hilal Bank P.J.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Doha Bank', 'Dubai', 1
    UNION ALL SELECT 1, 'The Saudi National Bank', 'Dubai', 1
    UNION ALL SELECT 1, 'Ajman Bank P.J.S.C', 'Ajman', 1
    UNION ALL SELECT 1, 'National Bank of Kuwait', 'Dubai', 1
    UNION ALL SELECT 1, 'Commercial Bank of China', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Deutsche Bank AG', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'KEB Hana Bank', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Barclays Bank PLC', 'Dubai', 1
    UNION ALL SELECT 1, 'Bank of China Limited', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Gulf International Bank B.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'MCB Bank Limited', 'Dubai', 1
    UNION ALL SELECT 1, 'Intesa Sanpaolo S.P.A', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Agricultural Bank of China Ltd.', 'Dubai', 1
    UNION ALL SELECT 1, 'Bank Al Falah Limited', 'Dubai', 1
    UNION ALL SELECT 1, 'BOK International Bank', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Al Maryah Community Bank L.L.C.', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'WIO Bank P.J.S.C', 'Abu Dhabi', 1
    UNION ALL SELECT 1, 'Zand Bank P.J.S.C', 'Dubai', 1
    UNION ALL SELECT 1, 'International Development Bank for Investment & Finance', 'Dubai', 1
    UNION ALL SELECT 1, 'Ruya Community Islamic Bank L.L.C', 'Ajman', 1
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM `erp_setup_banks`);

-- Register module + permissions (guarded, idempotent)
INSERT INTO `erp_modules` (`slug`, `module_name`, `module_type`, `systems`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 'setup_banks', 'Banks (Institutions)', 'module', '', 1, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `erp_modules` WHERE slug = 'setup_banks');

SET @setup_banks_mod_id = (SELECT id FROM `erp_modules` WHERE slug = 'setup_banks');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'view', 'View', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'view');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'create', 'Create', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'create');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'edit', 'Edit', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'edit');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'delete', 'Delete', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'delete');

-- ============================================================================
-- 4. GRANT Accounts role (role_id = 5) view/create/edit/delete on setup_banks.
--    erp_permissions is keyed by (role_id, module_id, permission_id); each
--    permission_id is the id of the matching erp_module_permissions row.
--    Guarded: a row is inserted only when it does not already exist.
-- ============================================================================

INSERT INTO `erp_permissions` (`role_id`, `permission_id`, `module_id`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 5, mp.id, @setup_banks_mod_id, 0, 1, 1, NOW(), NOW()
FROM `erp_module_permissions` mp
WHERE mp.module_id = @setup_banks_mod_id
  AND mp.slug = 'view'
  AND NOT EXISTS (
      SELECT 1 FROM `erp_permissions` p
      WHERE p.role_id = 5 AND p.module_id = @setup_banks_mod_id AND p.permission_id = mp.id
  );

INSERT INTO `erp_permissions` (`role_id`, `permission_id`, `module_id`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 5, mp.id, @setup_banks_mod_id, 0, 1, 1, NOW(), NOW()
FROM `erp_module_permissions` mp
WHERE mp.module_id = @setup_banks_mod_id
  AND mp.slug = 'create'
  AND NOT EXISTS (
      SELECT 1 FROM `erp_permissions` p
      WHERE p.role_id = 5 AND p.module_id = @setup_banks_mod_id AND p.permission_id = mp.id
  );

INSERT INTO `erp_permissions` (`role_id`, `permission_id`, `module_id`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 5, mp.id, @setup_banks_mod_id, 0, 1, 1, NOW(), NOW()
FROM `erp_module_permissions` mp
WHERE mp.module_id = @setup_banks_mod_id
  AND mp.slug = 'edit'
  AND NOT EXISTS (
      SELECT 1 FROM `erp_permissions` p
      WHERE p.role_id = 5 AND p.module_id = @setup_banks_mod_id AND p.permission_id = mp.id
  );

INSERT INTO `erp_permissions` (`role_id`, `permission_id`, `module_id`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 5, mp.id, @setup_banks_mod_id, 0, 1, 1, NOW(), NOW()
FROM `erp_module_permissions` mp
WHERE mp.module_id = @setup_banks_mod_id
  AND mp.slug = 'delete'
  AND NOT EXISTS (
      SELECT 1 FROM `erp_permissions` p
      WHERE p.role_id = 5 AND p.module_id = @setup_banks_mod_id AND p.permission_id = mp.id
  );

-- ============================================================================
-- DONE. Verify with:
--   SHOW COLUMNS FROM erp_invoices LIKE 'cbm';                     -- decimal(10,3) NOT NULL DEFAULT 0.000
--   SHOW COLUMNS FROM erp_setup_banks;                             -- id, organization_id, institution_name, head_office, is_active, timestamps
--   SELECT COUNT(*) FROM erp_setup_banks WHERE organization_id = 1; -- 61
--   SELECT id, slug FROM erp_modules WHERE slug = 'setup_banks';    -- module registered
--   SELECT p.permission_id, mp.slug FROM erp_permissions p
--     JOIN erp_module_permissions mp ON mp.id = p.permission_id
--     WHERE p.role_id = 5 AND p.module_id = @setup_banks_mod_id
--     AND p.is_active = 1;                                          -- view, create, edit, delete
-- ============================================================================
