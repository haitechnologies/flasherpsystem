## 2026-07-31

### Fixed — Jobs Module
- **Missing column**: `erp_jobs.cbm` added (`varchar(255) DEFAULT NULL`) — migration from 2026-07-29 was never applied, causing SQL error on job save.
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
- `migrations/jobs_fields_20260731.sql`: Consolidated DB changes for today (cbm column + hawb/mawb TEXT).

### Changed — Agent Context (token optimization)
- **docs/AGENTS.md**: 146→111 lines (-24%). Added `AGENTS_QUICKREF.md` to active refs.
- **docs/MANPOWER_FLOW.md**: 270→78 lines (-71%).
- **Skills**: `fix-sql/SKILL.md` 156→53 (-66%), `SKILL_COMMON.md` 67→46 (-31%), `create-crud/SKILL.md` 121→49, `migrate-repo/SKILL.md` 109→52.
- **opencode.json**: Removed redundant Intelephense LSP config (built-in).

### Added
- `docs/AGENTS_QUICKREF.md`: 52-line cheat sheet.
- `.opencode/skills/SKILL_COMMON.md`: Shared stack patterns.

### Live Deployment — SQL to run
```sql
-- File: migrations/jobs_fields_20260731.sql
ALTER TABLE `erp_jobs` ADD COLUMN `cbm` VARCHAR(255) DEFAULT NULL AFTER `chargeable_weight`;
ALTER TABLE `erp_jobs` MODIFY COLUMN `hawb` TEXT DEFAULT NULL;
ALTER TABLE `erp_jobs` MODIFY COLUMN `mawb` TEXT DEFAULT NULL;
```
