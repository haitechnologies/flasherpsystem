<?php

use App\Core\DB;
use App\Security\Roles;

include('admin_elements/admin_header.php');

if (!Roles::hasFullAccess($session_role_id)) {
    echo "<div class='alert alert-danger text-center mt-5'><h3>Access Denied</h3><p>This page is restricted to System and Super Administrators only.</p></div>";
    include('admin_elements/admin_footer.php');
    exit;
}

if (!function_exists('coverage_table_exists')) {
    function coverage_table_exists($mysqli, $tableName) {
        $sql = "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1";
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param('s', $tableName);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }
}

function coverageResolveSlug(string $name): string {
    $base = strtolower(pathinfo($name, PATHINFO_FILENAME));
    if (str_starts_with($base, 'listing_')) return substr($base, 8) ?: 'unknown';
    if (str_starts_with($base, 'dashboard_')) return substr($base, 10) ?: 'dashboard';
    if (str_starts_with($base, 'report_')) return 'reports';
    if (str_starts_with($base, 'view_')) return substr($base, 5);
    return $base !== '' && $base !== 'index' ? $base : 'dashboard';
}

// === CURATED QA PAGE LIST ===
// Only user-interactive pages a QA tester would click through.
// Technical/backend pages (ajax, api, cron, PDF generators, DataTable handlers,
// debug scripts, auto-populate, sync tools) are excluded.

$QA_PAGES = [
    // ── Listing Pages ─────────────────────────────────────────────
    'listing_accounts.php',
    'listing_accounts_report_categories.php',
    'listing_alerts.php',
    'listing_attendance.php',
    'listing_authentication_activity.php',
    'listing_banks.php',
    'listing_banned_words.php',
    'listing_carriers.php',
    'listing_categories.php',
    'listing_commodity_types.php',
    'listing_consignees.php',
    'listing_container_types.php',
    'listing_credit_notes.php',
    'listing_cron_jobs.php',
    'listing_currencies.php',
    'listing_customer_contacts.php',
    'listing_customer_invoices.php',
    'listing_customer_payments.php',
    'listing_customers.php',
    'listing_debit_notes.php',
    'listing_departments.php',
    'listing_designations.php',
    'listing_disposable_email_domains.php',
    'listing_document_categories.php',
    'listing_documents.php',
    'listing_email_history.php',
    'listing_email_providers.php',
    'listing_email_queue.php',
    'listing_employee_salaries.php',
    'listing_exit_points.php',
    'listing_expenses.php',
    'listing_geo_cities.php',
    'listing_geo_countries.php',
    'listing_geo_states.php',
    'listing_hscodes.php',
    'listing_incoterms.php',
    'listing_inquiries.php',
    'listing_invoices.php',
    'listing_items.php',
    'listing_job_statuses.php',
    'listing_jobs.php',
    'listing_journals.php',
    'listing_leads.php',
    'listing_leave_requests.php',
    'listing_leave_types.php',
    'listing_modules.php',
    'listing_organization_roles.php',
    'listing_organizations.php',
    'listing_pages_audit.php',
    'listing_payment_methods.php',
    'listing_payment_terms.php',
    'listing_payments_made.php',
    'listing_payments_received.php',
    'listing_payroll_components.php',
    'listing_payroll_runs.php',
    'listing_payslips.php',
    'listing_ports.php',
    'listing_projects.php',
    'listing_purchase_orders.php',
    'listing_purchase_types.php',
    'listing_purchases.php',
    'listing_quotations.php',
    'listing_roles.php',
    'listing_salary_structures.php',
    'listing_sale_orders.php',
    'listing_sale_types.php',
    'listing_services.php',
    'listing_setup_banks.php',
    'listing_setup_groups.php',
    'listing_setup_sources.php',
    'listing_setup_statuses.php',
    'listing_setup_tags.php',
    'listing_shippers.php',
    'listing_shipping_advice_items.php',
    'listing_shipping_advices.php',
    'listing_shipping_customers.php',
    'listing_shipping_stocks.php',
    'listing_storage_subtypes.php',
    'listing_storage_types.php',
    'listing_subcategories.php',
    'listing_system_settings.php',
    'listing_tax_treatments.php',
    'listing_units.php',
    'listing_user_documents.php',
    'listing_users.php',
    'listing_vendors.php',

    // ── Auth Pages ─────────────────────────────────────────────────
    'logout.php',
    'forgot_password.php',
    'reset_password.php',
    'select_organization.php',
    'organization_accept_invite.php',

    // ── Dashboard / Index ──────────────────────────────────────────
    'index.php',
    'dashboard_accounting.php',
    'dashboard_crm.php',
    'dashboard_hr.php',
    'dashboard_shipping.php',
    'dashboard_sitemap.php',

    // ── Core Modules ───────────────────────────────────────────────
    'accounts.php',
    'accounts_report_categories.php',
    'accounts_report_subcategories.php',
    'alerts.php',
    'attendance.php',
    'banks.php',
    'banned_words.php',
    'carriers.php',
    'categories.php',
    'category_hs_codes.php',
    'change_password.php',
    'commodity_types.php',
    'consignees.php',
    'container_types.php',
    'currencies.php',
    'customers.php',
    'customer_billing_addresses.php',
    'customer_comments.php',
    'customer_contacts.php',
    'customer_logs.php',
    'customer_mails.php',
    'customer_overview.php',
    'customer_shipping_addresses.php',
    'customer_statement.php',
    'customer_transactions.php',
    'credit_notes.php',
    'credit_note_overview.php',
    'debit_notes.php',
    'debit_note_overview.php',
    'departments.php',
    'designations.php',
    'document_categories.php',
    'documents.php',
    'email_history.php',
    'email_providers.php',
    'exit_points.php',
    'expense_overview.php',
    'expenses.php',
    'features.php',
    'global_settings.php',
    'hscodes.php',
    'import_shipping_advices.php',
    'incoterms.php',
    'inquiries.php',
    'invoice_overview.php',
    'invoices.php',
    'items.php',
    'job_statuses.php',
    'jobs.php',
    'journals.php',
    'leads.php',
    'lead.php',
    'lead_attachments.php',
    'lead_logs.php',
    'lead_notes.php',
    'leave_requests.php',
    'leave_types.php',
    'module_permissions.php',
    'modules.php',
    'organization_invites.php',
    'organization_roles.php',
    'organizations.php',
    'payment_methods.php',
    'payment_received_overview.php',
    'payment_terms.php',
    'payments_made.php',
    'payments_made_overview.php',
    'payments_received.php',
    'payroll_components.php',
    'payroll_runs.php',
    'ports.php',
    'profile.php',
    'projects.php',
    'purchases.php',
    'purchase_order_overview.php',
    'purchase_orders.php',
    'purchase_overview.php',
    'purchase_types.php',
    'quotations.php',
    'quotation_overview.php',
    'recurring_invoice_overview.php',
    'recurring_invoices.php',
    'roles.php',
    'salary_structures.php',
    'sale_orders.php',
    'sale_order_overview.php',
    'sale_types.php',
    'seo_health_check.php',
    'services.php',
    'setup.php',
    'setup_banks.php',
    'setup_groups.php',
    'setup_sources.php',
    'setup_statuses.php',
    'setup_tags.php',
    'shippers.php',
    'shipping_customers.php',
    'shipping_stocks.php',
    'sitemap.php',
    'sitemaps.php',
    'storage_subtypes.php',
    'storage_types.php',
    'subcategories.php',
    'subscription_management.php',
    'system_settings.php',
    'tax_treatments.php',
    'ui_design_settings.php',
    'units.php',
    'user.php',
    'user_documents.php',
    'users.php',
    'vendors.php',
    'vendor_credit_overview.php',
    'vendor_overview.php',
    'view_job.php',
    'view_payroll_run.php',
    'view_payslip.php',
    'view_project.php',
    'view_shipping_advice.php',
    'view_shipping_stocks.php',

    // ── Reports ────────────────────────────────────────────────────
    'reports.php',
    'report_account_transactions.php',
    'report_account_type_summary.php',
    'report_ar_aging_details.php',
    'report_ar_aging_summary.php',
    'report_ar_summary.php',
    'report_balance_sheet.php',
    'report_billable_expense_details.php',
    'report_business_performance_ratios.php',
    'report_cash_flow_statement.php',
    'report_clients.php',
    'report_credit_note_details.php',
    'report_customer_balance_summary.php',
    'report_detailed_general_ledger.php',
    'report_expense_details.php',
    'report_expenses_by_category.php',
    'report_expenses_by_customer.php',
    'report_general_ledger.php',
    'report_hr.php',
    'report_invoice_details.php',
    'report_invoices.php',
    'report_journal_report.php',
    'report_leads.php',
    'report_movement_of_equity.php',
    'report_payable_details.php',
    'report_payable_summary.php',
    'report_payments_received.php',
    'report_profit_and_loss.php',
    'report_quote_details.php',
    'report_receivable_details.php',
    'report_receivable_summary.php',
    'report_reconciliation_status.php',
    'report_recurring_invoice_details.php',
    'report_refund_history.php',
    'report_sales_by_customer.php',
    'report_sales_by_item.php',
    'report_sales_by_sales_person.php',
    'report_sales_summary.php',
    'report_shipping_stocks.php',
    'report_time_to_get_paid.php',
    'report_trial_balance.php',
    'report_vendor_balance_summary.php',

    // ── Admin Tools ────────────────────────────────────────────────
    'cron.php',
    'cron_logs.php',
    'system_settings.php',

    // ── Error/Info ─────────────────────────────────────────────────
    '404.php',
    '500.php',
];

// ── Auto-sync coverage table with the curated QA list ─────────────
$pendingRows = [];
if (coverage_table_exists($mysqli, DB::BACKEND_LOG_COVERAGE)) {
    // Delete stale entries not in the curated list
    $ph = implode(',', array_fill(0, count($QA_PAGES), '?'));
    $tp = str_repeat('s', count($QA_PAGES));
    $ds = $mysqli->prepare("DELETE FROM `" . DB::BACKEND_LOG_COVERAGE . "` WHERE page_path NOT IN ($ph)");
    if ($ds) { $ds->bind_param($tp, ...$QA_PAGES); $ds->execute(); $ds->close(); }

    // Insert new entries not yet tracked
    $is = $mysqli->prepare("INSERT IGNORE INTO `" . DB::BACKEND_LOG_COVERAGE . "`
        (module_slug, page_name, page_path, entrypoint_type, source_channel, bootstrap_included, first_seen_at, last_seen_at, seen_count)
        VALUES (?, ?, ?, 'page', 'dashboard_runtime', 1, NOW(), NOW(), 0)");
    if ($is) {
        foreach ($QA_PAGES as $pagePath) {
            $slug = coverageResolveSlug($pagePath);
            $is->bind_param('sss', $slug, $pagePath, $pagePath);
            $is->execute();
        }
        $is->close();
    }

    // Fetch ALL pages with their status for display
    $stmt = $mysqli->prepare("SELECT module_slug, page_name, page_path, seen_count, last_seen_error_at,
        CASE WHEN last_seen_error_at IS NOT NULL THEN 'error'
             WHEN seen_count > 0 THEN 'ok'
             ELSE 'unknown'
        END AS page_status
        FROM `" . DB::BACKEND_LOG_COVERAGE . "`
        ORDER BY page_path ASC");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result ? $result->fetch_assoc() : null) {
            $pendingRows[] = $row;
        }
        $stmt->close();
    }
}

// ── Compute stats ─────────────────────────────────────────────────
$totalCount = count($pendingRows);
$okCount = 0; $errorCount = 0; $unknownCount = 0;
foreach ($pendingRows as $r) {
    $s = (string)($r['page_status'] ?? 'unknown');
    if ($s === 'error') $errorCount++;
    elseif ($s === 'ok') $okCount++;
    else $unknownCount++;
}

// Group by module
$groupedRows = [];
foreach ($pendingRows as $row) {
    $mod = trim((string)($row['module_slug'] ?? 'unknown'));
    if ($mod === '') $mod = 'unknown';
    $groupedRows[$mod][] = $row;
}
uksort($groupedRows, fn($a, $b) => strcmp($a, $b));
$moduleCount = count($groupedRows);

$totalErrors = 0;
if (coverage_table_exists($mysqli, DB::BACKEND_ERROR_LOGS)) {
    $r2 = $mysqli->query("SELECT COUNT(*) AS cnt FROM `" . DB::BACKEND_ERROR_LOGS . "`");
    if ($r2) { $er = $r2->fetch_assoc(); $totalErrors = (int)($er['cnt'] ?? 0); $r2->free(); }
}
$coveragePct = $totalCount > 0 ? round(($okCount / $totalCount) * 100, 1) : 0;

// ── Logging verification: per-module PASS/FAIL ────────────────────
// PASS  = module has >=1 observed page (heartbeat fired) AND no coverage
//         rows with blank/unknown slug, page_name or page_path.
// PARTIAL = module pages exist but none observed yet (untested).
// The logged-errors column counts ERROR/CRITICAL rows actually captured
// in erp_backend_error_logs for that module (proves bug capture works).
$verifyResults = [];
if (coverage_table_exists($mysqli, DB::BACKEND_LOG_COVERAGE)) {
    $vc = $mysqli->query("SELECT module_slug,
            COUNT(*) AS page_count,
            SUM(CASE WHEN seen_count > 0 THEN 1 ELSE 0 END) AS observed_pages,
            SUM(CASE WHEN page_name = 'unknown' OR page_name = '' OR page_path = ''
                     OR module_slug = 'unknown' OR module_slug = '' THEN 1 ELSE 0 END) AS bad_rows
        FROM `" . DB::BACKEND_LOG_COVERAGE . "`
        GROUP BY module_slug");
    if ($vc) {
        while ($row = $vc->fetch_assoc()) {
            $verifyResults[(string)$row['module_slug']] = [
                'page_count'     => (int)$row['page_count'],
                'observed_pages' => (int)$row['observed_pages'],
                'bad_rows'       => (int)$row['bad_rows'],
                'log_errors'     => 0,
            ];
        }
        $vc->free();
    }
}
if ($verifyResults && coverage_table_exists($mysqli, DB::BACKEND_ERROR_LOGS)) {
    $ve = $mysqli->query("SELECT module_slug, COUNT(*) AS cnt
        FROM `" . DB::BACKEND_ERROR_LOGS . "`
        WHERE module_slug IS NOT NULL AND module_slug != ''
          AND severity IN ('ERROR', 'CRITICAL')
        GROUP BY module_slug");
    if ($ve) {
        while ($row = $ve->fetch_assoc()) {
            $m = (string)$row['module_slug'];
            if (isset($verifyResults[$m])) {
                $verifyResults[$m]['log_errors'] = (int)$row['cnt'];
            }
        }
        $ve->free();
    }
}
uksort($verifyResults, fn($a, $b) => strcmp($a, $b));
$verifyPassCount = 0;
$verifyPartialCount = 0;
foreach ($verifyResults as $v) {
    if ($v['observed_pages'] > 0 && $v['bad_rows'] === 0) {
        $verifyPassCount++;
    } else {
        $verifyPartialCount++;
    }
}
?>

<style>
    .coverage-sweep-wrap .stat-card { border-radius: 8px; padding: 10px 14px; border: 1px solid #e3e8f0; background: #fff; min-width: 100px; }
    .coverage-sweep-wrap .stat-card .stat-val { font-size: 1.25rem; font-weight: 700; line-height: 1.1; }
    .coverage-sweep-wrap .stat-card .stat-lbl { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6c7a8d; margin-top: 1px; }
    .coverage-sweep-wrap .sweep-table-card { border: 1px solid #dee2e9; border-radius: 8px; overflow: hidden; }
    .coverage-sweep-wrap .sweep-table { font-size: 0.84rem; }
    .coverage-sweep-wrap .sweep-table th { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.04em; color: #6c7a8d; background: #f7f9fc; white-space: nowrap; }
    .coverage-sweep-wrap .sweep-table td { vertical-align: middle; }
    .coverage-sweep-wrap .module-row { background: #eef3fb; }
    .coverage-sweep-wrap .module-row td { font-weight: 600; color: #33435d; border-top: 1px solid #d8e2f0; }
    .coverage-sweep-wrap .module-row .module-count { font-size: 0.75rem; color: #5f6d82; font-weight: 500; margin-left: 8px; }
    .coverage-sweep-wrap .progress-bar-coverage { height: 6px; border-radius: 4px; background: #e3e8f0; overflow: hidden; }
    .coverage-sweep-wrap .progress-bar-coverage .bar-fill { height: 100%; border-radius: 4px; background: #28a745; transition: width 0.4s; }
</style>

<div class="content-wrapper coverage-sweep-wrap">
    <div class="content-inner">
        <div class="content">

            <div class="page-header page-header-light shadow carriers-page-header">
                <div class="page-header-content d-lg-flex carriers-page-header-content">
                    <div class="d-flex align-items-center">
                        <h4 class="page-title mb-0">
                            QA Page Health
                            <small class="ms-2 text-muted"><?php echo $totalCount; ?> user-facing pages</small>
                        </h4>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-3 mt-2">
                <div class="stat-card">
                    <div class="stat-val text-success"><?php echo number_format($okCount); ?></div>
                    <div class="stat-lbl">Working</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-danger"><?php echo number_format($errorCount); ?></div>
                    <div class="stat-lbl">Errors</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-muted"><?php echo number_format($unknownCount); ?></div>
                    <div class="stat-lbl">Untested</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-danger"><?php echo number_format($totalErrors); ?></div>
                    <div class="stat-lbl">Error Logs</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-secondary"><?php echo number_format($moduleCount); ?></div>
                    <div class="stat-lbl">Modules</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-primary"><?php echo number_format($totalCount); ?></div>
                    <div class="stat-lbl">Total Pages</div>
                </div>
                <div class="stat-card" style="min-width:150px;">
                    <div class="stat-val <?php echo $coveragePct >= 80 ? 'text-success' : ($coveragePct >= 50 ? 'text-warning' : 'text-danger'); ?>"><?php echo $coveragePct; ?>%</div>
                    <div class="stat-lbl">Tested</div>
                    <div class="progress-bar-coverage mt-1">
                        <div class="bar-fill" style="width:<?php echo $coveragePct; ?>%;"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mb-2">
                <button type="button" id="runAllBtn" class="btn btn-sm btn-success">
                    <i class="ph-play me-1"></i> Run Full QA Test
                </button>
                <span id="sweepProgress" class="small text-muted" style="display:none;"></span>
            </div>

            <div class="sweep-table-card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 sweep-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th style="width:80px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupedRows as $moduleName => $moduleRows): ?>
                                <tr class="module-row">
                                    <td colspan="2">
                                        <i class="ph-folder me-1"></i>
                                        <?php echo htmlspecialchars(ucfirst(str_replace(['_', '-'], ' ', $moduleName)), ENT_QUOTES); ?>
                                        <span class="module-count"><?php echo count($moduleRows); ?></span>
                                    </td>
                                </tr>
                                <?php foreach ($moduleRows as $row):
                                    $pagePath = (string)($row['page_path'] ?? ($row['page_name'] ?? 'unknown'));
                                    $status   = (string)($row['page_status'] ?? 'unknown');
                                ?>
                                    <tr data-url="<?php echo htmlspecialchars($pagePath, ENT_QUOTES); ?>">
                                        <td><code><?php echo htmlspecialchars($pagePath, ENT_QUOTES); ?></code></td>
                                        <td>
                                            <?php if ($status === 'ok'): ?>
                                                <span class="badge bg-success">OK</span>
                                            <?php elseif ($status === 'error'): ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mb-2 mt-4">
                <h5 class="mb-0"><i class="ph-shield-check me-1"></i> Logging Verification</h5>
                <span class="small text-muted">per-module capture of bugs/logs in <code>view_backend_error_logs.php</code></span>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <div class="stat-card">
                    <div class="stat-val text-success"><?php echo number_format($verifyPassCount); ?></div>
                    <div class="stat-lbl">Modules PASS</div>
                </div>
                <div class="stat-card">
                    <div class="stat-val text-warning"><?php echo number_format($verifyPartialCount); ?></div>
                    <div class="stat-lbl">Untested / Needs Attention</div>
                </div>
            </div>

            <div class="sweep-table-card">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0 sweep-table">
                        <thead>
                            <tr>
                                <th>Module</th>
                                <th style="width:90px;">Pages</th>
                                <th style="width:110px;">Observed</th>
                                <th style="width:130px;">Logged ERR/CRIT</th>
                                <th style="width:110px;">Verification</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($verifyResults as $moduleName => $v): ?>
                                <?php $pass = $v['observed_pages'] > 0 && $v['bad_rows'] === 0; ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($moduleName, ENT_QUOTES); ?></code></td>
                                    <td><?php echo (int)$v['page_count']; ?></td>
                                    <td><?php echo (int)$v['observed_pages']; ?></td>
                                    <td>
                                        <?php if ((int)$v['log_errors'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo (int)$v['log_errors']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($pass): ?>
                                            <span class="badge bg-success">PASS</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">PARTIAL</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <?php include('admin_elements/copyright.php'); ?>
</div>
<script>
(function () {
    var runBtn = document.getElementById('runAllBtn');
    var progress = document.getElementById('sweepProgress');
    if (!runBtn) return;

    runBtn.addEventListener('click', async function () {
        var rows = Array.prototype.slice.call(document.querySelectorAll('tr[data-url]'));
        if (!rows.length) return;

        runBtn.disabled = true;
        progress.style.display = '';
        var ok = 0, fail = 0, total = rows.length;

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var badge = row.querySelector('.badge');
            var url = row.getAttribute('data-url') || '';
            progress.textContent = (i + 1) + ' / ' + total;

            if (!url) { fail++; continue; }

            if (badge) { badge.className = 'badge bg-primary'; badge.textContent = '...'; }
            try {
                var r = await fetch(url, { method: 'GET', credentials: 'include', cache: 'no-store', redirect: 'follow' });
                if (r.ok) {
                    if (badge) { badge.className = 'badge bg-success'; badge.textContent = 'OK'; }
                    ok++;
                } else {
                    if (badge) { badge.className = 'badge bg-danger'; badge.textContent = 'HTTP ' + r.status; }
                    fail++;
                }
            } catch (e) {
                if (badge) { badge.className = 'badge bg-danger'; badge.textContent = 'Fail'; }
                fail++;
            }
            await new Promise(function (r) { setTimeout(r, 200); });
        }

        runBtn.disabled = false;
        progress.textContent = 'Done: ' + ok + ' OK, ' + fail + ' failed';
    });
})();
</script>
<?php include('admin_elements/admin_footer.php'); ?>
