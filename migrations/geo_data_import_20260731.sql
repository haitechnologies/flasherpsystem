-- ============================================================================
-- Migration: Geo Data Import from uaehscodes database (2026-07-31)
-- Run against production: source this file or mysql -u user -p db < this_file.sql
-- Prerequisites: uaehscodes database must be accessible on the same MySQL server
-- ============================================================================

-- 1. Add ISO code columns and fix dialing_code type
ALTER TABLE `erp_geo_countries` 
  ADD COLUMN `alpha2_code` CHAR(2) DEFAULT NULL AFTER `abbr`,
  ADD COLUMN `alpha3_code` CHAR(3) DEFAULT NULL AFTER `alpha2_code`,
  ADD COLUMN `numeric_code` VARCHAR(10) DEFAULT NULL AFTER `alpha3_code`,
  MODIFY COLUMN `dialing_code` VARCHAR(20) DEFAULT NULL;

-- 2. Upsert countries from uaehscodes (update existing, insert new)
INSERT INTO `erp_geo_countries` (country, alpha2_code, alpha3_code, numeric_code, dialing_code, publish, is_active, created_at, updated_at)
SELECT c.country_name, c.alpha2_code, c.alpha3_code, c.numeric_code, 
       NULLIF(NULLIF(c.dialing_code, ''), '0'),
       1, 1, NOW(), NOW()
FROM uaehscodes.uaehs_geo_countries c
WHERE c.country_name IS NOT NULL
ON DUPLICATE KEY UPDATE
  alpha2_code = VALUES(alpha2_code),
  alpha3_code = VALUES(alpha3_code),
  numeric_code = VALUES(numeric_code),
  dialing_code = COALESCE(`erp_geo_countries`.dialing_code, VALUES(dialing_code));

-- Note: ON DUPLICATE KEY requires unique index on 'country'. If one doesn't exist, create it first:
-- ALTER TABLE `erp_geo_countries` ADD UNIQUE INDEX `uq_country` (`country`);

-- 3. Import states from uaehscodes with country_id remapping
INSERT INTO `erp_geo_states` (state, state_ar, country_id, publish, is_active, created_at, updated_at)
SELECT s.state_name, NULLIF(s.state_ar, ''),
       hc.id AS country_id,
       1, 1, NOW(), NOW()
FROM uaehscodes.uaehs_geo_states s
JOIN uaehscodes.uaehs_geo_countries uc ON uc.id = s.country_id
JOIN `erp_geo_countries` hc ON hc.country = uc.country_name;

-- 4. Upsert UAE country with ISO codes
INSERT INTO `erp_geo_countries` (country, country_ar, alpha2_code, alpha3_code, numeric_code, dialing_code, publish, is_active, created_at, updated_at)
VALUES ('United Arab Emirates', 'الإمارات العربية المتحدة', 'AE', 'ARE', '784', '971', 1, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  country_ar = 'الإمارات العربية المتحدة',
  alpha2_code = 'AE',
  alpha3_code = 'ARE',
  numeric_code = '784',
  dialing_code = '971';

-- 5. Remove transliteration duplicates from uaehscodes (keep proper English names)
DELETE FROM `erp_geo_states` WHERE id IN (
  SELECT id FROM (SELECT id, state FROM `erp_geo_states` WHERE country_id = 
    (SELECT id FROM `erp_geo_countries` WHERE alpha2_code = 'AE')
  ) t WHERE state IN ('Abu Zabi','Ras al-Khaymah','Sharjha','Umm al Qaywayn','al-Fujayrah','ash-Shariqah')
);

-- 6. Insert UAE 7 emirates
INSERT INTO `erp_geo_states` (state, country_id, publish, is_active, created_at, updated_at)
SELECT e.name, hc.id, 1, 1, NOW(), NOW()
FROM (SELECT 'Abu Dhabi' AS name UNION ALL SELECT 'Dubai' UNION ALL SELECT 'Sharjah' 
      UNION ALL SELECT 'Ajman' UNION ALL SELECT 'Umm Al Quwain' 
      UNION ALL SELECT 'Ras Al Khaimah' UNION ALL SELECT 'Fujairah') e
JOIN `erp_geo_countries` hc ON hc.alpha2_code = 'AE'
WHERE NOT EXISTS (SELECT 1 FROM `erp_geo_states` gs WHERE gs.state = e.name AND gs.country_id = hc.id);
