-- ============================================================================
-- Flash ERP System — PORTS COUNTRY BACKFILL — 2026-08-19
-- ============================================================================
-- Links erp_ports.country_id for UN/LOCODE-style ports that were imported
-- without a country. Required so the invoice/quotation/sale-order form can
-- auto-select the relevant origin/destination country when a port is chosen.
--
-- Mapping rule: the leading 2 characters of a UN/LOCODE port_code are the ISO
-- country code (e.g. GBAOL -> GB -> United Kingdom, AEJAL -> AE -> UAE,
-- SAAHA -> SA -> Saudi Arabia). We join that prefix to the country code using
-- COALESCE(alpha2_code, abbr) so countries with a 2-letter ISO code but no
-- `abbr` (e.g. Kazakhstan KZ) are still matched.
--
-- ALSO fixes pre-existing BAD data: ports whose country_id points to a
-- non-existent erp_geo_countries row (dangling references, ~334 rows) are
-- re-mapped by their code prefix, and the known 3-char IATA port
-- ALA - Almaty (Kazakhstan) is corrected manually.
--
-- Guards:
--   * Only touches ports where country_id is NULL/0 OR references a missing
--     country row -> idempotent, safe re-run.
--   * Only matches 5-char codes -> skips 3-char IATA codes (e.g. SHJ, LCA)
--     and codes with no country prefix.
--   * UPDATE IGNORE -> erp_ports has a unique key (port_name, country_id);
--     skips duplicate port names in one country.
--
-- RUN (after migrations/2026_08_19_geo_ports_gap_fill.sql):
--   mysql -u <user> -p <db_name> < migrations/2026_08_19_ports_backfill_country.sql
-- ============================================================================

SET NAMES utf8mb4;

-- 1) Backfill ports with a missing/invalid country using the UN/LOCODE prefix.
UPDATE IGNORE `erp_ports` p
JOIN `erp_geo_countries` g ON UPPER(LEFT(p.`port_code`, 2)) = UPPER(COALESCE(g.`alpha2_code`, g.`abbr`))
SET p.`country_id` = g.`id`
WHERE CHAR_LENGTH(p.`port_code`) >= 5
  AND (
    p.`country_id` IS NULL
    OR p.`country_id` = 0
    OR NOT EXISTS (SELECT 1 FROM `erp_geo_countries` x WHERE x.`id` = p.`country_id`)
  );

-- 2) Manual fix: ALA - Almaty (3-char IATA code, no country prefix) belongs to
--    Kazakhstan (erp_geo_countries.id = 218, alpha2_code = 'KZ'). The imported
--    value pointed at a non-existent country id, so no country was auto-selected.
UPDATE `erp_ports`
SET `country_id` = 218
WHERE `port_code` = 'ALA' AND `port_name` = 'Almaty';

-- ============================================================================
-- DONE. Verify:
--   SELECT id, port_code, port_name, country_id FROM erp_ports WHERE port_code='GBAOL';
--     -- country_id should be 84 (United Kingdom) for at least one row.
--   SELECT id, port_code, port_name, country_id FROM erp_ports WHERE port_code='ALA';
--     -- the 'Almaty' row should have country_id 218 (Kazakhstan).
--   SELECT COUNT(*) FROM erp_ports WHERE country_id IS NOT NULL AND country_id>0
--     AND NOT EXISTS (SELECT 1 FROM erp_geo_countries g WHERE g.id=country_id);
--     -- should be 0 after re-running (remaining NULLs are unmappable IATA codes).
-- ============================================================================
