<?php
/**
 * Vendor Navbar Component
 *
 * Navigation tabs for vendor-related pages
 */

// Get vendor ID from request
$vendor_id = $_GET['vendor_id'] ?? $_REQUEST['vendor_id'] ?? $_SESSION['vendor_id'] ?? 0;

if ($vendor_id > 0):
?>
<div class="card mb-3">
    <div class="card-body p-0">
        <ul class="nav nav-tabs nav-tabs-bottom border-bottom-0">
            <li class="nav-item">
                <a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>"
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'vendor_overview.php') ? 'active' : ''; ?>">
                    <i class="ph-user me-2"></i>Overview
                </a>
            </li>
            <li class="nav-item">
                <a href="vendor_contacts.php?vendor_id=<?php echo $vendor_id; ?>"
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'vendor_contacts.php') ? 'active' : ''; ?>">
                    <i class="ph-address-book me-2"></i>Contacts
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_purchases.php?dt_vendor_id=<?php echo $vendor_id; ?>"
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_purchases.php') ? 'active' : ''; ?>">
                    <i class="ph-file-text me-2"></i>Purchases
                </a>
            </li>
            <li class="nav-item">
                <a href="listing_payments_made.php?vendor_id=<?php echo $vendor_id; ?>"
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'listing_payments_made.php') ? 'active' : ''; ?>">
                    <i class="ph-currency-dollar me-2"></i>Payments
                </a>
            </li>
            <li class="nav-item">
                <a href="vendor_logs.php?vendor_id=<?php echo $vendor_id; ?>"
                   class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'vendor_logs.php') ? 'active' : ''; ?>">
                    <i class="ph-clock-counter-clockwise me-2"></i>Activity Log
                </a>
            </li>
        </ul>
    </div>
</div>
<?php
endif;
?>
