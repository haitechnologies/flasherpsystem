<?php
use App\Core\DB;
if (!empty($_GET["payments_made_ordering"] ?? $_REQUEST["payments_made_ordering"] ?? "")) {
    $_SESSION["payments_made_ordering"] = e_s__($_GET["payments_made_ordering"] ?? $_REQUEST["payments_made_ordering"]);
}
if (!isset($_SESSION["payments_made_ordering"])) $_SESSION["payments_made_ordering"] = "all";
$payments_made_ordering = $_SESSION["payments_made_ordering"];
$current_id = (int)($_GET["payment_id"] ?? $_GET["id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="payments_made_ordering" id="payments_made_ordering" onchange="window.location.href='payments_made_overview.php?payment_id=<?php echo $current_id; ?>&payments_made_ordering='+this.value;">
                <option value="all" <?php if ($payments_made_ordering === "all") echo "selected"; ?>>All Made Payments</option>
<?php
$statusLabels = ['paid' => 'Paid', 'draft' => 'Draft', 'void' => 'Void'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $payments_made_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='payments_made.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $payments_made_ordering !== "all" ? " AND t.payment_status = '" . $payments_made_ordering . "'" : "";
$r = $mysqli->query("SELECT t.id, t.payment_status, t.payment_date, t.total_amount_paid, t.payment_method, t.vendor_id FROM `" . DB::PAYMENTS_MADE . "` t WHERE t.id > 0 AND t.vendor_id != '' $where ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::PAYMENTS_MADE . "` t WHERE t.id > 0 AND t.vendor_id != '' $where")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::VENDORS, $row["vendor_id"]);
    $date = ddm_($row["payment_date"]);
    $amt = number_format($row["total_amount_paid"], 2);
    $method = getTableAttr("payment_method", DB::PAYMENT_METHODS, $row["payment_method"]);
    $status_class = match($row["payment_status"]) {
        'paid' => 'text-success',
        'void' => 'text-danger',
        'draft' => 'text-secondary',
        default => 'text-info'
    };
    $st = '<span class="' . $status_class . '">' . strtoupper($row["payment_status"]) . '</span>';
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="payments_made_overview.php?payment_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo "PM_" . $row["id"] . " - " . $date; ?></div>
            <div class="small text-muted"><?php echo $st; ?> - <?php echo $method; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_payments_made.php?dt_ordering_type=<?php echo $payments_made_ordering; ?>">View All Made Payments (<?php echo $total; ?>)</a></div>
</div></div>
