-- ============================================================================
-- Flash ERP System — PORTS COUNTRY BACKFILL — 2026-08-19
-- ============================================================================
-- Links erp_ports.country_id for the ~12.6k UN/LOCODE-style ports that were
-- imported without a country. Required so the invoice/quotation/sale-order
-- form can auto-select the relevant origin/destination country when a port is
-- chosen.
--
-- Mapping rule: the leading 2 characters of a UN/LOCODE port_code are the ISO
-- country code (e.g. GBAOL -> GB -> United Kingdom, AEJAL -> AE -> UAE,
-- SAAHA -> SA -> Saudi Arabia). We join that prefix to erp_geo_countries.abbr.
--
-- Guards:
--   * Only touches ports where country_id is NULL/0  -> idempotent, safe re-run.
--   * Only matches 5-char codes                       -> skips 3-char IATA codes
--     (e.g. SHJ, LCA) and codes with no country prefix.
--   * UPDATE IGNORE                                   -> erp_ports has a unique
--     key (port_name, country_id); skips duplicate port names in one country.
--
-- RUN (after migrations/2026_08_19_geo_ports_gap_fill.sql):
--   mysql -u <user> -p <db_name> < migrations/2026_08_19_ports_backfill_country.sql
-- ============================================================================

SET NAMES utf8mb4;

UPDATE IGNORE `erp_ports` p
JOIN `erp_geo_countries` g ON UPPER(LEFT(p.`port_code`, 2)) = UPPER(g.`abbr`)
SET p.`country_id` = g.`id`
WHERE (p.`country_id` IS NULL OR p.`country_id` = 0)
  AND CHAR_LENGTH(p.`port_code`) >= 5;

-- ============================================================================
-- DONE. Verify:
--   SELECT id, port_code, port_name, country_id FROM erp_ports WHERE port_code='GBAOL';
--     -- country_id should be 84 (United Kingdom) for at least one row.
--   SELECT COUNT(*) FROM erp_ports WHERE country_id IS NULL OR country_id=0;
--     -- remaining rows are 3-char IATA codes / duplicates / unmappable codes.
-- ============================================================================
