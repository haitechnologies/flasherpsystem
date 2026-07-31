-- Before dropping, verify the table exists and optionally migrate any data
-- Step 1: Check if table exists and has data
SELECT COUNT(*) AS row_count FROM information_schema.tables 
WHERE table_schema = DATABASE() AND table_name = 'erp_user_blocks';

-- Step 2: If data exists, migrate to erp_authentication_activity 
-- (only if not already migrated — run manually after inspection)

-- Step 3: Drop the table
DROP TABLE IF EXISTS `erp_user_blocks`;
