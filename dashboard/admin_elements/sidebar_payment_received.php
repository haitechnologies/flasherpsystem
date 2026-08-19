<?php
use App\Core\DB;
use App\Core\Session;
if (!empty($_GET["payments_received_ordering"] ?? $_REQUEST["payments_received_ordering"] ?? "")) {
    $_SESSION["payments_received_ordering"] = e_s__($_GET["payments_received_ordering"] ?? $_REQUEST["payments_received_ordering"]);
}
if (!isset($_SESSION["payments_received_ordering"])) $_SESSION["payments_received_ordering"] = "all";
$payments_received_ordering = $_SESSION["payments_received_ordering"];
$current_id = (int)($_GET["payment_received_id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="payments_received_ordering" id="payments_received_ordering" onchange="window.location.href='payment_received_overview.php?payment_received_id=<?php echo $current_id; ?>&payments_received_ordering='+this.value;">
                <option value="all" <?php if ($payments_received_ordering === "all") echo "selected"; ?>>All</option>
<?php
$statusLabels = ['draft' => 'Draft', 'partial' => 'Partial', 'paid' => 'Paid', 'void' => 'Void', 'refund' => 'Refund'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $payments_received_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='payments_received.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $payments_received_ordering !== "all" ? " AND t.payment_status = '" . $payments_received_ordering . "'" : "";
$org_id = (int) Session::orgId();
$r = $mysqli->query("SELECT t.id, t.payment_status, t.payment_date, t.total_amount_received, t.customer_id FROM `" . DB::PAYMENTS_RECEIVED . "` t WHERE t.id > 0 AND t.organization_id = {$org_id} $where ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::PAYMENTS_RECEIVED . "` t WHERE t.id > 0 AND t.organization_id = {$org_id} $where")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::CUSTOMERS, $row["customer_id"]);
    $date = ddm_($row["payment_date"]);
    $amt = number_format($row["total_amount_received"], 2);
    $status_class = match($row["payment_status"]) {
        'paid' => 'text-success',
        'partial' => 'text-warning',
        'void', 'refund' => 'text-danger',
        'draft' => 'text-secondary',
        default => 'text-info'
    };
    $st = '<span class="' . $status_class . '">' . strtoupper($row["payment_status"]) . '</span>';
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="payment_received_overview.php?payment_received_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo $date . " - " . $st; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_payments_received.php">View All (<?php echo $total; ?>)</a></div>
</div></div>