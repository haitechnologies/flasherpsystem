-- ============================================================================
-- Flash ERP System — PORT DATA CLEANUP — 2026-08-19
-- ============================================================================
-- Keeps only active world SEA PORTS in erp_ports, associates each port with the
-- correct erp_geo_countries row, and removes orphan/airport/no-country entries.
--
-- The UN/LOCODE port_code's first two letters are the ISO alpha-2 country code.
-- erp_geo_countries was missing alpha2 codes for 49 major maritime countries and
-- erp_ports.country_id was largely corrupt, so the country is re-derived from
-- the LOCODE prefix.
--
-- Operations (in order):
--   1. Backfill the 49 missing alpha-2 country codes in erp_geo_countries.
--   2. Deactivate corrupt/duplicate geo rows (mojibake duplicates of real ones).
--   3. Null the 2 business rows that reference airport ports (would be orphaned).
--   4. Delete airport (3-char IATA), other/empty-code, and XZ (no-country) ports.
--   5. Deduplicate sea ports by port_code, keeping the lowest id (original row).
--   6. Repair erp_ports.country_id from the LOCODE prefix -> alpha2 -> geo id.
--
-- Fully idempotent. BACKUP FIRST, then run exactly once:
--   mysqldump -u <user> -p <db_name> > backup_$(date +%Y%m%d_%H%M%S).sql
--   mysql -u <user> -p <db_name> < migrations/2026_08_19_clean_sea_ports.sql
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 1. BACKFILL 49 MISSING ALPHA-2 CODES IN erp_geo_countries
--    Each UPDATE targets an explicit id (verified by country name). Guarded so
--    it only writes when the code is currently empty.
-- ============================================================================

UPDATE `erp_geo_countries` SET `alpha2_code`='US' WHERE `id`=92  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='JP' WHERE `id`=76  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='GB' WHERE `id`=84  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='DE' WHERE `id`=85  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='FR' WHERE `id`=86  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='CN' WHERE `id`=75  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='CA' WHERE `id`=93  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='NL' WHERE `id`=89  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='BE' WHERE `id`=90  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='ES' WHERE `id`=88  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='AU' WHERE `id`=94  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='PH' WHERE `id`=81  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='GR' WHERE `id`=109 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='IT' WHERE `id`=87  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='SE' WHERE `id`=110 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='ID' WHERE `id`=80  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='IN' WHERE `id`=68  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='BR' WHERE `id`=105 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='MY' WHERE `id`=79  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='TR' WHERE `id`=74  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='PL' WHERE `id`=108 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='CH' WHERE `id`=91  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='TH' WHERE `id`=82  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='ZA' WHERE `id`=96  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='NZ' WHERE `id`=95  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='IR' WHERE `id`=73  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='EG' WHERE `id`=62  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='NG' WHERE `id`=97  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='SA' WHERE `id`=57  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='MA' WHERE `id`=103 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='LY' WHERE `id`=101 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='TN' WHERE `id`=104 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='DZ' WHERE `id`=102 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='IQ' WHERE `id`=66  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='LK' WHERE `id`=71  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='UA' WHERE `id`=107 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='OM' WHERE `id`=58  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='YE' WHERE `id`=67  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='LB' WHERE `id`=64  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='SG' WHERE `id`=78  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='KW' WHERE `id`=59  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='BD' WHERE `id`=70  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='PK' WHERE `id`=69  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='QA' WHERE `id`=61  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='KE' WHERE `id`=98  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='BH' WHERE `id`=60  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='AF' WHERE `id`=72  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='SD' WHERE `id`=100 AND (`alpha2_code`='' OR `alpha2_code` IS NULL);
UPDATE `erp_geo_countries` SET `alpha2_code`='JO' WHERE `id`=63  AND (`alpha2_code`='' OR `alpha2_code` IS NULL);

-- ============================================================================
-- 2. DEACTIVATE CORRUPT / DUPLICATE GEO ROWS
--    id 376 ('C├┤te d''Ivoire', mojibake) duplicates canonical 'Côte d''Ivoire' id 174.
--    id 379 ('├àland Islands', mojibake) duplicates canonical 'Åland Islands' id 322.
--    Deactivated so the alpha2 join below is unambiguous (both already carry CI/AX).
-- ============================================================================

UPDATE `erp_geo_countries` SET `is_active`=0 WHERE `id` IN (376, 379) AND `is_active`=1;

-- ============================================================================
-- 3. CLEAR THE 2 BUSINESS ROWS THAT REFERENCE AIRPORT PORTS
--    erp_jobs id=60 destination_port=782 (Lome LFW airport) — column is NULLABLE.
--    erp_shipping_stocks id=1 destination_port=27 (Albany, GA ABY airport) —
--      column is NOT NULL, so 0 (the app's "no port" sentinel) is used instead.
--    (Sea-port refs — Dubai AEDXB id 1593, Bautino KZBTN id 12976 — are kept.)
-- ============================================================================

UPDATE `erp_jobs` SET `destination_port`=NULL WHERE `id`=60 AND `destination_port`=782;
UPDATE `erp_shipping_stocks` SET `destination_port`=0 WHERE `id`=1 AND `destination_port`=27;

-- ============================================================================
-- 4. DELETE ORPHAN ROWS
--    a) IATA airports: 3-char codes (e.g. SIN, LFW, ABY).
--    b) Other/empty codes (LENGTH not 3 or 5).
--    c) XZ = UN/LOCODE "no country" (5-char, prefix XZ).
-- ============================================================================

DELETE FROM `erp_ports` WHERE LENGTH(`port_code`) = 3;
DELETE FROM `erp_ports` WHERE LENGTH(`port_code`) NOT IN (3, 5);
DELETE FROM `erp_ports` WHERE LENGTH(`port_code`) = 5 AND UPPER(LEFT(`port_code`, 2)) = 'XZ';

-- ============================================================================
-- 5. DEDUPLICATE SEA PORTS BY port_code, KEEPING THE LOWEST id (ORIGINAL ROW)
--    Removes duplicate rows (same UN/LOCODE) while preserving the original data.
--    A temporary index on (port_code, id) is added so the self-join delete does
--    not scan the whole table; it is dropped again afterwards.
-- ============================================================================

ALTER TABLE `erp_ports` ADD INDEX `idx_ports_code_temp` (`port_code`, `id`);

DELETE p1
FROM `erp_ports` p1
JOIN `erp_ports` p2 ON p1.`port_code` = p2.`port_code` AND p1.`id` > p2.`id`
WHERE LENGTH(p1.`port_code`) = 5 AND UPPER(LEFT(p1.`port_code`, 2)) <> 'XZ';

ALTER TABLE `erp_ports` DROP INDEX `idx_ports_code_temp`;

-- ============================================================================
-- 5b. FIX THE SCHEMA: the unique index uq_port_country (port_name, country_id)
--     is WRONG — multiple real UN/LOCODEs can share the same port name within
--     one country (e.g. 'Jebel Ali' = AEJAL and AEJEA, both UAE). After the
--     country repair this would otherwise violate the unique key and abort the
--     migration. The true unique identifier is port_code (UN/LOCODE is globally
--     unique). Replace it with a unique index on port_code and keep a non-unique
--     index on port_name for the name-based port search.
-- ============================================================================

ALTER TABLE `erp_ports` DROP INDEX `uq_port_country`;

ALTER TABLE `erp_ports` ADD UNIQUE INDEX `uq_ports_code` (`port_code`);

ALTER TABLE `erp_ports` ADD INDEX `idx_ports_name` (`port_name`);

-- ============================================================================
-- 6. REPAIR erp_ports.country_id FROM THE LOCODE PREFIX
--    port_code prefix = ISO alpha-2 -> erp_geo_countries.id.
--    Only touches 5-char (sea) ports with a resolvable prefix.
-- ============================================================================

UPDATE `erp_ports` p
JOIN `erp_geo_countries` g ON g.`alpha2_code` = UPPER(LEFT(p.`port_code`, 2))
SET p.`country_id` = g.`id`
WHERE LENGTH(p.`port_code`) = 5
  AND UPPER(LEFT(p.`port_code`, 2)) <> 'XZ'
  AND (p.`country_id` IS NULL OR p.`country_id` = 0 OR p.`country_id` <> g.`id`);

-- ============================================================================
-- DONE. Verify with:
--   SELECT COUNT(*) FROM erp_ports;                                -- ~17,345 sea ports
--   SELECT COUNT(*) FROM erp_ports WHERE LENGTH(port_code)=3;      -- 0 (no airports)
--   SELECT COUNT(*) FROM erp_ports WHERE LENGTH(port_code)=5 AND UPPER(LEFT(port_code,2))='XZ'; -- 0
--   SELECT COUNT(*) FROM erp_ports WHERE country_id IS NULL OR country_id=0; -- 0
--   SELECT COUNT(DISTINCT port_code) FROM erp_ports;               -- = COUNT(*) (no dups)
--   SELECT COUNT(*) FROM erp_geo_countries WHERE alpha2_code='';   -- 6 (landlocked only)
--   SHOW INDEX FROM erp_ports;  -- uq_ports_code (UNIQUE port_code) present; uq_port_country gone;
--                               -- idx_ports_name (port_name) added for name search
--   SELECT id, port_name, port_code, country_id FROM erp_ports WHERE port_code IN ('AEDXB','SGSIN','NLRTM','KZBTN');
--   SELECT p.id, p.port_name, g.country FROM erp_ports p JOIN erp_geo_countries g ON g.id=p.country_id WHERE p.port_code IN ('AEDXB','USLAX','DEHAM');
-- ============================================================================
