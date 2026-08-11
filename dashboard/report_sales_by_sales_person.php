<?php

include('admin_elements/admin_header.php');

$module = 'journals';
$module_caption = 'Sales by Sales Person';
$tbl_name = $tbl_prefix . $module;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS FOR SALES BY SALES PERSON
|--------------------------------------------------------------------------
|
*/

function getSalesBySalesPerson($mysqli, $date_from_ymd, $date_to_ymd, $activeOrganizationId) {
    $date_condition = "";
    if (!empty($date_from_ymd) && !empty($date_to_ymd)) {
        $date_condition = " AND i.invoice_date >= '" . $date_from_ymd . "' AND i.invoice_date <= '" . $date_to_ymd . "'";
    }
    $date_condition .= " AND i.organization_id = " . (int)$activeOrganizationId;
    
    $sql = "SELECT i.sales_person, 
                   COALESCE(u.full_name, '') AS full_name,
                   COUNT(i.id) AS invoice_count,
                   COALESCE(SUM(i.grand_total), 0) AS total_sales,
                   CASE WHEN COUNT(i.id) > 0 THEN COALESCE(SUM(i.grand_total), 0) / COUNT(i.id) ELSE 0 END AS average_invoice_value
            FROM `fls_invoices` i
            LEFT JOIN `fls_users` u ON u.id = i.sales_person
            WHERE i.sales_person > 0" . $date_condition . "
            GROUP BY i.sales_person, u.full_name
            ORDER BY total_sales DESC";
    
    $result = $mysqli->query($sql);
    $sales_persons = array();
    while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
        if ($row['total_sales'] > 0 || $row['invoice_count'] > 0) {
            $row['display_name'] = $row['full_name'];
            $sales_persons[] = $row;
        }
    }
    return $sales_persons;
}

function getTotalSales($sales_persons) {
    $total = 0;
    foreach ($sales_persons as $person) {
        $total += (float)$person['total_sales'];
    }
    return $total;
}


/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

$filter_by              = ((isset($_REQUEST['filter_by']) && !empty($_REQUEST['filter_by'])) ? e_s__($_REQUEST['filter_by']) : '');
$date_from              = ((isset($_REQUEST['date_from']) && !empty($_REQUEST['date_from'])) ? e_s__($_REQUEST['date_from']) : date('d-m-Y', strtotime('first day of this month')));
$date_to                = ((isset($_REQUEST['date_to']) && !empty($_REQUEST['date_to'])) ? e_s__($_REQUEST['date_to']) : date('d-m-Y', strtotime('last day of this month')));

if (!function_exists('getDateRangeByFilter')) {
    function getDateRangeByFilter($filter_by)
    {
        $today = new DateTimeImmutable('today');
        switch ($filter_by) {
            case 'today':
                $start = $today;
                $end = $today;
                break;
            case 'yesterday':
                $start = $today->modify('-1 day');
                $end = $start;
                break;
            case 'this_week':
                $start = $today->modify('monday this week');
                $end = $today->modify('sunday this week');
                break;
            case 'previous_week':
                $start = $today->modify('monday last week');
                $end = $today->modify('sunday last week');
                break;
            case 'this_month':
                $start = $today->modify('first day of this month');
                $end = $today->modify('last day of this month');
                break;
            case 'previous_month':
                $start = $today->modify('first day of last month');
                $end = $today->modify('last day of last month');
                break;
            case 'this_quarter':
                $month = (int)$today->format('n');
                $quarter = intdiv($month - 1, 3) + 1;
                $startMonth = ($quarter - 1) * 3 + 1;
                $start = DateTimeImmutable::createFromFormat('Y-m-d', $today->format('Y') . '-' . str_pad((string)$startMonth, 2, '0', STR_PAD_LEFT) . '-01');
                $end = $start->modify('+2 months')->modify('last day of this month');
                break;
            case 'previous_quarter':
                $month = (int)$today->format('n');
                $quarter = intdiv($month - 1, 3) + 1;
                $startMonth = ($quarter - 1) * 3 + 1;
                $start = DateTimeImmutable::createFromFormat('Y-m-d', $today->format('Y') . '-' . str_pad((string)$startMonth, 2, '0', STR_PAD_LEFT) . '-01');
                $start = $start->modify('-3 months');
                $end = $start->modify('+2 months')->modify('last day of this month');
                break;
            case 'this_year':
                $start = new DateTimeImmutable($today->format('Y') . '-01-01');
                $end = new DateTimeImmutable($today->format('Y') . '-12-31');
                break;
            case 'previous_year':
                $start = new DateTimeImmutable($today->format('Y') . '-01-01');
                $start = $start->modify('-1 year');
                $end = new DateTimeImmutable($today->format('Y') . '-12-31');
                $end = $end->modify('-1 year');
                break;
            default:
                return [null, null];
        }

        return [$start->format('d-m-Y'), $end->format('d-m-Y')];
    }
}

if (!empty($filter_by) && $filter_by !== '0') {
    [$filter_start, $filter_end] = getDateRangeByFilter($filter_by);
    if (!empty($filter_start) && !empty($filter_end)) {
        $date_from = $filter_start;
        $date_to = $filter_end;
    }
}


// Process dates
$date_from_ymd = processDateDtoY($date_from);
$date_to_ymd = processDateDtoY($date_to);

// Get sales data
$sales_data = getSalesBySalesPerson($mysqli, $date_from_ymd, $date_to_ymd, $activeOrganizationId);

// UPDATES LAST VISITED
$accounts_report_subcategory_id = getTableAttrv("id", "fls_accounts_report_subcategories", " slug = 'sales_by_sales_person'");
if ($accounts_report_subcategory_id > 0) {
    $mysqli->query("UPDATE `fls_accounts_report_subcategories` SET last_visited = NOW() WHERE id = $accounts_report_subcategory_id");
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>

<script src="<?php echo $base_url; ?>/dashboard/js/reports_filterby.js"></script>


<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
            <div class="page-header-content">

                <div class="row mt-2">
                    <div class="col-lg-6">
                        <div class="text-muted">Sales Analysis</div>
                        <div class="mb-0">
                            <span class="fw-semibold">Sales by Sales Person</span> - <span class="small">From <?php echo ddm_($date_from_ymd); ?> To <?php echo ddm_($date_to_ymd); ?></span>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="col-lg-12 text-end">
                            <button type="button" onclick="resetForm();" class="btn btn-light my-1 me-2"><i class="ph-arrows-clockwise"></i></button>
                        </div>
                    </div>

                </div>
            </div>

            <script>
                function resetForm() {
                    document.getElementById('filter_by').value = '0';
                    document.getElementById('date_from').value = '';
                    document.getElementById('date_to').value = '';
                }
            </script>

            <div class="page-header-content border-top carriers-page-header-content">

                <div class="row">
                    <div class="col-lg-12">

                        <form class="steps-basic clearfix" method="get" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" autocomplete="off" action="report_sales_by_sales_person.php">
                            <input type="hidden" name="action" id="action" value="run_report" />

                            <div class="card-body">

                                <div class="row">

                                    <div class="col-lg-2">
                                        <div class="mb-0">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <select name="filter_by" id="filter_by" class="form-select">
                                                    <option value='0'>Filter By</option>
                                                    <option value='today' <?php echo (($filter_by == 'today') ? 'selected' : '') ?>>Today</option>
                                                    <option value='this_week' <?php echo (($filter_by == 'this_week') ? 'selected' : '') ?>>This Week</option>
                                                    <option value='this_month' <?php echo (($filter_by == 'this_month') ? 'selected' : '') ?>>This Month</option>
                                                    <option value='this_quarter' <?php echo (($filter_by == 'this_quarter') ? 'selected' : '') ?>>This Quarter</option>
                                                    <option value='this_year' <?php echo (($filter_by == 'this_year') ? 'selected' : '') ?>>This Year</option>
                                                    <option value='yesterday' <?php echo (($filter_by == 'yesterday') ? 'selected' : '') ?>>Yesterday</option>
                                                    <option value='previous_week' <?php echo (($filter_by == 'previous_week') ? 'selected' : '') ?>>Previous Week</option>
                                                    <option value='previous_month' <?php echo (($filter_by == 'previous_month') ? 'selected' : '') ?>>Previous Month</option>
                                                    <option value='previous_quarter' <?php echo (($filter_by == 'previous_quarter') ? 'selected' : '') ?>>Previous Quarter</option>
                                                    <option value='previous_year' <?php echo (($filter_by == 'previous_year') ? 'selected' : '') ?>>Previous Year</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_from" id="date_from" value="<?php echo $date_from; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-2">
                                        <div class="mb-3">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="date_to" id="date_to" value="<?php echo $date_to; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-lg-3">
                                        <button type="submit" class="btn btn-light">Run Report</button>
                                    </div>

                                </div>

                            </div>

                        </form>

                    </div>

                </div>

            </div>
</div>
    <!-- /page header -->


    <div class="content">

        <div class="card">
            <div class="card-header text-center">
                <p>Flash Logistics FZC</p>
                <h5 class="mb-0">Sales by Sales Person</h5>
                <p><span class="text-muted">From</span> <?php echo ddm_($date_from_ymd); ?> <span class="text-muted">To</span> <?php echo ddm_($date_to_ymd); ?></p>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <tbody>
                        <tr>
                            <th class="table-light">SALES PERSON NAME</th>
                            <th class="table-light text-end">INVOICES</th>
                            <th class="table-light text-end">TOTAL SALES</th>
                            <th class="table-light text-end">AVERAGE PRICE</th>
                        </tr>

                        <?php
                        foreach ($sales_data as $row) {
                        ?>
                            <tr>
                                <td class="fw-semibold"><?php echo s__($row['display_name']); ?></td>
                                <td class="text-end"><?php echo (int)$row['invoice_count']; ?></td>
                                <td class="text-end"><?php echo dec_($row['total_sales'], BASE_CURRENCY['code']); ?></td>
                                <td class="text-end"><?php echo dec_($row['average_invoice_value'], BASE_CURRENCY['code']); ?></td>
                            </tr>
                        <?php } ?>

                        <tr class="table-light fw-semibold">
                            <td>TOTAL</td>
                            <td class="text-end"><?php
                                $total_invoices = 0;
                                foreach ($sales_data as $row) {
                                    $total_invoices += $row['invoice_count'];
                                }
                                echo $total_invoices;
                            ?></td>
                            <td class="text-end"><?php echo dec_(getTotalSales($sales_data), BASE_CURRENCY['code']); ?></td>
                            <td class="text-end"><?php
                                $total_sales = getTotalSales($sales_data);
                                $avg_price = $total_invoices > 0 ? $total_sales / $total_invoices : 0;
                                echo dec_($avg_price, BASE_CURRENCY['code']);
                            ?></td>
                        </tr>

                    </tbody>
                </table>
            </div>

        </div>

    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>