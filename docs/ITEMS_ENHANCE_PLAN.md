# Items Enhancement Plan — Selling Price, Tax, Cost Price, Preferred Vendor

## New Fields

| Field | Section | Type | Required | Source |
|-------|---------|------|----------|--------|
| `selling_price` | Sales Info | `decimal(12,2)` | Yes | manual input with AED badge |
| `tax_treatment` | Sales Info | FK→`erp_tax_treatments.id` | Optional | dropdown (Standard 5%, No Tax) |
| `cost_price` | Purchase Info | `decimal(12,2)` | Yes | manual input with AED badge |
| `preferred_vendor` | Purchase Info | FK→`erp_vendors.id` | Optional | vendor dropdown |

## Files to Change (8)

| # | File | Change |
|---|------|--------|
| 1 | **DB migration** | ALTER TABLE `erp_items` ADD 4 columns |
| 2 | `src/Model/Item.php` | Add 4 new properties |
| 3 | `src/Repository/ItemRepository.php` | Add columns to SELECT, INSERT, mapRowToDto |
| 4 | `src/Service/ItemService.php` | Add validation |
| 5 | `src/Http/Controller/ItemController.php` | Pass new fields; fetch vendors & tax treatments |
| 6 | `resources/views/items/form.php` | Add selling price + tax to Sales card; cost price + vendor to Purchase card |
| 7 | `src/DataTable/ItemsDataTable.php` | Add new columns with JOINs |
| 8 | `dashboard/listing_items.php` | Update thead + columns config |
