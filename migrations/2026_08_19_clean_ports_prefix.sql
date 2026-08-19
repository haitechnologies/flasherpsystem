-- ============================================================================
-- Flash ERP System — LIVE DEPLOYMENT FIX (all-in-one) — 2026-08-19
-- ============================================================================
-- Cleans erp_ports data.
--
--   1. Deletes port names that start with a Dutch definite-article prefix
--      ('s... / 't..., e.g. 's-Hertogenbosch, 't Horntje). 16 rows removed
--      locally (8 names x 2 duplicate rows). No business table references
--      these ids (verified against invoices/jobs/quotations/sale_orders/
--      shipping_advices/shipping_stocks origin/destination columns).
--
-- Idempotent: DELETE of already-removed rows is a no-op. Safe to re-run.
--
-- RUN:
--   mysql -u <user> -p <db_name> < migrations/2026_08_19_clean_ports_prefix.sql
-- ============================================================================

SET NAMES utf8mb4;

DELETE FROM `erp_ports`
WHERE `port_name` LIKE "'s%"
   OR `port_name` LIKE "'t%";

-- ============================================================================
-- Verify (should return 0 rows):
--   SELECT id, port_name FROM erp_ports WHERE port_name LIKE "'s%" OR port_name LIKE "'t%";
-- ============================================================================
