<?php

use App\Core\DB;
use App\Core\Session;
use App\Service\JournalService;
include('admin_elements/admin_header.php');

$module = 'recurring_invoices';
$module_caption = 'Recurring Invoice';
$tbl_name = DB::INVOICES;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

/*
|--------------------------------------------------------------------------
| GET RECURRING INVOICE ID
|--------------------------------------------------------------------------
|
*/

$invoice_id = '';
if (isset($_REQUEST['invoice_id']))        $invoice_id     = e_s__($_REQUEST['invoice_id']);
if (isset($_POST['invoice_id']))           $invoice_id     = e_s__($_POST['invoice_id']);
if (empty($invoice_id) && isset($_REQUEST['recurring_invoice_id'])) $invoice_id = e_s__($_REQUEST['recurring_invoice_id']);
if (empty($invoice_id) && isset($_REQUEST['id'])) $invoice_id = e_s__($_REQUEST['id']);

// CHECK IF EXISTS
$rs_valid     = $mysqli->query("SELECT id FROM `" . DB::INVOICES . "` WHERE id='" . $invoice_id . "' AND recurring=1");
if ($rs_valid->num_rows == 0) {
    flash_error('Invalid Recurring Invoice in the database.');
    header("Location:listing_recurring_invoices.php");
    exit;
}

// CSRF GATE (state-change actions must be POST + valid token)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid CSRF token.');
        header("Location:recurring_invoice_overview.php?invoice_id=$invoice_id");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE RECURRING INVOICE STATUS
|--------------------------------------------------------------------------
|
*/
$publish = 1;
$recurring_status = 0;
if (isset($_REQUEST['recurring_status']) && !empty($_REQUEST['recurring_status'])) {
    $recurring_status   = e_s__($_REQUEST['recurring_status']);
}

if (($action == "update_$module" && !empty($invoice_id) && isset($recurring_status))) {
    $result = $mysqli->query("UPDATE `$tbl_name` SET recurring_status = '" . $recurring_status . "' WHERE id=$invoice_id");
    
    if ($result) {
        $success_message = "The recurring $module_caption status has been updated successfully.";
        flash_success($success_message);
        header("Location:recurring_invoice_overview.php?invoice_id=$invoice_id");
        exit;
    } else {
        $error_message = "Sorry! $module Status Could Not Be Updated.";
    }
}

/*
|--------------------------------------------------------------------------
| CLONE RECURRING INVOICE PROFILE
|--------------------------------------------------------------------------
|
*/
if ($action == "clone_recurring_invoices" && !empty($invoice_id)) {
    $src = $mysqli->query("SELECT * FROM `" . DB::INVOICES . "` WHERE id=$invoice_id AND recurring=1");
    if ($src && $src->num_rows > 0) {
        $src_row = $src->fetch_array();

        $ym = date('ym');
        $last_no = $mysqli->query("SELECT invoice_no FROM `" . DB::INVOICES . "` WHERE invoice_no LIKE 'FL-IN" . $ym . "%' ORDER BY id DESC LIMIT 1");
        $serial = 1;
        if ($last_no && $last_no->num_rows > 0) {
            $last = $last_no->fetch_array()['invoice_no'];
            $serial = ((int)substr($last, -4)) + 1;
        }
        $new_invoice_no = 'FL-IN' . $ym . '-' . str_pad((string)$serial, 4, '0', STR_PAD_LEFT);

        $cols = "organization_id, recurring, invoice_no, customer_id, invoice_status, invoice_date, expiry_date,
                 reference_no, warehouse_id, expected_shipment_date, payment_term, shipment_type, sales_person,
                 job_reference_no, master_awb_no, shipper, consignee, origin, destination, no_of_packs,
                 gross_weight, chargeable_weight, volume, customer_notes, terms_and_conditions, grand_subtotal,
                 grand_discount_type, grand_discount_type_value, grand_discount_amount, grand_after_discount,
                 grand_tax, grand_total, profile_name, frequency, start_date, end_date, publish, is_active,
                 created_by, created_at";

        $vals = [
            (int)$src_row['organization_id'], 1, "'" . $new_invoice_no . "'", (int)$src_row['customer_id'],
            "'draft'", "'" . $src_row['invoice_date'] . "'",
            $src_row['expiry_date'] !== null ? "'" . $src_row['expiry_date'] . "'" : 'NULL',
            $src_row['reference_no'] !== null ? "'" . $src_row['reference_no'] . "'" : 'NULL',
            $src_row['warehouse_id'] !== null ? (int)$src_row['warehouse_id'] : 'NULL',
            $src_row['expected_shipment_date'] !== null ? "'" . $src_row['expected_shipment_date'] . "'" : 'NULL',
            (int)$src_row['payment_term'], $src_row['shipment_type'] !== null ? "'" . $src_row['shipment_type'] . "'" : 'NULL',
            $src_row['sales_person'] !== null ? (int)$src_row['sales_person'] : 'NULL',
            $src_row['job_reference_no'] !== null ? "'" . $src_row['job_reference_no'] . "'" : 'NULL',
            $src_row['master_awb_no'] !== null ? "'" . $src_row['master_awb_no'] . "'" : 'NULL',
            $src_row['shipper'] !== null ? "'" . $src_row['shipper'] . "'" : 'NULL',
            $src_row['consignee'] !== null ? "'" . $src_row['consignee'] . "'" : 'NULL',
            $src_row['origin'] !== null ? "'" . $src_row['origin'] . "'" : 'NULL',
            $src_row['destination'] !== null ? "'" . $src_row['destination'] . "'" : 'NULL',
            (int)$src_row['no_of_packs'], (float)$src_row['gross_weight'], (float)$src_row['chargeable_weight'],
            (float)$src_row['volume'],
            $src_row['customer_notes'] !== null ? "'" . $src_row['customer_notes'] . "'" : 'NULL',
            $src_row['terms_and_conditions'] !== null ? "'" . $src_row['terms_and_conditions'] . "'" : 'NULL',
            (float)$src_row['grand_subtotal'], "'" . $src_row['grand_discount_type'] . "'",
            (float)$src_row['grand_discount_type_value'], (float)$src_row['grand_discount_amount'],
            (float)$src_row['grand_after_discount'], (float)$src_row['grand_tax'], (float)$src_row['grand_total'],
            "'" . $src_row['profile_name'] . "'", "'" . $src_row['frequency'] . "'",
            "'" . $src_row['start_date'] . "'", "'" . $src_row['end_date'] . "'",
            (int)$src_row['publish'], 1, (int)Session::userId(), "'" . date('Y-m-d H:i:s') . "'",
        ];

        $mysqli->query("INSERT INTO `" . DB::INVOICES . "` ($cols) VALUES (" . implode(', ', $vals) . ")");
        $new_id = $mysqli->insert_id;

        if ($new_id > 0) {
            $items = $mysqli->query("SELECT service, description, qty, rate, sub_total, tax, tax_amount, total FROM `" . DB::INVOICE_ITEMS . "` WHERE invoice_id=$invoice_id");
            if ($items) {
                while ($item = $items->fetch_array()) {
                    $mysqli->query("INSERT INTO `" . DB::INVOICE_ITEMS . "`
                        (organization_id, invoice_id, service, description, qty, rate, sub_total, tax, tax_amount, total, created_at)
                        VALUES (" . (int)$src_row['organization_id'] . ", $new_id, " .
                        (int)$item['service'] . ", '" . $item['description'] . "', " . (float)$item['qty'] . ", " .
                        (float)$item['rate'] . ", " . (float)$item['sub_total'] . ", " . (float)$item['tax'] . ", " .
                        (float)$item['tax_amount'] . ", " . (float)$item['total'] . ", '" . date('Y-m-d H:i:s') . "')");
                }
            }
            flash_success("Recurring Invoice Profile cloned successfully as $new_invoice_no.");
            header("Location:recurring_invoice_overview.php?recurring_invoice_id=$new_id");
            exit;
        }
        $error_message = "Sorry! Recurring Invoice Profile could not be cloned.";
    }
}

/*
|--------------------------------------------------------------------------
| FETCH RECURRING INVOICE DATA
|--------------------------------------------------------------------------
|
*/

if (!empty($invoice_id)) {
    
    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$invoice_id");
    $row = $result->fetch_array();
    
    $customer_id            = s__($row['customer_id']);
    $warehouse_id           = s__($row['warehouse_id']);
    
    $profile_name           = s__($row['profile_name']);
    $frequency              = s__($row['frequency']);
    $start_date             = s__($row['start_date']);
    $end_date               = s__($row['end_date']);
    
    $invoice_no             = s__($row['invoice_no']);
    $recurring_status       = s__($row['recurring_status']);
    $invoice_date           = s__($row['invoice_date']);
    $expiry_date            = s__($row['expiry_date']);
    
    $reference_no           = s__($row['reference_no']);
    $expected_shipment_date = s__($row['expected_shipment_date']);
    $payment_term           = getTableAttr('payment_term', DB::CUSTOMERS, $customer_id);
    
    $shipment_type          = s__($row['shipment_type']);
    $sales_person           = s__($row['sales_person']);
    $job_reference_no       = s__($row['job_reference_no']);
    $master_awb_no          = s__($row['master_awb_no']);
    $shipper                = s__($row['shipper']);
    $consignee              = s__($row['consignee']);
    $origin                 = s__($row['origin']);
    $destination            = s__($row['destination']);
    $no_of_packs            = s__($row['no_of_packs']);
    $gross_weight           = s__($row['gross_weight']);
    $chargeable_weight      = s__($row['chargeable_weight']);
    $volume                 = s__($row['volume']);
    
    $customer_notes         = s__($row['customer_notes']);
    $terms_and_conditions   = s__($row['terms_and_conditions']);
    
    $final_terms_and_conditions = '';
    if (!empty($terms_and_conditions)) {
        $desc = explode("\r", $terms_and_conditions);
        $d_counter = 1;
        if (count($desc) > 0) {
            foreach ($desc as $d) {
                if (!empty($d)) {
                    $final_terms_and_conditions .= $d . '<br />';
                }
            }
        }
    }
    
    $grand_subtotal             = (float) s__($row['grand_subtotal']);
    $grand_discount_type        = s__($row['grand_discount_type']);
    $grand_discount_type_value  = (float) s__($row['grand_discount_type_value']);
    $grand_discount_amount      = (float) s__($row['grand_discount_amount']);
    $grand_after_discount       = (float) s__($row['grand_after_discount']);
    $grand_tax                  = (float) s__($row['grand_tax']);
    $grand_total                = (float) s__($row['grand_total']);
    $publish                    = s__($row['is_active']);
    
    // --- Customer Information
    $rs = $mysqli->query("SELECT * FROM `" . DB::CUSTOMERS . "` WHERE id=$customer_id");
    $row_customer = $rs->fetch_array();
    $salutation             = isset($row_customer['salutation']) ? s__($row_customer['salutation']) : '';
    $first_name             = isset($row_customer['first_name']) ? s__($row_customer['first_name']) : '';
    $last_name              = isset($row_customer['last_name']) ? s__($row_customer['last_name']) : '';
    $company_name           = isset($row_customer['company_name']) ? s__($row_customer['company_name']) : '';
    $email                  = isset($row_customer['email']) ? s__($row_customer['email']) : '';
    $phone                  = isset($row_customer['phone']) ? s__($row_customer['phone']) : '';
    $mobile                 = isset($row_customer['mobile']) ? s__($row_customer['mobile']) : '';
    $trn                    = isset($row_customer['trn']) ? s__($row_customer['trn']) : '';
    
    $display_name           = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
    
    // --- Warehouse/From Address Information
    $warehouse_name         = '';
    $warehouse_country      = '';
    $warehouse_phone        = '';
    $warehouse_email        = '';
    $warehouse_trn          = '';
    
    if (!empty($warehouse_id)) {
        $rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id=$warehouse_id");
        if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
            $row_warehouse = $rs_warehouse->fetch_array();
            $warehouse_name     = isset($row_warehouse['warehouse_name']) ? s__($row_warehouse['warehouse_name']) : '';
            $warehouse_country  = isset($row_warehouse['country']) ? s__($row_warehouse['country']) : '';
            $warehouse_phone    = isset($row_warehouse['phone']) ? s__($row_warehouse['phone']) : '';
            $warehouse_email    = isset($row_warehouse['email']) ? s__($row_warehouse['email']) : '';
            $warehouse_trn      = isset($row_warehouse['trn']) ? s__($row_warehouse['trn']) : '';
        }
    }
    
    // --- Billing Address Information (from fls_customer_addresses)
    $billing_attention      = '';
    $billing_address_line1  = '';
    $billing_address_line2  = '';
    $billing_city           = '';
    $billing_state          = '';
    $billing_zipcode        = '';
    $billing_country        = '';
    $billing_phone          = '';
    $billing_fax            = '';
    
    $rs_addr = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='billing' LIMIT 1");
    if ($rs_addr && $rs_addr->num_rows > 0) {
        $row_addr = $rs_addr->fetch_array();
        $billing_attention      = isset($row_addr['attention']) ? s__($row_addr['attention']) : '';
        $billing_address_line1  = isset($row_addr['address_line1']) ? s__($row_addr['address_line1']) : '';
        $billing_address_line2  = isset($row_addr['address_line2']) ? s__($row_addr['address_line2']) : '';
        $billing_city           = isset($row_addr['city']) ? s__($row_addr['city']) : '';
        $billing_state          = isset($row_addr['state']) ? s__($row_addr['state']) : '';
        $billing_zipcode        = isset($row_addr['zipcode']) ? s__($row_addr['zipcode']) : '';
        $billing_country        = isset($row_addr['country']) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $row_addr['country']) : '';
        $billing_phone          = isset($row_addr['phone']) ? s__($row_addr['phone']) : '';
        $billing_fax            = isset($row_addr['fax']) ? s__($row_addr['fax']) : '';
    }
    
    // Process dates like invoice_overview.php
    $invoice_date         = ddm_($invoice_date);
            $expiry_date            = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : ddm_($expiry_date);
            $expected_shipment_date = ($expected_shipment_date == '1970-01-01' || empty($expected_shipment_date)) ? '' : ddm_($expected_shipment_date);
    
    // --- Invoice Items
    $result_invoice_items = $mysqli->query("SELECT * FROM `" . DB::INVOICE_ITEMS . "` WHERE invoice_id=$invoice_id");
    
    $invoice_item_id_arr    = array();
    $service_arr            = array();
    $description_arr        = array();
    $qty_arr                = array();
    $rate_arr               = array();
    $sub_total_arr          = array();
    $tax_arr                = array();
    $tax_amount_arr         = array();
    $total_arr              = array();
    
    $total_rows = $result_invoice_items->num_rows;
    
    if ($total_rows > 0) {
        while ($row_invoice_items = $result_invoice_items->fetch_array()) {
            $invoice_item_id_arr[]  = s__($row_invoice_items['id']);
            $service_arr[]          = s__($row_invoice_items['service']);
            $description_arr[]      = s__($row_invoice_items['description']);
            $qty_arr[]              = (float) s__($row_invoice_items['qty']);
            $rate_arr[]             = (float) s__($row_invoice_items['rate']);
            $sub_total_arr[]        = (float) s__($row_invoice_items['sub_total']);
            $tax_arr[]              = (float) s__($row_invoice_items['tax']);
            $tax_amount_arr[]       = (float) s__($row_invoice_items['tax_amount']);
            $total_arr[]            = (float) s__($row_invoice_items['total']);
        }
    }
    
    if ($total_rows == 0)   $total_rows = 1;
}
?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <?php include('admin_elements/sidebar_recurring_invoice.php'); ?>
</div>

<div class="content-wrapper">
    <div class="content-inner">
        <?php include('admin_elements/page_header_recurring_invoice.php'); ?>
        
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <strong>Success!</strong> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <strong>Error!</strong> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($invoice_id)): ?>
                
                <!-- Recurring Invoice Header Info -->
                <div class="row">
                    <div class="col-lg-1"></div>
                    <div class="col-lg-10">
                        <div class="card">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 fw-semibold">
                                    <i class="ph-arrows-clockwise me-2"></i>Recurring Invoice Profile
                                </h6>
                            </div>
                            <div class="card-body p-4">
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <label class="text-muted small mb-1">Profile Name</label>
                                        <div class="fw-semibold"><?php echo $profile_name; ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted small mb-1">Frequency</label>
                                        <div class="fw-semibold">
                                            <span class="badge bg-primary"><?php echo !empty($frequency) ? ucfirst($frequency) : '-'; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted small mb-1">Start Date</label>
                                        <div class="fw-semibold"><?php echo ddm_($start_date); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="text-muted small mb-1">End Date</label>
                                        <div class="fw-semibold"><?php echo !empty($end_date) ? ddm_($end_date) : '<span class="badge bg-warning">Ongoing</span>'; ?></div>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                </div>
                
                <!-- Customer and Template Info -->
                <div class="row">
                    <div class="col-lg-1"></div>
                    <div class="col-lg-10">
                        <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-4">
                                        <span class="text-muted">Bill To:</span>
                                        <ul class="list list-unstyled mb-0">
                                            <li>
                                                <h5 class="my-2"><a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>"><?php echo $display_name; ?></a></h5>
                                            </li>
                                            <li><span class="fw-semibold"><?php echo $company_name; ?></span></li>
                                            <li><?php echo $billing_attention; ?></li>
                                            <li><?php echo $billing_country; ?></li>
                                            <li><?php echo $billing_address_line1; ?></li>
                                            <li><?php echo $billing_address_line2; ?></li>
                                            <li><?php echo $billing_city; ?></li>
                                            <li><?php echo $billing_state; ?></li>
                                            <li><?php echo $billing_zipcode; ?></li>
                                            <li><?php echo $billing_phone; ?></li>
                                            <li><?php echo $billing_fax; ?></li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div class="col-sm-6">
                                    <div class="text-sm-end mb-4">
                                        <?php
                                        $warehouse_information = '';
                                        $warehouse_information .= (!empty($warehouse_name) ? $warehouse_name . '<br />' : '');
                                        $warehouse_information .= (!empty($warehouse_country) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $warehouse_country) . '<br />' : '');
                                        $warehouse_information .= (!empty($warehouse_phone) ? $warehouse_phone . '<br />' : '');
                                        $warehouse_information .= (!empty($warehouse_email) ? $warehouse_email . '<br />' : '');
                                        $warehouse_information .= (!empty($warehouse_trn) ? $warehouse_trn : '');
                                        ?>
                                        <div class="text-muted">From:</div>
                                        <?php echo $warehouse_information; ?>
                                        <h6 class="text-primary mb-2 mt-lg-2">Recurring Profile #<?php echo $profile_name; ?></h6>
                                        <ul class="list list-unstyled mb-0">
                                            <li>Template Invoice No: <span class="fw-semibold"><?php echo $invoice_no; ?></span></li>
                                            <li>Frequency: <span class="fw-semibold"><?php echo ucfirst($frequency); ?></span></li>
                                            <li>Start Date: <span class="fw-semibold"><?php echo ddm_($start_date); ?></span></li>
                                            <li>End Date: <span class="fw-semibold"><?php echo (!empty($end_date) ? ddm_($end_date) : '<span class="badge bg-warning">Ongoing</span>'); ?></span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="d-lg-flex flex-lg-wrap border-top pt-3">

                                <div class="col-sm-6">
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Expected Shipment Date:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $expected_shipment_date; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Delivery Method:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $shipment_type; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Job Reference No:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $job_reference_no; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Shipper:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo getTableAttr('shipper_name', DB::SHIPPERS, $shipper); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Origin:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo getTableAttr('alpha3_code', DB::GEO_COUNTRIES, $origin); ?> - <?php echo getTableAttr('country_name', DB::GEO_COUNTRIES, $origin); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">No of Packs:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $no_of_packs; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Chargeable Weight:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $chargeable_weight; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Payment Terms:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo !empty($payment_term) ? getTableAttr('payment_term', DB::PAYMENT_TERMS, $payment_term) : '-'; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Sales Person:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo getTableAttr('warehouse_name', DB::WAREHOUSES, $sales_person); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Master AWB No:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $master_awb_no; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Consignee:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo getTableAttr('consignee_name', DB::CONSIGNEES, $consignee); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Destination:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo getTableAttr('alpha3_code', DB::GEO_COUNTRIES, $destination); ?> - <?php echo getTableAttr('country_name', DB::GEO_COUNTRIES, $destination); ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Gross Weight:</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $gross_weight; ?>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <label class="col-lg-5 col-form-label">Volume (CBM):</label>
                                        <div class="col-lg-7 mt-2">
                                            <?php echo $volume; ?>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                </div>
                
                <!-- Invoice Items Table -->
                <div class="row">
                    <div class="col-lg-1"></div>
                    <div class="col-lg-10">
                        <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-lg">
                                    <thead>
                                        <tr>
                                            <th>ITEM DETAILS</th>
                                            <th>DESCRIPTION</th>
                                            <th class="text-center">QUANTITY</th>
                                            <th class="text-center">RATE</th>
                                            <th class="text-center">SUBTOTAL</th>
                                            <th class="text-center">TAX</th>
                                            <th class="text-center">TOTAL</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        for ($invoice_item = 1; $invoice_item <= $total_rows; $invoice_item++) {
                                            $index = $invoice_item - 1;
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?php echo getTableAttr('item_name', DB::ITEMS, $service_arr[$index]); ?></div>
                                                    <span class="text-muted text-truncate">
                                                        <?php
                                                        if (!empty($description_arr[$index])) {
                                                            $desc = explode("\r", $description_arr[$index]);
                                                            $d_counter = 1;
                                                            if (count($desc) > 0) {
                                                                foreach ($desc as $d) {
                                                                    if (!empty($d)) {
                                                                        echo $d_counter++ . '. ' . $d;
                                                                        echo '<br />';
                                                                    }
                                                                }
                                                            }
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $description_arr[$index]; ?></td>
                                                <td class="text-center"><?php echo $qty_arr[$index]; ?></td>
                                                <td class="text-end"><?php echo number_format((float)($rate_arr[$index] ?? 0), 2); ?></td>
                                                <td class="text-end"><?php echo number_format((float)($sub_total_arr[$index] ?? 0), 2); ?></td>
                                                <td class="text-end"><?php echo $tax_arr[$index] ?? 0; ?>% (<?php echo number_format((float)($tax_amount_arr[$index] ?? 0), 2); ?>)</td>
                                                <td class="text-end"><span class="fw-semibold"><?php echo number_format((float)($total_arr[$index] ?? 0), 2); ?></span></td>
                                            </tr>
                                        <?php
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                </div>
                
                <!-- Totals Summary -->
                <div class="row">
                    <div class="col-lg-1"></div>
                    <div class="col-lg-5"></div>
                    <div class="col-lg-5">
                        <div class="card">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Subtotal:</span>
                                    <span class="fw-semibold"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_subtotal ?: 0, 2); ?></span>
                                </div>
                            </div>
                            
                            <?php if (!empty($grand_discount_amount) && $grand_discount_amount > 0): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Discount (<?php echo $grand_discount_type; ?> <?php echo (float)$grand_discount_type_value ?: 0; ?>):</span>
                                        <span class="fw-semibold text-danger">- <?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_discount_amount ?: 0, 2); ?></span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">After Discount:</span>
                                        <span class="fw-semibold"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_after_discount ?: 0, 2); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($grand_tax) && $grand_tax > 0): ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Tax:</span>
                                        <span class="fw-semibold"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_tax ?: 0, 2); ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="border-top pt-3 mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-semibold fs-5">Total Amount:</span>
                                    <h4 class="fw-bold text-primary mb-0"><?php echo BASE_CURRENCY['code']; ?> <?php echo number_format((float)$grand_total ?: 0, 2); ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                </div>
                
                <!-- Notes and Terms -->
                <?php if (!empty($customer_notes) || !empty($terms_and_conditions)): ?>
                    <div class="row">
                        <div class="col-lg-1"></div>
                        <div class="col-lg-10">
                            <div class="card">
                            <div class="card-body p-4">
                                <?php if (!empty($customer_notes)): ?>
                                    <div class="mb-4">
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="ph-note-pencil ph-lg text-primary me-2"></i>
                                            <h6 class="mb-0 fw-semibold">Customer Notes</h6>
                                        </div>
                                        <div class="text-muted"><?php echo nl2br($customer_notes); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($terms_and_conditions)): ?>
                                    <div>
                                        <div class="d-flex align-items-center mb-3">
                                            <i class="ph-file-text ph-lg text-primary me-2"></i>
                                            <h6 class="mb-0 fw-semibold">Terms & Conditions</h6>
                                        </div>
                                        <div class="text-muted"><?php echo $final_terms_and_conditions; ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-1"></div>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
        </div>
        
    </div>
</div>
</div>
<?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
