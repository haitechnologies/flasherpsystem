<?php
/**
 * Purchase Order PDF Generator
 * Generates PDF documents for purchase orders in FlashERP
 */

require_once __DIR__ . '/admin_elements/error_handler_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\DB;
use App\Core\Session;
require_once __DIR__ . '/../config/session.php';
startDashboardSession();
$activeOrgId = Session::orgId();
header("Content-Type: text/html; charset=utf-8");
require('../config/globals.php');
require('../config/database.php');
include('admin_elements/error_logger.php');

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

require_once('../tcpdf/examples/tcpdf_include.php');

if (!isset($_REQUEST['token']) || empty($_REQUEST['token'])) {
    die('Invalid request');
}

$id = isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? (int)e_s__($_REQUEST['id']) : 0;
if ($id <= 0) die('Invalid PO ID');

$salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
$sent_token = hash("sha512", 'bushogai' . $id);
$token = isset($_REQUEST['token']) ? e_s__($_REQUEST['token']) : '';

if ($token != $sent_token) die('Invalid token');

$po_result = $mysqli->query("SELECT id, vendor_id, warehouse_id, purchase_order_no, purchase_order_date, expiry_date, purchase_order_status, reference_no, grand_subtotal, grand_tax, grand_total, vendor_notes, terms_and_conditions FROM `" . tbl_purchase_orders . "` WHERE id=$id AND organization_id=$activeOrgId");
if (!$po_result || $po_result->num_rows == 0) die('Purchase Order not found');

$po_data = $po_result->fetch_assoc();
$vendor_id = s__($po_data['vendor_id']);
$po_number = s__($po_data['purchase_order_no']);
$po_date = s__($po_data['purchase_order_date']);
$due_date = s__($po_data['expiry_date']);
$status = s__($po_data['purchase_order_status'] ?? 'draft');
$terms_and_conditions = s__($po_data['terms_and_conditions'] ?? '');
$reference_no = s__($po_data['reference_no'] ?? '');
$grand_subtotal = s__($po_data['grand_subtotal'] ?? 0);
$grand_tax = s__($po_data['grand_tax'] ?? 0);
$grand_total = s__($po_data['grand_total'] ?? 0);
$vendor_notes = s__($po_data['vendor_notes'] ?? '');
$warehouse_id = s__($po_data['warehouse_id'] ?? '');

$vendor_name = getTableAttr('display_name', tbl_vendors, $vendor_id);
$vendor_email = getTableAttr('email', tbl_vendors, $vendor_id);
$vendor_phone = getTableAttr('phone', tbl_vendors, $vendor_id);
$vendor_trn = getTableAttr('trn', tbl_vendors, $vendor_id);

$vendor_address = '';
$vendor_city = '';
$vendor_state = '';
$vendor_country = '';
$addr_result = $mysqli->query("SELECT address_line1, address_line2, city, state, country FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendor_id AND type='billing'");
if ($addr_result && $addr_result->num_rows > 0) {
    $addr = $addr_result->fetch_assoc();
    $vendor_address = s__($addr['address_line1'] ?? '') . (($addr['address_line2'] ?? '') ? ', ' . s__($addr['address_line2']) : '');
    $vendor_city = s__($addr['city'] ?? '');
    $vendor_state = s__($addr['state'] ?? '');
    $vendor_country = s__($addr['country'] ?? '');
}

$items_result = $mysqli->query("SELECT description, qty, rate, tax, tax_amount FROM `" . tbl_purchase_order_items . "` WHERE purchase_order_id=$id ORDER BY id");
$po_items = [];
while ($item = $items_result->fetch_assoc()) {
    $po_items[] = $item;
}

$company_name = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="company_name"'));
$company_phone = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="company_phone"'));
$company_email = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="company_email"'));

$warehouse_information = '';
if (!empty($warehouse_id)) {
    $rs_warehouse = $mysqli->query("SELECT phone, email, trn FROM `" . tbl_warehouses . "` WHERE id=$warehouse_id");
    if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
        $row_warehouse = $rs_warehouse->fetch_array();
        $warehouse_phone = s__($row_warehouse['phone'] ?? '');
        $warehouse_email = s__($row_warehouse['email'] ?? '');
        $warehouse_trn = s__($row_warehouse['trn'] ?? '');

        $warehouse_information .= (!empty($warehouse_phone) ? $warehouse_phone . '<br />' : '');
        $warehouse_information .= (!empty($warehouse_email) ? $warehouse_email . '<br />' : '');
        $warehouse_information .= (!empty($warehouse_trn) ? $warehouse_trn : '');
    }
}

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor($company_name);
$pdf->setTitle('Purchase Order #' . $po_number);
$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
$pdf->SetMargins(10, 3, 10, true);
$pdf->setFooterMargin(5);
$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
$pdf->setFont('dejavusans', '', 14, '', true);
$pdf->setPrintHeader(false);
$pdf->AddPage();
$pdf->setFont('helvetica', '', 8);

$tbl = <<<EOD
<table cellpadding="0" cellspacing="2" border="0">
<tr>
    <td width="392" style="background-color: #fff;">
        <img src="../images/images.jfif" height="55" alt="Logo" /><br />
        <span style="font-size: 18px; color:#102B44"> $company_name </span>
    </td>
    <td width="272" align="right">
        $warehouse_information
    </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td width="275" style="background-color: #f1f1f1;"></td>
    <td width="120" align="center"><span style="color: #007B8B; font-size: 16px; font-weight: bold;">PURCHASE ORDER</span></td>
    <td width="275" style="background-color: #f1f1f1;"></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
    <td width="335"><strong>VENDOR</strong><br />$vendor_name</td>
    <td width="335" align="right"><strong>PO NUMBER</strong><br /> $po_number</td>
</tr>
<tr>
    <td width="325">
        <table>
        <tr><td>Email: $vendor_email</td></tr>
        <tr><td>Phone: $vendor_phone</td></tr>
        <tr><td>TRN: $vendor_trn</td></tr>
        <tr><td>Address: $vendor_address</td></tr>
        <tr><td>City: $vendor_city</td></tr>
        <tr><td>State: $vendor_state</td></tr>
        <tr><td>Country: $vendor_country</td></tr>
        </table>
    </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">PO Date </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Expected Delivery </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Status </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Ref Number </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$po_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$due_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$status </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$reference_no </span> </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$item_row = '';
$item_no = 1;
foreach ($po_items as $item) {
    $description = (string)($item['description'] ?? 'N/A');
    $quantity = (float)($item['qty'] ?? 0);
    $rate = (float)($item['rate'] ?? 0);
    $tax = (float)($item['tax'] ?? 0);
    $tax_amount = (float)($item['tax_amount'] ?? 0);
    $total = $quantity * $rate + $tax_amount;

    $quantity = (($quantity == 1) ? '1.00' : number_format($quantity, 2));
    $rate = (($rate == 0) ? '0.00' : number_format($rate, 2));
    $tax = (($tax == 0) ? '0' : $tax);
    $tax_amount = (($tax == 0) ? '0.00' : number_format($tax_amount, 2));
    $total = number_format($total, 2);

    $item_row .= <<<EOD
<tr>
    <td width="50" style="border:1px solid #f1f1f1;"><span style="color: #555;">$item_no</span></td>
    <td width="194" style="border:1px solid #f1f1f1;"><span style="color: #555;">$description</span></td>
    <td width="80" style="border:1px solid #f1f1f1;" align="right"><span style="color: #555;">$quantity</span></td>
    <td width="80" style="border:1px solid #f1f1f1;" align="right"><span style="color: #555;">$rate</span></td>
    <td width="80" style="border:1px solid #f1f1f1;" align="right"><span style="color: #555;">$tax%</span></td>
    <td width="80" style="border:1px solid #f1f1f1;" align="right"><span style="color: #555;">$tax_amount</span></td>
    <td width="100" style="border:1px solid #f1f1f1;" align="right"><span style="color: #555;">$total</span></td>
</tr>
EOD;
    $item_no++;
}

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="50" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> # </span> </td>
    <td width="194" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> Description </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Qty </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Rate </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Tax% </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Tax </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Amount </span> </td>
</tr>

$item_row

<tr>
<td colspan="3"><span style="color: #555;">Thanks for your business.</span></td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" " align="right"> Sub Total </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" " align="right"> Tax </td>
<td align="right"> $grand_tax  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style="background-color: #007B8B; color: white;" align="right"><strong> Total </strong></td>
<td style="background-color: #007B8B; color: white;" align="right"><strong> $grand_total </strong></td>
</tr>

</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$bank_name = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="bank_name"'));
$account_number = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="account_number"'));
$iban = s__(getTableAttrv('setting_value', tbl_system_settings, 'setting_slug ="iban"'));

if (!empty($bank_name)) {
    $bank_info = "Bank Name: " . $bank_name;
    if (!empty($account_number)) $bank_info .= " | Account Number: " . $account_number;
    if (!empty($iban)) $bank_info .= " | IBAN: " . $iban;

    $tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="670" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;"><strong>Bank Details</strong></span></td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$bank_info</span></td>
</tr>
</table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

if (!empty($terms_and_conditions)) {
    $tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="670" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;"><strong>Terms & Conditions</strong></span></td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$terms_and_conditions</span></td>
</tr>
</table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

if (!empty($vendor_notes)) {
    $tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="670" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;"><strong>Notes</strong></span></td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$vendor_notes</span></td>
</tr>
</table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

$encrypted_filename = hash('sha256', $salt . $id);

$pdfs_dir = dirname(__DIR__) . '/pdfs_purchase_orders';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';
if ($save_mode) {
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . tbl_purchase_orders . "` SET pdf = '" . $mysqli->real_escape_string($encrypted_filename) . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . tbl_purchase_orders . "` SET pdf = '" . $mysqli->real_escape_string($encrypted_filename) . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');
