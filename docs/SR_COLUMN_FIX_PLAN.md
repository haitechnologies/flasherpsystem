# SR Column Fix Plan

## Problem
DataTable `formatRow()` methods return `$id` (database primary key) as the first element of the return array in 53 files. Since listing files map `<th>SR.</th>` → `['data' => 0]`, the SR column shows raw DB IDs (e.g., 25, 26, 27) instead of sequential numbers (1, 2, 3...).

The `BaseDataTable` already provides `$this->rowNumber` which auto-increments per page starting from the DataTables offset — but most DataTable subclasses ignore it.

## Fix
Replace `$id` with `$this->rowNumber` at return array index 0 in all affected DataTable classes.

## Affected Files (53)

### Non-HR (safe to modify)
1. `src/DataTable/AccountsReportCategoriesDataTable.php`
2. `src/DataTable/AlertsDataTable.php`
3. `src/DataTable/AuthenticationActivityDataTable.php`
4. `src/DataTable/BanksDataTable.php`
5. `src/DataTable/BannedWordsDataTable.php`
6. `src/DataTable/CarriersDataTable.php`
7. `src/DataTable/CategoriesDataTable.php`
8. `src/DataTable/CategoryHSCodesDataTable.php`
9. `src/DataTable/CommodityTypesDataTable.php`
10. `src/DataTable/CompanyCategoriesDataTable.php`
11. `src/DataTable/ConsigneesDataTable.php`
12. `src/DataTable/ContainerTypesDataTable.php`
13. `src/DataTable/CustomerAddressesDataTable.php`
14. `src/DataTable/CustomerCommentsDataTable.php`
15. `src/DataTable/CustomerContactsDataTable.php`
16. `src/DataTable/CustomerLogsDataTable.php`
17. `src/DataTable/CustomerTransactionsDataTable.php`
18. `src/DataTable/DisposableEmailDomainsDataTable.php`
19. `src/DataTable/EmailHistoryDataTable.php`
20. `src/DataTable/EmailProvidersDataTable.php`
21. `src/DataTable/EmailQueueDataTable.php`
22. `src/DataTable/ExitPointsDataTable.php`
23. `src/DataTable/HSCodesDataTable.php`
24. `src/DataTable/IncotermsDataTable.php`
25. `src/DataTable/InquiriesDataTable.php`
26. `src/DataTable/ItemsDataTable.php`
27. `src/DataTable/JobStatusesDataTable.php`
28. `src/DataTable/JobsDataTable.php`
29. `src/DataTable/LeadQuotationsDataTable.php`
30. `src/DataTable/LeadsDataTable.php`
31. `src/DataTable/ModulesDataTable.php`
32. `src/DataTable/OrganizationRolesDataTable.php`
33. `src/DataTable/OrganizationsDataTable.php`
34. `src/DataTable/PaymentMethodsDataTable.php`
35. `src/DataTable/PaymentTermsDataTable.php`
36. `src/DataTable/PortsDataTable.php`
37. `src/DataTable/ProjectsDataTable.php`
38. `src/DataTable/PurchaseOrdersDataTable.php`
39. `src/DataTable/PurchaseTypesDataTable.php`
40. `src/DataTable/ServicesDataTable.php`
41. `src/DataTable/SetupGroupsDataTable.php`
42. `src/DataTable/SetupSourcesDataTable.php`
43. `src/DataTable/SetupStatusesDataTable.php`
44. `src/DataTable/SetupTagsDataTable.php`
45. `src/DataTable/ShippersDataTable.php`
46. `src/DataTable/ShippingAdvicesDataTable.php`
47. `src/DataTable/ShippingCustomersDataTable.php`
48. `src/DataTable/ShippingInvoicesDataTable.php`
49. `src/DataTable/StorageSubtypesDataTable.php`
50. `src/DataTable/StorageTypesDataTable.php`
51. `src/DataTable/SystemSettingsDataTable.php`
52. `src/DataTable/TaxTreatmentsDataTable.php`
53. `src/DataTable/UnitsDataTable.php`

### HR-protected (requires user approval)
54. `src/DataTable/AttendanceDataTable.php`
55. `src/DataTable/AttendanceDevicesDataTable.php`
56. `src/DataTable/DocumentCategoriesDataTable.php`

## Files Already Correct (no change needed)
- All HR-protected DataTables using `$this->rowNumber`: AnnualLeaveEntitlements, Departments, Designations, EmployeeSalaries, GratuitySettlements, LeaveRequests, LeaveTypes, PayrollComponents, PayrollRuns, SalaryStructures, UserDocuments, Users, AirTickets
- Report DataTables (associative arrays, no SR concept)
- Geo DataTables (associative arrays)
