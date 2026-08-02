## 2026-08-02 (Project Rename: haizon → flasherpsystem)

### Changed
- **Project rename**: All branding, code references, paths, and identifiers renamed from `haizon` → `flasherpsystem`.
- **Local database**: `haizon` DB renamed to `flasherpsystem` (dumped, imported, verified via checksums, old DB dropped).
- **`.env`**: `DB_DATABASE=flasherpsystem`, `PROJECT_PREFIX="flasherpsystem"`, `REMOTE_DB_DATABASE=u904789561_flasherpsystem`, `REMOTE_DB_USERNAME=u904789561_flasherpsystem`.
- **Session prefix**: `flashlogistics` → `flasherpsystem` — all existing sessions invalidated, users must re-login. `config/session.php` primary functions renamed `haizonSession*` → `flasherpsystemSession*` with recursion-free backward-compat aliases.
- **Code fallbacks**: 20+ files updated (`config/constants.php`, `config/globals.php`, `src/Core/Session.php`, `src/Core/ServerRequest.php`, `src/Core/DeletionManager.php`, `src/Security/Roles.php`, `src/Http/Middleware/*`, `dashboard/datatables_dispatcher.php`, `dashboard/admin_elements/error_logger.php`, `dashboard/api/BaseAPI.php`, `config/cli_database.php`, `tests/test_invoice_psr.php`).
- **Branding**: "HAIZON" → "FLASH ERP SYSTEM" (`config/seo_helpers.php`, `config/error_alerting.php`, `src/Service/SMTPMailer.php`, `dashboard/bootstrap.php`, `dashboard/global_settings.php`, cron templates); EHLO fallback `haizon.local` → `flasherpsystem.local`.
- **Paths**: Local Apache base path `/haizon/dashboard/` → `/flasherpsystem/dashboard/` (`dashboard/404.php`, `dashboard/.htaccess`). Live cron paths `/var/www/haizon`, `/var/log/haizon` → `/var/www/flasherpsystem`, `/var/log/flasherpsystem`.
- **Git remote**: `live` → `https://github.com/haitechnologies/flasherpsystem.git` (requires GitHub repo rename first).
- **composer.json** name → `haitechnologies/flasherpsystem`; `composer.lock` content-hash regenerated via `composer update --lock`.
- **Tests**: `@haizon.com` fixtures → `@flasherpsystem.com`; suite runner headers updated.
- **Local folder**: `G:\xampp\htdocs\haizon` copied to `G:\xampp\htdocs\flasherpsystem` (old folder deletion deferred — locked by session).
- `APP_NAME` ("Flash Logistics") intentionally unchanged. Legacy `hai_` table prefixes and `.htaccess` `haipulse` redirect rules intentionally left as-is.
- **Plan reference**: `RENAME_HAIZON_TO_FLASHEPRSYSTEM.md`.

## 2026-08-01 (Error Log Fixes — Live Debug)

### Fixed
- **`dashboard/listing_purchases.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_purchase_orders.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_expenses.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_credit_notes.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_sale_orders.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_invoices.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/listing_shipping_invoices.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`dashboard/report_leads.php`**: Fixed `require 'vendor/autoload.php'` → `require '../vendor/autoload.php'`
- **`resources/views/recurring_invoices/form.php`**: Fixed `country_name` → `country` in geo_countries SELECT (actual column name)
- **`dashboard/listing_jobs.php`**: Added `$page = $_GET['page'] ?? '';` initialization (was undefined)
- **`dashboard/view_shipping_advice.php`**: Added default variable initializations for ~20 undefined variables

### Added
- **`migrations/add_missing_modules_20260801.sql`**: Registers 5 missing module slugs in `erp_modules` — `customer_statement`, `customer_transactions`, `customer_comments`, `customer_billing_addresses`, `carriers` — with full permissions and role grants.

## 2026-08-01 (CS Agents)

### Added
- **`erp_cs_agents` table**: Dedicated table for CS agent assignment (id, name, email, is_active, timestamps)
- **DB constant**: `DB::CS_AGENTS` in `src/Core/DB.php`
- **Migration**: `migrations/cs_agents_20260801.sql` — seeds 3 Movestic Cargo agents:
  - Accounts Movestic Cargo (accounts@movesticargo.com)
  - Sales Movestic Cargo (sales@movesticargo.com)
  - Operations Movestic Cargo (cargo@movesticargo.com)

### Changed
- **`src/Http/Controller/JobController.php`**: CS Agent dropdown now queries `erp_cs_agents` instead of `erp_users`
- **`resources/views/jobs/form.php`**: CS Agent select uses `$cs_agents_options` (from cs_agents table)
- **`dashboard/view_job.php:649`**: CS Agent display resolved from `erp_cs_agents.name` instead of `erp_users.full_name`
- **`dashboard/pdf_job.php:98`**: CS Agent name resolved from `erp_cs_agents` instead of `getUsernameByID()`

## 2026-08-01 (Geo & Ports Data Migration)

### Added
- **`migrations/geo_countries_seed_20260801.sql`**: Standalone ISO 3166-1 country seed — 248 countries with alpha2/alpha3/numeric codes and dialing codes. No cross-database dependency. Idempotent (`INSERT IGNORE`). Also seeds 7 UAE emirates into `erp_geo_states`.
- **`migrations/ports_seed_20260801.sql`**: Worldwide port seed from UN/LOCODE — 17,452 maritime ports with UN/LOCODE codes, linked to `erp_geo_countries` via alpha2_code subquery. Includes unique index `uq_port_country` to prevent duplicates. Idempotent.

### Changed
- **`erp_geo_countries`**: Added `alpha2_code`, `alpha3_code`, `numeric_code` columns and unique index `uq_country` (safe if existing).
- **`erp_ports`**: Added unique index `uq_port_country` on `(port_name, country_id)` for deduplication.

### Fixed — Convert to Invoice: missing organization_id
- **`dashboard/quotation_overview.php:126-127`**: Added `organization_id` to INSERT INTO `erp_invoices` column list and SELECT from `erp_quotations`.
- **`dashboard/quotation_overview.php:136-137`**: Added `organization_id` to INSERT INTO `erp_invoice_items` column list and SELECT from `erp_quotation_items`.

### Fixed — Convert to Invoice: dimension_items not copied
- **`dashboard/quotation_overview.php`**: Added INSERT-SELECT to copy `erp_dimension_items` from `module_type='quotations'` to `module_type='invoices'` on conversion.

### Fixed — DELETE: orphaned quotation_items and dimension_items
- **`dashboard/quotation_overview.php:239`**: Added deletion of `erp_dimension_items` and `erp_quotation_items` before deleting the quotation header.
- **`dashboard/listing_quotations.php:26,29`**: Added deletion of `erp_dimension_items` before deleting quotation_items and quotation header (both admin and creator delete paths).

### Fixed — Sidebar: missing organization_id scoping (multi-tenancy violation)
- **`dashboard/admin_elements/sidebar_quotation.php:68,77`**: Added `q.organization_id = $activeOrganizationId` to both SELECT and COUNT queries. Added `dashboardRequireActiveOrganization()` fallback guard.

### Fixed — send_email.php: undefined $row on GET load
- **`dashboard/send_email.php:90-91`**: Removed `$doc_status` and `$doc_date` lines that referenced undefined `$row` variable (values were never used in the template).

### Fixed — send_email.php: subject shows DB id instead of document number
- **`dashboard/send_email.php:108`**: Changed subject from `"$module_caption - $id"` to `"$module_caption $doc_no"` to show actual quotation number.

### Fixed — send_email.php: email send SELECT missing org scoping
- **`dashboard/send_email.php:146`**: Added `AND organization_id = $activeOrganizationId` to the email-send SELECT query.

### Fixed — quotation_overview.php: status update missing validation
- **`dashboard/quotation_overview.php:213-216`**: Added allowlist validation for `quotation_status` values before UPDATE (13 allowed statuses).

### Fixed — pdf_quotation.php: hardcoded table name
- **`dashboard/pdf_quotation.php:240`**: Changed `erp_organizations` to `DB::ORGANIZATIONS` constant.

### Fixed — Clone quotation: quotation_id not passed in POST body
- **`dashboard/admin_elements/page_header_quotation.php`**: Added hidden `quotation_id` input to `postQuotationAction()` JS form — ensures clone/convert/status actions receive `quotation_id` even when PHP `request_order` excludes GET.

### Fixed — send_email.php: description empty on page load
- **`dashboard/send_email.php:90`**: Added pre-population query that fetches `subject` and `customer_notes` (or `notes`) from the source document. On GET load, description textarea is now pre-filled with relevant document content instead of empty.

### Fixed — opencode.json: dead @database reference to missing database_export.sql
- **`opencode.json:16`**: Changed `@database` reference path from `database_export.sql` (deleted) to `docs/DATABASE.md` (maintained). Updated description accordingly.
- **`.opencodeignore`**: Removed stale `database_export.sql` exclusion.

### Fixed — pdf_quotation.php: no organization_id scoping (cross-org data leak)
- **`dashboard/pdf_quotation.php:84`**: Added `AND q.organization_id = $activeOrgId` to the main quotation SELECT. Added `use App\Core\Session` and `$activeOrgId = Session::orgId()` initialization.

### Fixed — quotation_overview.php: delete restricted to SystemAdmin/SuperAdmin
- **`dashboard/quotation_overview.php:246-250`**: Changed delete gating from `is_SystemAdmin() || is_SuperAdmin()` to `Session::roleId() == '1'` for admin or `created_by` ownership for non-admin users — aligned with `listing_quotations.php` pattern. Added `use App\Core\Session`.

### Fixed — form.php: duplicate CSRF token hidden input
- **`resources/views/quotations/form.php:109-110`**: Removed redundant manual `<input name="csrf_token" value="...">` — `csrf_field()` already outputs the hidden input.

### Fixed — QuotationService: status allowlist too narrow
- **`src/Service/QuotationService.php:525`**: Expanded status allowlist from 6 to 13 values matching `quotation_overview.php`: added `declined, pending, approved, invoiced, not_confirmed, on_hold, cancelled`.

### Fixed — QuotationRepository: delete() missing dimension_items cascade
- **`src/Repository/QuotationRepository.php:272`**: Added `DELETE FROM DB::DIMENSION_ITEMS WHERE module_type='quotations' AND record_id = :id` before the quotation delete — eliminates orphaned dimension records on service-layer deletes.


### Added — Quotations Clone Plan
- **`QUOTATIONS_CLONE_PLAN.md`**: Full 14-file clone plan for quotations module migration from flashlogistics. Covers DB schema (3 new columns: hwb_hbol, origin_country, destination_country), model/repo/service/controller updates, AJAX infrastructure (4 new functions + 4 internal_request handlers), form.php full rewrite (~1200 lines), sidebar/page_header changes, rollback strategy via git branch.

### Changed — Security: .gitignore Markdown Rules
- **Crit fix**: `!HR_CHANGELOG.md` and `!ACCOUNTING_CHANGELOG.md` were placed BEFORE `*.md` rule, making them ineffective (last matching rule wins). Moved all `!` re-includes AFTER `*.md`.
- **Added**: `!/OPERATIONS_CHANGELOG.md` — was missing entirely, silently ignored by `*.md`.
- **Hardened**: Changed `!README.md` → `!/README.md` (root-only) and all `!` rules to `!/` prefix — prevents accidental re-inclusion of .md files in subdirectories.
- **Cleaned**: Removed 21 redundant individual .md exclusions already covered by `*.md` catch-all. Non-.md scratch files (html/txt/lnk) retained.

### Migration — Live Server
- **`migrations/jobs_fix_live_20260731.sql`**: Idempotent MODIFY-only migration — fixes `unhappy_reason`/`referral`/`notes` NOT NULL constraints, `hawb`/`mawb` → TEXT, `pending_approval` status rename. Safe to re-run.

### Fixed — Port Popup
- **New port only appends to clicked dropdown** (no longer duplicates to both POL/POD). Uses `portModalTarget` variable to track which select opened the modal.
- **Country pre-selected in port popup** — reads currently selected country from parent dropdown.

### Fixed — Customer Module
- **Approve/Disapprove badge + buttons not showing**: Root cause was `getTableAttr()` returning `''` for `0` values — `!empty($row[0])` evaluates `empty(0)` as `true`. Fixed to `$row[0] !== null` check in `config/globals.php:554`. This affects ALL modules using `getTableAttr()` with zero values.
- **Approve/Disapprove logic**: Explicit 3-way badge (Approved green / Awaiting Approval amber) and conditional button display. Restricted to Accounts (role=5) and FullAccess roles — no other role can approve/disapprove. Action handlers also guarded.
- **Opening Balance display**: Now shows balance value with Edit option when set, "Enter" link only when unset. Modal pre-populates existing balance.
- **Opening Balance accounting**: `updateOpeningBalance()` now creates a real journal entry (Debit: Accounts Receivable #9, Credit: Opening Balance Offset #118). Transactional — both customer record and journal succeed or rollback together.
- **Receivables calculation**: `getReceivables()` now includes `opening_balance` in total (invoices + opening balance).

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
- `CHANGELOG.md`: New root-level changelog tracking all changes from 2026-07-31 onward.

### Added — Customer Module
- **Opening Balance on create/edit form** (`customers.php`): Opening Balance input field added above TAX dropdown with `type="number" step="0.01"` and AED input-group prefix. `CustomerController` now passes `opening_balance` through defaults (`0.00`), DB load (`number_format()` for 2-decimal display), and render data.
- **Migration**: `migrations/accounts_role_address_perms_20260731.sql` — Grants Accounts role (id=5) view/create/edit/delete on `customer_billing_addresses` and `customer_shipping_addresses` modules. Registers both modules in `erp_modules`, permission types in `erp_module_permissions`, and role-permission mappings in `erp_permissions` for roles 1, 2, and 5.

### Fixed — View Job
- **CSRF token**: `view_job.php` form missing `csrf_token` hidden field — approve/reject buttons returned "Invalid security token."
- **Button alignment**: Edit/Approve/Reject/Cancel buttons now right-aligned via `ms-auto` on the container div.
- **Job status**: Renamed 'pending' → 'pending_approval' in `erp_job_statuses` (workflow expects 'pending_approval').

### Added — Country Quick-Add
- **Form UX**: All 4 country dropdowns (Loading, Billing, Destination, Shipping) now have a `+` button inline — opens modal to add a new country, inserts into `erp_geo_countries`, and refreshes all country selects via Select2/AJAX.
- **Controller**: `JobController::handleAddCountry()` — AJAX POST handler.

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

### Fixed — Quotations Form Flash Messages
- `src/Http/Controller/QuotationController.php`: removed `FlashMessage::all()` drain in `showForm` (lines 180-184); error redirects in `handleUpdate`/`handleCreate` now pass `&error_message=` URL param (urlencoded) instead of `flash_error()` — matches `JobController` pattern.
- `resources/views/quotations/form.php`: added `<?php include 'admin_elements/breadcrumb.php'; ?>` inside `.content` area; removed inline `alert alert-danger` block — error messages now render as toasts via `messages.php` `$_REQUEST['error_message']` source.
- `dashboard/admin_elements/page_header_quotation.php`: merged all action buttons (Cancel, Edit, Send Email, PDF, Convert to Invoice, dropdown) into `page-header-content d-lg-flex` container with `ms-lg-auto`; removed separate `<div class="row">` that caused 2-line button split.

### Fixed — Customer/Inquiry/Item Delete: CSRF Failure + Double Confirmation
- `assets/js/dashboard-datatable-initializer.js`: `bindDeleteHandler` now appends hidden `csrf_token` field (from `window.HAI_CSRF_TOKEN` or page `input[name="csrf_token"]`); supports custom confirm via `data-confirm` attribute on delete links.
- `dashboard/listing_customers.php`: removed duplicate `data-action="delete_record"` handler from `$listingConfig['extra_js']` (was causing double `confirm()` + missing csrf → "Invalid security token").
- `src/Helper/ActionButtonHelper.php`: `deleteButton()` now accepts optional `$confirmMessage` param, emits `data-confirm` attribute.
- `src/DataTable/CustomersDataTable.php`: passes `'Are you sure you want to delete this customer?'` to `deleteButton()`.
- `dashboard/listing_inquiries.php`: added `$(document).off('click.haiDatatableDelete', '[data-action="delete_record"]')` before page-level handler to avoid double-confirm (same pattern as `listing_invoices.php:363-364`).
- `dashboard/listing_items.php`: same `.off` call before page-level handler.

### Fixed — Quotation CRUD QA Fixes (7 bugs)
- **BUG 1 (Date corruption)**: `QuotationController::showForm` no longer converts `expiry_date`/`expected_shipment_date` to d/m/Y via `toDisplayDate` (mismatched datepicker `yy-mm-dd`), stays Y-m-d end-to-end. `QuotationService` fixed inverted `toDisplayDate`→`toDbDate` in all 6 date-parsing branches (createQuotation + updateQuotation).
- **BUG 2 (isActive fallback)**: `updateQuotation` line 266 `isActive` fallback `$quotation->publish` → `$quotation->isActive`.
- **BUG 3 (Item ownership)**: `updateQuotation` now validates incoming item `id` exists in `$existingIds` before `saveItem`; throws `ValidationException` if item doesn't belong to this quotation.
- **BUG 4 (Zero-item update)**: `updateQuotation` now throws `ValidationException` if `$itemsData` is empty (matching `createQuotation`'s guard).
- **BUG 5 (Server-side totals)**: Item `sub_total`/`tax_amount`/`total` and all grand totals (`grand_subtotal`, `grand_discount_amount`, `grand_after_discount`, `grand_tax`, `grand_total`) are now fully recomputed server-side (item total = qty * rate * (1 + tax/100); discount applied by type; grand totals derived). POSTed amounts are ignored.
- **BUG 6 (Delete security)**: `listing_quotations.php` delete now requires `POST`, `validate_csrf_token()`, `granted('delete', $module_id)`, `organization_id` scoping, and ownership check (non-admin = `created_by` match). Removed GET-triggerability.
- **BUG 7 (Stored XSS)**: `quotation_overview.php` item descriptions, customer notes, and terms & conditions are now wrapped with `e()` (htmlspecialchars entity escaping).

### Fixed — Quotation CRUD QA Fixes (round 2, 7 bugs)
- **BUG A (Discount never applied)**: `QuotationService` discount blocks now accept `'percent'` (form sends) alongside `'percentage'` (legacy). Previously `grand_discount_amount` always 0.
- **BUG B (publish always false)**: `buildQuotationData` now sets `publish` key only when `$request->has('publish')`, defaulting to `true` in service. Form has no publish field; all new quotations were stored `publish=0`.
- **BUG C (Overview actions insecure)**: `quotation_overview.php` action blocks (convert to invoice, clone, update status, delete) now require `POST`, CSRF token validation, and appropriate `granted()` checks. All queries org-scoped via `$activeOrganizationId` (IDOR fix).
- **BUG D (Overview Delete dropdown broken)**: `page_header_quotation.php` dropdown action links (Mark As Sent/Accepted/Declined, Clone, Delete) + Convert to Invoice converted to POST forms via `postQuotationAction()` JS function carrying `csrf_token`. Delete targets `listing_quotations.php` with POST (reuses secure BUG 6 block).
- **BUG E (servicesList query broken)**: `QuotationController::showForm` fixed to `SELECT id, item_name AS service_name, unit_price AS service_rate` (columns `service_name`/`service_rate` don't exist on `items` table).
- **BUG F (internal_request.php no CSRF/org-scope)**: `internal_request.php` added CSRF validation for `add_shipper`/`add_consignee` write actions. `ajax.js` all 6 functions now send `csrf_token` via `getCsrfToken()` helper (reads `window.HAI_CSRF_TOKEN || input[name="csrf_token"]`).
- **BUG G (Clone isActive + expiry)**: `cloneQuotation` fixed `isActive: $quotation->publish` → `$quotation->isActive` and `expiryDate: date('Y-m-d')` → `$quotation->expiryDate`.

### Fixed — XSS hardening in quotation_overview.php
- All dynamic outputs wrapped with `e()` (htmlspecialchars entity escaping), including: customer/lead display_name, company_name, billing address fields; quotation_no, job_reference_no, shipment fields; item table values (qty, rate, subtotal, tax amounts, total); grand total display (subtotal, discount, tax, total); getTableAttr results (shipper, consignee, country, port, payment_term, warehouse); warehouse information block; invoice table rows.

### Fixed — Quotation items insert failure (missing organization_id)
- **Root cause**: `erp_quotation_items` was the only `*_items` table missing the `organization_id` column. `QuotationRepository::insertItem()` references `organization_id` in its column list, causing `SQLSTATE[42S22]: Unknown column 'organization_id' in 'field list'` when creating a new quotation with items. Parent `erp_quotations` has the column and saves fine; the failure occurs on item insert.
- **Migration**: `migrations/quotation_items_org_id_20260731.sql` — adds `organization_id int unsigned DEFAULT NULL` + `KEY idx_org_id` to `erp_quotation_items` (matches `erp_invoice_items` definition), backfills existing 8 rows from parent quotations. Idempotent — safe to re-run.
- **Live deployment**: Run `mysql -u user -p db < migrations/quotation_items_org_id_20260731.sql` on the live server. No PHP code changes needed — repository and model already reference the column.

### Fixed — Quotation form: labels, mandatory warehouse, country dropdown, error logging (6 fixes)
- **Customer Name label**: red danger styling applied to entire label text (matching Quotation Date pattern), not just asterisk.
- **Warehouse mandatory**: `QuotationService::validateQuotationData()` now requires `warehouse_id` (was previously optional — saving without a warehouse would succeed but store `0`).
- **Country dropdown empty**: `QuotationController` countriesList query fixed to use actual column names (`country`, `abbr`) instead of non-existent `country_name`, `alpha2_code`. Added `WHERE is_active=1`. Form.php templates updated from `$row['country_name']` to `$row['country']`. `quotation_overview.php` all 5 `getTableAttr('country_name',...)` calls changed to `getTableAttr('country',...)`.
- **Country label wrapping**: origin/destination Country labels widened from `col-lg-1` to `col-lg-2`, port selects narrowed from `col-lg-4` to `col-lg-3`.
- **Volume label wrapping**: Volume label widened from `col-lg-1` to `col-lg-2`, input narrowed from `col-lg-5` to `col-lg-4`.
- **Error logging for quotations**: `QuotationController` now calls `log_error()` in all Throwable catch blocks (handleUpdate, handleCreate, showForm quotation-load) with module context `['module'=>'quotations','module_slug'=>'quotations']` + stack trace. Errors appear in `erp_backend_error_logs` table, viewable at `view_backend_error_logs.php`. Uncaught exceptions already logged globally via `error_handler_init.php` (wired in `bootstrap.php:100`). ValidationException not logged (user-input errors, expected).

### Fixed — Quotations: PDF, Clone, Send Email (4 fixes)
- **PDF 404**: Created `dashboard/pdf_quotation.php` (modeled on `pdf_invoice.php`) — TCPDF-based PDF with quotation data, billing address, items table, terms & conditions, token-based security.
- **Clone not working**: Both clone INSERT queries in `quotation_overview.php` were missing `organization_id` from column list and SELECT. Added `organization_id` to both `erp_quotations` clone INSERT (line 183) and `erp_quotation_items` clone INSERT (line 190).
- **Send email fixes**: `dashboard/send_email.php` —
  - `send_to` field now pre-populated on page load (removed `$send_to = ''` reset in else branch)
  - User-entered `description` now included in email body below the document summary
  - Page title changed from "New Quotation" to "Send Quotation #[quotation_no]"
  - Button text changed from "Save" to "Send"
  - `$doc_no` variable added for page title: fetches document number (quotation_no, invoice_no, etc.) from module table on initial load