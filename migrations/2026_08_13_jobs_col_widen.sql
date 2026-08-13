-- 2026-08-13: Widen erp_jobs columns that truncate user input.
-- job_no varchar(10) was too short for the FL-JBym-XXXX auto-gen format (14 chars).
-- transport_mode / shipment_type varchar(20) truncated comma-joined multi-select values.
ALTER TABLE `erp_jobs`
  MODIFY COLUMN `job_no` VARCHAR(50) NOT NULL,
  MODIFY COLUMN `transport_mode` VARCHAR(100) NULL DEFAULT NULL,
  MODIFY COLUMN `shipment_type` VARCHAR(100) NULL DEFAULT NULL;

-- Deactivate the duplicate 'pending_approval' job status (id 7) so lookups resolve to id 3.
UPDATE `erp_job_statuses`
SET `is_active` = 0
WHERE `id` = 7 AND LOWER(`job_status`) = 'pending_approval';
