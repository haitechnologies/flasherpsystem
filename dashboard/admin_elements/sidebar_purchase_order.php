<?php
use App\Core\DB;
if (!empty($_GET["purchase_orders_ordering"] ?? $_REQUEST["purchase_orders_ordering"] ?? "")) {
    $_SESSION["purchase_orders_ordering"] = e_s__($_GET["purchase_orders_ordering"] ?? $_REQUEST["purchase_orders_ordering"]);
}
if (!isset($_SESSION["purchase_orders_ordering"])) $_SESSION["purchase_orders_ordering"] = "all";
$purchase_orders_ordering = $_SESSION["purchase_orders_ordering"];
$current_id = (int)($_GET["purchase_order_id"] ?? $_GET["id"] ?? $_REQUEST["purchase_order_id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="purchase_orders_ordering" id="purchase_orders_ordering" onchange="window.location.href='purchase_order_overview.php?purchase_order_id=<?php echo $current_id; ?>&purchase_orders_ordering='+this.value;">
                <option value="all" <?php if ($purchase_orders_ordering === "all") echo "selected"; ?>>All Purchase Orders</option>
<?php
$statusLabels = ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'purchased' => 'Purchased', 'declined' => 'Declined', 'expired' => 'Expired'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $purchase_orders_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='purchase_orders.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $purchase_orders_ordering !== "all" ? " AND t.purchase_order_status = '" . $purchase_orders_ordering . "'" : "";
$r = $mysqli->query("SELECT t.id, t.purchase_order_status, t.purchase_order_date, t.grand_total, t.vendor_id FROM `" . DB::PURCHASE_ORDERS . "` t WHERE t.id > 0 AND t.vendor_id != '' $where ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::PURCHASE_ORDERS . "` t WHERE t.id > 0 AND t.vendor_id != '' $where")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::VENDORS, $row["vendor_id"]);
    $date = ddm_($row["purchase_order_date"]);
    $amt = number_format($row["grand_total"], 2);
    $status_class = match($row["purchase_order_status"]) {
        'accepted', 'purchased' => 'text-success',
        'declined', 'expired' => 'text-danger',
        'draft' => 'text-secondary',
        'sent' => 'text-info',
        default => 'text-info'
    };
    $st = '<span class="' . $status_class . '">' . strtoupper($row["purchase_order_status"]) . '</span>';
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="purchase_order_overview.php?purchase_order_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo $date . " - " . $st; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_purchase_orders.php">View All (<?php echo $total; ?>)</a></div>
</div></div>