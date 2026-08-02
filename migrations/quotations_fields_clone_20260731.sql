-- Add flashlogistics-matched columns to erp_quotations for 100% clone
-- Rollback: ALTER TABLE erp_quotations DROP COLUMN hwb_hbol, DROP COLUMN origin_country, DROP COLUMN destination_country;
-- Note: hwb_hbol, origin_country, destination_country already exist in local DB

-- Migration already applied locally — columns exist
-- For fresh deploy, this script adds the 3 columns
ALTER TABLE `erp_quotations`
    ADD COLUMN IF NOT EXISTS `hwb_hbol` VARCHAR(255) NULL AFTER `mawb_bol`,
    ADD COLUMN IF NOT EXISTS `origin_country` INT NULL AFTER `origin_port`,
    ADD COLUMN IF NOT EXISTS `destination_country` INT NULL AFTER `destination_port`;
