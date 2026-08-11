<?php
use App\Core\DB;
if (!empty($_GET["debit_notes_ordering"] ?? $_REQUEST["debit_notes_ordering"] ?? "")) {
    $_SESSION["debit_notes_ordering"] = e_s__($_GET["debit_notes_ordering"] ?? $_REQUEST["debit_notes_ordering"]);
}
if (!isset($_SESSION["debit_notes_ordering"])) $_SESSION["debit_notes_ordering"] = "all";
$debit_notes_ordering = $_SESSION["debit_notes_ordering"];
$current_id = (int)($_GET["debit_note_id"] ?? $_GET["id"] ?? 0);
?>
<div class="sidebar-content">
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="debit_notes_ordering" id="debit_notes_ordering" onchange="window.location.href='debit_note_overview.php?debit_note_id=<?php echo $current_id; ?>&debit_notes_ordering='+this.value;">
                <option value="all" <?php if ($debit_notes_ordering === "all") echo "selected"; ?>>All Debit Notes</option>
<?php
$statusLabels = ['draft' => 'Draft', 'issued' => 'Issued', 'partially_used' => 'Partially Used', 'fully_used' => 'Fully Used'];
foreach ($statusLabels as $val => $lbl) {
    $sel = $debit_notes_ordering === $val ? "selected" : "";
    echo "<option value=\"{$val}\" {$sel}>{$lbl}</option>";
}
?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='debit_notes.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>
    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover"><tbody>
<?php
$where = $debit_notes_ordering !== "all" ? " AND t.debit_note_status = '" . $debit_notes_ordering . "'" : "";
$orgWhere = (!empty($activeOrganizationId)) ? " AND t.organization_id = " . (int)$activeOrganizationId : "";
$r = $mysqli->query("SELECT t.id, t.debit_note_status, t.debit_note_date, t.grand_total, t.vendor_id FROM `" . DB::DEBIT_NOTES . "` t WHERE t.id > 0 AND t.vendor_id != '' $where $orgWhere ORDER BY t.id DESC LIMIT 25");
$c = $mysqli->query("SELECT COUNT(*) FROM `" . DB::DEBIT_NOTES . "` t WHERE t.id > 0 AND t.vendor_id != '' $where $orgWhere")->fetch_row();
$total = $c[0] ?? 0;
while ($row = $r->fetch_array()) {
    $sel = $row["id"] == $current_id ? "table-primary shadow-sm" : "";
    $name = getTableAttr("display_name", DB::VENDORS, $row["vendor_id"]);
    $date = ddm_($row["debit_note_date"]);
    $amt = number_format($row["grand_total"], 2);
    $status_class = match($row["debit_note_status"]) {
        'fully_used', 'issued' => 'text-success',
        'draft' => 'text-secondary',
        'partially_used' => 'text-warning',
        default => 'text-info'
    };
    $st = '<span class="' . $status_class . '">' . strtoupper($row["debit_note_status"]) . '</span>';
?>
    <tr id="<?php echo $row["id"]; ?>" class="<?php echo $sel; ?>">
        <td><a href="debit_note_overview.php?debit_note_id=<?php echo $row["id"]; ?>" class="text-black text-decoration-none d-block">
            <div class="row"><div class="col-lg-8"><?php echo $name; ?></div><div class="col-lg-4 text-end"><?php echo "AED " . $amt; ?></div></div>
            <div class="small text-muted"><?php echo $date . " - " . $st; ?></div>
        </a></td>
    </tr>
<?php } ?>
</tbody></table></div>
<div class="text-center mb-3"><a href="listing_debit_notes.php">View All (<?php echo $total; ?>)</a></div>
</div></div>