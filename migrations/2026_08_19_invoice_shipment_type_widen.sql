-- 2026-08-19: Widen erp_invoices.shipment_type for multi-select Delivery Method (Air/Sea/Land).
-- Delivery Method changed from a single value (export/import/transit) to a comma-separated
-- multi-select (air, sea, land). varchar(15) is too small for comma-joined values.
ALTER TABLE `erp_invoices`
  MODIFY COLUMN `shipment_type` VARCHAR(100) NULL DEFAULT NULL;
