-- ============================================================================
-- Migration: Make erp_setup_banks.register_no optional (nullable)
-- ============================================================================

SET NAMES utf8mb4;

ALTER TABLE `erp_setup_banks`
    MODIFY COLUMN `register_no` INT UNSIGNED NULL DEFAULT NULL;
