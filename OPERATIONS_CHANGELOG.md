## 2026-07-31

### Added — Geo Data Import
- **erp_geo_countries**: Added `alpha2_code`, `alpha3_code`, `numeric_code` columns. Changed `dialing_code` from `INT` to `VARCHAR(20)`. Imported 72 new countries + updated 196 existing from uaehscodes (269 total with ISO codes). UAE now has proper ISO data (AE/ARE/784/971).
- **erp_geo_states**: Imported 4,120 states from uaehscodes (was 0). UAE now has 7 proper emirates. Removed 6 transliteration duplicates from uaehscodes.
- **uae_geo_constants.php**: Updated UAE_COUNTRY_ID to 56 and emirate IDs to match actual DB records.
- **Migration**: `migrations/geo_data_import_20260731.sql` — cross-database import from uaehscodes.

### Fixed — Jobs Module
- **Missing columns**: `erp_jobs.cbm` added (`varchar(255)`) — migration from 2026-07-29 was never applied. Also added 12 missing columns (`quotation_id`, `subject`, `terms_and_conditions`, `grand_subtotal`, `grand_discount_type`, `grand_discount_type_value`, `grand_discount_amount`, `grand_after_discount`, `customer_notes`, `grand_tax`, `grand_total`, `customer_type`) — all referenced in `JobRepository` queries but absent from production schema, causing SQLSTATE[42S22] on every save.
- **Date picker**: Calendar icon now clickable to open datepicker across all date fields (`form_field_date.php`). Added `z-index: 10000 !important` to prevent icons/buttons from overlapping the popup (`haipulse-dashboard-compat.css`).
- **Dimension totals**: `calculateGrand()` now called after row removal (`clear_row`) and always after `calculateItemCBM` — fixes totals not updating on row delete or pcs=0.

### Changed — Jobs Module
- **Database**: `erp_jobs.hawb` and `erp_jobs.mawb` changed `varchar(300)` → `TEXT` for multi-line support.
- **Form UI**: HAWB/HBL and MAWB/MBL changed from `<input>` to auto-grow `<textarea>` with `field-sizing: content` + JS `data-autogrow` fallback.
- **Field defaults**: Dimension line items now default pcs=0 (was 1), min=0 (was 1), total pieces starts at 0.
- **Display views**: `view_job.php`, `pdf_job.php` now use `nl2br(htmlspecialchars())` for multi-line rendering.
- **JavaScript**: `$('input[name="mawb"]')` → `$('[name="mawb"]')` for textarea compatibility.

### Changed — Sidebar
- **Shipping > Settings**: Added "Countries" link above Ports pointing to `listing_geo_countries.php`. Operations role (4) already has permissions for `geo_countries` (module 133).

### Added — Migration
- `migrations/jobs_fields_20260731.sql`: Consolidated DB changes — cbm + 12 missing columns + hawb/mawb TEXT (15 ALTERs total).

### Changed — Agent Context (token optimization)
- **docs/AGENTS.md**: 146→111 lines (-24%). Added `AGENTS_QUICKREF.md` to active refs.
- **docs/MANPOWER_FLOW.md**: 270→78 lines (-71%).
- **Skills**: `fix-sql/SKILL.md` 156→53 (-66%), `SKILL_COMMON.md` 67→46 (-31%), `create-crud/SKILL.md` 121→49, `migrate-repo/SKILL.md` 109→52.
- **opencode.json**: Removed redundant Intelephense LSP config (built-in).

### Added
- `docs/AGENTS_QUICKREF.md`: 52-line cheat sheet.
- `.opencode/skills/SKILL_COMMON.md`: Shared stack patterns.

### Live Deployment — Run on production database

```sql
-- File: migrations/jobs_fields_20260731.sql (15 statements)
ALTER TABLE `erp_jobs` ADD COLUMN `cbm` VARCHAR(255) DEFAULT NULL AFTER `chargeable_weight`;
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` ADD COLUMN `quotation_id` INT UNSIGNED DEFAULT NULL AFTER `customer_id`;
ALTER TABLE `erp_jobs` ADD COLUMN `subject` TEXT DEFAULT NULL AFTER `shipping_country`;
ALTER TABLE `erp_jobs` ADD COLUMN `terms_and_conditions` TEXT DEFAULT NULL AFTER `subject`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_subtotal` DECIMAL(10,2) DEFAULT 0.00 AFTER `terms_and_conditions`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_type` VARCHAR(20) DEFAULT '0.00' AFTER `grand_subtotal`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_type_value` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_discount_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_type_value`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_after_discount` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_discount_amount`;
ALTER TABLE `erp_jobs` ADD COLUMN `customer_notes` TEXT DEFAULT NULL AFTER `grand_after_discount`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_tax` DECIMAL(10,2) DEFAULT 0.00 AFTER `customer_notes`;
ALTER TABLE `erp_jobs` ADD COLUMN `grand_total` DECIMAL(10,2) DEFAULT 0.00 AFTER `grand_tax`;
ALTER TABLE `erp_jobs` ADD COLUMN `customer_type` VARCHAR(100) DEFAULT NULL AFTER `approved_time_resubmission`;
```

mysql -u u904789561_haizon -p u904789561_haizon < migrations/jobs_fields_20260731.sql

rn|M|9D@Y2c

mysql -u u904789561_haizon -p u904789561_haizon < migrations/geo_data_import_20260731.sql
