<style>
    /* .timeline {
        position: relative;
        padding: 0;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: 50%;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #ddd;
        transform: translateX(-50%);
    }

    .timeline-item {
        display: flex;
        align-items: flex-start;
        position: relative;
        margin-bottom: 30px;
    }

    .timeline-date {
        width: 45%;
        text-align: right;
        padding-right: 20px;
        font-weight: 600;
        color: #555;
    }

    .timeline-marker {
        position: relative;
        z-index: 1;
        background: #fff;
        border: 2px solid #0d6efd;
        border-radius: 50%;
        width: 14px;
        height: 14px;
        margin: 0 10px;
        flex-shrink: 0;
        top: 5px;
    }

    .timeline-content {
        width: 45%;
        background: #fff;
        border: 1px solid #eee;
        border-radius: 6px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    } */
</style>

<aside class="sidebar sidebar-secondary sidebar-expand-lg" aria-label="Secondary Navigation">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_customer.php'); ?>
    <!-- /sidebar content -->

</aside>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_customer.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <div class="col-lg-6 col-xl-12">

                    <div class="card">

                        <div class="card-body">

                            <div class="row">

                                <?php include('admin_elements/sidebar_customer_overview.php'); ?>

                                <div class="col-lg-8">
                                    <div class="small text-muted">Payment due period</div>
                                    <div class=""><?php echo $payment_term; ?></div>


                                    <div class="mt-2">
                                        <div class="card-header border-0">
                                            <h5 class="mb-0 fw-normal">Receivables</h5>
                                        </div>

                                        <div class="table-responsive">
                                            <table class="table table-xs">
                                                <thead>
                                                    <tr class="bg-light">
                                                        <th class="small opacity-75">CURRENCY</th>
                                                        <th class="text-end small opacity-75">OUTSTANDING RECEIVABLES</th>
                                                        <th class="text-end small opacity-75">UNUSED CREDIT</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr>
                                                        <td class="small"><?php echo BASE_CURRENCY['code']; ?>- UAE Dirham</td>
                                                        <td class="text-end small text-primary">

                                                            <?php
                                                            // Calculate outstanding receivables (unpaid invoices) using service
                                                            $customer_receivables = $customerService->getReceivables((int)$customer_id, $activeOrganizationId);
                                                            ?>
                                                            <a href="listing_invoices.php?dt_customer_id=<?php echo $customer_id; ?>&dt_invoice_status=unpaid"><?php echo BASE_CURRENCY['code']; ?><?php echo dec_($customer_receivables); ?></a>

                                                        </td>
                                                        <td class="text-end small">

                                                            <?php
                                                            // Unused credit calculation (not using accounting system)
                                                            $unused_credit = 0;
                                                            // Credit calculation disabled - no accounting module
                                                            ?>
                                                            <?php echo BASE_CURRENCY['code']; ?><?php echo dec_($unused_credit); ?>

                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        <div class="ps-3 pe-3 pb-3">
                                            <?php $ob = $customerObj->openingBalance; ?>
                                            <?php if ($ob != 0): ?>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-semibold small">
                                                        Opening Balance: <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$ob, 2); ?>
                                                    </span>
                                                    <a href="#" data-bs-toggle="modal" data-bs-target="#modal_opening_balance" class="text-muted small text-decoration-none">
                                                        <i class="ph-pencil me-1"></i> Edit
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <a href="#" data-bs-toggle="modal" data-bs-target="#modal_opening_balance" class="text-primary">
                                                    Enter Opening Balance
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>




                                    <div class="row">

                                        <div class="card">

                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h5 class="mb-0">Monthly Receivables Trend</h5>
                                                <div class="d-flex gap-2">
                                                    <div class="flex-shrink-0" style="min-width: 150px;">
                                                        <label class="form-label small mb-1">Time Period</label>
                                                        <select id="chart_period" class="form-select form-select-sm update-receivables">
                                                            <option value="last_6_months">Last 6 Months</option>
                                                            <option value="this_fiscal_year">This Fiscal Year</option>
                                                            <option value="previous_fiscal_year">Previous Fiscal Year</option>
                                                            <option value="last_12_months">Last 12 Months</option>
                                                        </select>
                                                    </div>
                                                    <div class="flex-shrink-0" style="min-width: 120px;">
                                                        <label class="form-label small mb-1">Basis</label>
                                                        <select id="chart_basis" class="form-select form-select-sm update-receivables">
                                                            <option value="accrual">Accrual</option>
                                                            <option value="cash">Cash</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="card-body">

                                                <div class="col-sm-12 col-xl-12">

                                                    <!-- Daily Receivables Chart -->
                                                    <div id="receivables_chart_div" style="width: 100%; height: 400px;"></div>


                                                        <script type="text/javascript">
                                                        google.charts.load('current', {
                                                            'packages': ['corechart']
                                                        });

                                                        function loadReceivablesChart() {
                                                            if (typeof google !== 'undefined' && google.charts && google.visualization) {
                                                                updateReceivablesChart();
                                                            } else {
                                                                google.charts.setOnLoadCallback(updateReceivablesChart);
                                                            }
                                                        }

                                                        document.addEventListener('DOMContentLoaded', function() {
                                                            loadReceivablesChart();
                                                            document.querySelectorAll('.update-receivables').forEach(function(el) {
                                                                el.addEventListener('change', loadReceivablesChart);
                                                            });
                                                        });

                                                        function updateReceivablesChart() {
                                                            var period = document.getElementById('chart_period').value;
                                                            var basis = document.getElementById('chart_basis').value;
                                                            
                                                            // Determine date ranges based on selected period
                                                            var today = new Date();
                                                            var months = [];
                                                            
                                                            switch(period) {
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
                                                                    // Assuming fiscal year starts in January
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
                                                            
                                                            // Fetch all month data in a single AJAX call
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
                                                            
                                                            // Make single AJAX request with all months
                                                            var formData = new FormData();
                                                            formData.append('customer_id', '<?php echo $customer_id; ?>');
                                                            formData.append('basis', basis);
                                                            formData.append('months', JSON.stringify(monthsData));
                                                            
                                                            fetch('ajax/get_customer_monthly_receivables.php', {
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
                                                                    } catch(e) {
                                                                        console.error('Response text:', text);
                                                                        throw new Error('Invalid JSON response: ' + e.message);
                                                                    }
                                                                });
                                                            })
                                                            .then(data => {
                                                                if (!data.success) {
                                                                    console.error('Error fetching chart data:', data.error);
                                                                    var errorMsg = data.error || 'Unknown error';
                                                                    console.error('Full response:', data);
                                                                    document.getElementById('receivables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><strong>Error loading chart:</strong><br>' + errorMsg + '</div>';
                                                                    return;
                                                                }
                                                                
                                                                if (!data.months || data.months.length === 0) {
                                                                    console.warn('No data returned for chart');
                                                                    document.getElementById('receivables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No receivables data available for the selected period</div>';
                                                                    return;
                                                                }
                                                                
                                                                drawReceivablesChart(data.months, basis, '<?php echo $display_name; ?>');
                                                            })
                                                            .catch(error => {
                                                                console.error('AJAX Error:', error);
                                                                document.getElementById('receivables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #d32f2f;"><strong>Failed to load chart data:</strong><br>' + error.message + '</div>';
                                                            });
                                                        }
                                                        
                                                        function drawReceivablesChart(monthsData, basis, displayName) {
                                                            // Validate input data
                                                            if (!monthsData || !Array.isArray(monthsData) || monthsData.length === 0) {
                                                                document.getElementById('receivables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">No data available to display</div>';
                                                                return;
                                                            }
                                                            
                                                            var data_array = [['Month', 'Receivables', { role: 'style' }]];
                                                            var max_receivable = 0;
                                                            
                                                            // Find max receivable for color coding
                                                            monthsData.forEach(function(item) {
                                                                if (item.receivable && item.receivable > max_receivable) {
                                                                    max_receivable = item.receivable;
                                                                }
                                                            });
                                                            
                                                            // Build data array with colors
                                                            monthsData.forEach(function(item) {
                                                                var color = '#90ee90'; // Light green for all amounts
                                                                var receivableValue = item.receivable || 0;
                                                                
                                                                data_array.push([item.month || 'N/A', receivableValue, color]);
                                                            });
                                                            
                                                            try {
                                                                var data = google.visualization.arrayToDataTable(data_array);
                                                                
                                                                var basis_text = document.getElementById('chart_basis').options[document.getElementById('chart_basis').selectedIndex].text;
                                                                var options = {
                                                                    title: 'Monthly Receivables - ' + displayName + ' (' + basis_text + ')',
                                                                    titleTextStyle: {
                                                                        color: '#102B44',
                                                                        fontSize: 14,
                                                                        bold: true
                                                                    },
                                                                    legend: 'none',
                                                                    hAxis: {
                                                                        title: 'Month',
                                                                        titleTextStyle: {
                                                                            color: '#555',
                                                                            fontSize: 12
                                                                        }
                                                                    },
                                                                    vAxis: {
                                                                        title: 'Amount (<?php echo BASE_CURRENCY['code']; ?>)',
                                                                        titleTextStyle: {
                                                                            color: '#555',
                                                                            fontSize: 12
                                                                        },
                                                                        minValue: 0,
                                                                        format: '#,###'
                                                                    },
                                                                    pointSize: 6,
                                                                    lineWidth: 2,
                                                                    colors: ['#007B8B'],
                                                                    bar: {
                                                                        groupWidth: '70%'
                                                                    }
                                                                };
                                                                
                                                                var chart = new google.visualization.ColumnChart(document.getElementById('receivables_chart_div'));
                                                                chart.draw(data, options);
                                                            } catch(e) {
                                                                console.error('Error drawing chart:', e);
                                                                document.getElementById('receivables_chart_div').innerHTML = '<div style="padding: 20px; text-align: center; color: #666;">Error rendering chart: ' + e.message + '</div>';
                                                            }
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

    <!-- Modal: Edit Opening Balance -->
    <div id="modal_opening_balance" class="modal fade" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Opening Balance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <form method="POST" action="customer_overview.php?customer_id=<?php echo $customer_id; ?>&action=update_opening_balance">
                    <?php echo csrf_field(); ?>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Opening Balance <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                <input type="number" step="0.01" class="form-control" name="opening_balance" value="<?php echo $customerObj->openingBalance != 0 ? number_format((float)$customerObj->openingBalance, 2, '.', '') : ''; ?>" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="ph-info me-2"></i>
                            <strong>Tax Update:</strong> The opening balance for your customers will not be included in your VAT Return if your Migration Date is on or after your first VAT return generation date. If you want the amount to be included in your VAT return, record it by creating an invoice after your VAT return generation date.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>

                        <div class="alert alert-warning fade show" role="alert">
                            <i class="ph-books me-2"></i>
                            <small>A journal entry will be automatically created — Debit: Accounts Receivable, Credit: Opening Balance Offset. This ensures the opening balance is reflected in your general ledger and trial balance.</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ph-check me-2"></i> Save Opening Balance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- /Modal: Edit Opening Balance -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
