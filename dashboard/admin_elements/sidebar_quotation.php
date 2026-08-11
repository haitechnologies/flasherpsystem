<?php

use App\Core\DB;

if (!empty($_GET['quotations_ordering'] ?? $_REQUEST['quotations_ordering'] ?? '')) {
    $_SESSION['quotations_ordering'] = e_s__($_GET['quotations_ordering'] ?? $_REQUEST['quotations_ordering']);
}

$_SESSION['quotations_ordering'] = $_SESSION['quotations_ordering'] ?? 'all';
$quotations_ordering = $_SESSION['quotations_ordering'];

$status_map = [
    'draft'    => "q.quotation_status='draft'",
    'pending'  => "q.quotation_status='pending'",
    'approved' => "q.quotation_status='approved'",
    'sent'     => "q.quotation_status='sent'",
    'accepted' => "q.quotation_status='accepted'",
    'declined' => "q.quotation_status='declined'",
    'expired'  => "q.quotation_status='expired'",
    'invoiced' => "q.quotation_status='invoiced'",
];

$current_id = (int)($_GET['quotation_id'] ?? 0);

$activeOrganizationId = isset($activeOrganizationId) ? $activeOrganizationId : dashboardRequireActiveOrganization();

$search_query = isset($status_map[$quotations_ordering])
    ? " AND " . $status_map[$quotations_ordering]
    : " AND q.id >= 1";

?>

<div class="sidebar-content">

    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">
        <div>
            <select class="form-select border-0 fw-bold" name="quotations_ordering" id="quotations_ordering" onchange="window.location.href='quotation_overview.php?quotation_id=<?php echo $quotation_id; ?>&quotations_ordering=' + this.value;">
                <?php
                $ordering_options = [
                    'all'      => 'All Quotations',
                    'draft'    => 'Draft',
                    'pending'  => 'Pending Approval',
                    'approved' => 'Approved',
                    'sent'     => 'Sent',
                    'accepted' => 'Accepted',
                    'declined' => 'Declined',
                    'expired'  => 'Expired',
                    'invoiced' => 'Invoiced',
                ];
                foreach ($ordering_options as $value => $label) {
                    $selected = ($quotations_ordering === $value) ? 'selected' : '';
                    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                }
                ?>
            </select>
        </div>
        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='quotations.php';">
                <i class="ph-plus"></i>
            </button>
        </div>
    </div>

    <div class="sidebar-section pt-2">
        <div class="table-responsive">
            <table class="table table-hover">
                <tbody>
                    <?php
                    $result_side = $mysqli->query(
                        "SELECT q.id, q.quotation_no, q.customer_id, q.quotation_status,
                                q.quotation_date, q.grand_total
                         FROM `" . DB::QUOTATIONS . "` q
                         WHERE q.id > 0 AND q.customer_id != '' AND (q.lead_id = 0 OR q.lead_id IS NULL) AND q.organization_id = $activeOrganizationId {$search_query}
                         ORDER BY q.id DESC
                         LIMIT 25"
                    );

                    $result_count = $mysqli->query(
                        "SELECT COUNT(q.id) as total FROM `" . DB::QUOTATIONS . "` q
                         WHERE q.id > 0 AND (q.lead_id = 0 OR q.lead_id IS NULL) AND q.organization_id = $activeOrganizationId {$search_query}"
                    );
                    $count_row = $result_count->fetch_array();
                    $total_quotations = $count_row['total'] ?? 0;

                    while ($row = $result_side->fetch_array()) {
                        $isSelected = ($row['id'] == $current_id) ? 'table-primary shadow-sm' : '';
                        $display_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
                        $formatted_date = ddm_($row['quotation_date']);
                        $formatted_amount = BASE_CURRENCY['code'] . number_format($row['grand_total'] ?? 0, 2);
                        $formatted_status = strtoupper($row['quotation_status'] ?? '');

                        $status_class = match($row['quotation_status']) {
                            'accepted' => 'text-success',
                            'declined' => 'text-danger',
                            'draft' => 'text-secondary',
                            'pending' => 'text-warning',
                            'expired' => 'text-muted',
                            default => 'text-info'
                        };
                        $status_text = '<span class="' . $status_class . '">' . $formatted_status . '</span>';
                    ?>
                        <tr id="<?php echo $row['id']; ?>" class="<?php echo $isSelected; ?>">
                            <td>
                                <a href="quotation_overview.php?quotation_id=<?php echo $row['id']; ?>" class="text-black text-decoration-none d-block">
                                    <div class="row">
                                        <div class="col-lg-7"><?php echo $display_name; ?></div>
                                        <div class="col-lg-5 text-end"><?php echo $formatted_amount; ?></div>
                                    </div>
                                    <div class="small text-muted"><?php echo $row['quotation_no'] . ' - ' . $formatted_date; ?></div>
                                    <div class="small text-muted"><?php echo $status_text; ?></div>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mb-3">
            <a href="listing_quotations.php?dt_ordering_type=<?php echo $quotations_ordering; ?>">View All Quotations (<?php echo $total_quotations; ?>)</a>
        </div>
    </div>

</div>
