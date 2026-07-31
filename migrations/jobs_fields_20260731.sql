-- ============================================================================
-- Migration: Jobs Module Field Updates (2026-07-31 session)
-- Run against production with: mysql -u user -p db < this_file.sql
-- ============================================================================

-- 1. Add cbm column (pending from 2026-07-29)
ALTER TABLE `erp_jobs` ADD COLUMN `cbm` VARCHAR(255) DEFAULT NULL AFTER `chargeable_weight`;

-- 2. Expand hawb/mawb for multi-line text
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;

-- 3. Add missing columns referenced by JobRepository queries
ALTER TABLE `erp_jobs` ADD COLUMN `quotation_id` INT UNSIGNED DEFAULT NULL AFTER `customer_id`;
ALTER TABLE `erp_jobs` ADD COLUMN `subject` TEXT DEFAULT NULL AFTER `shipping_country`;
ALTER TABLE `erp_jobs` ADD COLUMN `terms_and_conditions` TEXT DEFAULT NULL AFTER `subject`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_subtotal` DECIMAL(10,2) DEFAULT 0.00 AFTER `terms_and_conditions`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_type` VARCHAR(20) DEFAULT '0.00' AFTER `grand_subtotal`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_type_value` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type_value`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_after_discount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_amount`;
ALTER TABLE `erp_jobs` ADD COLUMN `customer_notes` TEXT DEFAULT NULL AFTER `grand_after_discount`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_tax` DECIMAL(10,2) DEFAULT 0.00 AFTER `customer_notes`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_total` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_tax`;
ALTER TABLE `erp_jobs` ADD COLUMN `customer_type` VARCHAR(100) DEFAULT NULL AFTER `approved_time_resubmission`;

-- 4. Fix NOT NULL constraints mismatched with Model ?string types
ALTER TABLE `erp_jobs` MODIFY COLUMN `unhappy_reason` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `referral` VARCHAR(300) DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `notes` TEXT DEFAULT NULL;
