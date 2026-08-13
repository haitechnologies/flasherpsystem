-- ============================================================================
-- Migration: Create erp_setup_banks (bank institutions master data)
-- Source: CBUAE Central Bank Register (Banks) - August 2025
-- Single-organization (organization_id = 1)
-- ============================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `erp_setup_banks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `organization_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `register_no` INT UNSIGNED NOT NULL,
  `institution_name` VARCHAR(255) NOT NULL,
  `license_type` VARCHAR(50) NOT NULL,
  `license_category` VARCHAR(50) NOT NULL,
  `head_office` VARCHAR(50) NOT NULL,
  `identification_number` VARCHAR(50) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL DEFAULT NULL,
  `updated_by` INT UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_register_no` (`register_no`),
  UNIQUE KEY `uq_ident_no` (`identification_number`),
  KEY `idx_org` (`organization_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CBUAE CB Register - Banks (bank institutions master data)';

INSERT INTO `erp_setup_banks`
(`organization_id`, `register_no`, `institution_name`, `license_type`, `license_category`, `head_office`, `identification_number`, `is_active`)
VALUES
(1, 1,  'HSBC Bank Middle East Limited', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.001.1946.02', 1),
(1, 2,  'Standard Chartered Bank', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.002.1958.02', 1),
(1, 3,  'Emirates NBD Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Dubai', '01.01.01.003.1963.02', 1),
(1, 4,  'CitiBank N.A.', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.004.1964.02', 1),
(1, 5,  'Mashreq Bank P.S.C.', 'National Bank', 'Conventional Retail', 'Dubai', '01.01.01.005.1967.02', 1),
(1, 6,  'Habib Bank Ltd.', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.006.1967.02', 1),
(1, 7,  'United Bank Ltd.', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.007.1967.02', 1),
(1, 8,  'First Abu Dhabi Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Abu Dhabi', '01.01.01.008.1968.01', 1),
(1, 9,  'Bank Saderat Iran', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.009.1968.02', 1),
(1, 10, 'Bank of Dubai P.J.S.C', 'National Bank', 'Conventional Retail', 'Dubai', '01.01.01.010.1969.02', 1),
(1, 11, 'Al Ahli Bank of Kuwait', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.011.1969.02', 1),
(1, 12, 'Bank Melli Iran', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.012.1969.02', 1),
(1, 13, 'Arab African International Bank', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.013.1970.02', 1),
(1, 14, 'Banque Misr', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.014.1972.02', 1),
(1, 15, 'Bank of Sharjah P.J.S.C', 'National Bank', 'Conventional Retail', 'Sharjah', '01.01.01.015.1973.03', 1),
(1, 16, 'Arab Bank PLC', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.016.1973.01', 1),
(1, 17, 'BNP Paribas', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.017.1973.01', 1),
(1, 18, 'Al Khaliji (France) S.A.', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.018.1973.02', 1),
(1, 19, 'Rafidain Bank', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.020.1974.01', 1),
(1, 20, 'Bank of Baroda', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.021.1974.02', 1),
(1, 21, 'Janata Bank PLC', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.022.1974.01', 1),
(1, 22, 'Habib Bank A.G Zurich', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.023.1974.02', 1),
(1, 23, 'Banorient France', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.024.1974.02', 1),
(1, 24, 'Dubai Islamic Bank P.J.S.C', 'National Bank', 'Islamic Retail', 'Dubai', '01.01.02.025.1975.02', 1),
(1, 25, 'Sharjah Islamic Bank P.J.S.C.', 'National Bank', 'Islamic Retail', 'Sharjah', '01.01.02.026.1975.03', 1),
(1, 26, 'United Arab Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Sharjah', '01.01.01.027.1975.03', 1),
(1, 27, 'InvestBank P.J.S.C', 'National Bank', 'Conventional Retail', 'Sharjah', '01.01.01.028.1975.03', 1),
(1, 28, 'Credit Agricole-Corporate and Investment Bank', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.029.1975.02', 1),
(1, 29, 'Arab Bank for Inv.& Foreign Trade', 'National Bank', 'Conventional Retail', 'Abu Dhabi', '01.01.01.030.1976.01', 1),
(1, 30, 'Emirates Islamic Bank P.J.S.C.', 'National Bank', 'Islamic Retail', 'Dubai', '01.01.02.031.1976.02', 1),
(1, 31, 'National Bank of R.A.K P.J.S.C', 'National Bank', 'Conventional Retail', 'R.A.K', '01.01.01.032.1976.06', 1),
(1, 32, 'Emirates Investment Bank (PJSC)', 'National Bank', 'Conventional Investment', 'Dubai', '01.01.05.033.1976.02', 1),
(1, 33, 'El Nilein Bank', 'Foreign Bank', 'Islamic Retail', 'Abu Dhabi', '01.02.02.034.1976.01', 1),
(1, 34, 'Banque of Oman S.A.O.G.', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.035.1976.01', 1),
(1, 35, 'National Bank of U.A.Q PSC', 'National Bank', 'Conventional Retail', 'U.A.Q', '01.01.01.037.1982.05', 1),
(1, 36, 'National Bank of Bahrain', 'Foreign Bank', 'Conventional Retail', 'Abu Dhabi', '01.02.01.038.1982.01', 1),
(1, 37, 'National Bank of Fujairah PSC', 'National Bank', 'Conventional Retail', 'Fujairah', '01.01.01.039.1984.07', 1),
(1, 38, 'Abu Dhabi Commercial Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Abu Dhabi', '01.01.01.040.1985.01', 1),
(1, 39, 'Commercial Bank International P.J.S.C', 'National Bank', 'Conventional Retail', 'Dubai', '01.01.01.041.1991.02', 1),
(1, 40, 'Abu Dhabi Islamic Bank P.J.S.C', 'National Bank', 'Islamic Retail', 'Abu Dhabi', '01.01.02.042.1997.01', 1),
(1, 41, 'Al Hilal Bank P.J.S.C', 'National Bank', 'Islamic Retail', 'Abu Dhabi', '01.01.02.045.2007.01', 1),
(1, 42, 'Doha Bank', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.046.2007.02', 1),
(1, 43, 'The Saudi National Bank', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.047.2007.02', 1),
(1, 44, 'Ajman Bank P.J.S.C', 'National Bank', 'Islamic Retail', 'Ajman', '01.01.02.048.2008.04', 1),
(1, 45, 'National Bank of Kuwait', 'Foreign Bank', 'Conventional Retail', 'Dubai', '01.02.01.049.2008.02', 1),
(1, 46, 'Commercial Bank of China', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.050.2009.01', 1),
(1, 47, 'Deutsche Bank AG', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.051.2009.01', 1),
(1, 48, 'KEB Hana Bank', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.053.2012.01', 1),
(1, 49, 'Barclays Bank PLC', 'Foreign Bank', 'Conventional Wholesale', 'Dubai', '01.02.03.054.2014.02', 1),
(1, 50, 'Bank of China Limited', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.055.2014.01', 1),
(1, 51, 'Gulf International Bank B.S.C', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.056.2014.01', 1),
(1, 52, 'MCB Bank Limited', 'Foreign Bank', 'Conventional Wholesale', 'Dubai', '01.02.03.057.2015.02', 1),
(1, 53, 'Intesa Sanpaolo S.P.A', 'Foreign Bank', 'Conventional Wholesale', 'Abu Dhabi', '01.02.03.058.2016.01', 1),
(1, 54, 'Agricultural Bank of China Ltd.', 'Foreign Bank', 'Conventional Wholesale', 'Dubai', '01.02.03.059.2016.02', 1),
(1, 55, 'Bank Al Falah Limited', 'Foreign Bank', 'Conventional Wholesale', 'Dubai', '01.02.03.060.2017.02', 1),
(1, 56, 'BOK International Bank', 'Foreign Bank', 'Islamic Retail', 'Abu Dhabi', '01.02.02.061.2017.01', 1),
(1, 57, 'Al Maryah Community Bank L.L.C.', 'National Bank', 'Conventional Specialized', 'Abu Dhabi', '01.01.07.062.2021.01', 1),
(1, 58, 'WIO Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Abu Dhabi', '01.01.01.063.2022.01', 1),
(1, 59, 'Zand Bank P.J.S.C', 'National Bank', 'Conventional Retail', 'Dubai', '01.01.01.064.2022.02', 1),
(1, 60, 'International Development Bank for Investment & Finance', 'Foreign Bank', 'Conventional Wholesale', 'Dubai', '01.02.03.066.2022.02', 1),
(1, 61, 'Ruya Community Islamic Bank L.L.C', 'National Bank', 'Islamic Specialized', 'Ajman', '01.01.08.067.2024.04', 1);

-- Register module + permissions
INSERT INTO `erp_modules` (`slug`, `module_name`, `module_type`, `systems`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT 'setup_banks', 'Banks (Institutions)', 'module', '', 1, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `erp_modules` WHERE slug = 'setup_banks');

SET @setup_banks_mod_id = (SELECT id FROM `erp_modules` WHERE slug = 'setup_banks');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'view', 'View', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'view');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'create', 'Create', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'create');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'edit', 'Edit', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'edit');

INSERT INTO `erp_module_permissions` (`module_id`, `slug`, `permission_name`, `publish`, `is_active`, `created_by`, `created_at`, `updated_at`)
SELECT @setup_banks_mod_id, 'delete', 'Delete', 1, 1, 1, NOW(), NOW()
WHERE @setup_banks_mod_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `erp_module_permissions` WHERE module_id = @setup_banks_mod_id AND slug = 'delete');
