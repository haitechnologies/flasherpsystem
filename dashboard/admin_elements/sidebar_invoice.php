<?php


use App\Core\DB;
/*
|--------------------------------------------------------------------------
| SEARCH QUERY & ORDERING
|--------------------------------------------------------------------------
*/

// Consolidate session handling
if (!empty($_GET['invoices_ordering'] ?? $_REQUEST['invoices_ordering'] ?? '')) {
    $_SESSION['invoices_ordering'] = e_s__($_GET['invoices_ordering'] ?? $_REQUEST['invoices_ordering']);
}

// Set default if not exists
$_SESSION['invoices_ordering'] = $_SESSION['invoices_ordering'] ?? 'all';
$invoices_ordering = $_SESSION['invoices_ordering'];

// Build search query with array mapping
$status_map = [
    'draft'           => "i.invoice_status='draft'",
    'locked'          => "i.invoice_status='locked'",
    'pending'         => "i.invoice_status='pending'",
    'approved'        => "i.invoice_status='approved'",
    'sent'            => "i.invoice_status='sent'",
    'accepted'        => "i.invoice_status='accepted'",
    'invoiced'        => "i.invoice_status='invoiced'",
    'declined'        => "i.invoice_status='declined'",
    'expired'         => "i.invoice_status='expired'",
    'void'            => "i.invoice_status='void'",
    'debit_note'      => "i.invoice_status='debit_note'",
    'write_off'       => "(i.invoice_status='writeoff' OR i.invoice_status='write_off')"
];

$special_status_map = [
    'paid'            => "total_paid >= i.grand_total AND i.grand_total > 0",
    'partially_paid'  => "total_paid > 0 AND total_paid < i.grand_total",
    'unpaid'          => "total_paid = 0 AND i.invoice_status IN ('sent','unpaid','overdue')",
    'overdue'         => "i.invoice_status IN ('sent','unpaid','overdue') AND total_paid < i.grand_total AND due_date < CURDATE()"
];

// Get current selected invoice ID
$current_id = (int)($_GET['invoice_id'] ?? 0);

// Cache for payment term lookups
$payment_term_cache = [];

?>
<div class="sidebar-content">

    <!-- Header -->
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">

        <div>

            <select class="form-select border-0 fw-bold" name="invoices_ordering" id="invoices_ordering" onchange="window.location.href='invoice_overview.php?invoice_id=<?php echo $invoice_id; ?>&invoices_ordering=' + this.value;">
                <?php
                $ordering_options = [
                    'all'             => 'All Invoices',
                    'draft'           => 'Draft',
                    'locked'          => 'Locked',
                    'pending'         => 'Pending Approval',
                    'approved'        => 'Approved',
                    'sent'            => 'Sent',
                    'accepted'        => 'Accepted',
                    'invoiced'        => 'Invoiced',
                    'declined'        => 'Declined',
                    'expired'         => 'Expired',
                    'partially_paid'  => 'Partially Paid',
                    'unpaid'          => 'UnPaid',
                    'overdue'         => 'Overdue',
                    'paid'            => 'Paid',
                    'void'            => 'Void',
                    'debit_note'      => 'Debit Note',
                    'write_off'       => 'Write Off'
                ];
                
                foreach ($ordering_options as $value => $label) {
                    $selected = ($invoices_ordering === $value) ? 'selected' : '';
                    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                }
                ?>
            </select>

        </div>

        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='invoices.php';">
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
                    // Optimized single query with only needed columns
                                        $base_select = "SELECT i.id, i.invoice_no, i.customer_id, i.invoice_status,
                                                                                    i.invoice_date, i.grand_total, i.payment_term,
                                                                                    0 AS total_paid,
                                                                                    i.invoice_date AS due_date
                                                                     FROM `" . DB::INVOICES . "` i
                                                                     LEFT JOIN `" . DB::CUSTOMERS . "` c ON i.customer_id = c.id
                                                                     WHERE i.id > 0 AND i.customer_id != ''";

                    $where_status = '';
                    $having_status = '';

                    if (isset($special_status_map[$invoices_ordering])) {
                        $having_status = " HAVING " . $special_status_map[$invoices_ordering];
                    } else if (isset($status_map[$invoices_ordering])) {
                        $where_status = " AND " . $status_map[$invoices_ordering];
                    }

                    $group_by = " GROUP BY i.id";

                    $result_side = $mysqli->query(
                        $base_select . $where_status . $group_by . $having_status . " ORDER BY i.id DESC LIMIT 25"
                    );

                    // Get total count
                    $result_count = $mysqli->query(
                        "SELECT COUNT(*) as total FROM (" . $base_select . $where_status . $group_by . $having_status . ") t"
                    );
                    $count_row = $result_count->fetch_array();
                    $total_invoices = $count_row['total'] ?? 0;

                    // Render rows
                    while ($row = $result_side->fetch_array()) {
                        $isSelected = ($row['id'] == $current_id) ? 'table-primary shadow-sm' : '';
                        $display_name = getTableAttr('display_name', DB::CUSTOMERS, $row['customer_id']);
                        
                        // Calculate display info
                        $display_due_days = '';
                        $formatted_date = ddm_($row['invoice_date']);
                        $formatted_amount = BASE_CURRENCY['code'] . number_format($row['grand_total'], 2);
                        
                        // Status text color
                        $formatted_status = strtoupper($row['invoice_status']);
                        $status_class = match($row['invoice_status']) {
                            'paid' => 'text-success',
                            'void' => 'text-danger',
                            'draft' => 'text-secondary',
                            'pending' => 'text-warning',
                            'approved' => 'text-info',
                            'sent' => 'text-info',
                            'unpaid' => 'text-warning',
                            'overdue' => 'text-danger',
                            'partially_paid' => 'text-info',
                            default => 'text-secondary'
                        };

                        if ($row['invoice_status'] === 'sent') {
                            $payment_term_duration = $row['payment_term'] ?? '';
                            $due_date = calculateInvoiceDueDate($row['invoice_status'], $row['invoice_date'], $payment_term_duration);
                            if (!empty($due_date)) {
                                $today = new DateTime();
                                $today->setTime(0, 0, 0);
                                $due = new DateTime($due_date);
                                $due->setTime(0, 0, 0);

                                if ($today > $due) {
                                    $days_overdue = $due->diff($today)->days;
                                    $display_due_days = '<span class="text-danger">OVERDUE BY ' . $days_overdue . ' DAYS</span>';
                                } else if ($today == $due) {
                                    $display_due_days = '<span class="text-warning">DUE TODAY</span>';
                                } else {
                                    $days_remaining = $today->diff($due)->days;
                                    $display_due_days = '<span class="text-info">DUE IN ' . $days_remaining . ' DAYS</span>';
                                }
                            }
                        }

                        $computed_status = $row['invoice_status'];
                        if ($row['total_paid'] >= $row['grand_total'] && $row['grand_total'] > 0) {
                            $computed_status = 'paid';
                        } else if ($row['total_paid'] > 0 && $row['total_paid'] < $row['grand_total']) {
                            $computed_status = 'partially_paid';
                        } else if (!empty($row['due_date'])) {
                            $today = new DateTime();
                            $today->setTime(0, 0, 0);
                            $due = new DateTime($row['due_date']);
                            $due->setTime(0, 0, 0);
                            if ($row['invoice_status'] === 'sent' && $today > $due) {
                                $computed_status = 'overdue';
                            }
                        }

                        if (!empty($display_due_days)) {
                            $status_text = $display_due_days;
                        } else {
                            $status_text = '<span class="' . $status_class . '">' . strtoupper($computed_status) . '</span>';
                        }
                    ?>
                        <tr id="<?php echo $row['id']; ?>" class="<?php echo $isSelected; ?>">
                            <td>
                                <a href="invoice_overview.php?invoice_id=<?php echo $row['id']; ?>" class="text-black text-decoration-none d-block">
                                    <div class="row">
                                        <div class="col-lg-8"><?php echo $display_name; ?></div>
                                        <div class="col-lg-4 text-end"><?php echo $formatted_amount; ?></div>
                                    </div>
                                    <div class="small text-muted"><?php echo $row['invoice_no'] . ' - ' . $formatted_date; ?></div>
                                    <div class="small text-muted"><?php echo $status_text; ?></div>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <div class="text-center mb-3">
            <a href="listing_invoices.php?dt_ordering_type=<?php echo $invoices_ordering; ?>">View All Invoices (<?php echo $total_invoices; ?>)</a>
        </div>

    </div>
    <!-- /sub navigation -->

</div>

<?php
// -- FLUSH VARS
$invoice_id          = '';
$invoice_no          = '';
$customer_id         = '';
$display_name        = '';
$invoice_status      = '';
$invoice_date        = '';
$grand_total         = '';
?>