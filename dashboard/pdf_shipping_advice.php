<?php

require_once __DIR__ . '/admin_elements/error_handler_init.php';

use App\Core\DB;

require_once __DIR__ . '/../config/session.php';
startDashboardSession();
header("Content-Type: text/html; charset=utf-8");
require('../config/globals.php');
require('../config/database.php');
include('admin_elements/error_logger.php');

// Register custom error/exception/shutdown handlers
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

include('../config/images.php');
include('admin_elements/grab_vars.php');

// Load TCPDF library
require_once('../tcpdf/examples/tcpdf_include.php');

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
$token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';

if (empty($token)) {
    header("Location: index.php");
    exit;
}

$sent_token = hash("sha512", 'bushogai' . $id);
if ($token != $sent_token) {
    die('Access Denied');
}

if (empty($id)) {
    die('Invalid ID');
}

// Fetch shipping advice
$result = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICES . "` WHERE id=$id");
$row = $result->fetch_array();
if (!$row) {
    die('Shipping Advice not found.');
}

$shipment_type        = s__($row['shipment_type']);
$destination_port     = s__($row['destination_port']);
$exit_point           = s__($row['exit_point']);
$transport_mode       = s__($row['transport_mode']);
$incoterm             = s__($row['incoterm']);
$invoice_date         = s__($row['invoice_date']);
$invoice_no           = s__($row['invoice_no']);
$awb_no               = s__($row['awb_no']);
$license_no           = s__($row['license_no']);
$mirsal_II_code       = s__($row['mirsal_II_code']);

$country_of_origin    = s__($row['country_of_origin']);
$grand_advice_qty     = s__($row['grand_advice_qty']);
$grand_advice_weight  = s__($row['grand_advice_weight']);
$currency             = s__($row['currency']);
$grand_advice_value   = s__($row['grand_advice_value']);
$payment_method       = s__($row['payment_method']);

$invoice_pkgs               = s__($row['invoice_pkgs']);
$invoice_pkgs_unit          = s__($row['invoice_pkgs_unit']);
$invoice_weight             = s__($row['invoice_weight']);
$invoice_weight_unit        = s__($row['invoice_weight_unit']);
$invoice_grand_qty          = s__($row['invoice_grand_qty']);
$invoice_grand_total_amount = s__($row['invoice_grand_total_amount']);

// Fetch advice items
$advice_items = [];
$result_advice = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_ADVICE_ITEMS . "` WHERE advice_id=$id");
while ($advice_row = $result_advice->fetch_assoc()) {
    $advice_items[] = $advice_row;
}

// Fetch invoice items
$invoice_items = [];
$result_invoice = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE advice_id=$id");
while ($invoice_row = $result_invoice->fetch_assoc()) {
    $invoice_items[] = $invoice_row;
}

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologies');
$pdf->setTitle('Shipping Advice - ' . $id);
$pdf->setSubject('Shipping Advice PDF Export');

$pdf->SetMargins(10, 10, 10, true);
$pdf->setFooterMargin(10);
$pdf->setAutoPageBreak(TRUE, 15);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    require_once(dirname(__FILE__) . '/lang/eng.php');
    $pdf->setLanguageArray($l);
}

$pdf->setFontSubsetting(true);
$pdf->setFont('dejavusans', '', 10, '', true);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);

$pdf->AddPage();

$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

// Main Document Header
$html = '
<table cellpadding="4" cellspacing="0" border="0" width="100%">
    <tr>
        <td width="50%">
            <span style="font-size: 18px; font-weight: bold; color: #102B44;">' . $company_name . '</span><br />
            <span style="font-size: 14px; font-weight: bold; color: #007B8B;">SHIPPING ADVICE</span>
        </td>
        <td width="50%" align="right">
            <strong>Advice ID:</strong> ' . $id . '<br />
            <strong>Date:</strong> ' . date("d M Y", strtotime($invoice_date)) . '
        </td>
    </tr>
</table>
<hr />
';

// Meta information table
$html .= '
<h3>Delivery Advice Summary</h3>
<table cellpadding="4" cellspacing="0" border="1" width="100%">
    <tr>
        <td width="20%"><strong>Customs Bill Type:</strong></td>
        <td width="30%">' . $shipment_type . '</td>
        <td width="20%"><strong>Destination:</strong></td>
        <td width="30%">' . $destination_port . '</td>
    </tr>
    <tr>
        <td><strong>Exit Point:</strong></td>
        <td>' . $exit_point . '</td>
        <td><strong>Mode:</strong></td>
        <td>' . $transport_mode . '</td>
    </tr>
    <tr>
        <td><strong>Shipment Terms:</strong></td>
        <td>' . $incoterm . '</td>
        <td><strong>Invoice Date:</strong></td>
        <td>' . $invoice_date . '</td>
    </tr>
    <tr>
        <td><strong>Invoice No:</strong></td>
        <td>' . $invoice_no . '</td>
        <td><strong>AWB No:</strong></td>
        <td>' . $awb_no . '</td>
    </tr>
    <tr>
        <td><strong>License No:</strong></td>
        <td>' . $license_no . '</td>
        <td><strong>Mirsal II Code:</strong></td>
        <td>' . $mirsal_II_code . '</td>
    </tr>
    <tr>
        <td><strong>Country of Origin:</strong></td>
        <td>' . $country_of_origin . '</td>
        <td><strong>Payment Method:</strong></td>
        <td>' . $payment_method . '</td>
    </tr>
</table>
<br /><br />
';

// Shipping Advice Goods/Items Table
$html .= '
<h3>Shipping Advice Items</h3>
<table cellpadding="4" cellspacing="0" border="1" width="100%">
    <thead>
        <tr style="background-color: #f1f1f1;">
            <th width="15%"><strong>HS Code</strong></th>
            <th width="45%"><strong>Description of Goods</strong></th>
            <th width="10%" align="center"><strong>Qty</strong></th>
            <th width="10%" align="center"><strong>Origin</strong></th>
            <th width="10%" align="right"><strong>Value</strong></th>
            <th width="10%" align="right"><strong>Weight</strong></th>
        </tr>
    </thead>
    <tbody>';

if (empty($advice_items)) {
    $html .= '<tr><td colspan="6" align="center">No advice items found.</td></tr>';
} else {
    foreach ($advice_items as $item) {
        $html .= '
        <tr>
            <td>' . s__($item['hs_code']) . '</td>
            <td>' . s__($item['description']) . '</td>
            <td align="center">' . s__($item['qty']) . '</td>
            <td align="center">' . s__($item['origin']) . '</td>
            <td align="right">' . s__($item['value']) . '</td>
            <td align="right">' . s__($item['weight']) . '</td>
        </tr>';
    }
}

$html .= '
        <tr style="background-color: #fafafa;">
            <td colspan="2"><strong>GRAND TOTAL</strong></td>
            <td align="center"><strong>' . $grand_advice_qty . '</strong></td>
            <td align="center"></td>
            <td align="right"><strong>' . $grand_advice_value . '</strong></td>
            <td align="right"><strong>' . $grand_advice_weight . '</strong></td>
        </tr>
    </tbody>
</table>
<br /><br />
';

// Shipping Invoice Details Table
$html .= '
<h3>Invoice Details</h3>
<table cellpadding="4" cellspacing="0" border="1" width="100%">
    <thead>
        <tr style="background-color: #f1f1f1;">
            <th width="8%"><strong>Serial</strong></th>
            <th width="40%"><strong>Description</strong></th>
            <th width="10%" align="center"><strong>Origin</strong></th>
            <th width="15%"><strong>Declaration No</strong></th>
            <th width="10%"><strong>HS Code</strong></th>
            <th width="7%" align="center"><strong>Qty</strong></th>
            <th width="10%" align="right"><strong>Price</strong></th>
        </tr>
    </thead>
    <tbody>';

if (empty($invoice_items)) {
    $html .= '<tr><td colspan="7" align="center">No invoice details found.</td></tr>';
} else {
    foreach ($invoice_items as $item) {
        $html .= '
        <tr>
            <td>' . s__($item['serial_no']) . '</td>
            <td>' . s__($item['description']) . '</td>
            <td align="center">' . s__($item['origin']) . '</td>
            <td>' . s__($item['declaration_no']) . '</td>
            <td>' . s__($item['hs_code']) . '</td>
            <td align="center">' . s__($item['qty']) . '</td>
            <td align="right">' . s__($item['unit_price']) . '</td>
        </tr>';
    }
}

$html .= '
    </tbody>
</table>
';

$pdf->writeHTML($html, true, false, false, false, '');

// Close and output PDF document
$pdf->Output('shipping_advice_' . $id . '.pdf', 'I');
