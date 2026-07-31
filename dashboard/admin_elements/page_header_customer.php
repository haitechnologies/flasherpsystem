<?php

use App\Core\DB;
use App\Security\Roles;
/* -------------------------------------------------------------------------- */

$customer_id = 0;
if (isset($_REQUEST['customer_id'])) {
    $customer_id = (int)$_REQUEST['customer_id'];
}
if (isset($_POST['customer_id'])) {
    $customer_id = (int)$_POST['customer_id'];
}

if ($customer_id <= 0) {
    header('Location:listing_customers.php');
    exit;
}


$customer_type  = getTableAttr('customer_type', DB::CUSTOMERS, $customer_id);
$display_name   = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
$approved       = getTableAttr('approved', DB::CUSTOMERS, $customer_id);
$approved_at    = getTableAttr('approved_at', DB::CUSTOMERS, $customer_id);
$publish        = getTableAttr('is_active', DB::CUSTOMERS, $customer_id);
$created_at     = getTableAttr('created_at', DB::CUSTOMERS, $customer_id);
$created_by     = getTableAttr('created_by', DB::CUSTOMERS, $customer_id);

/*
    |--------------------------------------------------------------------------
    |--------------------------------------------------------------------------
    */
?>

<div class="page-header page-header-light shadow carriers-page-header">
    <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 align-items-center">
        <div class="my-1">
            <h1 class="ms-2"> <a href="customer_overview.php?customer_id=<?php echo $customer_id;?>" class="text-dark"><?php echo $display_name; ?></a></h1>
            <div class="ms-2">
                <span class="text-muted small"><?php if ($publish == '1') { ?>Active <?php } else { ?> InActive <?php } ?></span>
                <?php if ($approved == 1) { ?>
                    <span class="badge bg-success bg-opacity-10 text-success small fw-normal ms-1"><i class="ph-check-circle me-1"></i> Approved</span>
                <?php } elseif ($approved == 0) { ?>
                    <span class="badge bg-warning bg-opacity-10 text-warning small fw-normal ms-1"><i class="ph-clock-countdown me-1"></i> Awaiting Approval</span>
                <?php } ?>
            </div>
        </div>
        <div class="my-1 ms-auto d-flex align-items-center gap-2 flex-wrap">
            <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            <?php $can_approve = Roles::hasFullAccess($session_role_id) || Roles::isAccounts($session_role_id); ?>
            <?php if (granted_('edit', 'customers') && $can_approve) { ?>
                <?php if ($approved == 0) { ?>
                    <a href="customer_overview.php?action=approved&customer_id=<?php echo $customer_id; ?>&approve=1" class="btn btn-success btn-sm"><i class="ph-check-circle me-1"></i> Approve</a>
                <?php } elseif ($approved == 1) { ?>
                    <a href="customer_overview.php?action=disapproved&customer_id=<?php echo $customer_id; ?>&approve=0" class="btn btn-outline-danger btn-sm"><i class="ph-x-circle me-1"></i> Dis-Approve</a>
                <?php } ?>
            <?php } else { ?>
                <?php // Edit permission without approval right — only show notice ?>
            <?php } ?>
            <?php if (isset($module_id) && granted('edit', $module_id)) { ?>
                <button type="button" onclick="window.location.href='<?php echo $module; ?>.php?action=edit_customers&id=<?php echo $customer_id; ?>';" class="btn btn-light btn-sm">Edit</button>
            <?php } ?>
            <?php $transactions_disabled = ($approved != 1); ?>
            <div class="dropdown">
                <button type="button" class="btn btn-primary btn-sm dropdown-toggle<?php echo $transactions_disabled ? ' disabled' : ''; ?>" data-bs-toggle="dropdown" <?php echo $transactions_disabled ? 'aria-disabled="true"' : ''; ?> <?php echo $transactions_disabled ? 'tabindex="-1"' : ''; ?>>
                    New Transaction
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <div class="dropdown-header text-uppercase fs-sm lh-sm">SALES</div>
                    <a class="dropdown-item<?php echo $transactions_disabled ? ' disabled' : ''; ?>" href="<?php echo $transactions_disabled ? '#' : 'invoices.php?customer_id=' . $customer_id; ?>" <?php echo $transactions_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                        Invoice
                    </a>
                    <a class="dropdown-item<?php echo $transactions_disabled ? ' disabled' : ''; ?>" href="<?php echo $transactions_disabled ? '#' : 'expenses.php?customer_id=' . $customer_id; ?>" <?php echo $transactions_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                        Expense
                    </a>
                </div>
            </div>
            <div class="dropdown">
                <button type="button" class="btn btn-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">More</button>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="customer_overview.php?action=clone_customers&customer_id=<?php echo $customer_id; ?>">Clone</a>
                    <?php
                    $customer_active_status = getTableAttr('is_active', DB::CUSTOMERS, $customer_id);
                    if ($customer_active_status == 1) {
                    ?>
                        <a class="dropdown-item" href="customer_overview.php?action=mark_as_inactive&customer_id=<?php echo $customer_id; ?>">Mark as Inactive</a>
                    <?php } else { ?>
                        <a class="dropdown-item" href="customer_overview.php?action=mark_as_active&customer_id=<?php echo $customer_id; ?>">Mark as Active</a>
                    <?php } ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="listing_customers.php?action=delete_customers&id=<?php echo $customer_id; ?>">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <ul class="nav nav-tabs nav-tabs-underline mb-0" role="tablist">
            <li class="nav-item">
                <a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>" class="nav-link <?php if ($current_page == "customer_overview.php" || $current_page == "customer_billing_addresses.php" || $current_page == "customer_shipping_addresses.php") { ?> active fw-semibold<?php } ?>">Overview</a>
            </li>
            <li class="nav-item">
                <a href="customer_comments.php?customer_id=<?php echo $customer_id; ?>" class="nav-link <?php if ($current_page == "customer_comments.php") { ?> active fw-semibold<?php } ?>">Comments</a>
            </li>
            <li class="nav-item">
                <a href="<?php echo ($approved != 1) ? '#' : 'customer_transactions.php?customer_id=' . $customer_id; ?>" class="nav-link <?php if ($current_page == "customer_transactions.php") { ?> active fw-semibold<?php } ?><?php echo ($approved != 1) ? ' disabled' : ''; ?>" <?php echo ($approved != 1) ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Transactions</a>
            </li>
            <li class="nav-item">
                <a href="customer_mails.php?customer_id=<?php echo $customer_id; ?>" class="nav-link <?php if ($current_page == "customer_mails.php") { ?> active fw-semibold<?php } ?>">Mails</a>
            </li>
            <li class="nav-item">
                <a href="customer_statement.php?customer_id=<?php echo $customer_id; ?>" class="nav-link <?php if ($current_page == "customer_statement.php") { ?> active fw-semibold<?php } ?>">Statement</a>
            </li>
        </ul>
    </div>
</div>
