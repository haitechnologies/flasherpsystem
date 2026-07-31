-- ============================================================================
-- Migration: Jobs Module Field Updates (2026-07-31 session)
-- Run against production: php database/migrate.php OR manual execution
-- ============================================================================

-- 1. Add cbm column to erp_jobs (pending from 2026-07-29 migration)
ALTER TABLE `erp_jobs` ADD COLUMN `cbm` VARCHAR(255) DEFAULT NULL AFTER `chargeable_weight`;

-- 2. Expand hawb/mawb columns from varchar(300) to TEXT for multi-line support
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;
