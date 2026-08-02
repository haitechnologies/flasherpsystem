-- Live deployment: add erp_departments.email and replace department data with 3 Movestic Cargo departments.
-- Idempotent, safe to re-run. Run the whole block in phpMyAdmin (SQL tab).
--
-- Step 1 (optional): confirm the real table prefix on live.
--     SHOW TABLES LIKE '%departments%';
--     If the live DB uses a prefix other than 'erp_', update @tbl below.
--
-- Step 2: run the block below.

SET @tbl = 'erp_departments';

-- 1. Add nullable email column if it does not exist yet.
SET @col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tbl
      AND COLUMN_NAME = 'email'
);

SET @sql := IF(@col = 0,
    'ALTER TABLE `erp_departments` ADD COLUMN `email` VARCHAR(255) NULL DEFAULT NULL AFTER `department`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Rename matching departments and attach their email (idempotent).
UPDATE `erp_departments`
SET `department` = 'Sales Movestic Cargo', `email` = 'sales@movesticargo.com'
WHERE `department` = 'Sales' AND `email` IS NULL;

UPDATE `erp_departments`
SET `department` = 'Accounts Movestic Cargo', `email` = 'accounts@movesticargo.com'
WHERE `department` = 'Accounts' AND `email` IS NULL;

UPDATE `erp_departments`
SET `department` = 'Operations Movestic Cargo', `email` = 'cargo@movesticargo.com'
WHERE `department` = 'Operations' AND `email` IS NULL;

-- 3. Remove department links for users in removed departments,
--    plus any dangling department_id references (e.g. nonexistent dept 1).
UPDATE `erp_users`
SET `department_id` = NULL
WHERE `department_id` IS NOT NULL
  AND `department_id` NOT IN (SELECT `id` FROM `erp_departments`);

-- 4. Delete the old departments (idempotent - no-op if already gone).
DELETE FROM `erp_departments`
WHERE `department` IN ('Marketing', 'Shipping & Logistics', 'Technical', 'HR');

-- Done. Expected result: exactly 3 rows
--   Sales Movestic Cargo      (sales@movesticargo.com)
--   Accounts Movestic Cargo   (accounts@movesticargo.com)
--   Operations Movestic Cargo (cargo@movesticargo.com)
