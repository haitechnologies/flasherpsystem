<?php
use App\Core\DB;
if (!empty($_GET["recurring_invoices_ordering"] ?? $_REQUEST["recurring_invoices_ordering"] ?? "")) {
    $_SESSION["recurring_invoices_ordering"] = e_s__($_GET["recurring_invoices_ordering"] ?? $_REQUEST["recurring_invoices_ordering"]);
}
if (!isset($_SESSION["recurring_invoices_ordering"])) $_SESSION["recurring_invoices_ordering"] = "all";
$recurring_invoices_ordering = $_SESSION["recurring_invoices_ordering"];
$current_id = (int)($_GET["recurring_invoice_id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="recurring_invoices_ordering" id="recurring_invoices_ordering" onchange="window.location.href='recurring_invoice_overview.php?recurring_invoice_id=<?php echo $current_id; ?>&recurring_invoices_ordering='+this.value;">
                <option value="all" <?php if ($recurring_invoices_ordering === "all") echo "selected"; ?>>All</option>
<?php
$statusLabels = ['draft' => 'Draft', 'locked' => 'Locked', 'pending' => 'Pending', 'approved' => 'Approved', 'sent' => 'Sent', 'accepted' => 'Accepted', 'declined' => 'Declined', 'expired' => 'Expired', 'void' => 'Void'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $recurring_invoices_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='recurring_invoices.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $recurring_invoices_ordering !== "all" ? " AND t.invoice_status = '" . $recurring_invoices_ordering . "'" : "";
$r = $mysqli->query("SELECT t.id, t.invoice_status, t.invoice_date, t.grand_total, t.customer_id FROM `" . DB::INVOICES . "` t WHERE t.id > 0 AND t.customer_id != '' $where ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::INVOICES . "` t WHERE t.id > 0 AND t.customer_id != '' $where")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::CUSTOMERS, $row["customer_id"]);
    $date = ddm_($row["invoice_date"]);
    $amt = number_format($row["grand_total"], 2);
    $st = strtoupper($row["invoice_status"]);
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="recurring_invoice_overview.php?recurring_invoice_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo $date . " - " . $st; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_recurring_invoices.php">View All (<?php echo $total; ?>)</a></div>
</div></div>