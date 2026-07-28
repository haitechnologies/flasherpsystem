# Changelog

All notable changes to this project are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project uses **date-based versioning** (`YYYY-MM-DD`).

---

## [2026-07-28]

### Fixed
- `src/Http/Controller/JobController.php:464`: Fixed country dropdowns not loading — replaced `DB::PREFIX` (private) with `DB::GEO_COUNTRIES` (public)
- `dashboard/admin_elements/page_header_*.php` (13 files): Fixed button layout — removed nested `.row > .row` causing button wrapping, moved Cancel button out of `#breadcrumb_elements` collapse into same flex row as action buttons, moved all buttons inside `page-header-content` for proper flex alignment

## [2026-07-06]

### Added
- `dashboard/admin_elements/sidebar.php`: Added "Items" link above "Banking" in Accounting section
- `dashboard/listing_currencies.php`: Replaced DataTable with static listing showing only AED (primary) and USD
- `erp_items`: Seeded 18 shipping/service items (Airline handling, Brokerage, Dangerous goods, etc.) with random prices (25-750 AED)
- `src/DataTable/*.php`: Fixed SR column in 53 DataTable files — replaced `$id` with `$this->rowNumber` at return array index 0 so serial numbers show 1,2,3 instead of DB IDs
- `dashboard/items.php`, `src/` (Model, Repository, Service, Controller), `resources/views/items/form.php`, `src/DataTable/ItemsDataTable.php`, `dashboard/listing_items.php`: Cloned enhanced Items CRUD from flashlogisticsserver — added sale_account, sale_description, purchase_account, purchase_description fields with Sales/Purchase account dropdowns from Chart of Accounts
- Items form: Added selling price (mandatory), tax dropdown (optional), cost price (mandatory), preferred vendor dropdown (optional). Added DB columns selling_price, tax_treatment_id, cost_price, preferred_vendor_id. Updated DataTable listing with new columns and JOINs.
- `erp_ports`: Replaced 1,577 rows with 1,579 rows from uaehscodes (added Jebel Ali custom rows, country_id now populated)
- `erp_carriers`: Replaced 43 rows with 5,969 rows from uaehscodes (global carrier directory, 96 duplicate names skipped)
- `docs/MANPOWER_FLOW.md`: 6 Mermaid manpower flow charts (Org Hierarchy, Employee Lifecycle, Sales-to-Cash, Procure-to-Pay, Shipping Ops, Approval Workflows) designed from existing data model — **protected file, do not delete**
- `.opencode/skills/create-crud.md`: Full 6-step CRUD creation skill with Model→Repository→Service→Controller→View→Dashboard workflow and code templates
- `.opencode/skills/migrate-repo.md`: Migration skill covering the 8-phase plan, layered pattern conventions, and per-module migration checklist
- `.opencode/skills/fix-sql.md`: SQL convention violations skill with fix patterns for SELECT *, hard DELETE, mysqli usage, and missing org scoping
- `opencode.json`: Added granular references for `migration`, `database`, and `manpower` docs for targeted agent context

### Changed
- `docs/AGENTS.md`: Trimmed from 171 to 144 lines — moved CRUD creation workflow, convention violations, listing template, and page header standard into skills (loaded on demand via @skill). Added Skills reference table. Truncated migration status to essentials.
- `docs/MANPOWER_FLOW.md`: Moved from root to `docs/` with protected file banner
- `config/constants.php`, `config/seo_helpers.php`, `config/error_alerting.php`, `src/Service/SMTPMailer.php`, `dashboard/listing_inquiries.php`, `dashboard/seo_auto_populate.php`: Updated all domain references from `flashlogisticsserver.com` to `flasherpsystem.com` (10 occurrences across 6 files)
- `dashboard/.htaccess`: Updated domain references from `flashlogisticsserver.com` to `flasherpsystem.com`

### Added
- `index.php`: Replaced redirect-to-login with Coming Soon landing page. Root URL (`/`) shows "We're launching soon" message. Dashboard remains accessible at `/dashboard/` via direct URL — Apache serves `dashboard/index.php` directly since it's a real directory, never hitting root `index.php`.

## [2026-06-26]

### Changed
- `src/Model/LeaveType.php`: Removed `maxPerYear` and `paidDays` properties — days entered at leave request level
- `src/Repository/LeaveTypeRepository.php`: Removed `max_per_year` and `paid_days` from all SQL queries
- `src/Service/LeaveTypeService.php`: Removed `$maxPerYear` and `$paidDays` parameters from `create()`/`update()`
- `src/Service/LeaveRequestService.php`: Updated `calculatePaidDays()` to use `$totalDays` for paid leave types
- `src/Http/Controller/LeaveTypeController.php`: Removed `max_per_year` and `paid_days` request handling
- `resources/views/leave_types/form.php`: Removed Max Per Year and Paid Days fields; kept only Leave Type, Paid badge, and Rule
- `resources/views/leave_requests/form.php`: Simplified leave type rules display
- `src/DataTable/LeaveTypesDataTable.php`: Removed paid days column
- `src/DataTable/UsersDataTable.php` & `dashboard/listing_users.php`: Moved contact (UAE) under name column
- `src/DataTable/DocumentCategoriesDataTable.php`: Reduced Required badge size
- `resources/views/users/form.php`: Moved Required badge to new line in documents listing
- `src/DataTable/UserDocumentsDataTable.php` & `dashboard/listing_user_documents.php`: Removed actions column, added row click to navigate to users.php, hid New button
- `resources/views/leave_requests/form.php`: Removed Leave Type Rules section
- `resources/views/users/form.php`: Added Air Tickets section at bottom with listing, upload, departure/arrival dates, notes, and eligibility badge based on DOJ
- `src/Http/Controller/UserController.php`: Added air ticket AJAX handlers (list, add, update, delete)
- `dashboard/bootstrap.php`: Injected AirTicketService into UserController
- `src/Model/AirTicket.php`: Added `departureDate`, `arrivalDate`, `ticketFile` fields
- `src/Repository/AirTicketRepository.php`: Updated SQL for new air ticket fields
- `src/Service/AirTicketService.php`: Updated create/update for new fields
- DB: Added `departure_date`, `arrival_date`, `ticket_file` columns to `erp_air_tickets`
- `dashboard/listing_leave_types.php`: Removed Paid Days column header

### Fixed
- `src/Repository/SalaryStructureRepository.php`: Added `deleteByEmployee()` method for batch save support.
- DB: Added missing `is_basic` column to `erp_salary_structures` table (ALTER TABLE) — queries were failing with SQL error 1054.

### Changed
- `src/Service/SalaryStructureService.php`: Replaced single-component `create()`/`update()` with `saveBatch()` — deletes all existing records for an employee then re-inserts all paid components in a single operation. Basic Salary (component_id=1) auto-marked as `is_basic=true`.
- `src/Http/Controller/SalaryStructureController.php`: Rewrote `showForm()` to split components into `earningComponents`/`deductionComponents` arrays. Fetches employee `date_of_joining` to auto-fill effective_from for earnings. Removed `is_basic` from form data (now handled by Service).
- `resources/views/salary_structures/form.php`: Redesigned as batch form with two side-by-side tables — Earnings (left) and Deductions (right). Effective From auto-filled from employee's date of joining for earnings. Basic Salary shows a "Basic" badge instead of checkbox. Removed `is_basic` checkbox column.
- `src/DataTable/SalaryStructuresDataTable.php`: Edit button now links to `salary_structures.php?employee_id=X` (batch edit page) instead of individual record edit. Removed delete button.

### Fixed
- `resources/views/leave_types/form.php`: Changed `max_per_year` input `min="0"` to `min="1"` and help text to "Minimum 1 day per year". Updated Sick Leave default from 0 to 30.
- `resources/views/leave_requests/form.php`: Changed "Unlimited days per year" display text to "Subject to approval" for leave types with 0 max days.

### Changed
- `src/DataTable/UsersDataTable.php` & `dashboard/listing_users.php`: Redesigned employee listing — email, department, designation moved under name as a sub-line (e.g., `email@example.com · Dept · Designation`). Added document count icon next to name (`ph-paperclip`). Added new "Air Ticket" column showing the upcoming eligibility date from `erp_air_tickets`. Removed separate EMAIL, DEPARTMENT, DESIGNATION columns.

### Removed
- `erp_payroll_components`: Deleted 6 components — Social Security Contribution, Uniform Deduction, Pension Contribution, Medical Insurance Contribution, Damages Deduction, Housing Allowance (and their 2 orphan `erp_salary_structures` records).

### Added
- `CODEBASE-IMPROVEMENT-PLAN.md` — architecture review document with 11 recommendations.
- `docs/AGENTS.md`: Added "Known Convention Violations" table and "Known Pain Points" section.
- `CHANGELOG.md` — this file.
- `src/Model\User.php`: Added `designationId`, `dateOfJoining` properties with `toArray()` support.
- `src/Repository\UserRepository.php`: Added `designation_id`, `date_of_joining` to all SELECTs, INSERT, UPDATE, and `mapRowToDto()`.
- `src/Service\UserService.php`: Added `designation_id` pass-through; mandatory validation for `date_of_joining`.
- `src/Http\Controller\UserController.php`: Added `department_id`, `designation_id`, `date_of_joining` to create/update data and view; fetches departments/designations option lists.
- `resources/views/users/form.php`: Role dropdown now always visible. Added Department, Designation, and required Date of Joining fields.
- `database/migrations/2026_06_26_001_add_date_of_joining_to_users.php` — new migration.

### Changed
- `docs/AGENTS.md`: Updated migration status to clarify reports/overviews at 0% migration.
- `dashboard/listing_users.php`: `$module_caption` 'Users' → 'Employees'. HR-oriented columns: NAME, EMAIL, CONTACT (UAE), DEPARTMENT, DESIGNATION, DATE JOINED, ROLE, STATUS, ACTIONS. Default sort by name ascending.
- `src/DataTable/UsersDataTable.php`: Added department/designation name pre-fetching in `prepareRelatedData()`. Format row now includes department, designation, and date_of_joining columns. `sortableColumns` updated.
- `resources/views/users/form.php`: Department & Designation now mandatory (required + asterisk). Contact (PAK) now mandatory with asterisk. Country prefixes `+971`/`+92` changed to regular weight (removed `fw-semibold`). DOB field now also hides `1970-01-01` (Y-m-d format) in addition to `01-01-1970`.
- `src/Http/Controller/UserController.php`: Added `prependCountryCode()` helper; auto-prepends country codes on save, strips on edit display.
- `src/Service/UserService.php`: Added mandatory validation for `department_id`, `designation_id`, `contact2` (Contact PAK). Updated error labels.
- `dashboard/admin_elements/sidebar.php`: Reordered HR System to Employees, Departments, Designations, then remaining links.

### Removed
- `dashboard/listing_hr_todo_tasks.php` — legacy listing page and orphaned `HrTodoTasksDataTable`.
- `dashboard/admin_elements/hr_navbar.php` — HR sub-navigation bar removed from all 35 referencing files.

### Fixed
- `src/DataTable/DepartmentsDataTable.php`: Employee count column was hardcoded to `0` — now uses `prepareRelatedData()` to bulk-count active users per department. `sortableColumns` corrected.
- `src/DataTable/DesignationsDataTable.php`, `dashboard/listing_designations.php`: Added employee count column to designations listing, matching departments behavior.
- `src/DataTable/UserDocumentsDataTable.php`: `issued_date` and `expiry_date` columns now display in `dd-mm-yyyy` format.
- `src/Http/Controller/UserController.php`, `resources/views/users/form.php`: Missing required document categories now shown as warning alert above documents table. Works on both initial load and AJAX reload.
- `dashboard/dashboard_hr.php`: Replaced Today's Attendance section with Pending Air Tickets + Pending Leave Requests side by side. Removed expiring documents card and section. Replaced simple holidays list with rich interactive version (table with row click → modal with copyable announcement message).
- `dashboard/uae_public_holidays.php`: Removed (content integrated into dashboard_hr.php).
- `dashboard/admin_elements/sidebar.php`: Removed UAE Public Holidays link.
- `dashboard/process_payroll_run.php`: Created — generates payslips for a draft payroll run by iterating employee salary structures, computing gross/deductions/net, inserting payslip records, and updating run totals.
- `dashboard/mark_payslip_paid.php`: Created — handles three actions: mark single payslip as paid, mark single payslip as unpaid, mark all unpaid payslips in a run as paid.
- `resources/views/users/form.php`: Reduced "Documents Listing (Add/Update)" section font size — heading changed from `<h2>` to `<h6>`, table now uses `small` class to match "Add Document" section.
- `resources/views/users/form.php`: Action column buttons (edit/delete) now stay on one line via `text-nowrap`.
- `resources/views/users/form.php`: Edit document dates modal calendar now initializes on button click (was missing from `datepicker-config.js`).
- `dashboard/admin_elements/sidebar.php`: Added "Document Categories" link below "User Documents" in HR section.
- `dashboard/admin_elements/permissions.php`: Mapped `document_categories` module to HR system for permission checks.
- Granted HR Role (id=6) full permissions (view/create/edit/delete) to `document_categories` module in database.
- `dashboard/view_backend_error_logs.php`: `parse_php_error_entries()` treated each line as a separate entry, breaking multi-line log entries across rows. Changed to group continuation lines (no leading `[`) with the preceding entry. Added expand/collapse to the PHP error log section so full entries are visible on click.
- `database/migrations/006_create_attendance_devices.php`, `007_create_attendance_punches.php`, `008_add_zk_user_id_to_users.php`: Wrapped class-based migrations with `return ['up' => callable]` blocks so the MigrationRunner can process them.
- `src/Http\Controller\UserController.php`: Department/designation dropdown queries used `is_active = 1` but both tables use `publish = 1` — fixed.
- `src/Http\Controller\AnnualLeaveEntitlementController.php`: Same `is_active` → `publish` fix, plus corrected column names (`department`/`designation` aliased as `name`).
- `src/Model/DocumentCategory.php`, `src/Repository/DocumentCategoryRepository.php`, `src/Service/DocumentCategoryService.php`, `src/Http\Controller\DocumentCategoryController.php`, `resources/views/document_categories/form.php`: Fixed column name mismatch — repo was using `category_name`/`description` but the DB table has `document_category`/`document_category_type`. Added missing `publish` and `document_category_type` fields to model. Updated all CRUD operations, redirects, and form view to use correct column names. Redirects no longer point to non-existent `category_names.php`/`listing_category_names.php`.
- `resources/views/document_categories/form.php`, `src/Http/Controller/DocumentCategoryController.php`, `src/Service/DocumentCategoryService.php`: Removed `is_active` toggle from form UI and all related controller/service code. New records default to active, existing records keep their current value on update.
- `erp_document_categories`: Added `is_mandatory` column. Set `Emirates ID`, `Visa`, `Labor Card`, `Passport`, `Photo`, `Contract` as mandatory. Created missing `Labor Card`, `Photo`, `Contract` categories.
- `src/Model/DocumentCategory.php`: Added `isMandatory` property.
- `src/Repository/DocumentCategoryRepository.php`: Added `is_mandatory` to all SELECT/INSERT queries and mapper. Default sort by `document_category_type DESC` (employees first) then name.
- `src/DataTable/DocumentCategoriesDataTable.php`: Added `buildOrderClause()` override for employees-then-rest sorting. Added "Required" badge next to mandatory category names. Non-full-access roles (e.g. HR) now only see employees-type categories via `buildBaseQuery()` filter.
- `src/Model/DocumentCategory.php`: Removed `publish` property.
- `src/Repository/DocumentCategoryRepository.php`: Removed `publish` from SELECT, INSERT, and mapper.
- `src/Service/DocumentCategoryService.php`: Removed `publish` from create/update.
- `src/Http/Controller\DocumentCategoryController.php`: Removed `publish` from handleUpdate, handleCreate, and showForm.
- `resources/views/document_categories/form.php`: Removed publish toggle from form UI.
- `src/Http/Controller/UserController.php`: Document category query now includes `is_mandatory`, ordered by mandatory then name.
- `resources/views/users/form.php`: Shows "Required" badge on mandatory category names in existing documents table. Marks mandatory options with `*` and bold in the Add Document dropdown.
- `dashboard/dashboard_hr.php`: Added live UAE (UTC+4) and Pakistan (UTC+5) clocks with date in page header.
- `dashboard/dashboard_hr.php`: Re-added Today's Attendance table in a col-xl-6 card alongside UAE Public Holidays card (col-xl-6, right).
- `resources/views/hr_guide/index.php`: Rewritten with all current HR modules (Employees, Departments, Designations, Document Categories, Attendance & Leave, Payroll, Air Tickets, Gratuity, HR Dashboard), improved card-based layout with quick-nav links.
- `dashboard/admin_elements/sidebar.php`: Created "HR Settings" submenu containing Departments, Designations, Leave Types, Document Categories, Payroll Components. Attendance & Leave submenu split into individual Attendance and Leave Requests links. Payroll Components moved from Payroll submenu to HR Settings.
- `dashboard/listing_user_documents.php`, `src/DataTable/UserDocumentsDataTable.php`: Added Status column (Up to Date / Near Expiry / Expired). Removed DOCUMENT NAME column. DataTable and category badges now sort by fixed order: Emirates ID → Visa → Labor Card → Passport → Photo → Contract.
- `src/Http/Controller/UserDocumentController.php`: Document category dropdown in form orders by same fixed category priority.
- `src/Http/Controller/LeaveRequestController.php`, `resources/views/leave_requests/form.php`: Medical certificate upload moved to separate right-side card. Date inputs changed to dd-mm-yyyy datepicker format. Form data retained on validation errors via session storage. Medical certificate now mandatory only when leave type is Sick Leave (JS toggle + server-side validation).
- `src/Model/LeaveRequest.php`: Fixed deprecated optional parameter `$paidDays` before required `$status` by adding `= null` default to `$reason`.
- `src/Service/LeaveTypeService.php`, `src/Http/Controller/LeaveTypeController.php`, `resources/views/leave_types/form.php`: Leave type creation restricted to 3 predefined types (Annual Leave, Sick Leave, Urgent Leave). Form uses dropdown with auto-filled readonly fields. Rule description shown per type.
- `src/DataTable/LeaveTypesDataTable.php`, `dashboard/listing_leave_types.php`: Added Paid Days and Rule columns. Removed Max Days/Year column. DataTable now filters by organization_id (org sees only its own 3 types).
- Seeded `Urgent Leave` type for all organizations (existing orgs got only missing types).
- `dashboard/report_hr.php`: Rewritten with 6 KPI cards (Employees, Pending Leaves, Air Tickets, Expiring Docs, On Payroll, Pending Gratuity) and 3-row layout covering Headcount, Leave Summary, Payroll Summary, Document Status. Fixed SQL error (missing `u` alias in total employees query).
- `src/Http/Controller/SalaryStructureController.php`: Removed invalid `is_active=1` filter on `payroll_components` query — table has no such column, causing SQL error 1054 when opening the form.
- `src/Http/Controller/SalaryStructureController.php`: Fixed `requiresModule()` slug from `effective_froms` to `salary_structures`.
- `src/Http/Controller/PayrollRunController.php`: Fixed `requiresModule()` slug from `period_starts` to `payroll_runs`.
- `src/Http/Controller/PayrollComponentController.php`: Fixed `requiresModule()` slug from `component_names` to `payroll_components`.
- `src/Model/SalaryStructure.php`, `src/Repository/SalaryStructureRepository.php`, `src/Service/SalaryStructureService.php`, `src/Http/Controller/SalaryStructureController.php`, `resources/views/salary_structures/form.php`, `src/DataTable/SalaryStructuresDataTable.php`, `dashboard/listing_salary_structures.php`: Complete rewrite of salary structures form. Previously managed a fake "effective from" lookup (2 fields, wrong DB columns). Now correctly manages employee salary component assignments with fields: Employee, Payroll Component, Amount, Effective From/To dates, and Basic Salary flag. DataTable uses JOINs instead of N+1 `getTableAttr()`. Delete uses service layer. Added org-scoped filtering.



---

## [2026-07-28]

### Added
- `database/migrations/accounts_role_migration_20260728.sql`: Revoke all non-accounting module permissions for Accounts role (id=5). Keep only accounting modules + projects/jobs/job_statuses.

### Changed
- `dashboard/index.php`: Redirect role_id=5 to `dashboard_accounting.php`.
- `dashboard/admin_elements/sidebar.php`: Added `projects`, `jobs`, `job_statuses` to `$sectionModuleMap['crm']` for sidebar visibility.
- `dashboard/admin_elements/admin_header.php`: Mega menu now shows only "Accounting" section for role_id=5.
- `accounts_role_scoping_plan.md`: Plan document saved at project root.

### Added
- `.opencode/skills/create-crud/SKILL.md`: 7-step CRUD module creation skill (Model → Repository → Service → Controller → View → DataTable → Registration).
- `.opencode/skills/migrate-repo/SKILL.md`: 8-step migration workflow for procedural-to-layered architecture conversion.
- `.opencode/skills/fix-sql/SKILL.md`: SQL convention violation fixer (SELECT *, hard DELETE, raw mysqli, missing org_id, interpolation).
- `.vscode/tasks.json`: Build/test tasks for PHP syntax check, PHPStan, PHPCS, PHPUnit, and PHPCS auto-fix.
- `.vscode/launch.json`: Xdebug launch configurations (listen, run PHPUnit current file, run script).

### Changed
- `docs/AGENTS.md`: Removed Skills table (replaced by actual `.opencode/skills/` files — loaded on-demand via `skill` tool) and Form Partials reference table (agents can read `resources/views/` directly).
- `opencode.json`: Lowered `compaction.tail_turns` 20→12 (triggers auto-compaction sooner), reduced `tool_output.max_lines` 200→100 and `max_bytes` 8192→4096 (less blob context), added `experimental.primary_tools` (reduced tool definition tokens).
- `composer.lock`: Updated to include `phpunit/phpunit ^11.0` and 27 dependency packages.
- Cleaned up stale `.md` files in `.opencode/skills/` (remnants of prior skill setup).

### Fixed
- `.vscode/settings.json`: Corrected `phpunit.args` path from `tests/phpunit.xml` to `phpunit.xml`.
- Installed `phpunit/phpunit ^11.0` (was declared in `composer.json` but missing from lock file).

## [2026-07-28]

### Changed
- `dashboard/admin_elements/sidebar.php`: Fixed Sales & Purchases submenu `pages` arrays — added missing overview/sub-pages. Added all 38 accounting report files to Reports menu `pages` array so sidebar highlights correctly on any report page.
- 15 `dashboard/report_*.php`: Changed `$module` from non-existent module slugs to `'journals'` — Accounts role (id=5) can now access all accounting reports without 403 (`customer_overview.php`, `customer_billing_addresses.php`, `customer_shipping_addresses.php`, `customer_comments.php`, `customer_transactions.php`, `customer_mails.php`, `customer_statement.php`, `customer_logs.php`, `customer_contacts.php`, `quotation_overview.php`, `sale_order_overview.php`, `invoice_overview.php`, `payment_received_overview.php`, `credit_note_overview.php`, `vendor_overview.php`, `expense_overview.php`, `purchase_order_overview.php`, `purchase_overview.php`, `payments_made_overview.php`, `debit_note_overview.php`) so sidebar menu highlights correctly when navigating to any related page. Updated parent submenu `pages` arrays to keep dropdown open.

### Fixed
- `dashboard/admin_elements/sidebar.php`: Recurring Invoices menu item — changed `href` from `listing_invoices.php?view=recurring` (non-functional) to `recurring_invoices.php`, fixed `pages` from `['listing_invoices.php', 'invoices.php']` (wrongly overlapping Invoices) to `['recurring_invoices.php', 'recurring_invoice_overview.php']`

- `dashboard/listing_jobs.php`: Added row click handler via `extra_js` — clicking a job row navigates to `view_job.php?job_id=JOB_ID` (excludes action button clicks)
- `dashboard/report_movement_of_equity.php`, `dashboard/report_sales_by_sales_person.php`: Fixed copyright footer position — moved `copyright.php` include before `.content-wrapper` close (was after, causing footer to render on the right side)
- `dashboard/report_sales_by_customer.php`, `dashboard/report_sales_by_item.php`, `dashboard/report_sales_summary.php`: Fixed DataTables "unknown parameter" warning — switched from server-side (`ajax_action = 'listing_journals'` → `JournalsDataTable`) to client-side with PHP data queries (sales by customer grouped by customer, sales by item from invoice_items, sales summary by month)

---

### Added
- `dashboard/dashboard_operations.php`: New operations dashboard page — shows total/open leads, projects, jobs stats cards + quick links
- `migrations/operations_role_migration_20260728.sql`: Migration to revoke non-CRM permissions from Operations role (id=4), keeping only `leads`, `lead_quotations`, `projects`, `jobs`, `job_statuses`

### Changed
- `dashboard/index.php`: Added redirect for `role_id=4` → `dashboard_operations.php` (ordered before role 5 redirect for consistency)
- `dashboard/admin_elements/admin_header.php`: Added `role_id=4` mega menu filter — shows only CRM section (matching existing role_id=5 pattern)
- `migrations/update_email_provider_titan_20260728.sql`: Updated Titan Email provider credentials (`system@flasherpsystem.com`, host `smtp.titan.email:465/SSL`)

### Fixed
- `dashboard/send_email.php`: Fixed 403 for Accounts role — moved `$current_module` assignment before `permissions.php` include, fixed `$module_catpion` typo, added `quotations` and `debit_notes` to `$modules_config`

*For earlier history, see `docs/archive/` and `docs/MIGRATION-AUDIT-REMAINING.md`.*
