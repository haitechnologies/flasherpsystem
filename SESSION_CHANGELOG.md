# Session Changelog

All notable changes from the current session are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project uses **date-based versioning** (`YYYY-MM-DD`).

---

## [2026-07-30]

### Added
- `resources/views/jobs/form.php`: Full form layout restructure to match source (flashlogisticsserver). Added dimension items L×W×H table with CBM auto-calculation, total CBM/volume/pieces summary, After Service card (happy_customer, unhappy_reason, shipment_on_time, referral), System Fields card (created_by, modified_by, customer_type, quote_id, books_customer_id, approved_time, project_id), Sales Person from Lead field, Job Seq field, port AJAX population on landing/destination country change
- `src/Http/Controller/JobController.php`: Added `JobItemService` dependency (`buildJobItemsData()`, dimension items processing in `handleCreate`/`handleUpdate`), `handleAjaxPorts()` endpoint for port population by country, added `item_dim_*_arr`/`total_rows`/`created_by`/`modified_by`/`books_customer_id`/`approved_time`/`approved_time_resubmission` to template data
- `dashboard/bootstrap.php`: Injected `JobItemService` into `JobController` constructor
- `dashboard/pdf_job.php`: New PDF generation page for jobs using bundled TCPDF library. Renders all job details as downloadable PDF.
- `docs/JOBS_IMPROVEMENTS_V2.md`: Planning document for round 2 jobs improvements.

### Changed
- `resources/views/jobs/form.php`: Layout restructured into 10 card rows following source — Row 1 (Warehouse/Customer/SalesPersonFromLead/JobSeq/SalesPerson + Currency/ExchangeRate/TransportMode/ShipmentType/JobOwner/Tags), Row 2 (Job Status Details), Row 3 (Detailed Job Information + Dates & Transport), Row 4 (L×W×H Dimensions + totals), Row 5 (Commodity Details + By Sea), Row 6 (Port Details landing/destination with AJAX port selects), Row 7 (After Service), Row 8 (System Fields read-only), Row 9 (Notes), Row 10 (Additional Details haizon extras)
- `resources/views/jobs/form.php`: `landing_port` and `destination_port` changed from text inputs to `<select>` with AJAX population on country change
- `resources/views/jobs/form.php`: `no_of_containers` changed from text input to `<select>` (0-100)
- `resources/views/jobs/form.php`: All 4 country dropdowns (Loading, Billing, Destination, Shipping) changed from `form-select select` to `form-select select2-enable` for Select2 search. `customer_id` empty_option changed from `'&nbsp;'` to `'Please select'`.
- `resources/views/jobs/form.php`: Removed '+' button and Add Service modal from Type of Services field — reverted to plain multi-select (without input-group). Added formula hint card inside dimensions section (CBM/Volume Weight formulas).
- `dashboard/view_job.php`: Replaced page header with source-style header — shows Job # and ID, job status, Edit button (conditional on draft status), Create Project button (conditional on approved status and project_created=0), "Project Created" disabled button (when project_created=1). Removed publish toggle from view page.
- `dashboard/admin_elements/admin_footer.php`: **100% Select2 CSS match to flashlogisticsserver Limitless theme** — 7 deltas fixed: border `#ddd`→`#D1D5DB`, choice padding `2px 8px`→`0.125rem 1rem`, remove X `opacity: 0.75` + `float: right` + `font-size: 1.25rem`, remove X hover `opacity: 1`, added `.select2-results__option--highlighted` (non-selected hover `#F3F4F6`/`#1F2937`), focus border `#0c83ff`, pills border-radius `calc(0.375rem - 1px)`. Removed `closeOnSelect: false` and hardcoded `'Select options...'` placeholder from Select2 init (now auto-detected / uses per-field `<option>` text).
- `src/Http/Controller/JobController.php`: Added `ajax_add_service` route and `handleAddService()` method — later removed when '+' button was reverted. Added `resolveUserName()` helper — queries `erp_users.email` by ID. Created By / Modified By now show user email instead of numeric IDs. Removed undefined `'cbm' => $cbm` from template data. Removed `ajax_add_service` route and `handleAddService()` method.
- `src/Http/Controller/JobController.php`: `handleAddCarrier()` — fixed `$this->db->insert()` call (was passing `DB::CARRIERS` constant as raw SQL instead of building proper INSERT statement with backtick-wrapped table name + named params).
- `src/DataTable/JobsDataTable.php`: PDF icon action link changed from `view_job.php?job_id=X` to `pdf_job.php?job_id=X` (opens in new tab).
- `dashboard/pdf_job.php`: Complete rewrite — styling now 100% matches `pdf_invoice.php` (teal `#e8f7f4` section headers, `#f1f1f1` borders, `#555` text, company name + warehouse info header, `#007B8B` title). Added ~40 missing data fields across 11 sections: Company Header, Job Info, Customer & Support, Route, Transport, Commodity Details, Dimensions, Billing & Financial, After Service, System Fields, Notes & Terms.
- `config/globals.php`: `getUsernameByID()` — added null guard (`if (empty($id)) return '';`) and null check on `fetch_array()` result to prevent "Trying to access array offset on null" crash when user ID references deleted/nonexistent user.
- `dashboard/customers.php`: Active Status checkbox — added `form="frm<?php echo $module; ?>"` HTML5 attribute so checkbox outside `<form>` element is still submitted.

### Fixed
- `src/Model/Job.php`: Changed `$loadingPlace` type from `int` to `?string` to match DB schema (VARCHAR).
- `src/Repository/JobRepository.php`: Updated `loadingPlace` mapping from `(int)($row['loading_place'] ?? 0)` to `$row['loading_place'] !== null ? (string)$row['loading_place'] : null`. Replaced 3 `SELECT *` queries with explicit column lists in `find()`, `findAll()`, `findByField()`.
- `src/Service/JobService.php`: Removed `(int)` cast on `commodityType` in both `createJob()` and `updateJob()` — field is now stored as string to match DB. Changed `loadingPlace` from `(int)` cast to `?string` with null handling.
- `src/Http/Controller/JobController.php`: Added missing `books_customer_id` field to `buildJobData()` array. `resolveUserName()` now queries `email` instead of `full_name`. `sales_person_from_lead` default changed from `'0'` to `''`. **Form input retention on validation error** — `handleCreate()` and `handleUpdate()` catch blocks store `$_POST` in `$_SESSION['__jobs_old_input']` before redirect; `showForm()` restores all scalar fields, multi-select arrays, and dimension items. **Publish ternary** — `publish => $request->get('publish') ? true : true` fixed to `:: false` (both branches returned `true`).
- `dashboard/view_job.php`: **SQL injection fix** — 6 vectors converted from `$mysqli->query()` interpolation to PDO named params via `$db->fetchOne/fetchAll/execute`. **Create Project security** — added CSRF token validation and org-level permission gate. **Modified By** now resolves to user's `full_name` via `getTableAttr()` instead of raw ID. **XSS prevention** — `htmlspecialchars()` wrapping on `getTableAttr()` output for tags and services. **Notes textarea** added `disabled` attribute in view-only mode. Changed `print_r($tags_captions)` to `echo $tags_captions`.
- `resources/views/jobs/form.php`: **MAWB label JS** — removed redundant `parts = ['MAWB']` reassignment in `import_export` handler. **dim_volume JS** — `add_item_row()` now generates dim_volume input with `readonly` and `bg-light bg-opacity-75` classes. Job No and Job Ref No now marked as mandatory.

### Migration Applied
- **Job# population**: All existing records with empty `job_no` set via `UPDATE erp_jobs SET job_no = CONCAT('JB-', LPAD(id, 4, '0')) WHERE job_no IS NULL OR job_no = ''`.

### Migrations Required on Live
1. **commodity_type column** — Run on live DB:
   ```sql
   ALTER TABLE `erp_jobs` MODIFY `commodity_type` VARCHAR(255) DEFAULT NULL;
   ```
   (Previously `INT`, changed to `VARCHAR(255)` during Commodity Type dropdown→text input change.)

2. **Job# population** — Run on live DB (for any records created after prior migration):
   ```sql
   UPDATE `erp_jobs` SET `job_no` = CONCAT('JB-', LPAD(id, 4, '0')) WHERE `job_no` IS NULL OR `job_no` = '';
   ```

### Added
- `resources/views/jobs/form.php`: New **CBM** decimal input field after Volume Weight in Commodity Details card.
- `dashboard/view_job.php`: CBM field display added after Volume Weight.
- `dashboard/pdf_job.php`: CBM field added to Commodity Details section (after Volume Weight).

### Changed
- `src/Model/Job.php`: Added `public float $cbm` property + `'cbm' => $this->cbm` in `toArray()`.
- `src/Repository/JobRepository.php`: Added `cbm` to all 3 SELECT queries, INSERT (columns + values), UPDATE SET, and `mapRowToJob()`.
- `src/Service/JobService.php`: Added `cbm:` to `createJob()` and `updateJob()` DTO construction.
- `src/Http/Controller/JobController.php`: Added `'cbm'` to `buildJobData()`, defaults, fetched values, and template data.

### Migrations Required on Live
1. **cbm column** — Run on live DB:
   ```sql
   ALTER TABLE `erp_jobs` ADD `cbm` DECIMAL(10,4) NOT NULL DEFAULT 0.0000 AFTER `volume_weight`;
   ```

2. **commodity_type column** — Run on live DB:
   ```sql
   ALTER TABLE `erp_jobs` MODIFY `commodity_type` VARCHAR(255) DEFAULT NULL;
   ```

3. **Job# population** — Run on live DB (for any records created after prior migration):
   ```sql
   UPDATE `erp_jobs` SET `job_no` = CONCAT('JB-', LPAD(id, 4, '0')) WHERE `job_no` IS NULL OR `job_no` = '';
   ```

### Session Maintenance Instructions
- **Every session must append all changes to this file under the current date** (`YYYY-MM-DD`) before finishing.
- Group entries under `### Added`, `### Changed`, `### Fixed`, `### Migration Applied`, `### Migrations Required on Live` as appropriate.
- List **all** files modified, with a concise description of what changed and why.
- Collect all `ALTER TABLE` / `UPDATE` / `INSERT` SQL statements under "Migrations Required on Live" so the user can deploy them to production.
- After updating this file, commit and push to `live/main`:
  ```bash
  git add SESSION_CHANGELOG.md
  git commit -m "docs: update SESSION_CHANGELOG.md"
  git push live main
  ```
