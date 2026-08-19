-- ============================================================================
-- Flash ERP System — GEO/PORTS GAP FILL — 2026-08-19
-- ============================================================================
-- Companion to migrations/2026_08_19_clean_sea_ports.sql.
--
-- The clean_sea_ports migration backfills alpha-2 codes for 49 major maritime
-- countries, but 5 COASTAL countries were missed locally:
--   id 65 Syria (SY), 77 South Korea (KR), 83 Vietnam (VN),
--   id 99 Ethiopia (ET), 106 Russia (RU)
-- Together they own 490 UN/LOCODE sea ports locally (RU 261, VN 134, KR 82,
-- SY 12, ET 1). Without these codes, step 6 of clean_sea_ports cannot resolve
-- their country_id and leaves them NULL.
--
-- Also deactivates the junk test row id 658 'Test Country' (is_active=1,
-- no codes) so it never appears in address/country dropdowns.
--
-- RUN THIS AFTER 2026_08_19_clean_sea_ports.sql (or with it, same batch).
-- Fully idempotent: guarded so it only writes when the code is empty.
--
-- RUN:
--   mysql -u <user> -p <db_name> < migrations/2026_08_19_geo_ports_gap_fill.sql
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. BACKFILL THE 5 MISSING COASTAL ALPHA-2 CODES
-- ============================================================================

UPDATE `erp_geo_countries` SET `alpha2_code`='SY' WHERE `id`=65  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='KR' WHERE `id`=77  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='VN' WHERE `id`=83  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='ET' WHERE `id`=99  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='RU' WHERE `id`=106 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);

-- ============================================================================
-- 2. DEACTIVATE THE JUNK TEST COUNTRY
-- ============================================================================

UPDATE `erp_geo_countries` SET `is_active`=0 WHERE `id`=658 AND `is_active`=1;

-- ============================================================================
-- 3. RESOLVE country_id FOR PORTS OF THESE 5 COUNTRIES (same logic as
--    clean_sea_ports step 6, now that their alpha-2 codes exist).
-- ============================================================================

UPDATE `erp_ports` p
JOIN `erp_geo_countries` g ON g.`alpha2_code` = UPPER(LEFT(p.`port_code`, 2))
SET p.`country_id` = g.`id`
WHERE LENGTH(p.`port_code`) = 5
  AND UPPER(LEFT(p.`port_code`, 2)) IN ('SY','KR','VN','ET','RU')
  AND (p.`country_id` IS NULL OR p.`country_id` = 0 OR p.`country_id` <> g.`id`);

-- ============================================================================
-- DONE. Verify:
--   SELECT id, country, alpha2_code FROM erp_geo_countries WHERE id IN (65,77,83,99,106);
--   SELECT COUNT(*) FROM erp_ports WHERE LENGTH(port_code)=5 AND country_id IS NULL OR country_id=0;
--     -- should now be 0 across the whole table after both migrations.
--   SELECT id, country, is_active FROM erp_geo_countries WHERE id=658;  -- is_active=0
-- ============================================================================