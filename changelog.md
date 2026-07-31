# CHANGELOG

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
