-- 2026-08-13: Fix free-text columns in erp_jobs that were wrongly typed as INT.
-- loading_place, fdp, container_number store free-text values (place/destination/container number),
-- but the columns were INT so string input was coerced to 0.
ALTER TABLE `erp_jobs`
    MODIFY COLUMN `loading_place` VARCHAR(255) NULL DEFAULT NULL,
    MODIFY COLUMN `fdp` VARCHAR(255) NULL DEFAULT NULL,
    MODIFY COLUMN `container_number` VARCHAR(255) NULL DEFAULT NULL;

-- Clean up previously zeroed-out values (leave them empty rather than "0")
UPDATE `erp_jobs` SET `loading_place` = NULL WHERE `loading_place` = 0;
UPDATE `erp_jobs` SET `fdp` = NULL WHERE `fdp` = 0;
UPDATE `erp_jobs` SET `container_number` = NULL WHERE `container_number` = 0;
