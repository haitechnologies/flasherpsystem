# Accounting Changelog

All notable changes to accounting-related modules are recorded here.

The format is based on [Keep a Changelog](https://keepachangelog.com/),
and this project uses **date-based versioning** (`YYYY-MM-DD`).

---

*Accounting modules: banks, customers, quotations, sale_orders, invoices, payments_received, credit_notes, vendors, expenses, purchase_orders, purchases, payments_made, debit_notes, journals, accounts, reports.*

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
