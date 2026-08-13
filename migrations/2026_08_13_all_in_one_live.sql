-- ============================================================================
-- Flash ERP System — LIVE DEPLOYMENT MIGRATION (all-in-one) — 2026-08-13
-- ============================================================================
-- Combines, in order, the 2026-08-13 release migrations:
--   1. create_setup_banks.sql               (idempotent)
--   2. alter_setup_banks_register_no_nullable.sql  (SKIPPED: superseded by #3,
--      which drops register_no entirely)
--   3. simplify_setup_banks.sql
--   4. jobs_string_columns.sql
--   5. jobs_col_widen.sql
--
-- Safe to run even if some steps were already applied (all DDL guarded via
-- information_schema, all INSERTs guarded with NOT EXISTS). Run exactly once.
--
-- BACKUP FIRST:
--   mysqldump -u <user> -p <db_name> > backup_$(date +%Y%m%d_%H%M%S).sql
--
-- RUN:
--   mysql -u <user> -p <db_name> < migrations/2026_08_13_all_in_one_live.sql
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. CREATE erp_setup_banks (bank institutions master data)
--    Created directly in its final simplified form.
--    On legacy installs the table already exists in the old full form — the
--    simplify step below (guarded) drops the obsolete columns.
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

-- ============================================================================
-- 2. SIMPLIFY erp_setup_banks — keep only institution_name + head_office.
--    Drops register_no, license_type, license_category, identification_number
--    (guarded: skipped when the columns are already dropped / never existed).
-- ============================================================================

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
-- 4. FIX erp_jobs free-text columns wrongly typed as INT
--    (loading_place, fdp, container_number -> VARCHAR(255))
--    Guarded: skipped when the columns are already VARCHAR.
-- ============================================================================

SELECT COUNT(*) INTO @has_int_text_cols
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'erp_jobs'
  AND COLUMN_NAME IN ('loading_place', 'fdp', 'container_number')
  AND DATA_TYPE = 'int';

SET @jobs_string_sql := IF(@has_int_text_cols > 0,
  'ALTER TABLE `erp_jobs` MODIFY COLUMN `loading_place` VARCHAR(255) NULL DEFAULT NULL, MODIFY COLUMN `fdp` VARCHAR(255) NULL DEFAULT NULL, MODIFY COLUMN `container_number` VARCHAR(255) NULL DEFAULT NULL',
  'SELECT 1');

PREPARE stmt FROM @jobs_string_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Null out previously zeroed values so empties display blank (idempotent)
UPDATE `erp_jobs` SET `loading_place` = NULL WHERE `loading_place` = '0';
UPDATE `erp_jobs` SET `fdp` = NULL WHERE `fdp` = '0';
UPDATE `erp_jobs` SET `container_number` = NULL WHERE `container_number` = '0';

-- ============================================================================
-- 5. WIDEN erp_jobs columns that truncated user input
--    job_no VARCHAR(10) -> VARCHAR(50); transport_mode / shipment_type -> VARCHAR(100)
--    Guarded: skipped when job_no is already >= VARCHAR(50).
-- ============================================================================

SELECT COUNT(*) INTO @needs_widen
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'erp_jobs'
  AND COLUMN_NAME = 'job_no'
  AND DATA_TYPE = 'varchar'
  AND CHARACTER_MAXIMUM_LENGTH < 50;

SET @widen_sql := IF(@needs_widen > 0,
  'ALTER TABLE `erp_jobs` MODIFY COLUMN `job_no` VARCHAR(50) NOT NULL, MODIFY COLUMN `transport_mode` VARCHAR(100) NULL DEFAULT NULL, MODIFY COLUMN `shipment_type` VARCHAR(100) NULL DEFAULT NULL',
  'SELECT 1');

PREPARE stmt FROM @widen_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Deactivate the duplicate 'pending_approval' job status (id 7) so status
-- lookups resolve to the canonical row (id 3). (idempotent)
UPDATE `erp_job_statuses`
SET `is_active` = 0
WHERE `id` = 7 AND LOWER(`job_status`) = 'pending_approval';

-- ============================================================================
-- DONE. Verify with:
--   SELECT COUNT(*) FROM erp_setup_banks WHERE organization_id = 1;   -- 61
--   SHOW COLUMNS FROM erp_setup_banks;                                 -- only id, organization_id, institution_name, head_office, is_active, timestamps
--   SHOW COLUMNS FROM erp_jobs LIKE 'job_no';                          -- varchar(50)
--   SHOW COLUMNS FROM erp_jobs LIKE 'transport_mode';                  -- varchar(100)
--   SHOW COLUMNS FROM erp_jobs LIKE 'loading_place';                   -- varchar(255)
--   SELECT id, job_status, is_active FROM erp_job_statuses WHERE LOWER(job_status) = 'pending_approval';
-- ============================================================================
