<?php

declare(strict_types=1);

require_once __DIR__ . '/admin_elements/error_handler_init.php';

use App\Core\DB;

require_once __DIR__ . '/../config/session.php';
startDashboardSession();
header('Content-Type: text/html; charset=utf-8');
require '../config/globals.php';
require '../config/database.php';
include 'admin_elements/error_logger.php';

if (function_exists('custom_error_handler')) {
    set_error_handler('custom_error_handler');
}
if (function_exists('custom_exception_handler')) {
    set_exception_handler('custom_exception_handler');
}
if (function_exists('handle_fatal_error')) {
    register_shutdown_function('handle_fatal_error');
}
if (function_exists('backend_log_coverage_heartbeat')) {
    backend_log_coverage_heartbeat();
}
include '../config/images.php';
include 'admin_elements/grab_vars.php';

require_once '../tcpdf/examples/tcpdf_include.php';

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologiesLLC');
$pdf->setTitle('Job');
$pdf->setSubject('na');

$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(10, 3, 10, true);
$pdf->setFooterMargin(5);
$pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->setFontSubsetting(true);
$pdf->setFont('helvetica', '', 8);
$pdf->setPrintHeader(false);
$pdf->AddPage();

$job_id = isset($_REQUEST['job_id']) && !empty($_REQUEST['job_id']) ? (int)$_REQUEST['job_id'] : 0;
if ($job_id <= 0) {
    $pdf->setFont('helvetica', '', 11);
    $pdf->Cell(0, 10, 'Invalid Job ID', 0, 1, 'C');
    $pdf->Output('error.pdf', 'I');
    exit;
}

try {
    $db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);
} catch (\Throwable $e) {
    $pdf->setFont('helvetica', '', 11);
    $pdf->Cell(0, 10, 'System error', 0, 1, 'C');
    $pdf->Output('error.pdf', 'I');
    exit;
}

$row = $db->fetchOne("SELECT * FROM `" . DB::JOBS . "` WHERE id = :id", ['id' => $job_id]);
if (!$row) {
    $pdf->setFont('helvetica', '', 11);
    $pdf->Cell(0, 10, 'Job not found', 0, 1, 'C');
    $pdf->Output('error.pdf', 'I');
    exit;
}

// Resolve IDs
$customer_id          = (int)($row['customer_id'] ?? 0);
$job_status_id        = (int)($row['job_status'] ?? 0);
$carrier_id           = (int)($row['carrier'] ?? 0);
$landing_port_id      = (int)($row['landing_port'] ?? 0);
$destination_port_id  = (int)($row['destination_port'] ?? 0);
$warehouse_id         = (int)($row['warehouse_id'] ?? 0);
$sales_person_id      = (int)($row['sales_person'] ?? 0);
$cs_agent_id          = (int)($row['cs_agent'] ?? 0);

$customer_name          = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
$status_label           = getTableAttr('job_status', DB::JOB_STATUSES, $job_status_id);
$carrier_name           = getTableAttr('carrier_name', DB::CARRIERS, $carrier_id);
$landing_port_name      = getTableAttr('port_name', DB::PORTS, $landing_port_id);
$destination_port_name  = getTableAttr('port_name', DB::PORTS, $destination_port_id);
$landing_country        = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['landing_country'] ?? 0));
$destination_country    = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['destination_country'] ?? 0));
$shipping_country       = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['shipping_country'] ?? 0));
$billing_country        = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['billing_country'] ?? 0));
$container_type_label   = getTableAttr('container_type', DB::CONTAINER_TYPES, (int)($row['container_type'] ?? 0));
$sales_person_name      = getUsernameByID($sales_person_id);
$cs_agent_name          = getUsernameByID($cs_agent_id);
$created_by_name        = getUsernameByID((int)($row['created_by'] ?? 0));
$modified_by_name       = getUsernameByID((int)($row['modified_by'] ?? 0));

$job_date = $row['job_date'] ?? '';
$job_date = ($job_date === '1970-01-01') ? '' : processDateYtoD($job_date);
$etd = $row['etd'] ?? '';
$etd = ($etd === '1970-01-01') ? '' : processDateYtoD($etd);
$eta = $row['eta'] ?? '';
$eta = ($eta === '1970-01-01') ? '' : processDateYtoD($eta);
$vessel_departure = $row['vessel_departure_date'] ?? '';
$vessel_departure = ($vessel_departure === '1970-01-01' || empty($vessel_departure)) ? '' : processDateYtoD($vessel_departure);
$flight_departure = $row['flight_departure_date'] ?? '';
$flight_departure = ($flight_departure === '1970-01-01' || empty($flight_departure)) ? '' : processDateYtoD($flight_departure);
$shipping_date = $row['shipping_date'] ?? '';
$shipping_date = ($shipping_date === '1970-01-01' || empty($shipping_date)) ? '' : processDateYtoD($shipping_date);
$job_completion = $row['job_completion_date'] ?? '';
$job_completion = ($job_completion === '1970-01-01' || empty($job_completion)) ? '' : processDateYtoD($job_completion);
$approved_time = $row['approved_time'] ?? '';

// Services: stored as comma-separated IDs, resolve to names
$services_ids = array_filter(explode(', ', (string)($row['services'] ?? '')));
$services_names = [];
foreach ($services_ids as $sid) {
    $sid_val = (int)trim($sid);
    if ($sid_val > 0) {
        $svc_name = getTableAttr('item_name', DB::ITEMS, $sid_val);
        if (!empty($svc_name)) $services_names[] = $svc_name;
    }
}
$services_display = !empty($services_names) ? implode(', ', $services_names) : '';

// ---- Pre-compute all display values for heredoc ----
$d_job_no           = s__((string)($row['job_no'] ?? ''));
$d_job_ref          = s__((string)($row['job_ref_no'] ?? ''));
$d_job_date         = $job_date;
$d_status           = s__($status_label);
$d_job_seq          = s__((string)($row['job_seq'] ?? ''));
$d_customer         = s__($customer_name);
$d_carrier          = s__($carrier_name);
$d_sales_person     = s__($sales_person_name);
$d_cs_agent         = s__($cs_agent_name);
$d_sales_lead       = s__((string)($row['sales_person_from_lead'] ?? ''));
$d_tags             = s__((string)($row['tags'] ?? ''));
$d_services         = $services_display;
$d_warehouse        = s__(getTableAttr('warehouse_name', DB::ORGANIZATIONS, $warehouse_id));
$d_email            = s__((string)($row['email'] ?? ''));
$d_incoterm         = s__((string)($row['incoterm'] ?? ''));
$d_subject          = s__((string)($row['subject'] ?? ''));
$d_job_owner        = s__((string)($row['job_owner'] ?? ''));
$d_quotation_id     = s__((string)($row['quotation_id'] ?? ''));

$d_loading_country  = s__($landing_country);
$d_loading_place    = s__((string)($row['loading_place'] ?? ''));
$d_port_loading     = s__($landing_port_name);
$d_dest_country     = s__($destination_country);
$d_port_dest        = s__($destination_port_name);
$d_fdp              = s__((string)($row['fdp'] ?? ''));
$d_shipping_country = s__($shipping_country);
$d_shipping_city    = s__((string)($row['shipping_city'] ?? ''));
$d_shipping_state   = s__((string)($row['shipping_state'] ?? ''));
$d_shipping_code    = s__((string)($row['shipping_code'] ?? ''));

$d_shipment_type    = s__((string)($row['shipment_type'] ?? ''));
$d_transport_mode   = s__((string)($row['transport_mode'] ?? ''));
$d_etd              = $etd;
$d_eta              = $eta;
$d_vessel           = s__((string)($row['vessel_name'] ?? ''));
$d_flight           = s__((string)($row['flight_no'] ?? ''));
$d_mbl              = s__((string)($row['mawb'] ?? ''));
$d_hbl              = s__((string)($row['hawb'] ?? ''));
$d_booking          = s__((string)($row['declaration_no'] ?? ''));
$d_payment_terms    = s__((string)($row['payment_terms'] ?? ''));
$d_container_details = s__((string)($row['container_details'] ?? ''));
$d_vessel_departure = $vessel_departure;
$d_flight_departure = $flight_departure;
$d_shipping_date    = $shipping_date;
$d_job_completion   = $job_completion;

$d_commodity        = s__((string)($row['commodity_type'] ?? ''));
$d_containers       = s__((string)($row['no_of_containers'] ?? ''));
$d_container_type   = s__($container_type_label);
$d_container_number = s__((string)($row['container_number'] ?? ''));
$d_gross_weight     = s__((string)($row['gross_weight'] ?? ''));
$d_volume_weight    = s__((string)($row['volume_weight'] ?? ''));
$d_chargeable_weight = s__((string)($row['chargeable_weight'] ?? ''));
$d_no_pieces        = s__((string)($row['no_of_pieces'] ?? ''));
$d_insurance        = ((string)($row['insurance_needed'] ?? '') === '1') ? 'Yes' : 'No';
$d_temp_control     = ((string)($row['temperature_control_required'] ?? '') === '1') ? 'Yes' : 'No';
$d_special_comments = s__((string)($row['special_comments'] ?? ''));

$d_subtotal         = number_format((float)($row['grand_subtotal'] ?? 0), 2);
$d_discount_type    = s__((string)($row['grand_discount_type'] ?? ''));
$d_discount_amount  = number_format((float)($row['grand_discount_amount'] ?? 0), 2);
$d_after_discount   = number_format((float)($row['grand_after_discount'] ?? 0), 2);
$d_tax              = number_format((float)($row['grand_tax'] ?? 0), 2);
$d_total            = number_format((float)($row['grand_total'] ?? 0), 2);
$d_currency         = s__((string)($row['currency'] ?? ''));
$d_exchange_rate    = s__((string)($row['exchange_rate'] ?? ''));
$d_est_invoice      = number_format((float)($row['estimated_invoice_amount'] ?? 0), 2);
$d_est_net_profit   = number_format((float)($row['estimated_net_profit'] ?? 0), 2);
$d_supplier_rate    = s__((string)($row['supplier_rate'] ?? ''));
$d_subtotal_after   = s__((string)($row['grand_subtotal'] ?? '0'));

$d_billing_country  = s__($billing_country);
$d_billing_city     = s__((string)($row['billing_city'] ?? ''));
$d_billing_state    = s__((string)($row['billing_state'] ?? ''));
$d_billing_code     = s__((string)($row['billing_code'] ?? ''));

$d_happy            = ((string)($row['happy_customer'] ?? '') === 'yes') ? 'Yes' : 'No';
$d_unhappy          = s__((string)($row['unhappy_reason'] ?? ''));
$d_ontime           = ((string)($row['shipment_on_time'] ?? '') === 'yes') ? 'Yes' : 'No';
$d_referral         = s__((string)($row['referral'] ?? ''));

$d_notes            = s__((string)($row['notes'] ?? ''));
$d_terms            = s__((string)($row['terms_and_conditions'] ?? ''));
$d_customer_notes   = s__((string)($row['customer_notes'] ?? ''));

$d_created_by       = s__($created_by_name);
$d_modified_by      = s__($modified_by_name);
$d_quote_id         = s__((string)($row['quote_id'] ?? ''));
$d_project_id       = s__((string)($row['project_id'] ?? ''));
$d_customer_type    = s__((string)($row['customer_type'] ?? ''));
$d_books_customer_id = s__((string)($row['books_customer_id'] ?? ''));

// Job items
$jobItems = $db->fetchAll(
    "SELECT dim_length, dim_width, dim_height, dim_pcs, dim_volume, dim_cbm FROM `" . DB::JOB_ITEMS . "` WHERE job_id = :job_id AND organization_id = :org_id",
    ['job_id' => $job_id, 'org_id' => $row['organization_id'] ?? 1]
);

// Company info
$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

$warehouse_info = '';
$row_warehouse = $db->fetchOne("SELECT * FROM `erp_organizations` WHERE id = :id", ['id' => $warehouse_id]);
if ($row_warehouse) {
    $wname   = s__($row_warehouse['warehouse_no'] ?? '');
    $wname2  = s__($row_warehouse['warehouse_name'] ?? '');
    $wstreet1 = s__($row_warehouse['street1'] ?? '');
    $wstreet2 = s__($row_warehouse['street2'] ?? '');
    $wcountry = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row_warehouse['country'] ?? 0));
    $wstate   = getTableAttr('state', DB::GEO_STATES, (int)($row_warehouse['state'] ?? 0));
    $wphone   = s__($row_warehouse['phone'] ?? '');
    $wemail   = s__($row_warehouse['email'] ?? '');
    $wtrn     = s__($row_warehouse['trn'] ?? '');

    $warehouse_info .= (!empty($wname2)  ? '<strong>'.$wname2.'</strong><br />' : '');
    $warehouse_info .= (!empty($wname)   ? $wname.'<br />' : '');
    $warehouse_info .= (!empty($wstreet1) ? $wstreet1.'<br />' : '');
    $warehouse_info .= (!empty($wstreet2) ? $wstreet2.'<br />' : '');
    $warehouse_info .= (!empty($wstate)  ? $wstate.', ' : '');
    $warehouse_info .= (!empty($wcountry) ? $wcountry.'<br />' : '');
    $warehouse_info .= (!empty($wphone)  ? $wphone.'<br />' : '');
    $warehouse_info .= (!empty($wemail)  ? $wemail.'<br />' : '');
    $warehouse_info .= (!empty($wtrn)    ? $wtrn : '');
}

// ================================================================
// HEADER: Company Name + Warehouse Info
// ================================================================
$tbl = <<<EOD
<table cellpadding="0" cellspacing="2" border="0">
<tr>
    <td width="392" style="background-color:#fff;" align="center"> <br /><br /><br />
        <span style="font-size:18px; color:#102B44"> $company_name </span>
    </td>
    <td width="272" align="right">
        $warehouse_info
    </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// TITLE: JOB DETAILS
// ================================================================
$tbl = <<<EOD
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td width="275" style="background-color:#f1f1f1;"></td>
    <td width="120" align="center"><span style="color:#007B8B; font-size:16px; font-weight:bold;">JOB DETAILS</span></td>
    <td width="275" style="background-color:#f1f1f1;"></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Job Info
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Job #</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_no</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Job Date</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_date</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Reference #</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_ref</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Status</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_status</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Job Seq</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_seq</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Quotation #</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_quotation_id</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Customer & Support
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Customer</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_customer</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Carrier</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_carrier</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Sales Person</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_sales_person</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Sales (Lead)</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_sales_lead</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">CS Agent</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_cs_agent</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Job Owner</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_owner</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Warehouse</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_warehouse</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Email</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_email</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Incoterm</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_incoterm</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Subject</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_subject</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Tags</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_tags</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Services</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_services</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Route (Origin & Destination)
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Loading Country</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_loading_country</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Loading Place</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_loading_place</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Port of Loading</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_port_loading</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Destination Country</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_dest_country</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Port of Destination</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_port_dest</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">FDP</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_fdp</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipping Country</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipping_country</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipping City</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipping_city</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipping State</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipping_state</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipping Code</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipping_code</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Transport
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipment Type</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipment_type</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Transport Mode</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_transport_mode</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">ETD</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_etd</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">ETA</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_eta</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Vessel Name</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_vessel</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Vessel Departure</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_vessel_departure</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Flight No</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_flight</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Flight Departure</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_flight_departure</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">MBL No</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_mbl</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">HBL No</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_hbl</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Customs Declaration No</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_booking</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Payment Terms</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_payment_terms</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipping Date</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_shipping_date</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Job Completion</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_job_completion</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Container Details</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_container_details</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Commodity Details
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Commodity Type</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_commodity</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">No of Containers</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_containers</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Container Type</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_container_type</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Container Number</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_container_number</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Gross Weight</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_gross_weight</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Volume Weight</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_volume_weight</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Chargeable Weight</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_chargeable_weight</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">No of Pieces</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_no_pieces</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Insurance Needed</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_insurance</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Temperature Control</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_temp_control</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Special Comments</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_special_comments</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Dimensions (Items Table)
// ================================================================
if (!empty($jobItems)) {
    $item_rows = '';
    $total_cbm = 0;
    $row_no = 1;
    $total_vol = 0;
    foreach ($jobItems as $item) {
        $cbm_val = (float)($item['dim_cbm'] ?? 0);
        $total_cbm += $cbm_val;
        $vol_val = (float)($item['dim_volume'] ?? 0);
        $total_vol += $vol_val;
        $d_item_cbm = number_format($cbm_val, 4);
        $item_rows .= '
        <tr>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . $row_no++ . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . s__((string)$item['dim_length']) . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . s__((string)$item['dim_width']) . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . s__((string)$item['dim_height']) . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . s__((string)$item['dim_pcs']) . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . s__((string)$item['dim_volume']) . '</span></td>
            <td align="center" style="border:1px solid #f1f1f1"><span style="color:#555;">' . $d_item_cbm . '</span></td>
        </tr>';
    }
    $d_total_vol = number_format($total_vol, 2);
    $d_total_cbm = number_format($total_cbm, 4);

    $tbl = <<<EOD
    <table cellpadding="5" cellspacing="0" border="0">
    <tr><td></td></tr>
    <tr>
        <td width="50" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">#</span></td>
        <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">Length (cm)</span></td>
        <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">Width (cm)</span></td>
        <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">Height (cm)</span></td>
        <td width="70" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">Pcs</span></td>
        <td width="110" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">Volume (cm&sup3;)</span></td>
        <td width="110" style="background-color:#e8f7f4; border:1px solid #f1f1f1;" align="center"><span style="color:#555;">CBM</span></td>
    </tr>
    $item_rows
    <tr>
        <td colspan="5" align="right" style="border:1px solid #f1f1f1;"><strong><span style="color:#555;">Total CBM</span></strong></td>
        <td align="center" style="border:1px solid #f1f1f1;"><strong><span style="color:#555;">$d_total_vol</span></strong></td>
        <td align="center" style="border:1px solid #f1f1f1;"><strong><span style="color:#555;">$d_total_cbm</span></strong></td>
    </tr>
    </table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

// ================================================================
// SECTION: Billing & Financial
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Subtotal</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_subtotal</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Discount</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_discount_amount</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">After Discount</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_after_discount</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Tax</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_tax</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Total</span></td>
    <td style="border:1px solid #f1f1f1;"><strong><span style="color:#555;">$d_total</span></strong></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Currency</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_currency</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Exchange Rate</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_exchange_rate</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Supplier Rate</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_supplier_rate</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Est. Invoice Amount</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_est_invoice</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Est. Net Profit</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_est_net_profit</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Billing Country</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_billing_country</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Billing City</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_billing_city</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Billing State</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_billing_state</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Billing Code</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_billing_code</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: After Service
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Happy Customer</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_happy</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Shipment On Time</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_ontime</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Unhappy Reason</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_unhappy</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Referral</span></td>
    <td colspan="3" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_referral</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: System Fields
// ================================================================
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Created By</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_created_by</span></td>
    <td width="100" style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Modified By</span></td>
    <td width="235" style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_modified_by</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Quote ID</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_quote_id</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Project ID</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_project_id</span></td>
</tr>
<tr>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Customer Type</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_customer_type</span></td>
    <td style="background-color:#e8f7f4; border:1px solid #f1f1f1;"><span style="color:#555;">Books Cust ID</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color:#555;">$d_books_customer_id</span></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// ================================================================
// SECTION: Notes & Terms
// ================================================================
$notes_content = '';
if (!empty($d_notes)) {
    $notes_content .= '<tr><td colspan="4" style="border:1px solid #f1f1f1; padding:6px 5px;"><span style="color:#555;">' . nl2br($d_notes) . '</span></td></tr>';
}
if (!empty($d_customer_notes)) {
    $notes_content .= '<tr><td colspan="4" style="border:1px solid #f1f1f1; padding:6px 5px;"><span style="color:#555;"><strong>Customer Notes:</strong> ' . nl2br($d_customer_notes) . '</span></td></tr>';
}
if (!empty($d_terms)) {
    $notes_content .= '<tr><td colspan="4" style="border:1px solid #f1f1f1; padding:6px 5px;"><span style="color:#555;"><strong>Terms & Conditions:</strong> ' . nl2br($d_terms) . '</span></td></tr>';
}

if (!empty($notes_content)) {
    $tbl = <<<EOD
    <table cellpadding="5" cellspacing="0" border="0">
    <tr><td></td></tr>
    $notes_content
    </table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

// ================================================================
// OUTPUT
// ================================================================
$salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
$encrypted_filename = hash('sha256', $salt . $job_id);

$pdf->Output($encrypted_filename, 'I');
