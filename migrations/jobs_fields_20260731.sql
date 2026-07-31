-- ============================================================================
-- Migration: Jobs Module Field Updates (2026-07-31 session)
-- Run against production: mysql -u user -p db < this_file.sql
-- Safe to re-run — all statements are idempotent
-- ============================================================================

-- 1. Add missing columns (skip if already exist — Column exists error is expected)
-- If you get "Duplicate column name", the column already exists — continue.
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `cbm` VARCHAR(255) DEFAULT NULL AFTER `chargeable_weight`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `quotation_id` INT UNSIGNED DEFAULT NULL AFTER `customer_id`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `subject` TEXT DEFAULT NULL AFTER `shipping_country`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `terms_and_conditions` TEXT DEFAULT NULL AFTER `subject`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_subtotal` DECIMAL(10,2) DEFAULT 0.00 AFTER `terms_and_conditions`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_discount_type` VARCHAR(20) DEFAULT '0.00' AFTER `grand_subtotal`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_discount_type_value` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_discount_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type_value`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_after_discount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_amount`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `customer_notes` TEXT DEFAULT NULL AFTER `grand_after_discount`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_tax` DECIMAL(10,2) DEFAULT 0.00 AFTER `customer_notes`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `grand_total` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_tax`;
ALTER TABLE `erp_jobs` ADD COLUMN IF NOT EXISTS `customer_type` VARCHAR(100) DEFAULT NULL AFTER `approved_time_resubmission`;

-- 2. Expand hawb/mawb for multi-line text (safe to re-run)
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;

-- 3. Fix NOT NULL constraints mismatched with Model ?string types (CRITICAL)
ALTER TABLE `erp_jobs` MODIFY COLUMN `unhappy_reason` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `referral` VARCHAR(300) DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `notes` TEXT DEFAULT NULL;

-- 4. Fix job status: rename 'pending' to 'pending_approval' for workflow
UPDATE `erp_job_statuses` SET job_status = 'pending_approval' WHERE job_status = 'pending';
