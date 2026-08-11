<?php
use App\Core\DB;
if (!empty($_GET["sale_orders_ordering"] ?? $_REQUEST["sale_orders_ordering"] ?? "")) {
    $_SESSION["sale_orders_ordering"] = e_s__($_GET["sale_orders_ordering"] ?? $_REQUEST["sale_orders_ordering"]);
}
if (!isset($_SESSION["sale_orders_ordering"])) $_SESSION["sale_orders_ordering"] = "all";
$sale_orders_ordering = $_SESSION["sale_orders_ordering"];
$current_id = (int)($_GET["sale_order_id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="sale_orders_ordering" id="sale_orders_ordering" onchange="window.location.href='sale_order_overview.php?sale_order_id=<?php echo $sale_order_id; ?>&sale_orders_ordering='+this.value;">
                <option value="all" <?php if ($sale_orders_ordering === "all") echo "selected"; ?>>All</option>
<?php
$statusLabels = ['draft' => 'Draft', 'sent' => 'Sent', 'approved' => 'Approved', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $sale_orders_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='sale_orders.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $sale_orders_ordering !== "all" ? " AND t.sale_order_status = '" . $sale_orders_ordering . "'" : "";
$r = $mysqli->query("SELECT t.id, t.sale_order_status, t.sale_order_date, t.grand_total, t.customer_id FROM `" . DB::SALE_ORDERS . "` t WHERE t.id > 0 AND t.customer_id != '' $where ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::SALE_ORDERS . "` t WHERE t.id > 0 AND t.customer_id != '' $where")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::CUSTOMERS, $row["customer_id"]);
    $date = ddm_($row["sale_order_date"]);
    $amt = number_format($row["grand_total"], 2);
    $status_class = match($row["sale_order_status"]) {
        'approved', 'delivered', 'shipped' => 'text-success',
        'cancelled' => 'text-danger',
        'draft' => 'text-secondary',
        'sent' => 'text-info',
        default => 'text-info'
    };
    $st = '<span class="' . $status_class . '">' . strtoupper($row["sale_order_status"]) . '</span>';
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="sale_order_overview.php?sale_order_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo $date . " - " . $st; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_sale_orders.php">View All (<?php echo $total; ?>)</a></div>
</div></div>