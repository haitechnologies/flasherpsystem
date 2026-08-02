-- ============================================================================
-- Migration: Add organization_id to erp_quotation_items (2026-07-31)
-- Run: mysql -u user -p db < this_file.sql
-- Idempotent — safe to run multiple times
-- ============================================================================
-- Background: erp_quotation_items was the ONLY *_items table missing
-- the organization_id column, causing QuotationRepository::insertItem()
-- to fail with "Unknown column 'organization_id' in 'field list'" on
-- creating new quotations. All other items tables (invoice_items,
-- job_items, etc.) already have this column for multi-tenancy isolation.
-- ============================================================================

-- 1. Add organization_id column (matches erp_invoice_items definition)
ALTER TABLE `erp_quotation_items`
ADD COLUMN `organization_id` int unsigned DEFAULT NULL AFTER `quotation_id`,
ADD KEY `idx_org_id` (`organization_id`);

-- 2. Backfill existing quotation items from parent quotations
UPDATE `erp_quotation_items` qi
JOIN `erp_quotations` q ON qi.quotation_id = q.id
SET qi.organization_id = q.organization_id;
