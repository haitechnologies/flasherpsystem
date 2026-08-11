# CHANGELOG

## 2026-08-02

### Added
- **Accounts Receivable account dropdown on Customer form** (`customers.php`): New "Accounts Receivable Account:" select above the Opening Balance field. Lists active Asset accounts from Chart of Accounts as `code - name`. Selection persists per customer and is used as the debit account in the opening-balance journal.
- **`erp_customers.receivable_account_id`**: New nullable INT column referencing `erp_accounts.id`.
- **`migrations/010_add_receivable_account_id_to_customers.php`**: Idempotent PHP migration adding the column after `opening_balance` (`SHOW COLUMNS` guard, prefix-aware).
- **`migrations/add_customers_receivable_account_20260802.sql`**: Idempotent standalone SQL for live — adds `receivable_account_id` if missing (`INFORMATION_SCHEMA` guard, prefix-aware via `@tbl`), safe to re-run.

### Changed
- **`src/Model/Customer.php`**: Added `receivableAccountId` readonly property (default `null`) + `toArray()` mapping for `receivable_account_id`.
- **`src/Repository/CustomerRepository.php`**: `receivable_account_id` added to `insert()` columns/VALUES, `update()` SET, `clone()` SELECT/INSERT, and `mapRowToCustomer()`.
- **`src/Service/CustomerService.php`**: `createCustomer()`/`updateCustomer()` persist the selected AR account; all DTO copy-construction sites (`approveCustomer`, `disapproveCustomer`, `updateOpeningBalance`, `cloneCustomer`, etc.) preserve it; `createOpeningBalanceJournal()` now debits `$receivableAccountId ?? 9` (fallback to account 9), credit remains offset account 118.
- **`src/Http/Controller/CustomerController.php`**: `buildCustomerData()` reads `receivable_account_id`; `showForm()` loads active Asset accounts (`DB::ACCOUNTS WHERE is_active=1 AND account_type='Assets'`) into `arAccountsList` and passes `receivable_account_id` to the view.
- **`resources/views/customers/form.php`**: Added `@var` docblocks for `$arAccountsList` / `$receivable_account_id` and the AR account dropdown row above Opening Balance (bank form.php select pattern).
- **Changelog policy**: All session changes recorded in `changelog.md` (root) going forward; no longer appended to `OPERATIONS_CHANGELOG.md`.

### Fixed
- **Live customer create/update/clone failure after AR-account deployment**: The live DB (`u904789561_haizon`) was missing `erp_customers.receivable_account_id`, so `CustomerRepository::update()` threw `Unknown column 'receivable_account_id'` and saves failed with the generic "could not be updated" flash. Fix: run `migrations/add_customers_receivable_account_20260802.sql` on live (now idempotent). Code changes were already correct; the issue was schema out of sync with deployed code.

---

## 2026-07-31

### Added
- **Opening Balance field on Customer form** (`customers.php`): Added `Opening Balance` input above the TAX dropdown. The `CustomerController` now passes `opening_balance` to the view; existing values load on edit.
- **CHANGELOG.md**: New root-level changelog tracking all changes from 2026-07-31 onward.

### Migration
- **`accounts_role_address_perms_20260731.sql`**: Grants Accounts role (id=5) view/create/edit/delete permissions for `customer_billing_addresses` and `customer_shipping_addresses` modules. Also ensures the modules exist in `erp_modules`, their permission types in `erp_module_permissions`, and role-permission mappings in `erp_permissions` for roles 1, 2, and 5.

### Changed
- `src/Http/Controller/CustomerController.php`: Added `$opening_balance` to defaults (`0.00`), DB load on edit with `number_format()` for proper decimal display, and `render()` data array.
- `resources/views/customers/form.php`: Added `@var string $opening_balance` docblock + opening balance input row above Tax field. Uses `type="number" step="0.01"` with AED input-group prefix.

### Fixed
- **Opening Balance on customer edit form**: Value now displays with 2 decimal places (`number_format()`). Input uses `type="number" step="0.01"` with AED currency prefix.

---

## 2026-07-31 (Quotations Module 100% Clone)

### Added
- **3 new DB columns** (`migrations/quotations_fields_clone_20260731.sql`): `hwb_hbol VARCHAR(255)`, `origin_country INT`, `destination_country INT` on `erp_quotations`.
- **4 AJAX functions** in `dashboard/assets/custom_js/ajax.js`: `ajax_add_shipper`, `ajax_add_consignee`, `ajax_select_port_country`, `ajax_select_country_ports`.
- **4 AJAX case handlers** in `dashboard/internal_request.php`: `add_shipper`, `add_consignee`, `select_port_country`, `select_country_ports`.
- **Shipper/Consignee popup modals** in form.php: inline creation via AJAX with full field sets.
- **Dimensions modal**: PCS/Units/L/W/H/Formula/CBM/Volume calculator with Add New, Grand totals, Save button.
- **Payment Terms dropdown** visible in form (was hidden before).

### Changed
- **`src/Model/Quotation.php`**: Added 3 readonly props (`hwbHbol`, `originCountry`, `destinationCountry`) + `toArray()` entries.
- **`src/Repository/QuotationRepository.php`**: INSERT, UPDATE, and `mapRowToQuotation()` now include hwb_hbol, origin_country, destination_country.
- **`src/Service/QuotationService.php`**: `createQuotation()`, `updateQuotation()`, `cloneQuotation()` pass new fields.
- **`src/Http/Controller/QuotationController.php`**: `buildQuotationData()` +3 fields, `showForm()` defaults +3 fields + DB load + 4 new dropdown queries (`paymentTermsList`, `countriesList`, `portsList`, `servicesList`). Render data +7 fields, fixed missing `$payment_term`.
- **`resources/views/quotations/form.php`**: Complete rewrite (~700 lines) — 100% clones flashlogistics HTML/CSS/JS. 2-column layout, dimensions modal, items table, grand totals with AED prefix, all JS functions (toggleQuotationPartySelectors, calculateChargeableWeight, calculateDim, saveDimensions, calculateItemAmount, add_item_row, calculateGrand, etc.), shipper/consignee modals.
- **`dashboard/admin_elements/page_header_quotation.php`**: Cloned from flashlogistics — added Convert to Invoice, Accepted/Declined status actions, Mark As Sent, restored flashlogistics exact layout.
- **`dashboard/admin_elements/sidebar_quotation.php`**: Cloned from flashlogistics — added pending/approved status options, `lead_id=0 OR lead_id IS NULL` filter, col widths (7/5).
- **`dashboard/quotations.php`**: Now wraps controller output with admin_header + admin_footer; handles redirects via Location header detection.
- **`dashboard/listing_quotations.php`**: Matches flashlogistics layout: `.card > .content.clearfix`, `custom_datatables` table class, inline DataTable init with serverSide.

### Fixed
- **Quotation Model & Repository — column name mismatch**: Model `toArray()` and Repository INSERT/UPDATE/mapRowToQuotation() were referencing wrong column names (`master_awb_no`→should be `mawb_bol`, `shipper`→`shipper_id`, `consignee`→`consignee_id`, `origin`→`origin_port`, `destination`→`destination_port`). Corrected in `src/Model/Quotation.php` toArray() and `src/Repository/QuotationRepository.php` INSERT columns, VALUES placeholders, UPDATE SET clauses, and mapRowToQuotation(). Migration AFTER clauses also fixed.
