# Accounting Changelog

All notable changes to accounting-related modules are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project uses **date-based versioning** (`YYYY-MM-DD`).

---


---

## [2026-08-12] Part 4 — QA: 6 Purchase Modules Fixes + Form Retention

### Fixed
- DebitNoteService: added computeNoteTotals() (ported from CreditNoteService); createNote + updateNote now recompute grand totals from items instead of trusting submitted values; updateNote now deletes+recreates debit_note journal on items/totals change when status='open'
- Form value retention on validation error added to 4 controllers: PurchaseController, ExpenseController, VendorController, DebitNoteController (PurchaseOrderController already had it)

### Added
- renderFormWithData() method added to PurchaseController, ExpenseController, VendorController, DebitNoteController — renders form directly with submitted data on validation failure instead of redirect (no data loss)
- Full QA test harness validates all 6 modules CRUD (25/25 pass, journals balanced)

*Accounting modules: banks, customers, quotations, sale_orders, invoices, payments_received, credit_notes, vendors, expenses, purchase_orders, purchases, payments_made, debit_notes, journals, accounts, reports.*

---

## [2026-08-12]

### Removed
- Deleted 4,107 spam `erp_inquiries` rows (public contact form).
- Deleted 20+ QA/test orphan rows in org 1: tasks (ids 1,4,6,7,8), jobs (ids 4,5), shipping_advices (ids 19-21), shipping_invoices (ids 1-11), projects (id 5 'test'), shippers (id 17 'test SSO').

### Added
- Seeded org 1 (Flash Logistics FZCO) with real/dummy accounting data (≥10 per module): 10 customers, 10 vendors, 10 quotations, 10 sale_orders, 10 invoices, 10 purchase_orders, 10 purchases, 10 payments_received, 10 payments_made, 10 expenses, 4 credit_notes, 4 debit_notes, 78 balanced journals (debits = credits = 370,004.08 AED).
- Full document chains: quotation→sale_order→invoice→payment_received; purchase_order→purchase→payment_made; credit_notes and debit_notes with items.
- Journals for all transaction types: customer/vendor opening balances, sales invoices, payments_received, credit_notes, purchases, payments_made, debit_notes, expenses.

### Fixed
- Customer overview: payment due period now shows text label (Net 30, Due end of month etc.) instead of raw digit.
- Vendor overview: same payment_term fix applied.
- Expense sidebar: listing now loads expenses with vendor_id (was excluding all vendor-only expenses due to `customer_id != ''` filter).
- Customer sidebar: zero-invoice customers now show "No Invoices" (muted) instead of misleading "PAID AED 0.00".
- Vendor sidebar: zero-purchase vendors now show "No Purchases" (muted) instead of misleading "PAID AED 0.00".
- Payment received page header: restyled to match invoice header UX (status boxed label).
- Vendor overview: full right-sidebar panel rewritten to mirror customer overview — shows primary contact, billing/shipping addresses, other details, contact persons, record info.
- Vendor logs: breadcrumb now shows conditional Update/Activity like customer_logs.
- Customer logs: fixed escaped-quote bug in Exit button markup.
- invoice_overview.php: fixed undefined `$journal_items_table` (now uses `DB::JOURNAL_ITEMS`).
- expense_overview.php: guarded attachments query behind `if (!empty($expense_id))` to prevent SQL syntax error.
- pdf_payment_received.php: removed `UPDATE SET pdf` calls (erp_payments_received has no `pdf` column).
- generate_invoice_qrcode.php: added auto-create for missing `qrcodes_invoices/` directory.
- Currencies: deleted unused currencies (AUD,BND,CAD,CNY,EUR,GBP,INR,JPY,SAR,ZAR) — kept only AED+USD.
- Customer/Vendor overview: fixed Class "DB" not found error by adding `use App\Core\DB;` to view files.
- Vendor overview: fixed undefined `$vendor_type` variable in right sidebar panel.
- Expense overview: fixed undefined array key 0 by guarding items loop with `isset()` check.
- Payment received page header: reverted to credit-note style (h1 + ms-2 text-muted small).
- Customer overview: added "Activity Log" tab in page header nav (after Mails + Statement), linking to customer_logs.php.
- Customer logs: restructured page to use tab-bar layout (page_header_customer.php + sidebar_customer.php), matching Mails/Statement UX. Removed old breadcrumb header + navbar card.
- Purchase order/purchase/payment made/debit note page headers: fixed button alignment (right instead of left) by adding `carriers-page-header-content` CSS class.
- Customer/Vendor balance calculations: replaced all receivables/payables formulas system-wide (sidebar, repositories, DataTable) with canonical statement formula (opening + invoiced − paid − credited/debit notes). No longer trusts stale invoice_status flags; handles all statuses correctly.
- Customer statement: added `payment_status != 'void'` filter to both prior-payment and in-period payment queries.
- Dashboard accounting: AR KPI now includes `sent`, `partially_paid`, and `overdue` invoices (was `sent`-only), fixing undercounted AR total.
- Seed data: corrected invoices 1622/1623 from `paid` to `partially_paid` with actual `balance_due` values (308.70 / 588.00).
- PaymentReceivedService: removed status forcing — user's explicit `payment_status` (e.g. 'draft') now respected instead of being silently changed to 'paid' when amount>0.
- CreditNoteService::deleteNote: added journal cleanup (`credit_note` + `credit_note_void` journals) before repo delete to prevent orphan journals.
- CreditNoteService::updateNote: now recomputes grand_* totals from submitted items (qty*rate, tax%, discounts) instead of trusting stale submitted totals.
- PaymentReceivedController: invoice dropdown query added `invoice_status NOT IN ('paid','writeoff','void')` to prevent allocating fully-paid invoices.
- RecurringInvoiceController: fixed two references to non-existent `send_invoice.php` → `send_email.php?current_module=recurring_invoices&id=`.

### Added
- 7 recurring invoice profiles (dormant until Sep 2026) with items, across 7 customers, 3 frequencies (monthly/weekly/yearly).
- 12 vendor addresses (billing + shipping for 6 vendors) + 6 extra vendor contacts.
- Recurring invoice profiles stored in `erp_invoices` with `recurring=1, recurring_status=1`.

### Changed
- docs/AGENTS.md slimmed from 165 to 129 lines; polymorphic table distinguishers corrected (contactable_type/addressable_type).
- Deleted all one-time scripts (database/scripts/, database/backups/), AGENTS_QUICKREF.md, old HR/dev scripts, stale error logs.
- Added Module Classification Accounting vs Operations (IMMUTABLE) to memory files.

---

## [2026-08-11]

### Removed
- Deleted ALL accounting data for organizations 2, 3, 9998 (~500 rows across 30+ tables): customers, vendors, quotations, sale_orders, invoices, credit_notes, debit_notes, purchase_orders, purchases, payments_received, payments_made, expenses, journals, items, contacts, leads, taxonomies, and child/dependent tables.
- Deleted `erp_organizations` rows for orgs 2, 3, 9998.

### Changed
- **Single-organization lockdown** — system permanently locked to org 1 (Flash Logistics FZCO):
  - `dashboardGetAccessibleOrganizations()` hardcoded to return org 1 only (all roles).
  - Session normalized to org 1 on every dashboard request.
  - `dashboardCanCreateOrganizations()` returns `false` — organization creation permanently disabled.
  - Rule memorialized in `docs/AGENTS.md` and global `~/.config/opencode/AGENTS.md`.

---

## [2026-06-26]

### Added
- DB: Added `branch`, `address`, `iban` columns to `erp_banks`
- `src/Model/Bank.php`: Added `branch`, `address`, `iban` properties
- `src/Repository/BankRepository.php`: Added `branch`, `address`, `iban` to all SQL queries (SELECT, INSERT, UPDATE) and mapper
- `src/Service/BankService.php`: Added `branch`, `address`, `iban` pass-through in create/update
- `src/Http/Controller/BankController.php`: Added `branch`, `address`, `iban` to request handling and form view data
- `resources/views/banks/form.php`: Added UAE banks dropdown (20 banks) above Account Name, and Branch, Address, IBAN fields
- `src/DataTable/BanksDataTable.php`: Added branch, iban columns to query and format
- `dashboard/listing_banks.php`: Moved PRIMARY and STATUS columns to left, added BRANCH, IBAN columns

### Changed
- `resources/views/banks/form.php`: Moved Is Primary and Publish toggles to left side of page header (next to title)

### Added
- Rebuilt Vendors module to match Customers module (45+ fields, org-scoped, full CRUD):
  - `src/Model/Vendor.php`: Added all 43 properties matching `erp_vendors` columns + `toArray()`
  - `src/Repository/VendorRepository.php`: Full CRUD with explicit column lists, org scoping, email dedup check, getReceivables
  - `src/Service/VendorService.php`: `createVendor()`/`updateVendor()` with full field mapping, validation, date helpers, NotFoundException
  - `src/Http/Controller/VendorController.php`: Fixed module slug (`vendors`), actions, redirects; extracts all form fields; loads dropdowns (statuses, sources, users, tax treatments, currencies, tags)
  - `resources/views/vendors/form.php`: 3-column Bootstrap layout matching customers form with all fields
- `src/DataTable/VendorsDataTable.php`: Updated overview link to use `vendor_id` param

### Changed
- `dashboard/vendors.php` dispatcher — unchanged (already routes correctly to VendorController)
- `dashboard/bootstrap.php` — unchanged (VendorController registration already correct)
