-- ============================================================================
-- Migration: Jobs Critical Fixes (2026-07-31 live)
-- Run: mysql -u user -p db < this_file.sql
-- Idempotent — safe to run multiple times
-- ============================================================================

-- 1. Fix hawb/mawb to TEXT for multi-line support
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;

-- 2. Fix NOT NULL constraints (CRITICAL — causes INSERT/UPDATE failures)
ALTER TABLE `erp_jobs` MODIFY COLUMN `unhappy_reason` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `referral` VARCHAR(300) DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `notes` TEXT DEFAULT NULL;

-- 3. Job status workflow fix
UPDATE `erp_job_statuses` SET job_status = 'pending_approval' WHERE job_status = 'pending';
