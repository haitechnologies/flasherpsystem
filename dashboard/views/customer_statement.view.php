<?php include('admin_elements/breadcrumb.php'); ?>

<style>
/* =========================================================================
   Customer Statement – Zoho-Inspired Premium Styles
   ========================================================================= */
.stmt-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    padding: 14px 20px;
    background: #fff;
    border-bottom: 1px solid #e8e8e8;
    border-radius: 6px 6px 0 0;
}
.stmt-toolbar .filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.stmt-toolbar .filter-group label {
    font-weight: 600;
    font-size: 13px;
    color: #333;
    margin: 0;
    white-space: nowrap;
}
.stmt-toolbar .filter-group select,
.stmt-toolbar .filter-group input[type="date"] {
    padding: 6px 12px;
    border: 1px solid #d5d5d5;
    border-radius: 4px;
    font-size: 13px;
    height: 34px;
    background: #fff;
}
.stmt-toolbar .action-group {
    display: flex;
    align-items: center;
    gap: 8px;
}
.stmt-toolbar .action-group .btn {
    font-size: 13px;
    padding: 6px 16px;
    border-radius: 4px;
}
.custom-date-inputs {
    display: none;
    align-items: center;
    gap: 8px;
}
.custom-date-inputs.active {
    display: flex;
}

/* Statement Document */
.stmt-document {
    max-width: 960px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 0 0 6px 6px;
    padding: 40px 48px;
}

/* Header Section */
.stmt-header {
    display: flex;
    justify-content: space-between;
    padding-bottom: 28px;
    border-bottom: 2px solid #1a73e8;
    margin-bottom: 32px;
}
.stmt-header .company-info h3 {
    font-size: 22px;
    font-weight: 700;
    color: #1a2b49;
    margin-bottom: 4px;
}
.stmt-header .company-info .meta {
    font-size: 12.5px;
    color: #666;
    line-height: 1.7;
}
.stmt-header .doc-title {
    text-align: right;
}
.stmt-header .doc-title h2 {
    font-size: 15px;
    font-weight: 700;
    color: #1a73e8;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    margin-bottom: 6px;
}
.stmt-header .doc-title .period {
    font-size: 12.5px;
    color: #555;
}

/* To / Summary */
.stmt-parties {
    display: flex;
    justify-content: space-between;
    margin-bottom: 36px;
    gap: 24px;
}
.stmt-parties .bill-to {
    flex: 1;
}
.stmt-parties .bill-to .label {
    font-size: 11px;
    text-transform: uppercase;
    color: #888;
    font-weight: 700;
    letter-spacing: 0.8px;
    margin-bottom: 8px;
}
.stmt-parties .bill-to h5 {
    font-size: 15px;
    font-weight: 700;
    color: #1a2b49;
    margin-bottom: 2px;
}
.stmt-parties .bill-to .address-text {
    font-size: 12.5px;
    color: #555;
    line-height: 1.7;
}

/* Account Summary Card */
.stmt-summary {
    flex: 0 0 320px;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    overflow: hidden;
}
.stmt-summary .summary-header {
    background: #f7f8fa;
    padding: 10px 16px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: #555;
    letter-spacing: 0.6px;
    border-bottom: 1px solid #e0e0e0;
}
.stmt-summary .summary-body {
    padding: 14px 16px;
}
.stmt-summary .summary-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 13px;
}
.stmt-summary .summary-row .lbl {
    color: #555;
}
.stmt-summary .summary-row .val {
    font-weight: 600;
    color: #333;
}
.stmt-summary .summary-total {
    display: flex;
    justify-content: space-between;
    padding: 10px 16px;
    background: #1a73e8;
    color: #fff;
    font-weight: 700;
    font-size: 14px;
}

/* Transaction Table */
.stmt-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.stmt-table thead th {
    background: #f7f8fa;
    padding: 10px 12px;
    text-align: left;
    font-weight: 700;
    color: #444;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    border-bottom: 2px solid #ddd;
}
.stmt-table thead th.text-right {
    text-align: right;
}
.stmt-table tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: background 0.15s ease;
}
.stmt-table tbody tr:hover {
    background: #fafbfd;
}
.stmt-table tbody td {
    padding: 10px 12px;
    color: #333;
    vertical-align: middle;
}
.stmt-table tbody td.text-right {
    text-align: right;
}
.stmt-table tbody td.text-muted-dash {
    text-align: right;
    color: #ccc;
}
.stmt-table .opening-row {
    background: #fafbfd;
    font-weight: 600;
}
.stmt-table .opening-row td {
    color: #555;
}
.stmt-table tfoot td {
    padding: 12px 12px;
    font-weight: 700;
    font-size: 13.5px;
    background: #f7f8fa;
    border-top: 2px solid #ddd;
}
.stmt-table tfoot td.text-right {
    text-align: right;
}

/* Type badges */
.type-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.type-badge.invoice { background: #e8f0fe; color: #1a73e8; }
.type-badge.payment { background: #e6f4ea; color: #188038; }
.type-badge.credit-note { background: #fce8e6; color: #c5221f; }

/* Empty state */
.stmt-empty {
    text-align: center;
    padding: 48px 20px;
    color: #888;
}
.stmt-empty i {
    font-size: 48px;
    color: #ccc;
    margin-bottom: 12px;
    display: block;
}

/* Print overrides */
@media print {
    body * { visibility: hidden; }
    #statementDocument, #statementDocument * { visibility: visible; }
    #statementDocument {
        position: absolute;
        left: 0; top: 0;
        width: 100%; max-width: 100%;
        margin: 0; padding: 24px;
        box-shadow: none !important;
        border: none !important;
    }
    .stmt-toolbar, .page-header, .sidebar, .sidebar-main,
    .navbar, footer, .breadcrumb-line, .btn, .content-wrapper > .page-header { display: none !important; }
    .type-badge { border: 1px solid #aaa; color: #000 !important; background: transparent !important; }
    .stmt-summary .summary-total { background: #333 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .stmt-table thead th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}

/* Responsive */
@media (max-width: 768px) {
    .stmt-document { padding: 20px 16px; }
    .stmt-header { flex-direction: column; gap: 16px; }
    .stmt-header .doc-title { text-align: left; }
    .stmt-parties { flex-direction: column; }
    .stmt-summary { flex: 1; }
    .stmt-toolbar { flex-direction: column; align-items: flex-start; }
}
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

    <!-- Toolbar: Filters + Actions -->
    <div class="stmt-toolbar" style="max-width: 960px; margin: 0 auto; border-left: 1px solid #e0e0e0; border-right: 1px solid #e0e0e0;">
        <form method="GET" id="dateFilterForm" class="filter-group">
            <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
            <label for="date_filter_select">Period:</label>
            <select name="date_filter" id="date_filter_select" onchange="handleDateFilter(this)">
                <option value="today" <?php echo $date_filter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="this_week" <?php echo $date_filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                <option value="this_month" <?php echo $date_filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                <option value="last_month" <?php echo $date_filter === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                <option value="this_quarter" <?php echo $date_filter === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                <option value="this_year" <?php echo $date_filter === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                <option value="last_year" <?php echo $date_filter === 'last_year' ? 'selected' : ''; ?>>Last Year</option>
                <option value="all_time" <?php echo $date_filter === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                <option value="custom" <?php echo $date_filter === 'custom' ? 'selected' : ''; ?>>Custom</option>
            </select>
            <div class="custom-date-inputs <?php echo $date_filter === 'custom' ? 'active' : ''; ?>" id="customDateInputs">
                <input type="date" name="custom_from" value="<?php echo e($custom_from ?: $start_date); ?>">
                <span style="color:#888;">to</span>
                <input type="date" name="custom_to" value="<?php echo e($custom_to ?: $end_date); ?>">
                <button type="submit" class="btn btn-sm btn-primary">Go</button>
            </div>
        </form>
        <div class="action-group">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
                <i class="ph-printer mr-1"></i> Print
            </button>
        </div>
    </div>

    <!-- Statement Document -->
    <div class="stmt-document" id="statementDocument">
        
        <!-- Document Header -->
        <div class="stmt-header">
            <div class="company-info">
                <h3><?php echo e($company_name ?: 'Company Name'); ?></h3>
                <div class="meta">
                    <?php if ($company_address): ?>
                        <?php echo e($company_address); ?><br>
                    <?php endif; ?>
                    <?php if ($company_phone): ?>
                        Phone: <?php echo e($company_phone); ?><br>
                    <?php endif; ?>
                    <?php if ($company_email): ?>
                        Email: <?php echo e($company_email); ?><br>
                    <?php endif; ?>
                    <?php if ($company_trn): ?>
                        TRN: <?php echo e($company_trn); ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="doc-title">
                <h2>Statement of Accounts</h2>
                <div class="period">
                    <?php if ($date_filter !== 'all_time'): ?>
                        <?php echo date('d M Y', strtotime($start_date)); ?> &mdash; <?php echo date('d M Y', strtotime($end_date)); ?>
                    <?php else: ?>
                        As of <?php echo date('d M Y'); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Customer Details + Account Summary -->
        <div class="stmt-parties">
            <div class="bill-to">
                <div class="label">Bill To</div>
                <h5><?php echo e($customerData->displayName); ?></h5>
                <?php if ($customerData->companyName && $customerData->companyName !== $customerData->displayName): ?>
                    <div class="address-text" style="font-weight:600;"><?php echo e($customerData->companyName); ?></div>
                <?php endif; ?>
                <div class="address-text">
                    <?php if ($customerData->address): ?>
                        <?php echo nl2br(e($customerData->address)); ?><br>
                    <?php endif; ?>
                    <?php if ($customerData->phone): ?>
                        <?php echo e($customerData->phone); ?><br>
                    <?php endif; ?>
                    <?php if ($customerData->email): ?>
                        <?php echo e($customerData->email); ?>
                    <?php endif; ?>
                    <?php if ($customerData->trn): ?>
                        <br>TRN: <?php echo e($customerData->trn); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stmt-summary">
                <div class="summary-header">Account Summary</div>
                <div class="summary-body">
                    <div class="summary-row">
                        <span class="lbl">Opening Balance</span>
                        <span class="val"><?php echo e($currency_code); ?> <?php echo number_format($opening_balance, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="lbl">Invoiced Amount</span>
                        <span class="val"><?php echo e($currency_code); ?> <?php echo number_format($total_invoiced, 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="lbl">Amount Received</span>
                        <span class="val" style="color: #188038;"><?php echo e($currency_code); ?> <?php echo number_format($total_received, 2); ?></span>
                    </div>
                    <?php if ($total_credits > 0): ?>
                    <div class="summary-row">
                        <span class="lbl">Credits Applied</span>
                        <span class="val" style="color: #c5221f;"><?php echo e($currency_code); ?> <?php echo number_format($total_credits, 2); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="summary-total">
                    <span>Balance Due</span>
                    <span><?php echo e($currency_code); ?> <?php echo number_format($balance_due, 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Transactions Table -->
        <?php if (!empty($transactions)): ?>
        <table class="stmt-table">
            <thead>
                <tr>
                    <th style="width:110px;">Date</th>
                    <th style="width:110px;">Type</th>
                    <th>Details</th>
                    <th style="width:80px;">Status</th>
                    <th class="text-right" style="width:130px;">Amount</th>
                    <th class="text-right" style="width:130px;">Payments</th>
                    <th class="text-right" style="width:130px;">Balance</th>
                </tr>
            </thead>
            <tbody>
                <!-- Opening Balance Row -->
                <tr class="opening-row">
                    <td><?php echo $date_filter !== 'all_time' ? date('d/m/Y', strtotime($start_date)) : ''; ?></td>
                    <td colspan="3"><strong>Opening Balance</strong></td>
                    <td class="text-muted-dash">&mdash;</td>
                    <td class="text-muted-dash">&mdash;</td>
                    <td class="text-right"><strong><?php echo e($currency_code); ?> <?php echo number_format($opening_balance, 2); ?></strong></td>
                </tr>

                <?php 
                $running_balance = $opening_balance;
                foreach ($transactions as $tx):
                    $amt = (float)$tx['amount'];
                    $isInvoice = ($tx['type'] === 'Invoice');
                    $isPayment = ($tx['type'] === 'Payment');
                    $isCreditNote = ($tx['type'] === 'Credit Note');
                    
                    if ($isInvoice) {
                        $running_balance += $amt;
                    } else {
                        $running_balance -= $amt;
                    }
                    
                    $badgeClass = $isInvoice ? 'invoice' : ($isPayment ? 'payment' : 'credit-note');
                ?>
                <tr>
                    <td><?php echo date('d/m/Y', strtotime($tx['trans_date'])); ?></td>
                    <td><span class="type-badge <?php echo $badgeClass; ?>"><?php echo e($tx['type']); ?></span></td>
                    <td><?php echo e($tx['ref_no']); ?></td>
                    <td><span style="font-size:11px;color:#888;"><?php echo e(ucwords(str_replace('_', ' ', $tx['status'] ?? ''))); ?></span></td>
                    <td class="<?php echo $isInvoice ? 'text-right' : 'text-muted-dash'; ?>"><?php echo $isInvoice ? e($currency_code) . ' ' . number_format($amt, 2) : '&mdash;'; ?></td>
                    <td class="<?php echo ($isPayment || $isCreditNote) ? 'text-right' : 'text-muted-dash'; ?>" style="<?php echo ($isPayment || $isCreditNote) ? 'color:#188038;' : ''; ?>"><?php echo ($isPayment || $isCreditNote) ? e($currency_code) . ' ' . number_format($amt, 2) : '&mdash;'; ?></td>
                    <td class="text-right" style="font-weight:600;"><?php echo e($currency_code); ?> <?php echo number_format($running_balance, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6" class="text-right text-uppercase">Balance Due</td>
                    <td class="text-right" style="color: #c5221f;"><?php echo e($currency_code); ?> <?php echo number_format($running_balance, 2); ?></td>
                </tr>
            </tfoot>
        </table>
        <?php else: ?>
        <div class="stmt-empty">
            <i class="ph-clipboard-text"></i>
            <h5>No Transactions</h5>
            <p>No transactions found for the selected period.</p>
        </div>
        <?php endif; ?>
    </div>

        </div>

        <?php include('admin_elements/copyright.php'); ?>
    </div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>

<script>
function handleDateFilter(select) {
    var customInputs = document.getElementById('customDateInputs');
    if (select.value === 'custom') {
        customInputs.classList.add('active');
    } else {
        customInputs.classList.remove('active');
        document.getElementById('dateFilterForm').submit();
    }
}
</script>
