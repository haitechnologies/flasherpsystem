<?php
/**
 * Debit Note PDF Generator
 * Ported from flashlogisticsserver to flasherpsystem conventions
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

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('FlashLogistics');
$pdf->setTitle('Debit Note');
$pdf->setSubject('Debit Note');

$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

$pdf->SetMargins(10, 3, 10, true);
$pdf->setFooterMargin(5);
$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

$pdf->setFontSubsetting(true);
$pdf->setFont('dejavusans', '', 14, '', true);
$pdf->setPrintHeader(false);

$pdf->AddPage();

$pdf->setFont('helvetica', '', 8);

$base_currency_code = BASE_CURRENCY['code'];

$id = isset($_REQUEST['id']) && !empty($_REQUEST['id']) ? e_s__($_REQUEST['id']) : 0;
$token = isset($_REQUEST['token']) && !empty($_REQUEST['token']) ? e_s__($_REQUEST['token']) : '';

if (!isset($_REQUEST['token']) || empty($_REQUEST['token'])) {
    die('Invalid request');
}

$id = (int) $id;
if ($id <= 0) die('Invalid Debit Note ID');

$sent_token = hash("sha512", 'bushogai' . $id);
if ($token != $sent_token) die('Invalid token');

$row_bg = '';

$result = $mysqli->query("SELECT * FROM `" . DB::DEBIT_NOTES . "` WHERE id=$id AND organization_id=$activeOrgId");
if (!$result || $result->num_rows == 0) die('Debit Note not found');
$row = $result->fetch_array();

$vendor_id              = s__($row['vendor_id']);
$vendor_name            = getTableAttr('display_name', DB::VENDORS, $vendor_id);
$vendor_address         = getTableAttr('address', DB::VENDORS, $vendor_id);
$vendor_phone           = getTableAttr('phone', DB::VENDORS, $vendor_id);

$debit_note_no          = s__($row['debit_note_no']);
$debit_note_status      = s__($row['debit_note_status']);
$debit_note_date        = s__($row['debit_note_date']);
$reference_no           = s__($row['reference_no']);
$purchase_id            = s__($row['purchase_id']);
$warehouse_id           = s__($row['warehouse_id']);

$vendor_notes           = s__($row['vendor_notes']);
$terms_and_conditions   = s__($row['terms_and_conditions']);

$grand_subtotal         = s__($row['grand_subtotal']);
$grand_discount_type    = s__($row['grand_discount_type']);
$grand_discount_type_value = s__($row['grand_discount_type_value']);
$grand_discount_amount  = s__($row['grand_discount_amount']);
$grand_after_discount   = s__($row['grand_after_discount']);
$grand_tax              = s__($row['grand_tax']);
$grand_total            = s__($row['grand_total']);
$publish                = s__($row['publish']);

$debit_note_date = processDateYtoD($debit_note_date);

$purchase_no = '';
if (!empty($purchase_id) && $purchase_id != '0') {
    $purchase_no = getTableAttr('purchase_no', DB::PURCHASES, $purchase_id);
}

$spell_out = '';
$f = new NumberFormatter("en", NumberFormatter::SPELLOUT);

if (!empty($grand_total)) {
    $spell_out = $f->format($grand_total);
    $spell_out = str_ireplace(' point ', '.', ucwords($spell_out));

    if (str_contains($spell_out, '.')) {
        $spell_out .= $base_currency_code;
    }
}

$grand_total_in_words = ucwords($spell_out);

$billing_attention = '';
$billing_country = '';
$billing_address_line1 = '';
$billing_address_line2 = '';
$billing_city = '';
$billing_state = '';
$billing_zipcode = '';
$billing_phone = '';
$billing_fax = '';

$rs_billing = $mysqli->query("SELECT * FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendor_id AND type='billing' ");
if ($rs_billing && $rs_billing->num_rows > 0) {
    $row_billing = $rs_billing->fetch_array();
    $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');
    $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
    $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
    $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
    $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
    $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
    $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
    $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
    $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');
}

$shipping_attention = '';
$shipping_country = '';
$shipping_address_line1 = '';
$shipping_address_line2 = '';
$shipping_city = '';
$shipping_state = '';
$shipping_zipcode = '';
$shipping_phone = '';
$shipping_fax = '';

$rs_shipping = $mysqli->query("SELECT * FROM `" . DB::VENDOR_ADDRESSES . "` WHERE addressable_type='Vendor' AND addressable_id=$vendor_id AND type='shipping' ");
if ($rs_shipping && $rs_shipping->num_rows > 0) {
    $row_shipping = $rs_shipping->fetch_array();
    $shipping_attention      = (!empty($row_shipping['attention']) ? s__($row_shipping['attention']) : '');
    $shipping_country        = (!empty($row_shipping['country']) ? s__($row_shipping['country']) : '');
    $shipping_address_line1  = (!empty($row_shipping['address_line1']) ? s__($row_shipping['address_line1']) : '');
    $shipping_address_line2  = (!empty($row_shipping['address_line2']) ? s__($row_shipping['address_line2']) : '');
    $shipping_city           = (!empty($row_shipping['city']) ? s__($row_shipping['city']) : '');
    $shipping_state          = (!empty($row_shipping['state']) ? s__($row_shipping['state']) : '');
    $shipping_zipcode        = (!empty($row_shipping['zipcode']) ? s__($row_shipping['zipcode']) : '');
    $shipping_phone          = (!empty($row_shipping['phone']) ? s__($row_shipping['phone']) : '');
    $shipping_fax            = (!empty($row_shipping['fax']) ? s__($row_shipping['fax']) : '');
}

$row_no = 1;
$item_row = '';

$result_debit_note_items = $mysqli->query("SELECT * FROM `" . DB::DEBIT_NOTE_ITEMS . "` WHERE debit_note_id=$id");
$total_rows = $result_debit_note_items->num_rows;

if ($total_rows > 0) {
    while ($row_debit_note_items = $result_debit_note_items->fetch_array()) {
        $service        = $row_debit_note_items['service'];
        $service_name   = getTableAttr('item_name', DB::ITEMS, $service);

        $description    = $row_debit_note_items['description'];

        $qty            = $row_debit_note_items['qty'];
        $rate           = $row_debit_note_items['rate'];
        $tax            = $row_debit_note_items['tax'];
        $tax_amount     = $row_debit_note_items['tax_amount'];
        $total          = $row_debit_note_items['total'];

        $qty            = (($qty == 1)  ? '1.00': $qty);
        $rate           = (($rate == 0)  ? '1.00': $rate);
        $tax            = (($tax == 0)  ? '0': $tax);
        $tax_amount     = (($tax == 0)  ? '0.00': $tax_amount);

        $item_row .= '
        <tr>
            <td align="center" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $row_no++ . '</span> </td>
            <td align="left" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $service_name . ' ' . $description . '</span> </td>
            <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $qty . '</span> </td>
            <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $rate . '</span> </td>
            <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $tax . '%</span>  </td>
            <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $tax_amount . '</span>  </td>
            <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"> <span style="color: #555;">' . $total . '</span> </td>
        </tr>';
    }
}

$logo = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"');

if (!empty($logo) && file_exists('../uploads/global_settings/thumbs/' . $logo)) {
    $display_logo = '../uploads/global_settings/' . s__($logo);
} else {
    $display_logo = $base_url . '../images/default_logo.png';
}

$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

$warehouse_information = '';
$rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id=$warehouse_id");
if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
    $row_warehouse = $rs_warehouse->fetch_array();

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

    $warehouse_information .= (!empty($warehouse_name) ? '<strong>'.$warehouse_name . '</strong><br />' : '');
    $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
    $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
    $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
    $warehouse_information .= (!empty($state) ? $state . ', ' : '');
    $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
    $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
    $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
    $warehouse_information .= (!empty($trn) ? $trn : '');
}

$tbl = <<<EOD
<table cellpadding="0" cellspacing="2" border="0">
<tr>
    <td width="392" style="background-color: #fff;" align="center"> <br /><br /><br />
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
    <td width="120" align="center"><span style="color: #DC3545; font-size: 16px; font-weight: bold;">DEBIT NOTE</span></td>
    <td width="275" style="background-color: #f1f1f1;"></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">

<tr>
    <td width="335"><strong>DEBIT NOTE TO</strong><br />$vendor_name</td>
    <td width="335" align="rigth"><strong>DEBIT NOTE #</strong><br /> $debit_note_no</td>
</tr>

<tr>
    <td width="325">
        <table>
        <tr><td>Billing Address</td></tr>
        <tr><td>Attention: $billing_attention</td></tr>
        <tr><td>Country: $billing_country</td></tr>
        <tr><td>Address Line 1: $billing_address_line1</td></tr>
        <tr><td>Address Line 2: $billing_address_line2</td></tr>
        <tr><td>City: $billing_city</td></tr>
        <tr><td>State: $billing_state</td></tr>
        <tr><td>POBOX: $billing_zipcode</td></tr>
        <tr><td>Phone: $billing_phone</td></tr>
        <tr><td>Fax: $billing_fax</td></tr>
        </table>
    </td>

    <td width="325">
        <table>
        <tr><td>Shipping Address</td></tr>
        <tr><td>Attention: $shipping_attention</td></tr>
        <tr><td>Country: $shipping_country</td></tr>
        <tr><td>Address Line 1: $shipping_address_line1</td></tr>
        <tr><td>Address Line 2: $shipping_address_line2</td></tr>
        <tr><td>City: $shipping_city</td></tr>
        <tr><td>State: $shipping_state</td></tr>
        <tr><td>POBOX: $shipping_zipcode</td></tr>
        <tr><td>Phone: $shipping_phone</td></tr>
        <tr><td>Fax: $shipping_fax</td></tr>
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
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Debit Note Date </span> </td>
    <td width="200" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Reference No </span> </td>
    <td width="200" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Purchase No </span> </td>
    <td width="118" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Status </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$debit_note_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$reference_no </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$purchase_no </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$debit_note_status </span> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="50" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> # </span> </td>
    <td width="194" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> Item & Description </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Qty </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Rate </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1" align="right"> <span style="color: #555;"> Tax% </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1" align="right"> <span style="color: #555;"> Tax </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1" align="right"> <span style="color: #555;"> Amount </span> </td>
</tr>

$item_row

<tr>
<td colspan="3"><span style="color: #555;">Thank you for your business.</span></td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" " align="right"> Sub Total </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" align="right"> Standard Rate (5%) </td>
<td align="right"> $grand_tax  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> TOTAL DEBIT </strong> </td>
<td style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> $base_currency_code$grand_total </strong>  </td>
</tr>

<tr>
<td colspan="7" align="right"> Total in Words: <strong> UAE Dirham  $grand_total_in_words </strong> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td>Tax Summary</td></tr>
<tr>
    <td width="370" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> Tax Details </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Taxable Amount ($base_currency_code) </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Tax Amount ($base_currency_code) </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #555;"> Standard Rate (5%) </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> $grand_after_discount </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> $grand_tax </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #000;"> Total </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #000;"> $base_currency_code $grand_after_discount </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #000;"> $base_currency_code $grand_tax </span> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

if (!empty($vendor_notes)) {
    $tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td><strong>Vendor Notes</strong>: $vendor_notes </td>
</tr>
</table>
EOD;

    $pdf->writeHTML($tbl, true, false, false, false, '');
}

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td width="670"><strong>Bank Details</strong></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$bank_name      = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="bank_name"'));
$Beneficiary    = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="Beneficiary"'));
$account_number = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="account_number"'));
$iban           = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="iban"'));
$currency       = $base_currency_code;

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0" style="border: 1px solid silver;">
<tr>
<td width="150"> Bank Name </td>
<td width="513">: $bank_name </td>
</tr>
<tr>
<td width="150"> Beneficiary </td>
<td width="513">: $Beneficiary </td>
</tr>
<tr>
<td width="150"> Account Number </td>
<td width="513">: $account_number </td>
</tr>
<tr>
<td> IBAN </td>
<td>: $iban </td>
</tr>
<tr>
<td> Currency </td>
<td>: $currency </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

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
$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td width="670">Terms & Conditions: </td>
</tr>
<tr>
<td width="670">$final_terms_and_conditions </td>
</tr>
<tr>
<td width="670">
1. E & OE<br />
2. This debit note is issued against the original purchase and must be accounted accordingly.<br />
3. The debit amount will be adjusted against future purchases or refunded as per company policy.<br />
4. For any queries regarding this debit note, please contact us within 5 working days.<br />
5. Cheques (crossed) should be in favor of $warehouse_name.<br />
</td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

$salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
$encrypted_filename = hash('sha256', $salt . $id);

$pdfs_dir = dirname(__DIR__) . '/pdfs_debit_notes';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';
if ($save_mode) {
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . DB::DEBIT_NOTES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . DB::DEBIT_NOTES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');
