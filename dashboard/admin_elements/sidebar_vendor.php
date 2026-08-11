<?php

use App\Core\DB;

// Consolidate session handling
if (!empty($_GET['vendors_ordering'] ?? $_REQUEST['vendors_ordering'] ?? '')) {
    $_SESSION['vendors_ordering'] = e_s__($_GET['vendors_ordering'] ?? $_REQUEST['vendors_ordering']);
}

// Set default if not exists
$_SESSION['vendors_ordering'] = $_SESSION['vendors_ordering'] ?? 'all';
$vendors_ordering = $_SESSION['vendors_ordering'];

// Build search query with array mapping
$status_map = [
    'active'    => "v.is_active=1",
    'inactive'  => "v.is_active=0",
    'approved'  => "v.approved=1",
    'pending'   => "v.approved=0",
    'overdue'   => "v.is_active=1"
];

$search_query = isset($status_map[$vendors_ordering]) ? " AND " . $status_map[$vendors_ordering] : " AND v.id >= 1";

// Get current selected vendor ID
$current_id = (int)($_GET['vendor_id'] ?? 0);

?>
<div class="sidebar-content">

    <!-- Header -->
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">

        <div>

            <select class="form-select border-0 fw-bold" name="vendors_ordering" id="vendors_ordering" onchange="window.location.href='vendor_overview.php?vendor_id=<?php echo $current_id; ?>&vendors_ordering=' + this.value;">
                <?php
                $ordering_options = [
                    'all'       => 'All Vendors',
                    'active'    => 'Active Vendors',
                    'approved'  => 'Approved Vendors',
                    'pending'   => 'Awaiting Approval',
                    'inactive'  => 'Inactive Vendors'
                ];

                foreach ($ordering_options as $value => $label) {
                    $selected = ($vendors_ordering === $value) ? 'selected' : '';
                    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                }
                ?>
            </select>
        </div>

        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='vendors.php';">
                <i class="ph-plus"></i>
            </button>
        </div>

    </div>
    <!-- /header -->


    <!-- Sub navigation -->
    <div class="sidebar-section pt-2">

        <div class="table-responsive">
            <table class="table table-hover">
                <tbody>
                    <?php
                    $result = $mysqli->query(
                        "SELECT v.id, v.company_name, v.display_name, v.is_active
                         FROM `" . DB::VENDORS . "` v
                         WHERE v.id > 0 {$search_query}
                         ORDER BY v.id DESC
                         LIMIT 25"
                    );

                    $result_count = $mysqli->query(
                        "SELECT COUNT(DISTINCT v.id) as total FROM `" . DB::VENDORS . "` v
                         WHERE v.id > 0 {$search_query}"
                    );
                    $count_row = $result_count->fetch_array();
                    $total_vendors = $count_row['total'] ?? 0;

                    while ($row = $result->fetch_array()) {
                        $isSelected = ($row['id'] == $current_id) ? 'table-primary shadow-sm' : '';
                        $name = $row['company_name'] ?: $row['display_name'];

                        // Calculate outstanding payables (opening + purchases - payments - debit notes)
                        $vendor_payables = 0;

                        $ob_query = $mysqli->query("SELECT COALESCE(opening_balance, 0) as ob FROM `" . DB::VENDORS . "` WHERE id = {$row['id']}");
                        $opening_balance = ($ob_query && $ob_row = $ob_query->fetch_assoc()) ? (float)($ob_row['ob'] ?? 0) : 0.0;
                        $vendor_payables += $opening_balance;

                        $pay_query = $mysqli->query("SELECT COALESCE(SUM(grand_total),0) as total FROM `" . DB::PURCHASES . "` WHERE vendor_id = {$row['id']} AND purchase_status NOT IN ('draft', 'declined', 'expired')");
                        if ($pay_query && $pay_row = $pay_query->fetch_assoc()) {
                            $vendor_payables += (float)($pay_row['total'] ?? 0);
                        }
                        $pay_paid_query = $mysqli->query("SELECT COALESCE(SUM(total_amount_paid),0) as total FROM `" . DB::PAYMENTS_MADE . "` WHERE vendor_id = {$row['id']} AND payment_status != 'void'");
                        if ($pay_paid_query && $pay_paid_row = $pay_paid_query->fetch_assoc()) {
                            $vendor_payables -= (float)($pay_paid_row['total'] ?? 0);
                        }

                        $dn_query = $mysqli->query("SELECT COALESCE(SUM(grand_total),0) as total FROM `" . DB::DEBIT_NOTES . "` WHERE vendor_id = {$row['id']} AND debit_note_status NOT IN ('draft', 'void')");
                        if ($dn_query && $dn_row = $dn_query->fetch_assoc()) {
                            $vendor_payables -= (float)($dn_row['total'] ?? 0);
                        }

                        $vendor_payables = max($vendor_payables, 0);

                        $formatted_amount = BASE_CURRENCY['code'] . dec_($vendor_payables);

                        $purchase_count_query = $mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::PURCHASES . "` WHERE vendor_id = {$row['id']}");
                        $purchase_count = (int)(($purchase_count_query && $purchase_count_row = $purchase_count_query->fetch_assoc()) ? ($purchase_count_row['cnt'] ?? 0) : 0);
                        if ($purchase_count === 0) {
                            $vend_status_class = 'text-muted';
                            $vend_status_label = 'No Purchases';
                        } elseif ($vendor_payables > 0) {
                            $vend_status_class = 'text-warning';
                            $vend_status_label = 'OWED';
                        } else {
                            $vend_status_class = 'text-success';
                            $vend_status_label = 'PAID';
                        }
                        $vend_status_html = '<span class="' . $vend_status_class . ' small fw-semibold">' . $vend_status_label . '</span>';
                    ?>
                        <tr id="<?php echo $row['id']; ?>" class="<?php echo $isSelected; ?>">
                            <td>
                                <a href="vendor_overview.php?vendor_id=<?php echo $row['id']; ?>" class="text-black text-decoration-none d-block">
                                    <div class="row">
                                        <div class="col-lg-10">
                                            <div><?php echo $name; ?></div>
                                            <div class="text-muted small">
                                                <?php echo $formatted_amount; ?>
                                            </div>
                                            <div class="small mt-1">
                                                <?php echo $vend_status_html; ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mb-3">
            <a href="listing_vendors.php?dt_ordering_type=<?php echo $vendors_ordering; ?>">View All Vendors (<?php echo $total_vendors; ?>)</a>
        </div>

    </div>
    <!-- /sub navigation -->

</div>
