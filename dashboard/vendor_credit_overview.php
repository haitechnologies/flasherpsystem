<?php

include('admin_elements/admin_header.php');

$module         = 'vendor_credits';
$module_caption = 'Vendor Credit';
$tbl_name       = $tbl_prefix . $module;

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
*/
include('admin_elements/permissions.php');

/*
|--------------------------------------------------------------------------
| GET VENDOR CREDIT ID
|--------------------------------------------------------------------------
*/
if (isset($_REQUEST['vendor_credit_id']) && !empty($_REQUEST['vendor_credit_id'])) {
    $vendor_credit_id = e_s__($_REQUEST['vendor_credit_id']);
    $vendor_credit_id = intval($vendor_credit_id);
} else if (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $vendor_credit_id = e_s__($_REQUEST['id']);
    $vendor_credit_id = intval($vendor_credit_id);
} else {
    $vendor_credit_id = 0;
}

// Validate vendor credit exists
$vendor_credit = $mysqli->query("SELECT id FROM `$tbl_name` WHERE id=$vendor_credit_id")->fetch_assoc();
if (!$vendor_credit) {
    header("Location:listing_vendors.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| PUBLISH ACTION
|--------------------------------------------------------------------------
*/
if (isset($_POST['action']) && $_POST['action'] == 'publish_vendor_credit' && !empty($vendor_credit_id)) {
    
    $publish_status = isset($_POST['publish']) ? 1 : 0;
    
    $update_sql = "UPDATE `$tbl_name` SET is_active=$publish_status, updated_at=NOW() WHERE id=$vendor_credit_id";
    
    if ($mysqli->query($update_sql)) {
        $success_message = "Vendor Credit publish status updated.";
    } else {
        $error_message = "Error updating publish status: " . $mysqli->error;
    }
}

/*
|--------------------------------------------------------------------------
| GET VENDOR CREDIT DATA
|--------------------------------------------------------------------------
*/
$tbl_vendors = defined('tbl_vendors') ? tbl_vendors : 'fls_vendors';
$tbl_users = defined('tbl_users') ? tbl_users : 'fls_users';
$tbl_vendor_credit_items = defined('tbl_vendor_credit_items') ? tbl_vendor_credit_items : 'fls_vendor_credit_items';

$query = "SELECT 
            vc.id,
            vc.vendor_credit_no,
            vc.vendor_id,
            vc.purchase_id,
            vc.warehouse_id,
            vc.reference_no,
            vc.vendor_credit_date,
            vc.vendor_credit_status,
            vc.grand_subtotal,
            vc.grand_discount_type,
            vc.grand_discount_type_value,
            vc.grand_discount_amount,
            vc.grand_after_discount,
            vc.grand_tax,
            vc.grand_total,
            vc.vendor_notes,
            vc.terms_and_conditions,
            vc.purchase_person,
            vc.is_active,
            vc.created_at,
            vc.created_by,
            v.display_name as vendor_name,
            v.id as vendor_id_check,
            u.user_email
        FROM `$tbl_name` vc
        LEFT JOIN `" . $tbl_vendors . "` v ON vc.vendor_id = v.id
        LEFT JOIN `" . $tbl_users . "` u ON vc.created_by = u.id
        WHERE vc.id=$vendor_credit_id";

$vendor_credit_data = $mysqli->query($query)->fetch_assoc();

/*
|--------------------------------------------------------------------------
| GET VENDOR CREDIT ITEMS
|--------------------------------------------------------------------------
*/
$items_query = "SELECT * FROM `" . $tbl_vendor_credit_items . "` WHERE vendor_credit_id=$vendor_credit_id";
$items_result = $mysqli->query($items_query);

?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <?php include('admin_elements/sidebar_vendor.php'); ?>
</div>

<div class="content-wrapper">
    <div class="content-inner">
        <?php include('admin_elements/page_header_vendor.php'); ?>

        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php if (isset($success_message) && !empty($success_message)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php echo $success_message; ?>
                </div>
            <?php } ?>

            <?php if (isset($error_message) && !empty($error_message)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <?php echo $error_message; ?>
                </div>
            <?php } ?>

            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Vendor Credit: <?php echo $vendor_credit_data['vendor_credit_no']; ?></h6>
                    <div class="float-end">
                        <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $vendor_credit_data['vendor_credit_status'])); ?></span>
                        <a href="listing_vendors.php" class="btn btn-secondary btn-sm ms-2">Back</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <strong>Vendor Credit Date:</strong><br>
                            <?php echo ddm_(($vendor_credit_data['vendor_credit_date'] ?? '') ?: null); ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Vendor:</strong><br>
                            <?php echo $vendor_credit_data['vendor_name']; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Reference #:</strong><br>
                            <?php echo $vendor_credit_data['reference_no']; ?>
                        </div>
                        <div class="col-md-3">
                            <strong>Status:</strong><br>
                            <?php 
                            $status_badge = '';
                            switch($vendor_credit_data['vendor_credit_status']) {
                                case 'draft':
                                    $status_badge = '<span class="badge bg-secondary">Draft</span>';
                                    break;
                                case 'issued':
                                    $status_badge = '<span class="badge bg-primary">Issued</span>';
                                    break;
                                case 'partially_used':
                                    $status_badge = '<span class="badge bg-warning">Partially Used</span>';
                                    break;
                                case 'fully_used':
                                    $status_badge = '<span class="badge bg-success">Fully Used</span>';
                                    break;
                            }
                            echo $status_badge;
                            ?>
                        </div>
                    </div>

                    <hr>

                    <!-- Line Items -->
                    <h6 class="mb-3">Line Items</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Service</th>
                                    <th>Description</th>
                                    <th class="text-center">Qty</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Sub Total</th>
                                    <th class="text-end">Tax %</th>
                                    <th class="text-end">Tax Amount</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                while ($item = $items_result->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td>" . $item['service'] . "</td>";
                                    echo "<td>" . $item['description'] . "</td>";
                                    echo "<td class='text-center'>" . $item['qty'] . "</td>";
                                    echo "<td class='text-end'>" . number_format($item['rate'], 2) . "</td>";
                                    echo "<td class='text-end'>" . number_format($item['sub_total'], 2) . "</td>";
                                    echo "<td class='text-end'>" . number_format($item['tax'], 2) . "%</td>";
                                    echo "<td class='text-end'>" . number_format($item['tax_amount'], 2) . "</td>";
                                    echo "<td class='text-end'>" . number_format($item['total'], 2) . "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="row justify-content-end mb-4">
                        <div class="col-md-4">
                            <div class="table-responsive">
<table class="table table-bordered">
                                <tr>
                                    <td><strong>Subtotal:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($vendor_credit_data['grand_subtotal'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Discount:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($vendor_credit_data['grand_discount_amount'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Tax:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($vendor_credit_data['grand_tax'], 2); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Total:</strong></td>
                                    <td class="text-end"><strong><?php echo number_format($vendor_credit_data['grand_total'], 2); ?></strong></td>
                                </tr>
                            </table>
</div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <?php if (!empty($vendor_credit_data['vendor_notes']) || !empty($vendor_credit_data['terms_and_conditions'])) { ?>
                    <hr>
                    <div class="row">
                        <?php if (!empty($vendor_credit_data['vendor_notes'])) { ?>
                        <div class="col-md-6">
                            <h6>Vendor Notes:</h6>
                            <p><?php echo nl2br($vendor_credit_data['vendor_notes']); ?></p>
                        </div>
                        <?php } ?>
                        <?php if (!empty($vendor_credit_data['terms_and_conditions'])) { ?>
                        <div class="col-md-6">
                            <h6>Terms & Conditions:</h6>
                            <p><?php echo nl2br($vendor_credit_data['terms_and_conditions']); ?></p>
                        </div>
                        <?php } ?>
                    </div>
                    <?php } ?>

                    <!-- Publish Status -->
                    <hr>
                    <form method="post" class="row align-items-end">
                        <input type="hidden" name="action" value="publish_vendor_credit">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="publish" id="publish" <?php echo ($vendor_credit_data['is_active'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="publish">
                                    Publish Vendor Credit
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                        </div>
                    </form>

                    <!-- Meta Information -->
                    <hr>
                    <div class="text-muted small">
                        <p>
                            Created: <?php echo date('d-m-Y H:i', strtotime($vendor_credit_data['created_at'])); ?> 
                            by <?php echo $vendor_credit_data['user_email']; ?>
                        </p>
                    </div>

                </div>
            </div>

        </div>
    </div>
    <?php include('admin_elements/copyright.php'); ?>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
