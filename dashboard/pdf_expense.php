<?php

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

ob_start();

require_once('../tcpdf/examples/tcpdf_include.php');

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologiesLLC');
$pdf->setTitle('Expense');
$pdf->setSubject('Expense');

$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

$pdf->SetMargins(10, 3, 10, true);
$pdf->setFooterMargin(5);

$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    require_once(dirname(__FILE__) . '/lang/eng.php');
    $pdf->setLanguageArray($l);
}

$pdf->setFontSubsetting(true);
$pdf->setFont('dejavusans', '', 14, '', true);

$pdf->setPrintHeader(false);

$pdf->AddPage();

$pdf->setFont('helvetica', '', 8);

$base_currency_code = BASE_CURRENCY['code'];

$id = e_s__($_REQUEST['id'] ?? '') ?? 0;
$token = e_s__($_REQUEST['token'] ?? '') ?? '';
if (empty($token)) {
    die('Invalid request');
}
$id = (int)$id;
if ($id <= 0) die('Invalid Expense ID');
$sent_token = hash("sha512", 'bushogai' . $id);
if ($token != $sent_token) die('Invalid token');

$result = $mysqli->query("SELECT * FROM `" . DB::EXPENSES . "` WHERE id=$id AND organization_id=$activeOrgId");
if (!$result || $result->num_rows == 0) die('Expense not found');
$row = $result->fetch_assoc();

$vendor_id          = s__($row['vendor_id']);
$vendor_name        = getTableAttr('display_name', DB::VENDORS, $vendor_id);
$vendor_trn         = getTableAttr('trn', DB::VENDORS, $vendor_id);
$vendor_phone       = getTableAttr('phone', DB::VENDORS, $vendor_id);

$reference_no       = s__($row['reference_no']);
if (empty($reference_no)) {
    $reference_no = 'EXP-' . $id;
}

$expense_date       = s__($row['expense_date']);
$expense_date       = dd_($expense_date);

$expense_status     = s__($row['expense_status']);
$grand_total        = s__($row['grand_total']);

$paid_through       = s__($row['paid_through']);
$paid_through_name  = '';
if (!empty($paid_through)) {
    $paid_through_name = getTableAttr('account_name', DB::ACCOUNTS, $paid_through);
}

$spell_out = '';
$f = new NumberFormatter("en", NumberFormatter::SPELLOUT);

if (!empty($grand_total)) {
    $spell_out = $f->format($grand_total);
    $spell_out = str_ireplace(' point ', '.', ucwords($spell_out));
    $spell_out .= ' ' . $base_currency_code;
}

$amount_in_words = ucwords($spell_out);

$row_no = 1;
$item_row = '';

$result_expense_items = $mysqli->query("SELECT * FROM `" . DB::EXPENSE_ITEMS . "` WHERE expense_id=$id");
$total_rows = $result_expense_items->num_rows;

if ($total_rows > 0) {
    while ($row_items = $result_expense_items->fetch_assoc()) {
        $account_name  = getTableAttr('account_name', DB::ACCOUNTS, $row_items['expense_account']);
        $description   = s__($row_items['description']);
        $total         = s__($row_items['total']);

        $item_row .= '<tr>';
        $item_row .= '<td style="border:1px solid #ddd; color:#555;">' . $description . '</td>';
        $item_row .= '<td style="border:1px solid #ddd; color:#555;">' . $account_name . '</td>';
        $item_row .= '<td style="border:1px solid #ddd; color:#555; text-align:right;">' . $base_currency_code . ' ' . number_format((!empty($total) ? $total : 0), 2) . '</td>';
        $item_row .= '</tr>';
    }
}

$logo = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"');

if (!empty($logo) && file_exists('../uploads/global_settings/thumbs/' . $logo)) {
    $display_logo = '../uploads/global_settings/' . s__($logo);
} else {
    $display_logo = $base_url . '../images/default_logo.png';
}

$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

$warehouse_id = 1;
$warehouse_information = '';

$rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id=$warehouse_id");
if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
    $row_warehouse = $rs_warehouse->fetch_assoc();

    $warehouse_no       = s__($row_warehouse['warehouse_no']);
    $warehouse_name     = s__($row_warehouse['warehouse_name']);
    $street1            = s__($row_warehouse['street1']);
    $street2            = s__($row_warehouse['street2']);

    $country            = s__($row_warehouse['country']);
    $country            = getTableAttr('country_name', DB::GEO_COUNTRIES, $country);

    $state              = s__($row_warehouse['state']);
    $state              = getTableAttr('state_name', DB::GEO_STATES, $state);

    $phone              = s__($row_warehouse['phone']);
    $email              = s__($row_warehouse['email']);
    $trn                = s__($row_warehouse['trn']);

    $warehouse_information .= (!empty($warehouse_name) ? '<strong>' . $warehouse_name . '</strong><br />' : '');
    $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
    $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
    $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
    $warehouse_information .= (!empty($state) ? $state . ', ' : '');
    $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
    $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
    $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
    $warehouse_information .= (!empty($trn) ? 'TRN: ' . $trn : '');
}

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
<br />
<table cellpadding="4" cellspacing="0" border="0">
<tr>
    <td width="664" align="center">
        <span style="font-size: 16px; color:#102B44; font-weight: bold;">EXPENSE</span>
    </td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="1" style="width:100%; border-collapse:collapse;">
<tr>
    <td width="55%" style="border:1px solid #ddd; padding:8px; vertical-align:top;">
        <table cellpadding="2" cellspacing="0" style="width:100%;">
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555; width:40%;">Expense Date:</td>
                <td style="border:none; color:#333; font-weight:bold;">$expense_date</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555;">Reference Number:</td>
                <td style="border:none; color:#333; font-weight:bold;">$reference_no</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555;">Paid Through:</td>
                <td style="border:none; color:#333; font-weight:bold;">$paid_through_name</td>
            </tr>
        </table>
    </td>
    <td width="45%" style="border:1px solid #28a745; background-color:#28a745; padding:12px; text-align:center; vertical-align:middle;">
        <div style="color:#ffffff; font-size:9px; margin-bottom:4px;">
            AMOUNT
        </div>
        <div style="color:#ffffff; font-size:16px; font-weight:bold;">$base_currency_code $grand_total</div>
    </td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="0" style="width:100%;">
<tr>
    <td style="color: #666; font-size:10px; font-weight:bold;">Paid To</td>
</tr>
<tr>
    <td style="color: #333; font-size:12px; font-weight:bold;">$vendor_name</td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$item_table = '';
if ($total_rows > 0) {
    $item_table = '
<table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
<tr style="background-color:#f0f0f0; font-weight:bold;">
<td style="border:1px solid #ddd; color:#333;">Description</td>
<td style="border:1px solid #ddd; color:#333;">Account</td>
<td style="border:1px solid #ddd; color:#333; text-align:right;">Amount</td>
</tr>';
    $item_table .= $item_row;
    $item_table .= '</table>';
} else {
    $item_table = '<div style="text-align:center; color:#999; font-size:10px; padding:20px;">No expense items found</div>';
}

$tbl = <<<EOD
$item_table
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="0" style="width:100%;">
<tr>
    <td style="color: #555; font-size:10px;">
        <strong>Amount in Words:</strong><br />
        <span style="color:#333;">$amount_in_words</span>
    </td>
</tr>
</table>
<br /><br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="0">
<tr>
    <td width="332" align="center" style="border-top:1px solid #333; color: #555;">
        <br />
        <strong>Authorized Signature</strong>
    </td>
    <td width="332" align="center" style="border-top:1px solid #333; color: #555;">
        <br />
        <strong>Vendor Signature</strong>
    </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

ob_clean();

$encrypted_filename = \App\Helper\PdfHelper::filename((int)$id);
$pdfs_dir = dirname(__DIR__) . '/pdfs_expenses';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';
if ($save_mode) {
    $pdf->Output($pdf_path, 'F');
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}

$pdf->Output($encrypted_filename, 'I');
