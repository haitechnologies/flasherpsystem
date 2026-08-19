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

require_once('../tcpdf/examples/tcpdf_include.php');

$base_currency_code = BASE_CURRENCY['code'];

// Security
$id = e_s__($_REQUEST['id']) ?? 0;
$token = e_s__($_REQUEST['token']) ?? '';
if (empty($token)) {
    die('Invalid request');
}
$id = (int)$id;
if ($id <= 0) die('Invalid Purchase ID');
$sent_token = hash("sha512", 'bushogai' . $id);
if ($token != $sent_token) die('Invalid token');

// Fetch purchase data
$result = $mysqli->query("SELECT * FROM `" . DB::PURCHASES . "` WHERE id=$id AND organization_id=$activeOrgId");
if (!$result || $result->num_rows == 0) die('Purchase not found');
$row = $result->fetch_assoc();

$vendor_id       = s__($row['vendor_id']);
$display_name    = getTableAttr('display_name', DB::VENDORS, $vendor_id);
$vendor_trn      = getTableAttr('trn', DB::VENDORS, $vendor_id);
$vendor_phone    = getTableAttr('phone', DB::VENDORS, $vendor_id);

$purchase_no             = s__($row['purchase_no']);
$reference_no            = s__($row['reference_no']);
$warehouse_id            = s__($row['warehouse_id']);
$vendor_notes            = s__($row['vendor_notes']);
$terms_and_conditions    = s__($row['terms_and_conditions']);
$purchase_date           = s__($row['purchase_date']);
$purchase_date           = dd_($purchase_date);
$expiry_date             = s__($row['expiry_date']);
$grand_subtotal          = s__($row['grand_subtotal'] ?? 0);
$grand_tax               = s__($row['grand_tax'] ?? 0);
$grand_total             = s__($row['grand_total'] ?? 0);

$created_at     = s__($row['created_at']);
$created_time   = date('h:i:s', strtotime($created_at));
$created_date   = date('d-m-Y', strtotime($created_at));
$created_by     = s__($row['created_by']);
$created_by     = getUsernameByID($created_by);
$publish        = s__($row['publish']);

// Grand total in words
$grand_total_in_words = '';
if (!empty($grand_total)) {
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
    $spell_out = $f->format($grand_total);
    $spell_out = str_ireplace(' point ', '.', ucwords($spell_out));
    if (str_contains($spell_out, '.')) {
        $spell_out .= $base_currency_code;
    }
    $grand_total_in_words = ucwords($spell_out);
}

// Vendor billing address
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
    $billing_attention          = !empty($row_billing['attention']) ? s__($row_billing['attention']) : '';
    $billing_country            = !empty($row_billing['country']) ? s__($row_billing['country']) : '';
    $billing_address_line1      = !empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '';
    $billing_address_line2      = !empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '';
    $billing_city               = !empty($row_billing['city']) ? s__($row_billing['city']) : '';
    $billing_state              = !empty($row_billing['state']) ? s__($row_billing['state']) : '';
    $billing_zipcode            = !empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '';
    $billing_phone              = !empty($row_billing['phone']) ? s__($row_billing['phone']) : '';
    $billing_fax                = !empty($row_billing['fax']) ? s__($row_billing['fax']) : '';
}

// Vendor shipping address
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
    $shipping_attention          = !empty($row_shipping['attention']) ? s__($row_shipping['attention']) : '';
    $shipping_country            = !empty($row_shipping['country']) ? s__($row_shipping['country']) : '';
    $shipping_address_line1      = !empty($row_shipping['address_line1']) ? s__($row_shipping['address_line1']) : '';
    $shipping_address_line2      = !empty($row_shipping['address_line2']) ? s__($row_shipping['address_line2']) : '';
    $shipping_city               = !empty($row_shipping['city']) ? s__($row_shipping['city']) : '';
    $shipping_state              = !empty($row_shipping['state']) ? s__($row_shipping['state']) : '';
    $shipping_zipcode            = !empty($row_shipping['zipcode']) ? s__($row_shipping['zipcode']) : '';
    $shipping_phone              = !empty($row_shipping['phone']) ? s__($row_shipping['phone']) : '';
    $shipping_fax                = !empty($row_shipping['fax']) ? s__($row_shipping['fax']) : '';
}

// Purchase items
$result_purchase_items = $mysqli->query("SELECT * FROM `" . DB::PURCHASE_ITEMS . "` WHERE purchase_id=$id");
$total_rows = $result_purchase_items ? $result_purchase_items->num_rows : 0;

// Company
$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

// Logo
$logo = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"');
if (!empty($logo) && file_exists('../uploads/global_settings/thumbs/' . $logo)) {
    $display_logo = '../uploads/global_settings/' . s__($logo);
} else {
    $display_logo = $base_url . '../images/default_logo.png';
}

// Warehouse information
$warehouse_information = '';
if (!empty($warehouse_id)) {
    $rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::WAREHOUSES . "` WHERE id=$warehouse_id");
    if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
        $row_warehouse = $rs_warehouse->fetch_array();
        $warehouse_name = s__($row_warehouse['warehouse_name']);
        $warehouse_no   = s__($row_warehouse['warehouse_no']);
        $street1        = s__($row_warehouse['street1']);
        $street2        = s__($row_warehouse['street2']);
        $country        = s__($row_warehouse['country']);
        $state          = s__($row_warehouse['state']);
        $phone          = s__($row_warehouse['phone']);
        $email          = s__($row_warehouse['email']);
        $trn            = s__($row_warehouse['trn']);

        $country = !empty($country) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $country) : '';
        $state   = !empty($state) ? getTableAttr('state_name', DB::GEO_STATES, $state) : '';

        $warehouse_information .= (!empty($warehouse_name)) ? '<strong>' . $warehouse_name . '</strong><br />' : '';
        $warehouse_information .= (!empty($warehouse_no)) ? $warehouse_no . '<br />' : '';
        $warehouse_information .= (!empty($street1)) ? $street1 . '<br />' : '';
        $warehouse_information .= (!empty($street2)) ? $street2 . '<br />' : '';
        $warehouse_information .= (!empty($state)) ? $state . ', ' : '';
        $warehouse_information .= (!empty($country)) ? $country . '<br />' : '';
        $warehouse_information .= (!empty($phone)) ? $phone . '<br />' : '';
        $warehouse_information .= (!empty($email)) ? $email . '<br />' : '';
        $warehouse_information .= (!empty($trn)) ? $trn : '';
    }
}

// Create PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologiesLLC');
$pdf->setTitle('Purchase');
$pdf->setSubject('na');

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

// Company header
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

// Document title
$tbl = <<<EOD
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td width="275" style="background-color: #f1f1f1;"></td>
    <td width="120" align="center"><span style="color: #007B8B; font-size: 16px; font-weight: bold;">PURCHASE</span></td>
    <td width="275" style="background-color: #f1f1f1;"></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// Purchase from and purchase number
$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
    <td width="335"><strong>PURCHASE FROM</strong><br />$display_name</td>
    <td width="335" align="right"><strong>PURCHASE #</strong><br /> $purchase_no</td>
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

// Purchase details
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Purchase Date </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Terms </span> </td>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Due Date </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Job No </span> </td>
    <td width="105" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Master AWB No: </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Ref No </span> </td>
    <td width="123" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Shipper </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$purchase_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">Net 15 </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$expiry_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">MC-LJ1474 </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">250-51311945 </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$reference_no </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;"> </span> </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// Items table
$row_bg = '';
$row_no = 1;
$item_row = '';
if ($result_purchase_items) {
    while ($row_purchase_items = $result_purchase_items->fetch_array()) {
        $service         = $row_purchase_items['service'];
        $service_name    = getTableAttr('item_name', DB::ITEMS, $service);
        $description     = s__($row_purchase_items['description']);
        $qty             = $row_purchase_items['qty'];
        $rate            = $row_purchase_items['rate'];
        $tax             = $row_purchase_items['tax'];
        $tax_amount      = $row_purchase_items['tax_amount'];
        $total           = $row_purchase_items['total'];

        $qty        = (($qty == 1) ? '1.00' : $qty);
        $rate       = (($rate == 0) ? '1.00' : $rate);
        $tax        = (($tax == 0) ? '0' : $tax);
        $tax_amount = (($tax == 0) ? '0.00' : $tax_amount);

        $item_row .= '<tr><td align="center" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $row_no++ . '</span></td>
        <td align="left" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $service_name . ' ' . $description . '</span></td>
        <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $qty . '</span></td>
        <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $rate . '</span></td>
        <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $tax . '%</span></td>
        <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $tax_amount . '</span></td>
        <td align="right" style="' . $row_bg . ' border:1px solid #f1f1f1"><span style="color:#555;">' . $total . '</span></td></tr>';
    }
}

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td width="50" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;"># </span> </td>
    <td width="194" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Item &amp; Description </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Qty </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Rate </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Tax% </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Tax </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Amount </span> </td>
</tr>
$item_row
<tr><td colspan="3">Thanks for your business.</td></tr>
<tr>
    <td colspan="3"></td>
    <td colspan="3" align="right"><span style="color: #555;"><strong>Sub Total</strong> </span> </td>
    <td align="right"><span style="color: #555;"><strong>$base_currency_code $grand_subtotal</strong> </span> </td>
</tr>
<tr>
    <td colspan="3"></td>
    <td colspan="3" align="right"><span style="color: #555;"><strong>Standard Rate (5%)</strong> </span> </td>
    <td align="right"><span style="color: #555;"><strong>$base_currency_code $grand_subtotal</strong> </span> </td>
</tr>
<tr>
    <td colspan="3"></td>
    <td colspan="3" align="right"><span style="color: #555;"><strong>Zero Rate (0%)</strong> </span> </td>
    <td align="right"><span style="color: #555;"><strong>$base_currency_code $grand_subtotal</strong> </span> </td>
</tr>
<tr>
    <td colspan="6" align="right" style="border-top:1px solid silver; border-bottom:1px solid silver;"><span style="color: #555;"><strong>TOTAL</strong> </span> </td>
    <td align="right" style="border-top:1px solid silver; border-bottom:1px solid silver;"><span style="color: #555;"><strong>$base_currency_code $grand_total</strong> </span> </td>
</tr>
<tr>
    <td colspan="6" align="right"><span style="color: #555;"><strong>BALANCE DUE</strong> </span> </td>
    <td align="right"><span style="color: #555;"><strong>$base_currency_code $grand_total</strong> </span> </td>
</tr>
<tr>
    <td colspan="7" align="right"><span style="color: #555;">Total in Words: <strong>UAE Dirham $grand_total_in_words</strong> </span> </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// Tax summary
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td width="370" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Tax Details</span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Taxable Amount ($base_currency_code) </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Tax Amount ($base_currency_code) </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">Zero Rate (0%) </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">365.00 </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">0.00 </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">Standard Rate (5%) </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">375.00 </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">18.75 </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">Total </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">$base_currency_code 740.00 </span> </td>
    <td align="right" style="border:1px solid #f1f1f1;"><span style="color: #555;">$base_currency_code 18.75 </span> </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// Vendor notes
if (!empty($vendor_notes)) {
    $tbl = <<<EOD
    <table cellpadding="5" cellspacing="0" border="0">
    <tr>
        <td><strong>Vendor Notes</strong>: $vendor_notes</td>
    </tr>
    </table>
    EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

// Bank details
$bank_name         = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="bank_name"'));
$Beneficiary       = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="Beneficiary"'));
$account_number    = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="account_number"'));
$iban              = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="iban"'));
$currency          = $base_currency_code;

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td><strong>Bank Details</strong></td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td width="150" style="border:1px solid silver;"><span style="color: #555;">Bank Name : </span> </td>
    <td width="513" style="border:1px solid silver;"><span style="color: #555;">$bank_name </span> </td>
</tr>
<tr>
    <td style="border:1px solid silver;"><span style="color: #555;">Beneficiary : </span> </td>
    <td style="border:1px solid silver;"><span style="color: #555;">$Beneficiary </span> </td>
</tr>
<tr>
    <td style="border:1px solid silver;"><span style="color: #555;">Account Number : </span> </td>
    <td style="border:1px solid silver;"><span style="color: #555;">$account_number </span> </td>
</tr>
<tr>
    <td style="border:1px solid silver;"><span style="color: #555;">IBAN : </span> </td>
    <td style="border:1px solid silver;"><span style="color: #555;">$iban </span> </td>
</tr>
<tr>
    <td style="border:1px solid silver;"><span style="color: #555;">Currency : </span> </td>
    <td style="border:1px solid silver;"><span style="color: #555;">$currency </span> </td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

// Terms and conditions
$final_terms_and_conditions = '';
$data = explode("\r", $terms_and_conditions);
foreach ($data as $d) {
    if (!empty($d)) {
        $final_terms_and_conditions .= $d . '<br />';
    }
}

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr>
    <td><strong>Terms &amp; Conditions: </strong></td>
</tr>
<tr>
    <td>$final_terms_and_conditions</td>
</tr>
</table>
EOD;
$pdf->writeHTML($tbl, true, false, false, false, '');

$salt = '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN';
$encrypted_filename = hash('sha256', $salt . $id);

$pdfs_dir = dirname(__DIR__) . '/pdfs_purchases';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';
if ($save_mode) {
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . DB::PURCHASES . "` SET pdf='" . $encrypted_filename . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . DB::PURCHASES . "` SET pdf='" . $encrypted_filename . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');
