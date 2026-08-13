-- ============================================================================
-- Migration: Simplify erp_setup_banks — keep only institution_name + head_office
-- Drops register_no, license_type, license_category, identification_number
-- (their UNIQUE indexes are dropped automatically with the columns)
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `erp_setup_banks`
    DROP COLUMN `register_no`,
    DROP COLUMN `license_type`,
    DROP COLUMN `license_category`,
    DROP COLUMN `identification_number`;
