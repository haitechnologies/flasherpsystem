-- ============================================================================
-- Migration: CS Agents Table (2026-08-01)
-- Run: mysql --force -u user -p db < this_file.sql
-- Creates dedicated cs_agents table and seeds 3 Movestic agents
-- ============================================================================

CREATE TABLE IF NOT EXISTS `erp_cs_agents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `erp_cs_agents` (id, name, email, is_active, created_at, updated_at) VALUES
(1, 'Accounts Movestic Cargo', 'accounts@movesticargo.com', 1, NOW(), NOW()),
(2, 'Sales Movestic Cargo', 'sales@movesticargo.com', 1, NOW(), NOW()),
(3, 'Operations Movestic Cargo', 'cargo@movesticargo.com', 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  email = VALUES(email);
