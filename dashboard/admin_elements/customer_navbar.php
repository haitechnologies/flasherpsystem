<?php
/**
 * Customer Navbar Component
 * 
 * Navigation tabs for customer-related pages
 * Auto-generated stub to prevent include errors
 */

// Get customer ID from session or request
$customer_id = $_SESSION['customer_id'] ?? $_GET['customer_id'] ?? $_REQUEST['customer_id'] ?? 0;

if ($customer_id > 0):
?>
<div class="card mb-3">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-tabs-bottom border-bottom-0">
            <li class="nav-item">
                <a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'customer_overview.php') ? 'active' : ''; ?>">
                    <i class="ph-user me-2"></i>Overview
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_customer_contacts.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_customer_contacts.php') ? 'active' : ''; ?>">
                    <i class="ph-address-book me-2"></i>Contacts
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_customer_invoices.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_customer_invoices.php') ? 'active' : ''; ?>">
                    <i class="ph-file-text me-2"></i>Invoices
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_customer_sale_orders.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_customer_sale_orders.php') ? 'active' : ''; ?>">
                    <i class="ph-shopping-cart me-2"></i>Sale Orders
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_customer_payments.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_customer_payments.php') ? 'active' : ''; ?>">
                    <i class="ph-currency-dollar me-2"></i>Payments
                </a>
            </li>

            <li class="nav-item">
                <a href="customer_logs.php?customer_id=<?php echo $customer_id; ?>" 
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'customer_logs.php') ? 'active' : ''; ?>">
                    <i class="ph-clock-counter-clockwise me-2"></i>Activity Log
                </a>
            </li>
        </ul>
    </div>
</div>
<?php
endif;
?>
