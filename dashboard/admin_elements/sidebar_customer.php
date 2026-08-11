<?php


use App\Core\DB;
/*
|--------------------------------------------------------------------------
| ACCOUNTING FUNCTIONS - NOT NEEDED
|--------------------------------------------------------------------------
*/
// Accounting module disabled in this system

// Consolidate session handling
if (!empty($_GET['customers_ordering'] ?? $_REQUEST['customers_ordering'] ?? '')) {
    $_SESSION['customers_ordering'] = e_s__($_GET['customers_ordering'] ?? $_REQUEST['customers_ordering']);
}

// Set default if not exists
$_SESSION['customers_ordering'] = $_SESSION['customers_ordering'] ?? 'all';
$customers_ordering = $_SESSION['customers_ordering'];

// Build search query with array mapping
$status_map = [
    'active'    => "c.is_active=1",
    'inactive'  => "c.is_active=0",
    'crm'       => "c.is_active=1",
    'duplicate' => "SPECIAL_DUPLICATE",
    'overdue'   => "SPECIAL_OVERDUE",
    'unpaid'    => "SPECIAL_UNPAID"
];

$search_query = isset($status_map[$customers_ordering]) ? " AND " . $status_map[$customers_ordering] : " AND c.id >= 1";

// Get current selected customer ID
$current_id = (int)($_GET['customer_id'] ?? 0);

?>
<div class="sidebar-content">

    <!-- Header -->
    <div class="sidebar-section sidebar-section-body d-flex align-items-center pb-2 border-bottom border-1">

        <div>

            <select class="form-select border-0 fw-bold" name="customers_ordering" id="customers_ordering" onchange="window.location.href='customer_overview.php?customer_id=<?php echo $customer_id; ?>&customers_ordering=' + this.value;">
                <?php
                $ordering_options = [
                    'all'       => 'All Customers',
                    'active'    => 'Active Customers',
                    'crm'       => 'CRM Customers',
                    'overdue'   => 'Overdue Customers',
                    'unpaid'    => 'Unpaid Customers',
                    'duplicate' => 'Duplicate Customers',
                    'inactive'  => 'Inactive Customers'
                ];

                foreach ($ordering_options as $value => $label) {
                    $selected = ($customers_ordering === $value) ? 'selected' : '';
                    echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
                }
                ?>
            </select>
        </div>

        <div class="ms-auto">
            <button type="button" class="btn btn-primary border-transparent btn-icon btn-sm opacity-75 p-1 lh-1 fs-6" onclick="window.location.href='customers.php';">
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
                    // Build query based on ordering type
                    if ($customers_ordering === 'duplicate') {
                        // Duplicate customers query
                        $result = $mysqli->query(
                            "SELECT c.id, c.display_name, 0 as total_attachments
                             FROM `" . DB::CUSTOMERS . "` c
                             WHERE c.display_name IN (
                                SELECT display_name FROM `" . DB::CUSTOMERS . "`
                                GROUP BY display_name HAVING COUNT(display_name) > 1
                             )
                             GROUP BY c.id
                             ORDER BY c.display_name ASC
                             LIMIT 25"
                        );

                        $total_customers = $result->num_rows;
                    } elseif ($customers_ordering === 'overdue') {
                        // Overdue customers - customers with overdue invoices
                        $result = $mysqli->query(
                            "SELECT c.id, c.display_name, c.is_active,
                                    0 as total_attachments
                             FROM `" . DB::CUSTOMERS . "` c
                             INNER JOIN `" . DB::INVOICES . "` i ON c.id = i.customer_id
                             WHERE i.invoice_status = 'overdue'
                             GROUP BY c.id
                             ORDER BY c.display_name ASC
                             LIMIT 25"
                        );

                        // Get total count
                        $result_count = $mysqli->query(
                            "SELECT COUNT(DISTINCT c.id) as total FROM `" . DB::CUSTOMERS . "` c
                             INNER JOIN `" . DB::INVOICES . "` i ON c.id = i.customer_id
                             WHERE i.invoice_status = 'overdue'"
                        );
                        $count_row = $result_count->fetch_array();
                        $total_customers = $count_row['total'] ?? 0;
                    } elseif ($customers_ordering === 'unpaid') {
                        // Unpaid customers - customers with any unpaid invoices (sent, partially_paid, overdue)
                        $result = $mysqli->query(
                            "SELECT c.id, c.display_name, c.is_active,
                                    0 as total_attachments
                             FROM `" . DB::CUSTOMERS . "` c
                             INNER JOIN `" . DB::INVOICES . "` i ON c.id = i.customer_id
                             WHERE i.invoice_status IN ('sent', 'partially_paid', 'overdue')
                             GROUP BY c.id
                             ORDER BY c.display_name ASC
                             LIMIT 25"
                        );

                        // Get total count
                        $result_count = $mysqli->query(
                            "SELECT COUNT(DISTINCT c.id) as total FROM `" . DB::CUSTOMERS . "` c
                             INNER JOIN `" . DB::INVOICES . "` i ON c.id = i.customer_id
                             WHERE i.invoice_status IN ('sent', 'partially_paid', 'overdue')"
                        );
                        $count_row = $result_count->fetch_array();
                        $total_customers = $count_row['total'] ?? 0;
                    } else {
                        // Standard query with attachment count using JOIN
                        $result = $mysqli->query(
                            "SELECT c.id, c.display_name, c.is_active,
                                    0 as total_attachments
                             FROM `" . DB::CUSTOMERS . "` c
                             WHERE c.id > 0 {$search_query}
                             ORDER BY c.id DESC
                             LIMIT 25"
                        );

                        // Get total count
                        $result_count = $mysqli->query(
                            "SELECT COUNT(DISTINCT c.id) as total FROM `" . DB::CUSTOMERS . "` c
                             WHERE c.id > 0 {$search_query}"
                        );
                        $count_row = $result_count->fetch_array();
                        $total_customers = $count_row['total'] ?? 0;
                    }

                    // Render rows
                    while ($row = $result->fetch_array()) {
                        $isSelected = ($row['id'] == $current_id) ? 'table-primary shadow-sm' : '';

                        // Calculate balance due = opening + invoiced - paid - credited
                        $customer_balance = 0.0;

                        $ob_query = $mysqli->query("SELECT COALESCE(opening_balance, 0) as ob FROM `" . DB::CUSTOMERS . "` WHERE id = {$row['id']}");
                        $opening_balance = ($ob_query && $ob_row = $ob_query->fetch_assoc()) ? (float)($ob_row['ob'] ?? 0) : 0.0;

                        $inv_query = $mysqli->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM `" . DB::INVOICES . "` WHERE customer_id = {$row['id']} AND invoice_status NOT IN ('draft','void','cancelled','writeoff')");
                        $invoiced = ($inv_query && $inv_row = $inv_query->fetch_assoc()) ? (float)($inv_row['total'] ?? 0) : 0.0;

                        $pay_query = $mysqli->query("SELECT COALESCE(SUM(total_amount_received), 0) as total FROM `" . DB::PAYMENTS_RECEIVED . "` WHERE customer_id = {$row['id']} AND payment_status != 'void'");
                        $paid = ($pay_query && $pay_row = $pay_query->fetch_assoc()) ? (float)($pay_row['total'] ?? 0) : 0.0;

                        $cr_query = $mysqli->query("SELECT COALESCE(SUM(grand_total), 0) as total FROM `" . DB::CREDIT_NOTES . "` WHERE customer_id = {$row['id']} AND credit_note_status NOT IN ('draft','void')");
                        $credited = ($cr_query && $cr_row = $cr_query->fetch_assoc()) ? (float)($cr_row['total'] ?? 0) : 0.0;

                        $customer_balance = $opening_balance + $invoiced - $paid - $credited;

                        $formatted_amount = BASE_CURRENCY['code'] . dec_($customer_balance);
                        $attachment_icon = ($row['total_attachments'] > 0) ? '<i class="ph-paperclip"></i>' : '';

                        $overdue_query = $mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::INVOICES . "` WHERE customer_id = {$row['id']} AND invoice_status NOT IN ('draft','void','cancelled','writeoff') AND expiry_date < CURDATE()");
                        $overdue_cnt = (int)(($overdue_query && $overdue_row = $overdue_query->fetch_assoc()) ? ($overdue_row['cnt'] ?? 0) : 0);

                        $invoice_count_query = $mysqli->query("SELECT COUNT(*) as cnt FROM `" . DB::INVOICES . "` WHERE customer_id = {$row['id']}");
                        $invoice_count = (int)(($invoice_count_query && $invoice_count_row = $invoice_count_query->fetch_assoc()) ? ($invoice_count_row['cnt'] ?? 0) : 0);
                        if ($invoice_count === 0) {
                            $cust_status_class = 'text-muted';
                            $cust_status_label = 'No Invoices';
                        } elseif ($customer_balance > 0) {
                            $cust_status_class = $overdue_cnt > 0 ? 'text-danger' : 'text-warning';
                            $cust_status_label = $overdue_cnt > 0 ? 'OVERDUE' : 'UNPAID';
                        } else {
                            $cust_status_class = 'text-success';
                            $cust_status_label = 'PAID';
                        }
                        $cust_status_html = '<span class="' . $cust_status_class . ' small fw-semibold">' . $cust_status_label . '</span>';
                    ?>
                        <tr id="<?php echo $row['id']; ?>" class="<?php echo $isSelected; ?>">
                            <td>
                                <a href="customer_overview.php?customer_id=<?php echo $row['id']; ?>" class="text-black text-decoration-none d-block">
                                    <div class="row">
                                        <div class="col-lg-10">
                                            <div><?php echo $row['display_name']; ?></div>
                                            <div class="text-muted small">
                                                <?php echo $formatted_amount; ?>
                                            </div>
                                            <div class="small mt-1">
                                                <?php echo $cust_status_html; ?>
                                            </div>
                                        </div>
                                        <div class="col-lg-2 text-end">
                                            <?php echo $attachment_icon; ?>
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
            <a href="listing_customers.php?dt_ordering_type=<?php echo $customers_ordering; ?>">View All Customers (<?php echo $total_customers; ?>)</a>
        </div>

    </div>
    <!-- /sub navigation -->

</div>
