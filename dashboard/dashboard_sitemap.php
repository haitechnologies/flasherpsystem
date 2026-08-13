<?php

use App\Security\Roles;
include('admin_elements/admin_header.php');
$module = 'sitemap';
$module_caption = 'Dashboard Sitemap';

// Dashboard modules organized by category
// Only includes files that exist in the current codebase
// Last updated: February 27, 2026 (Verified against actual codebase)
$sitemap = [
    'Core Dashboards' => [
        ['name'=>'Main Dashboard','url'=>'index.php'],
        ['name'=>'CRM Dashboard','url'=>'dashboard_crm.php'],
        ['name'=>'Dashboard Sitemap','url'=>'dashboard_sitemap.php']
    ],
    'Business Directory' => [
        ['name'=>'Inquiries','url'=>'listing_inquiries.php']
    ],
    'Category Taxonomy (New System)' => [
        ['name'=>'Categories','url'=>'listing_categories.php'],
        ['name'=>'Add/Edit Category','url'=>'categories.php'],
        ['name'=>'Subcategories','url'=>'listing_subcategories.php'],
        ['name'=>'Add/Edit Subcategory','url'=>'subcategories.php'],
        ['name'=>'HS Codes','url'=>'listing_hscodes.php'],
        ['name'=>'HS Code Mappings','url'=>'listing_hscodesmappings.php']
    ],
    'CRM - Customers' => [
        ['name'=>'Customers','url'=>'listing_customers.php'],
        ['name'=>'Add/Edit Customer','url'=>'customers.php'],
        ['name'=>'Customer Overview','url'=>'customer_overview.php','id'=>1],
        ['name'=>'Customer Contacts','url'=>'listing_customer_contacts.php'],
        ['name'=>'Add/Edit Contact','url'=>'customer_contacts.php','id'=>1],
        ['name'=>'Customer Invoices','url'=>'listing_customer_invoices.php'],
        ['name'=>'Customer Payments','url'=>'listing_customer_payments.php'],
        ['name'=>'Customer Statement','url'=>'customer_statement.php','id'=>1],
        ['name'=>'Customer Transactions','url'=>'customer_transactions.php','id'=>1]
    ],
    'CRM - Sales & Invoices' => [
        ['name'=>'Items','url'=>'listing_items.php'],
        ['name'=>'Add/Edit Item','url'=>'items.php'],
        ['name'=>'Invoices','url'=>'listing_invoices.php'],
        ['name'=>'Add/Edit Invoice','url'=>'invoices.php'],
        ['name'=>'Invoice Overview','url'=>'invoice_overview.php','id'=>1],
        ['name'=>'PDF Invoice','url'=>'pdf_invoice.php','id'=>1],
        ['name'=>'Payment Methods','url'=>'listing_payment_methods.php'],
        ['name'=>'Add/Edit Payment Method','url'=>'payment_methods.php']
    ],
    'Inventory & Services' => [
        ['name'=>'Services','url'=>'services.php'],
        ['name'=>'Organizations','url'=>'listing_organizations.php'],
        ['name'=>'Add/Edit Organization','url'=>'organizations.php']
    ],
    'Content Management' => [
        ['name'=>'Banned Words','url'=>'listing_banned_words.php'],
        ['name'=>'Add/Edit Banned Word','url'=>'banned_words.php']
    ],
    'Email Marketing' => [
        ['name'=>'Email Providers','url'=>'listing_email_providers.php'],
        ['name'=>'Add/Edit Provider','url'=>'email_providers.php'],
        ['name'=>'Email History','url'=>'listing_email_history.php'],
        ['name'=>'Email Queue','url'=>'listing_email_queue.php'],
        ['name'=>'Email Queue Worker','url'=>'email_queue_worker.php']
    ],
    'Master Data & Configuration' => [
        ['name'=>'Geo Countries','url'=>'listing_geo_countries.php'],
        ['name'=>'Geo States','url'=>'listing_geo_states.php'],
        ['name'=>'Geo Cities','url'=>'listing_geo_cities.php'],
        ['name'=>'Category HS Codes','url'=>'listing_category_hs_codes.php']
    ],
    'Advanced Features' => [
        ['name'=>'System Settings','url'=>'listing_system_settings.php']
    ],
    'Reports & Analytics' => [
        ['name'=>'Reports Hub','url'=>'reports.php'],
        ['name'=>'Sales Summary','url'=>'report_sales_summary.php'],
        ['name'=>'Sales by Customer','url'=>'report_sales_by_customer.php'],
        ['name'=>'Sales by Item','url'=>'report_sales_by_item.php']
    ],
    'Setup & Configuration' => [
        ['name'=>'Setup Overview','url'=>'setup.php'],
        ['name'=>'Global Settings','url'=>'global_settings.php'],
        ['name'=>'UI Design Settings','url'=>'ui_design_settings.php'],
        ['name'=>'Sources','url'=>'listing_setup_sources.php'],
        ['name'=>'Add/Edit Source','url'=>'setup_sources.php'],
        ['name'=>'Statuses','url'=>'listing_setup_statuses.php'],
        ['name'=>'Add/Edit Status','url'=>'setup_statuses.php'],
        ['name'=>'Tags','url'=>'listing_setup_tags.php'],
        ['name'=>'Add/Edit Tag','url'=>'setup_tags.php'],
        ['name'=>'Banks (Institutions)','url'=>'listing_setup_banks.php'],
        ['name'=>'Add/Edit Bank Institution','url'=>'setup_banks.php'],
        ['name'=>'Job Statuses','url'=>'listing_job_statuses.php']
    ],
    'Operations & Automation' => [
        ['name'=>'Cron Jobs','url'=>'listing_cron_jobs.php'],
        ['name'=>'Cron Logs','url'=>'cron_logs.php'],
        ['name'=>'Features','url'=>'features.php']
    ],
    'System Administration' => [
        ['name'=>'Users','url'=>'listing_users.php'],
        ['name'=>'Add/Edit User','url'=>'users.php'],
        ['name'=>'User Details','url'=>'user.php','id'=>1],
        ['name'=>'Profile','url'=>'profile.php'],
        ['name'=>'Change Password','url'=>'change_password.php'],
        ['name'=>'Authentication Activity','url'=>'listing_authentication_activity.php'],
        ['name'=>'Roles & Permissions','url'=>'listing_roles.php'],
        ['name'=>'Add/Edit Role','url'=>'roles.php'],
        ['name'=>'Module Permissions','url'=>'module_permissions.php'],
        ['name'=>'Modules','url'=>'listing_modules.php'],
        ['name'=>'Add/Edit Module','url'=>'modules.php'],
        ['name'=>'Alerts','url'=>'listing_alerts.php']
    ],
    'Tools & Utilities' => [
        ['name'=>'Sitemap Generator','url'=>'sitemap.php'],
        ['name'=>'Generate PDF','url'=>'generate_pdf.php'],
        ['name'=>'Generate','url'=>'generate.php'],
        ['name'=>'Send Email','url'=>'send_email.php'],
        ['name'=>'DataTable Pages Audit','url'=>'listing_pages_audit.php'] // not part of pages module
    ],
    'Logs & Debugging' => [
        ['name'=>'View Error Logs','url'=>'view_backend_error_logs.php'],
        ['name'=>'View Frontend Error Logs','url'=>'view_frontend_error_logs.php']
    ],
    'Authentication' => [
        ['name'=>'Login','url'=>'login.php'],
        ['name'=>'Forgot Password','url'=>'forgot_password.php'],
        ['name'=>'Reset Password','url'=>'reset_password.php'],
        ['name'=>'Logout','url'=>'logout.php']
    ]
];
$total_pages=0;foreach($sitemap as $p)$total_pages+=count($p);
?>
<style>
/* Modern Dashboard Sitemap Styling */
:root {
    --primary: #667eea;
    --primary-light: #f3f4ff;
    --success: #48bb78;
    --danger: #f56565;
    --warning: #ecc94b;
    --info: #4299e1;
    --bg-light: #f7fafc;
    --border: #e2e8f0;
    --text: #2d3748;
    --text-light: #718096;
}

.sitemap-header {
    background: linear-gradient(135deg, var(--primary) 0%, #764ba2 100%);
    color: white;
    padding: 24px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.sitemap-header h1 {
    margin: 0 0 8px;
    font-size: 28px;
    font-weight: 700;
}

.sitemap-header p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 12px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 12px;
    color: var(--text-light);
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}

.stat-card.success .stat-value { color: var(--success); }
.stat-card.danger .stat-value { color: var(--danger); }
.stat-card.warning .stat-value { color: var(--warning); }

.controls-bar {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.controls-bar button {
    font-size: 12px;
    padding: 8px 14px;
}

.category-section {
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    margin-bottom: 12px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.cat-header {
    background: linear-gradient(135deg, #f7fafc 0%, #fff 100%);
    padding: 12px 16px;
    border-left: 4px solid var(--primary);
    cursor: pointer;
    user-select: none;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 600;
    color: var(--text);
    transition: all 0.2s ease;
}

.cat-header:hover {
    background: var(--primary-light);
}

.cat-header strong {
    flex: 1;
    font-size: 14px;
}

.cat-header .badge {
    font-size: 11px;
    padding: 4px 8px;
    border-radius: 4px;
    background: var(--primary-light);
    color: var(--primary);
    font-weight: 600;
}

.cat-icon {
    transition: transform 0.2s ease;
    font-size: 14px;
    color: var(--primary);
}

.cat-header:hover .cat-icon {
    color: #764ba2;
}

.cat-body {
    display: none;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.cat-body.show {
    display: block;
    max-height: 2000px;
}

.compact-table {
    margin: 0 !important;
    border: none;
}

.compact-table thead {
    background: var(--bg-light);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-light);
}

.compact-table td {
    padding: 10px 12px !important;
    font-size: 13px;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.compact-table tbody tr:hover {
    background: var(--bg-light);
}

.page-link {
    color: var(--primary);
    text-decoration: none;
    font-weight: 500;
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    transition: all 0.2s ease;
}

.page-link:hover {
    background: var(--primary-light);
    color: #764ba2;
}

.status-badge {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 600;
    min-width: 50px;
    justify-content: center;
}

.status-badge.bg-success { background: #c6f6d5; color: #22543d; }
.status-badge.bg-danger { background: #fed7d7; color: #742a2a; }
.status-badge.bg-warning { background: #feebc8; color: #7c2d12; }
.status-badge.bg-info { background: #bee3f8; color: #2c5282; }
.status-badge.bg-secondary { background: #e2e8f0; color: #4a5568; }

.test-btn {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 3px;
}

.test-btn i {
    font-size: 11px;
}

.progress-compact {
    height: 16px;
    margin: 12px 0;
    border-radius: 4px;
    background: var(--border);
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, var(--success), var(--primary));
    height: 100%;
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 10px;
    font-weight: 600;
}

.alert-info {
    background: var(--primary-light);
    border: 1px solid #c6d9f5;
    border-left: 4px solid var(--primary);
    color: var(--primary);
    font-size: 12px;
}

@media (max-width: 768px) {
    .sitemap-header { padding: 16px; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .controls-bar { flex-direction: column; }
    .controls-bar button { width: 100%; }
    .compact-table td { padding: 8px !important; font-size: 12px; }
}
</style>
<div class="content-wrapper">
    <div class="content pt-3">
        <div class="content-inner">

            <!-- Header Section -->
            <div class="sitemap-header">
                <h1><i class="ph-sitemap me-2"></i>Dashboard Sitemap</h1>
                <p>Complete navigation index of all dashboard modules and pages</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?=$total_pages?></div>
                    <div class="stat-label">Total Pages</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?=count($sitemap)?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-value" id="w">-</div>
                    <div class="stat-label">Working</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-value" id="b">-</div>
                    <div class="stat-label">Broken</div>
                </div>
            </div>

            <!-- Control Buttons -->
            <div class="controls-bar">
                <button class="btn btn-outline-primary btn-sm" id="exp"><i class="ph-arrows-out me-1"></i>Expand All</button>
                <button class="btn btn-outline-secondary btn-sm" id="col"><i class="ph-arrows-in me-1"></i>Collapse All</button>
                <button class="btn btn-primary btn-sm" id="ta"><i class="ph-play me-1"></i>Test All</button>
                <button class="btn btn-warning btn-sm" id="tb" style="display:none"><i class="ph-arrow-clockwise me-1"></i>Retest Broken</button>
            </div>

            <!-- Progress Bar -->
            <div id="pg" style="display:none">
                <div class="progress progress-compact">
                    <div class="progress-bar" id="pb" style="width:0%">
                        <span id="pt">0%</span>
                    </div>
                </div>
            </div>

            <!-- Info Alert -->
            <div class="alert alert-info border-0 py-2 px-3 mb-3">
                <small><strong>Status Legend:</strong> <span class="text-success">✓ OK</span> | <span class="text-danger">✗ Error</span> | <span class="text-warning">⚠ Needs ID</span></small>
            </div>

            <!-- Sitemap Content -->
            <div id="sitemap-container">
                <?php foreach($sitemap as $cat=>$pages):?>
                <div class="category-section">
                    <div class="cat-header" data-toggle="collapse">
                        <i class="ph-caret-right cat-icon"></i>
                        <strong><?=$cat?></strong>
                        <div class="badge"><?=count($pages)?> pages</div>
                    </div>
                    <div class="cat-body">
                        <div class="table-responsive">
<table class="table table-sm table-hover compact-table mb-0">
                            <thead>
                                <tr>
                                    <th width="40">#</th>
                                    <th>Page</th>
                                    <th width="80">Status</th>
                                    <th width="60">Code</th>
                                    <th width="60">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($pages as $i=>$p):$rid=isset($p['id'])?1:0;?>
                                <tr class="compact-row" data-u="<?=$p['url']?>" data-r="<?=$rid?>">
                                    <td><?=$i+1?></td>
                                    <td><a href="<?=$p['url']?>" class="page-link" target="_blank"><i class="ph-link me-1"></i><?=$p['name']?></a></td>
                                    <td class="s text-center"><span class="status-badge bg-secondary"><small>⏳</small></span></td>
                                    <td class="c text-center text-muted"><small>-</small></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary test-btn ts" title="Test this page"><i class="ph-play"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach;?>
                            </tbody>
                        </table>
</div>
                    </div>
                </div>
                <?php endforeach;?>
            </div>

        </div>
    </div>
            <!-- Footer -->
            <?php include('admin_elements/copyright.php');?>
</div>
<script>
$(function(){
    // ===== Collapsible Categories =====
    $('.cat-header').on('click', function() {
        const body = $(this).next('.cat-body');
        const icon = $(this).find('.cat-icon');
        
        body.toggleClass('show');
        icon.toggleClass('ph-caret-right ph-caret-down');
    });

    // Expand/Collapse all buttons
    $('#exp').on('click', function() {
        $('.cat-body').addClass('show');
        $('.cat-icon').removeClass('ph-caret-right').addClass('ph-caret-down');
    });

    $('#col').on('click', function() {
        $('.cat-body').removeClass('show');
        $('.cat-icon').removeClass('ph-caret-down').addClass('ph-caret-right');
    });

    // ===== Testing Functionality =====
    let totalTests = 0;
    let testsCompleted = 0;
    let workingPages = 0;
    let brokenPages = 0;

    // Count testable pages (those without ID requirement)
    $('tr[data-u]').each(function() {
        if ($(this).data('r') == 0) totalTests++;
    });

    /**
     * Test a single page
     */
    function testPage($row) {
        const url = $row.data('u');
        const requiresId = $row.data('r') == 1;

        // Pages requiring ID can't be auto-tested
        if (requiresId) {
            $row.find('.s').html('<span class="status-badge bg-warning"><small>⚠</small></span>');
            $row.find('.c').html('<small>ID</small>');
            return Promise.resolve();
        }

        $row.find('.s').html('<span class="status-badge bg-info"><small>⏳</small></span>');

        return $.ajax({
            url: url,
            method: 'HEAD',
            timeout: 8000
        })
        .done(function(data, textStatus, xhr) {
            const statusCode = xhr.status;
            
            if (statusCode == 200) {
                $row.find('.s').html('<span class="status-badge bg-success"><small>✓</small></span>');
                workingPages++;
            } else if (statusCode == 403) {
                $row.find('.s').html('<span class="status-badge bg-warning"><small>⊘</small></span>');
            } else {
                $row.find('.s').html('<span class="status-badge bg-danger"><small>✗</small></span>');
                brokenPages++;
            }
            
            $row.find('.c').html('<small>' + statusCode + '</small>');
            testsCompleted++;
            updateProgress();
        })
        .fail(function(xhr) {
            $row.find('.s').html('<span class="status-badge bg-danger"><small>✗</small></span>');
            $row.find('.c').html('<small>' + (xhr.status || 'ERR') + '</small>');
            brokenPages++;
            testsCompleted++;
            updateProgress();
        });
    }

    /**
     * Update progress display
     */
    function updateProgress() {
        const percent = totalTests > 0 ? Math.round((testsCompleted / totalTests) * 100) : 0;
        $('#pb').css('width', percent + '%');
        $('#pt').text(percent + '%');
        $('#w').text(workingPages);
        $('#b').text(brokenPages);
    }

    /**
     * Test all pages
     */
    $('#ta').on('click', function() {
        testsCompleted = 0;
        workingPages = 0;
        brokenPages = 0;

        $('#pg').show();
        $(this).prop('disabled', true);

        const $rows = $('tr[data-u]').filter(function() {
            return $(this).data('r') == 0;
        });

        let index = 0;

        function processNext() {
            if (index < $rows.length) {
                testPage($rows.eq(index)).always(function() {
                    index++;
                    setTimeout(processNext, 150);
                });
            } else {
                $('#ta').prop('disabled', false);
                if (brokenPages > 0) $('#tb').show();
                
                if (typeof Noty !== 'undefined') {
                    new Noty({
                        text: 'Test complete! Working: ' + workingPages + ' | Broken: ' + brokenPages,
                        type: brokenPages > 0 ? 'warning' : 'success',
                        timeout: 4000
                    }).show();
                }
            }
        }

        processNext();
    });

    /**
     * Test single page button
     */
    $('.ts').on('click', function() {
        testPage($(this).closest('tr'));
    });

    /**
     * Retest broken pages
     */
    $('#tb').on('click', function() {
        const $brokenRows = $('tr[data-u]').filter(function() {
            return $(this).find('.s .status-badge').hasClass('bg-danger');
        });

        if ($brokenRows.length == 0) {
            if (typeof Noty !== 'undefined') {
                new Noty({
                    text: 'No broken pages found!',
                    type: 'info',
                    timeout: 2000
                }).show();
            }
            return;
        }

        testsCompleted = 0;
        workingPages = 0;
        brokenPages = 0;
        totalTests = $brokenRows.length;

        $('#pg').show();

        let index = 0;

        function processNext() {
            if (index < $brokenRows.length) {
                testPage($brokenRows.eq(index)).always(function() {
                    index++;
                    setTimeout(processNext, 150);
                });
            } else {
                if (typeof Noty !== 'undefined') {
                    new Noty({
                        text: 'Retest complete! Fixed: ' + workingPages + ' | Still Broken: ' + brokenPages,
                        type: brokenPages > 0 ? 'warning' : 'success',
                        timeout: 4000
                    }).show();
                }
            }
        }

        processNext();
    });
});
</script>
<?php include('admin_elements/admin_footer.php');?>
