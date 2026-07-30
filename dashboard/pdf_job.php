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
$pdf->setFont('helvetica', '', 9);
$pdf->setPrintHeader(false);
$pdf->AddPage();

$pdf->setFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'JOB DETAILS', 0, 1, 'C');
$pdf->Ln(2);

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

$customer_id = (int)($row['customer_id'] ?? 0);
$job_status_id = (int)($row['job_status'] ?? 0);
$carrier_id = (int)($row['carrier'] ?? 0);
$landing_port_id = (int)($row['landing_port'] ?? 0);
$destination_port_id = (int)($row['destination_port'] ?? 0);

$customer_name = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
$status_label = getTableAttr('job_status', DB::JOB_STATUSES, $job_status_id);
$carrier_name = getTableAttr('carrier_name', DB::CARRIERS, $carrier_id);
$landing_port_name = getTableAttr('port_name', DB::PORTS, $landing_port_id);
$destination_port_name = getTableAttr('port_name', DB::PORTS, $destination_port_id);
$landing_country = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['landing_country'] ?? 0));
$destination_country = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['destination_country'] ?? 0));
$shipping_country = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['shipping_country'] ?? 0));
$billing_country = getTableAttr('country', DB::GEO_COUNTRIES, (int)($row['billing_country'] ?? 0));
$container_type_label = getTableAttr('container_type', DB::CONTAINER_TYPES, (int)($row['container_type'] ?? 0));

$job_date = $row['job_date'] ?? '';
$job_date = ($job_date === '1970-01-01') ? '' : processDateYtoD($job_date);
$etd = $row['etd'] ?? '';
$etd = ($etd === '1970-01-01') ? '' : processDateYtoD($etd);
$eta = $row['eta'] ?? '';
$eta = ($eta === '1970-01-01') ? '' : processDateYtoD($eta);

// Items
$jobItems = $db->fetchAll(
    "SELECT dim_length, dim_width, dim_height, dim_pcs, dim_volume, dim_cbm FROM `" . DB::JOB_ITEMS . "` WHERE job_id = :job_id AND organization_id = :org_id",
    ['job_id' => $job_id, 'org_id' => $row['organization_id'] ?? 1]
);

// ==================== HEADER TABLE ====================
$pdf->setFont('helvetica', 'B', 10);
$tbl = '
<style>
    table { border-collapse: collapse; font-size: 9pt; width: 100%; }
    td, th { padding: 3px 5px; border: 1px solid #444; }
    .label { background-color: #E5E7EB; font-weight: bold; width: 28%; }
    .value { width: 22%; }
    .section-title { background-color: #0c83ff; color: #fff; font-weight: bold; text-align: center; font-size: 10pt; }
    .item-header { background-color: #E5E7EB; font-weight: bold; text-align: center; }
    .item-cell { text-align: center; }
    .right { text-align: right; }
</style>

<table cellpadding="4">
    <tr>
        <td class="label">Job #</td>
        <td class="value">' . s__($row['job_no']) . '</td>
        <td class="label">Reference #</td>
        <td class="value">' . s__($row['job_ref_no']) . '</td>
    </tr>
    <tr>
        <td class="label">Job Date</td>
        <td class="value">' . $job_date . '</td>
        <td class="label">Status</td>
        <td class="value">' . s__($status_label) . '</td>
    </tr>
    <tr>
        <td class="label">Customer</td>
        <td class="value">' . s__($customer_name) . '</td>
        <td class="label">Carrier</td>
        <td class="value">' . s__($carrier_name) . '</td>
    </tr>
    <tr>
        <td class="label">Shipment Type</td>
        <td class="value">' . s__($row['shipment_type']) . '</td>
        <td class="label">Transport Mode</td>
        <td class="value">' . s__($row['transport_mode']) . '</td>
    </tr>
    <tr>
        <td class="label">ETD</td>
        <td class="value">' . $etd . '</td>
        <td class="label">ETA</td>
        <td class="value">' . $eta . '</td>
    </tr>
    <tr>
        <td class="label">Incoterm</td>
        <td class="value">' . s__($row['incoterm']) . '</td>
        <td class="label">Currency</td>
        <td class="value">' . s__($row['currency']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($tbl, true, false, false, false, '');
$pdf->Ln(2);

// ==================== ORIGIN & DESTINATION ====================
$tbl2 = '<table cellpadding="4">
    <tr><td colspan="4" class="section-title">ORIGIN & DESTINATION</td></tr>
    <tr>
        <td class="label">Loading Country</td>
        <td class="value">' . s__($landing_country) . '</td>
        <td class="label">Loading Place</td>
        <td class="value">' . s__($row['loading_place']) . '</td>
    </tr>
    <tr>
        <td class="label">Port of Loading</td>
        <td class="value">' . s__($landing_port_name) . '</td>
        <td class="label">Destination Country</td>
        <td class="value">' . s__($destination_country) . '</td>
    </tr>
    <tr>
        <td class="label">Port of Destination</td>
        <td class="value">' . s__($destination_port_name) . '</td>
        <td class="label">FDP</td>
        <td class="value">' . s__($row['fdp']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($tbl2, true, false, false, false, '');
$pdf->Ln(2);

// ==================== COMMODITY DETAILS ====================
$insurance = s__($row['insurance_needed']) === '1' ? 'Yes' : 'No';
$temp_control = s__($row['temperature_control_required']) === '1' ? 'Yes' : 'No';

$tbl3 = '<table cellpadding="4">
    <tr><td colspan="4" class="section-title">COMMODITY DETAILS</td></tr>
    <tr>
        <td class="label">Commodity Type</td>
        <td class="value">' . s__($row['commodity_type']) . '</td>
        <td class="label">No of Containers</td>
        <td class="value">' . s__((string)$row['no_of_containers']) . '</td>
    </tr>
    <tr>
        <td class="label">Container Type</td>
        <td class="value">' . s__($container_type_label) . '</td>
        <td class="label">Container Number</td>
        <td class="value">' . s__($row['container_number']) . '</td>
    </tr>
    <tr>
        <td class="label">Gross Weight</td>
        <td class="value">' . s__((string)$row['gross_weight']) . '</td>
        <td class="label">Volume Weight</td>
        <td class="value">' . s__((string)$row['volume_weight']) . '</td>
    </tr>
    <tr>
        <td class="label">Chargeable Weight</td>
        <td class="value">' . s__((string)$row['chargeable_weight']) . '</td>
        <td class="label">No of Pieces</td>
        <td class="value">' . s__((string)$row['no_of_pieces']) . '</td>
    </tr>
    <tr>
        <td class="label">Insurance Needed</td>
        <td class="value">' . $insurance . '</td>
        <td class="label">Temperature Control</td>
        <td class="value">' . $temp_control . '</td>
    </tr>
</table>';

$pdf->writeHTML($tbl3, true, false, false, false, '');
$pdf->Ln(2);

// ==================== SHIPPING DETAILS ====================
$tbl4 = '<table cellpadding="4">
    <tr><td colspan="4" class="section-title">SHIPPING DETAILS</td></tr>
    <tr>
        <td class="label">Vessel Name</td>
        <td class="value">' . s__($row['vessel_name']) . '</td>
        <td class="label">Flight No</td>
        <td class="value">' . s__($row['flight_no']) . '</td>
    </tr>
    <tr>
        <td class="label">MBL No</td>
        <td class="value">' . s__($row['mawb']) . '</td>
        <td class="label">HBL No</td>
        <td class="value">' . s__($row['hawb']) . '</td>
    </tr>
    <tr>
        <td class="label">Booking No</td>
        <td class="value">' . s__($row['declaration_no']) . '</td>
        <td class="label">Payment Terms</td>
        <td class="value">' . s__($row['payment_terms']) . '</td>
    </tr>
</table>';

$pdf->writeHTML($tbl4, true, false, false, false, '');
$pdf->Ln(2);

// ==================== JOB ITEMS (DIMENSIONS) ====================
if (!empty($jobItems)) {
    $tbl5 = '<table cellpadding="4">
        <tr><td colspan="6" class="section-title">DIMENSIONS (L x W x H)</td></tr>
        <tr>
            <td class="item-header" width="16%">Length (cm)</td>
            <td class="item-header" width="16%">Width (cm)</td>
            <td class="item-header" width="16%">Height (cm)</td>
            <td class="item-header" width="15%">Pcs</td>
            <td class="item-header" width="18%">Volume (cm³)</td>
            <td class="item-header" width="19%">CBM</td>
        </tr>';

    $total_cbm = 0;
    foreach ($jobItems as $item) {
        $cbm_val = (float)($item['dim_cbm'] ?? 0);
        $total_cbm += $cbm_val;
        $tbl5 .= '<tr>
            <td class="item-cell">' . s__((string)$item['dim_length']) . '</td>
            <td class="item-cell">' . s__((string)$item['dim_width']) . '</td>
            <td class="item-cell">' . s__((string)$item['dim_height']) . '</td>
            <td class="item-cell">' . s__((string)$item['dim_pcs']) . '</td>
            <td class="item-cell">' . s__((string)$item['dim_volume']) . '</td>
            <td class="item-cell">' . number_format($cbm_val, 4) . '</td>
        </tr>';
    }

    $tbl5 .= '<tr>
        <td colspan="5" class="right" style="font-weight:bold;">Total CBM</td>
        <td class="item-cell" style="font-weight:bold;">' . number_format($total_cbm, 4) . '</td>
    </tr>
    </table>';

    $pdf->writeHTML($tbl5, true, false, false, false, '');
    $pdf->Ln(2);
}

// ==================== BILLING ====================
$tbl6 = '<table cellpadding="4">
    <tr><td colspan="4" class="section-title">BILLING</td></tr>
    <tr>
        <td class="label">Subtotal</td>
        <td class="value">' . number_format((float)($row['grand_subtotal'] ?? 0), 2) . '</td>
        <td class="label">Discount</td>
        <td class="value">' . number_format((float)($row['grand_discount_amount'] ?? 0), 2) . '</td>
    </tr>
    <tr>
        <td class="label">Tax</td>
        <td class="value">' . number_format((float)($row['grand_tax'] ?? 0), 2) . '</td>
        <td class="label">Total</td>
        <td class="value" style="font-weight:bold;">' . number_format((float)($row['grand_total'] ?? 0), 2) . '</td>
    </tr>
</table>';

$pdf->writeHTML($tbl6, true, false, false, false, '');
$pdf->Ln(2);

// ==================== NOTES ====================
$notes = s__($row['notes'] ?? '');
if (!empty($notes)) {
    $tbl7 = '<table cellpadding="4">
        <tr><td colspan="4" class="section-title">NOTES</td></tr>
        <tr><td colspan="4" style="padding:6px 5px;">' . nl2br(s__($notes)) . '</td></tr>
    </table>';
    $pdf->writeHTML($tbl7, true, false, false, false, '');
}

$salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
$encrypted_filename = hash('sha256', $salt . $job_id);

$pdf->Output($encrypted_filename, 'I');
