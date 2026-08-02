-- Live deployment fix: add erp_customers.receivable_account_id (idempotent, safe to re-run).
--
-- Step 1 (optional): confirm the real table prefix on live.
--     SHOW TABLES LIKE '%customers%';
--     If the live DB uses a prefix other than 'erp_', update @tbl below.
--
-- Step 2: run the block below.

SET @tbl = 'erp_customers';

SET @col := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = @tbl
      AND COLUMN_NAME = 'receivable_account_id'
);

SET @sql := IF(@col = 0,
    'ALTER TABLE `erp_customers` ADD COLUMN `receivable_account_id` INT NULL DEFAULT NULL AFTER `opening_balance`',
    'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
