<?php

use App\Core\DB;
include('admin_elements/admin_header.php');

$module = 'vendors';
$module_caption = 'Vendor';
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


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

// ------------------ CHECK IF VENDOR EXISTS ----------------
$vendor_id = '';
if (isset($_REQUEST['vendor_id']))        $vendor_id     = e_s__($_REQUEST['vendor_id']);
if (isset($_POST['vendor_id']))           $vendor_id     = e_s__($_POST['vendor_id']);
if (empty($vendor_id) && isset($_REQUEST['id'])) $vendor_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID 
$rs_valid     = $mysqli->query("SELECT id FROM `" . tbl_vendors . "` WHERE id='" . $vendor_id . "'");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_vendors.php");
    exit;
}

// //---------------
// if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
//     $id     = e_s__($_REQUEST['id']);
// }

$contact_id = 0;
if (isset($_REQUEST['contact_id']))        $contact_id     = e_s__($_REQUEST['contact_id']);
if (isset($_POST['contact_id']))           $contact_id     = e_s__($_POST['contact_id']);


/*
|--------------------------------------------------------------------------
| APPROVAL REQUEST
|--------------------------------------------------------------------------
|
*/
if ($action == "approved" && !empty($vendor_id)) {

    $rs = $mysqli->query("UPDATE `" . tbl_vendors . "` SET approved='1', approved_by=Session::userId(), approved_at=now() WHERE id=$vendor_id");

    $success_message = 'This Vendor is Approved.';
    flash_success($success_message);
    header("Location:vendor_overview.php?vendor_id=$vendor_id");
    exit;

    /*
|--------------------------------------------------------------------------
| DISAPPROVE
|--------------------------------------------------------------------------
|
*/
} else if ($action == "disapproved" && !empty($vendor_id)) {

    $rs = $mysqli->query("UPDATE `" . tbl_vendors . "` SET approved='0', updated_at=now() WHERE id=$vendor_id");

    $success_message = 'This Vendor is Dis-Approved.';
    flash_success($success_message);
    header("Location:vendor_overview.php?vendor_id=$vendor_id");
    exit;

    /*
|--------------------------------------------------------------------------
|  CLONE
|--------------------------------------------------------------------------
|
*/
} else if ($action == "clone_vendors" && !empty($vendor_id)) {

    // Corrected Version
    $query = "INSERT INTO " . tbl_vendors . " (
                lead_id, vendor_owner, vendor_type, vendor_status, vendor_source, 
                assigned_to, salutation, first_name, last_name, company_name, display_name, 
                address, email, phone, mobile, payment_term, tax_treatment, trn, 
                license_number, license_expiry, sales_person, lead_category, cs_agent, 
                rating, currency, exchange_rate, website, department, designation, 
                x, facebook, instagram, photo, description, tags, contacted_date, 
                approved, approved_by, approved_at, is_active, created_at, updated_at, created_by
            )
            SELECT 
                lead_id, vendor_owner, vendor_type, vendor_status, vendor_source, 
                assigned_to, salutation, first_name, last_name, company_name, display_name, 
                address, email, phone, mobile, payment_term, tax_treatment, trn, 
                license_number, license_expiry, sales_person, lead_category, cs_agent, 
                rating, currency, exchange_rate, website, department, designation, 
                x, facebook, instagram, photo, description, tags, contacted_date, 
                0, approved_by, approved_at, 0, NOW(), NOW(), created_by
            FROM " . tbl_vendors . " WHERE id = $vendor_id";

    $cloned_query = $mysqli->query($query);

    if ($cloned_query) {

        $new_cloned_id = $mysqli->insert_id;
        $success_message = 'Vendor has been cloned Successfully. Please click here to view. <a href="vendor_overview.php?vendor_id=' . $new_cloned_id . '"> Vendor ID: ' . $new_cloned_id . '</a>';
        // Vendor Logs
        // updateVendorLogs($vendor_id, 'vendor', 'updated');
        flash_success($success_message);
        header("Location:vendor_overview.php?vendor_id=$vendor_id");
        exit;
    } else {
        $error_message = "$module_caption Could Not Be Cloned.";
        //header("Location:$module.php?action=edit_$module&id=$id&error_message=$error_message");
    }

    /*
|--------------------------------------------------------------------------
|  MARK AS ACTIVE
|--------------------------------------------------------------------------
|
*/
} else if ($action == "mark_as_active" && !empty($vendor_id)) {

    $rs = $mysqli->query("UPDATE `" . tbl_vendors . "` SET is_active='1' WHERE id=$vendor_id");

    $success_message = 'Vendor has marked as Active';
    flash_success($success_message);
    header("Location:vendor_overview.php?vendor_id=$vendor_id");
    exit;

    /*
|--------------------------------------------------------------------------
|  MARK AS INACTIVE
|--------------------------------------------------------------------------
|
*/
} else if ($action == "mark_as_inactive" && !empty($vendor_id)) {

    $rs = $mysqli->query("UPDATE `" . tbl_vendors . "` SET is_active='0' WHERE id=$vendor_id");

    $success_message = 'Vendor has marked as Inactive';
    flash_success($success_message);
    header("Location:vendor_overview.php?vendor_id=$vendor_id");
    exit;

    /*
|--------------------------------------------------------------------------
|  MARK AS PRIMARY
|--------------------------------------------------------------------------
|
*/
} else if ($action == "mark_as_primary" && !empty($contact_id) && !empty($vendor_id)) {

    // SET UNPRIMARY ALL OTHERS
    $mysqli->query("UPDATE `" . tbl_vendor_contacts . "` SET is_primary='0' WHERE vendor_id=$vendor_id");

    // SET PRIMARY ONLY SELECTED
    $mysqli->query("UPDATE `" . tbl_vendor_contacts . "` SET is_primary='1' WHERE id=$contact_id AND vendor_id=$vendor_id");

    $success_message = 'Contact Person is Set as Primary';
    flash_success($success_message);
    header("Location:vendor_overview.php?vendor_id=$vendor_id");
    exit;
}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
|
*/
$vendor_name = '';
$payment_term = '';

if (!empty($vendor_id)) {

    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id='" . $vendor_id . "'");
    $row = $result->fetch_array();

    $vendor_owner             = s__($row['vendor_owner']);
    $vendor_owner             = getTableAttr('full_name', tbl_users, $vendor_owner);

    $payment_term               = s__($row['payment_term']);
    $payment_term               = getTableAttr('payment_term', tbl_payment_terms, $payment_term);

    $vendor_status            = s__($row['vendor_status']);
    $vendor_status            = getTableAttr('value', DB::TAXONOMIES, $vendor_status);

    $vendor_source            = s__($row['vendor_source']);
    $vendor_source            = getTableAttr('value', DB::TAXONOMIES, $vendor_source);

    $assigned_to                = s__($row['assigned_to']);
    $assigned_to                = getTableAttr('full_name', tbl_users, $assigned_to);

    // $vendor_type              = s__($row['vendor_type']);
    $salutation                 = ((!empty($row['salutation']) ? ucwords(s__($row['salutation'])) : ''));
    $first_name                 = s__($row['first_name']);
    $last_name                  = s__($row['last_name']);
    // $company_name               = s__($row['company_name']);
    $display_name               = s__($row['display_name']);
    $address                    = s__($row['address']);
    $email                      = s__($row['email']);
    $phone                      = s__($row['phone']);
    $mobile                     = s__($row['mobile']);

    $tax_treatment          = s__($row['tax_treatment']);
    $trn                    = s__($row['trn']);
    $license_number         = s__($row['license_number']);
    $license_expiry         = s__($row['license_expiry']);
    $license_expiry         = ($license_expiry == '1970-01-01' ? '' : processDateYtoD($license_expiry));

    $currency               = s__($row['currency']);
    $currency               = getTableAttr("currency", tbl_currencies, $currency);
    $exchange_rate          = s__($row['exchange_rate']);

    $sales_person           = s__($row['sales_person']);
    $sales_person           = getTableAttr("full_name", tbl_users, $sales_person);

    $cs_agent               = s__($row['cs_agent']);
    $cs_agent               = getTableAttr("department", DB::DEPARTMENTS, $cs_agent);

    $lead_category          = s__($row['lead_category']);
    $rating                 = s__($row['rating']);

    $contacted_date         = s__($row['contacted_date']);
    $contacted_date         = ($contacted_date == '1970-01-01 00:00:00' ? '' : date('d-m-Y h:i A', strtotime($contacted_date)));

    $description                = s__($row['description']);

    // -- Tags
    $tags                   = s__($row['tags']);
    $tags_arr               = array();
    $tags_captions = '';

    if ($tags != NULL) {
        $tags_arr               = explode(',', $tags);

        // $tags_captions = '';

        foreach ($tags_arr as $tag_id) {
            $tags_captions .= '<span class="badge bg-light text-dark">' . getTableAttr('value', DB::TAXONOMIES, $tag_id) . '</span> &nbsp;';
        }
    }

    $website                = s__($row['website']);
    $department             = s__($row['department']);
    $designation            = s__($row['designation']);
    $x                      = s__($row['x']);
    $facebook               = s__($row['facebook']);
    $instagram              = s__($row['instagram']);


    $approved               = s__($row['approved']);
    $approved_by            = s__($row['approved_by']);
    $approved_at            = s__($row['approved_at']);

    $publish                = s__($row['is_active']);
    $created_at             = s__($row['created_at']);
    $created_by             = s__($row['created_by']);
}

// $photo = getTableAttr('photo', $tbl_name, $id);
/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_vendor.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_vendor.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <div class="col-lg-6 col-xl-12">

                    <div class="card">

                        <div class="card-body">

                            <div class="row">

                                <?php include('admin_elements/sidebar_vendor_overview.php'); ?>

                                <div class="col-lg-8">
                                    <div class="small text-muted">Payment due period</div>
                                    <div class=""><?php echo $payment_term; ?></div>


                                    <div class="mt-2">
                                        <div class="card-header border-0">
                                            <h5 class="mb-0 fw-normal">Payables</h5>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-xs">
                                                <thead>
                                                    <tr class="bg-light">
                                                        <th class="small opacity-75">CURRENCY</th>
                                                        <th class="text-end small opacity-75">OUTSTANDING PAYABLES</th>
                                                        <th class="text-end small opacity-75">UNUSED CREDIT</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="small"><?php echo BASE_CURRENCY['code']; ?>- UAE Dirham</td>
                                                        <td class="text-end small text-primary">

                                                            <?php

                                                            // Outstanding Payables = Total Purchases (excluding draft/declined/expired) - Total Payments Made
                                                            $vendor_payables = getVendorPayables($vendor_id, $mysqli);

                                                            ?>
                                                            <a href="listing_purchases.php?dt_vendor_id=<?php echo $vendor_id; ?>"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($vendor_payables); ?></a>

                                                        </td>
                                                        <td class="text-end small"><?php echo BASE_CURRENCY['code']; ?>0.00</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>




                                    <div class="row">

                                        <div class="card">

                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Monthly Payables Trend</h5>
                                                <div class="d-flex gap-2">
                                                    <div class="flex-shrink-0" style="min-width: 150px;">
                                                        <label class="form-label small mb-1">Time Period</label>
                                                        <select id="vendor_chart_period" class="form-select form-select-sm" onchange="updatePayablesChart()">
                                                            <option value="last_6_months">Last 6 Months</option>
                                                            <option value="this_fiscal_year">This Fiscal Year</option>
                                                            <option value="previous_fiscal_year">Previous Fiscal Year</option>
                                                            <option value="last_12_months">Last 12 Months</option>
                                                        </select>
                                                    </div>
                                                    <div class="flex-shrink-0" style="min-width: 120px;">
                                                        <label class="form-label small mb-1">Basis</label>
                                                        <select id="vendor_chart_basis" class="form-select form-select-sm" onchange="updatePayablesChart()">
                                                            <option value="accrual">Accrual</option>
                                                            <option value="cash">Cash</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card-body">

                                                <div class="col-sm-12 col-xl-12">

                                                    <!-- Prepare a DOM with a defined width and height for ECharts -->
                                                    <!-- <div id="main" style="width: 600px;height:400px;"></div> -->
                                                    <div id="payables_chart_div" style="width: 100%; height: 400px;"></div>


                                                    <script type="text/javascript">
                                                        google.charts.load('current', {
                                                            'packages': ['corechart']
                                                        });
                                                        google.charts.setOnLoadCallback(function() {
                                                            updatePayablesChart();
                                                        });

                                                        function updatePayablesChart() {
                                                            var period = document.getElementById('vendor_chart_period').value;
                                                            var basis = document.getElementById('vendor_chart_basis').value;

                                                            var today = new Date();
                                                            var months = [];

                                                            switch (period) {
                                                                case 'last_6_months':
                                                                    for (var i = 5; i >= 0; i--) {
                                                                        var d = new Date(today.getFullYear(), today.getMonth() - i, 1);
                                                                        months.push(d);
                                                                    }
                                                                    break;
                                                                case 'last_12_months':
                                                                    for (var i = 11; i >= 0; i--) {
                                                                        var d = new Date(today.getFullYear(), today.getMonth() - i, 1);
                                                                        months.push(d);
                                                                    }
                                                                    break;
                                                                case 'this_fiscal_year':
                                                                    for (var i = 0; i < 12 && new Date(today.getFullYear(), i, 1) <= today; i++) {
                                                                        months.push(new Date(today.getFullYear(), i, 1));
                                                                    }
                                                                    break;
                                                                case 'previous_fiscal_year':
                                                                    for (var i = 0; i < 12; i++) {
                                                                        months.push(new Date(today.getFullYear() - 1, i, 1));
                                                                    }
                                                                    break;
                                                            }

                                                            var monthsData = [];
                                                            months.forEach(function(date) {
                                                                var year = date.getFullYear();
                                                                var month = date.getMonth() + 1;
                                                                var start_date = year + '-' + String(month).padStart(2, '0') + '-01';
                                                                var end_date_obj = new Date(year, month, 0);
                                                                var end_date_str = year + '-' + String(month).padStart(2, '0') + '-' + String(end_date_obj.getDate()).padStart(2, '0');

                                                                monthsData.push({
                                                                    date: date,
                                                                    start: start_date,
                                                                    end: end_date_str
                                                                });
                                                            });

                                                            var formData = new FormData();
                                                            formData.append('vendor_id', '<?php echo $vendor_id; ?>');
                                                            formData.append('basis', basis);
                                                            formData.append('months', JSON.stringify(monthsData));

                                                            fetch('ajax/get_vendor_monthly_payables.php', {
                                                                    method: 'POST',
                                                                    body: formData
                                                                })
                                                                .then(response => {
                                                                    if (!response.ok) {
                                                                        throw new Error('HTTP ' + response.status + ': ' + response.statusText);
                                                                    }
                                                                    return response.text().then(text => {
                                                                        try {
                                                                            return JSON.parse(text);
                                                                        } catch (e) {
                                                                            console.error('Response text:', text);
                                                                            throw new Error('Invalid JSON response: ' + e.message);
                                                                        }
                                                                    });
                                                                })
                                                                .then(data => {
                                                                    if (!data.success) {
                                                                        var errorMsg = data.error || 'Unknown error';
                                                                        document.getElementById('payables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><strong>Error loading chart:</strong><br>' + errorMsg + '</div>';
                                                                        return;
                                                                    }

                                                                    if (!data.months || data.months.length === 0) {
                                                                        document.getElementById('payables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No payables data available for the selected period</div>';
                                                                        return;
                                                                    }

                                                                    drawPayablesChart(data.months, basis, '<?php echo $display_name; ?>');
                                                                })
                                                                .catch(error => {
                                                                    document.getElementById('payables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><strong>Failed to load chart data:</strong><br>' + error.message + '</div>';
                                                                });
                                                        }

                                                        function drawPayablesChart(monthsData, basis, displayName) {
                                                            if (!monthsData || !Array.isArray(monthsData) || monthsData.length === 0) {
                                                                document.getElementById('payables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No data available to display</div>';
                                                                return;
                                                            }

                                                            var data_array = [['Month', 'Payables', { role: 'style' }]];

                                                            monthsData.forEach(function(item) {
                                                                var color = '#90ee90';
                                                                var payableValue = item.payable || 0;
                                                                data_array.push([item.month || 'N/A', payableValue, color]);
                                                            });

                                                            var data = google.visualization.arrayToDataTable(data_array);

                                                            var basis_text = document.getElementById('vendor_chart_basis').options[document.getElementById('vendor_chart_basis').selectedIndex].text;
                                                            var options = {
                                                                title: 'Monthly Payables - ' + displayName + ' (' + basis_text + ')',
                                                                titleTextStyle: {
                                                                    color: '#102B44',
                                                                    fontSize: 14,
                                                                    bold: true
                                                                },
                                                                legend: 'none',
                                                                hAxis: {
                                                                    title: 'Month',
                                                                    titleTextStyle: {
                                                                        color: '#555'
                                                                    }
                                                                },
                                                                vAxis: {
                                                                    minValue: 0,
                                                                    format: '<?php echo BASE_CURRENCY['code']; ?> #,##0.00'
                                                                },
                                                                chartArea: {
                                                                    left: 80,
                                                                    top: 50,
                                                                    width: '80%',
                                                                    height: '60%'
                                                                }
                                                            };

                                                            var chart = new google.visualization.ColumnChart(document.getElementById('payables_chart_div'));
                                                            chart.draw(data, options);
                                                        }
                                                    </script>

                                                </div>

                                            </div>
                                        </div>
                                    </div>



                                    <style>
                                        .timeline-container {
                                            position: relative;
                                            padding-left: 140px;
                                        }

                                        .timeline-line {
                                            position: absolute;
                                            left: 145px;
                                            top: 0;
                                            bottom: 0;
                                            width: 2px;
                                            background: #e0e6ed;
                                        }

                                        .timeline-item {
                                            position: relative;
                                            margin-bottom: 2rem;
                                        }

                                        .timeline-date {
                                            position: absolute;
                                            left: -140px;
                                            text-align: right;
                                            width: 110px;
                                            font-size: 0.8rem;
                                            color: #6c757d;
                                            line-height: 1.2;
                                        }

                                        .timeline-icon {
                                            position: absolute;
                                            left: -18px;
                                            width: 36px;
                                            height: 36px;
                                            background: white;
                                            border: 1px solid #cfe2f3;
                                            border-radius: 50%;
                                            display: flex;
                                            align-items: center;
                                            justify-content: center;
                                            z-index: 2;
                                        }

                                        .timeline-content {
                                            background: white;
                                            border: 1px solid #f1f3f5;
                                            border-radius: 8px;
                                            padding: 1.25rem;
                                            margin-left: 40px;
                                        }
                                    </style>

                                    <div class="timeline-container mt-4 py-4">
                                        <div class="timeline-line"></div>

                                        <div class="timeline-item">
                                            <div class="timeline-date">
                                                <div class="small">20 Dec 2025</div>
                                                <div class="small">06:11 PM</div>
                                            </div>
                                            <div class="timeline-icon">
                                                <i class="ph-chat-centered-text text-primary"></i>
                                            </div>
                                            <div class="timeline-content shadow-sm">
                                                <div class="text-muted mb-1">test3</div>
                                                <div class="small text-muted">by <span class="fw-bold">Flash Logistcis FZC</span></div>
                                            </div>
                                        </div>

                                        <div class="timeline-item">
                                            <div class="timeline-date">
                                                <div class="small">20 Dec 2025</div>
                                                <div class="small">06:11 PM</div>
                                            </div>
                                            <div class="timeline-icon">
                                                <i class="ph-file-text text-primary"></i>
                                            </div>
                                            <div class="timeline-content shadow-sm">
                                                <h6 class="fw-bold mb-1">Quote added</h6>
                                                <div class="text-muted small mb-1">Quote QT-000004 of amount <?php echo BASE_CURRENCY['code']; ?>1,500.00 created</div>
                                                <div class="small text-muted">by <span class="fw-bold">Flash Logistcis FZC</span> - <a href="#" class="text-primary text-decoration-none">View Details</a></div>
                                            </div>
                                        </div>
                                    </div>


                                </div>
                            </div>

                        </div>

                    </div>

                </div>
            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>