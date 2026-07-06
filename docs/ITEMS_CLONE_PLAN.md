# Items Clone Plan — flashlogisticsserver → haizon

## Goal
Clone the enhanced Items CRUD from `G:/xampp/htdocs/flashlogisticsserver/dashboard/items.php` into the haizon layered architecture, adding 4 missing fields: `sale_account`, `sale_description`, `purchase_account`, `purchase_description`.

## Dependencies (already exist in haizon)
- `fetchAccountsDropdown(accountType, prefix, selectedId)` — in `admin_header.php`
- `BASE_CURRENCY['code']` — in `config/database.php`

## Files to Change (8)

| # | File | Change |
|---|------|--------|
| 1 | DB migration | ALTER TABLE `erp_items` ADD 4 columns |
| 2 | `src/Model/Item.php` | Add 4 properties |
| 3 | `src/Repository/ItemRepository.php` | Add columns to SELECT/INSERT/UPDATE/mapRowToDto |
| 4 | `src/Service/ItemService.php` | Add validation, pass new fields |
| 5 | `src/Http/Controller/ItemController.php` | Handle new fields in create/update/showForm |
| 6 | `resources/views/items/form.php` | Rewrite with Sales + Purchase info cards |
| 7 | `src/DataTable/ItemsDataTable.php` | Add account name columns |
| 8 | `dashboard/listing_items.php` | Update thead + columns config |
