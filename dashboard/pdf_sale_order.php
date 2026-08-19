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

$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologiesLLC');
$pdf->setTitle('Sale Order');
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

$lg = array();
$lg['a_meta_charset'] = 'UTF-8';
$lg['a_meta_dir'] = 'rtl';
$lg['a_meta_language'] = 'fa';
$lg['w_page'] = 'page';

$base_currency_code = BASE_CURRENCY['code'];

if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))  $id     = e_s__($_REQUEST['id']);
else $id = 0;

if (isset($_REQUEST['token']) && !empty($_REQUEST['token']))  $token     = e_s__($_REQUEST['token']);
else $token = '';

if (!isset($_REQUEST['token']) || empty($_REQUEST['token'])) {
    header("Location:index.php");
}

$sent_token = hash("sha512", 'bushogai' . $id);

if ($token != $sent_token) die('');

$row_bg = '';

if (!empty($id)) {

    $result = $mysqli->query("SELECT q.*, c.display_name, c.trn, c.phone, c.company_name 
                               FROM `" . DB::SALE_ORDERS . "` q 
                               LEFT JOIN `" . DB::CUSTOMERS . "` c ON q.customer_id = c.id 
                               WHERE q.id = $id AND q.organization_id = $activeOrgId");
    if ($result->num_rows === 0) die('Sale Order not found.');
    $row = $result->fetch_array();

    $customer_id            = s__($row['customer_id']);
    $display_name           = s__($row['display_name']);
    $customer_trn           = s__($row['trn']);
    $customer_phone         = s__($row['phone']);
    $company_name           = s__($row['company_name']);

    $sale_order_no           = s__($row['sale_order_no']);
    $sale_order_status       = s__($row['sale_order_status']);
    $sale_order_date         = s__($row['sale_order_date']);
    $expiry_date            = s__($row['expiry_date']);
    $job_reference_no       = s__($row['job_reference_no']);

    $expected_shipment_date = s__($row['expected_shipment_date']);
    $payment_term           = s__($row['payment_term']);
    $shipment_type          = s__($row['shipment_type']);
    $sales_person           = s__($row['sales_person']);
    $mawb_bol               = s__(!empty($row['master_awb_no']) ? $row['master_awb_no'] : $row['mawb_bol']);
    $hwb_hbol               = s__($row['hwb_hbol']);
    $shipper_id             = s__($row['shipper_id']);
    $consignee_id           = s__($row['consignee_id']);
    $origin_port            = s__($row['origin_port']);
    $origin_country         = s__($row['origin_country']);
    $destination_port       = s__($row['destination_port']);
    $no_of_packs            = s__($row['no_of_packs']);
    $gross_weight           = s__($row['gross_weight']);
    $chargeable_weight      = s__($row['chargeable_weight']);
    $volume                 = s__($row['volume']);

    $customer_notes         = s__($row['customer_notes']);
    $terms_and_conditions   = s__($row['terms_and_conditions']);

    $grand_subtotal             = s__($row['grand_subtotal']);
    $grand_discount_type        = s__($row['grand_discount_type']);
    $grand_discount_type_value  = s__($row['grand_discount_type_value']);
    $grand_discount_amount      = s__($row['grand_discount_amount']);
    $grand_after_discount       = s__($row['grand_after_discount']);
    $grand_tax                  = s__($row['grand_tax']);
    $grand_total                = s__($row['grand_total']);

    $warehouse_id           = s__($row['warehouse_id']);

    // Customer Billing Address 
    $billing_attention      = '';
    $billing_country        = '';
    $billing_address_line1  = '';
    $billing_address_line2  = '';
    $billing_city           = '';
    $billing_state          = '';
    $billing_zipcode        = '';
    $billing_phone          = '';
    $billing_fax            = '';

    if (!empty($customer_id)) {
        $rs_billing = $mysqli->query("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type='Customer' AND addressable_id=$customer_id AND type='billing'");
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

    $sale_order_date         = ddm_($sale_order_date);
    $expiry_date            = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : ddm_($expiry_date);
    $expected_shipment_date = ($expected_shipment_date == '1970-01-01' || empty($expected_shipment_date)) ? '' : ddm_($expected_shipment_date);

    $shipper_name   = getTableAttr('shipper_name', DB::SHIPPERS, $shipper_id);
    $consignee_name = getTableAttr('consignee_name', DB::CONSIGNEES, $consignee_id);

    $origin = '';
    if (!empty($origin_port)) {
        $origin = getTableAttr('abbr', DB::GEO_COUNTRIES, $origin_port) . ' - ' . getTableAttr('country', DB::GEO_COUNTRIES, $origin_port);
    }
    $destination = '';
    if (!empty($destination_port)) {
        $destination = getTableAttr('abbr', DB::GEO_COUNTRIES, $destination_port) . ' - ' . getTableAttr('country', DB::GEO_COUNTRIES, $destination_port);
    }

    $spell_out = '';
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);

    if (!empty($grand_total)){
        $spell_out = $f->format($grand_total);
        $spell_out = str_ireplace(' point ', '.', ucwords($spell_out));

        if (str_contains($spell_out, '.')) {
            $spell_out .= $base_currency_code;
        }
    }

    $grand_total_in_words  = ucwords($spell_out);

    $row_no = 1;
    $item_row = '';

    // Quotation items
    $result_sale_order_items = $mysqli->query("SELECT id, service, description, qty, rate, tax, tax_amount, sub_total, total FROM `" . DB::SALE_ORDER_ITEMS . "` WHERE sale_order_id = $id ORDER BY id");
    $total_rows = $result_sale_order_items->num_rows;

    if ($total_rows > 0) {
        while ($item = $result_sale_order_items->fetch_array()) {
            $service        = s__($item['service']);
            $service_name   = getTableAttr('item_name', DB::ITEMS, $service);
            $description    = s__($item['description']);
            $qty            = s__($item['qty']);
            $rate           = s__($item['rate']);
            $tax            = s__($item['tax']);
            $tax_amount     = s__($item['tax_amount']);
            $total          = s__($item['total']);

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
}

$pdf->AddPage();

$pdf->setFont('helvetica', '', 8);

// --- Background ---
$pdf_background = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="pdf_background"'));
$bMargin = $pdf->getBreakMargin();
$auto_page_break = $pdf->getAutoPageBreak();
$pdf->SetAutoPageBreak(false, 0);
$img_file = '';
$pdf->Image($img_file, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
$pdf->SetAutoPageBreak($auto_page_break, $bMargin);
$pdf->setPageMark();

// --- Logo & Company Info ---
$logo = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"');

if (!empty($logo) && file_exists('../uploads/global_settings/thumbs/' . $logo)) {
    $display_logo = '../uploads/global_settings/' . s__($logo);
} else {
    $display_logo = $base_url . '../images/default_logo.png';
}

$company_name = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));

// Warehouse / Organization info
$warehouse_information = '';
$warehouse_name = '';
$warehouse_no   = '';
$street1        = '';
$street2        = '';
$w_country      = '';
$w_state        = '';
$w_phone        = '';
$w_email        = '';
$w_trn          = '';

if (!empty($warehouse_id)) {
    $rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::ORGANIZATIONS . "` WHERE id = $warehouse_id");
    $row_warehouse = $rs_warehouse->fetch_array();

    $warehouse_name     = s__($row_warehouse['warehouse_name'] ?? '');
    $warehouse_no       = s__($row_warehouse['warehouse_no'] ?? '');
    $street1            = s__($row_warehouse['street1'] ?? '');
    $street2            = s__($row_warehouse['street2'] ?? '');
    $w_country          = s__($row_warehouse['country'] ?? '');
    $w_country          = getTableAttr('country', DB::GEO_COUNTRIES, $w_country);
    $w_state            = s__($row_warehouse['state'] ?? '');
    $w_state            = getTableAttr('state', DB::GEO_STATES, $w_state);
    $w_phone            = s__($row_warehouse['phone'] ?? '');
    $w_email            = s__($row_warehouse['email'] ?? '');
    $w_trn              = s__($row_warehouse['trn'] ?? '');
}

$warehouse_information .= (!empty($warehouse_name) ? '<strong>'.$warehouse_name . '</strong><br />' : '');
$warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
$warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
$warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
$warehouse_information .= (!empty($w_state) ? $w_state . ', ' : '');
$warehouse_information .= (!empty($w_country) ? $w_country . '<br />' : '');
$warehouse_information .= (!empty($w_phone) ? $w_phone . '<br />' : '');
$warehouse_information .= (!empty($w_email) ? $w_email . '<br />' : '');
$warehouse_information .= (!empty($w_trn) ? $w_trn : '');

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

// --- Quotation Title ---
$tbl = <<<EOD
<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td width="275" style="background-color: #f1f1f1;"></td>
    <td width="120" align="center"><span style="color: #007B8B; font-size: 16px; font-weight: bold;">SALE ORDER</span></td>
    <td width="275" style="background-color: #f1f1f1;"></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Quotation To & Number ---
$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
    <td width="335"><strong>SALE ORDER TO</strong><br />$display_name</td>
    <td width="335" align="right"><strong>SALE ORDER #</strong><br /> $sale_order_no</td>
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
        <tr><td colspan="2"><strong>Details</strong></td></tr>
        <tr><td width="120">Sale Order Date:</td><td>$sale_order_date</td></tr>
        <tr><td>Expiry Date:</td><td>$expiry_date</td></tr>
        <tr><td>Payment Terms:</td><td>$payment_term</td></tr>
        <tr><td>Sales Person:</td><td>$sales_person</td></tr>
        <tr><td>Job Ref No:</td><td>$job_reference_no</td></tr>
        </table>
    </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Shipping Info ---
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Shipment Type</span></td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">MAWB/BOL</span></td>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">HWB/HBOL</span></td>
    <td width="105" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Shipper</span></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Consignee</span></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Expected Shipment</span></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Origin</span></td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$shipment_type</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$mawb_bol</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$hwb_hbol</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$shipper_name</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$consignee_name</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$expected_shipment_date</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$origin</span></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Second row of shipping ---
$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Destination</span></td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">No of Packs</span></td>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Gross Weight</span></td>
    <td width="105" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Chargeable Weight</span></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Volume (cbm)</span></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"></td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"></td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$destination</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$no_of_packs</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$gross_weight</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$chargeable_weight</span></td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$volume</span></td>
    <td style="border:1px solid #f1f1f1;"></td>
    <td style="border:1px solid #f1f1f1;"></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Items Table ---
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
<td colspan="3"><span style="color: #555;">Thanks for your consideration.</span></td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" " align="right"> Sub Total </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> TOTAL </strong> </td>
<td style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> $base_currency_code$grand_total </strong>  </td>
</tr>

<tr>
<td colspan="7" align="right"> Total in Words: <strong> $base_currency_code  $grand_total_in_words </strong> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Discount & Tax Summary ---
$discount_label = '';
if (!empty($grand_discount_type) && !empty($grand_discount_type_value)) {
    $discount_label = 'Discount (' . ucfirst($grand_discount_type) . ' ' . $grand_discount_type_value . '):';
}

$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="370" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> Item Total </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Subtotal </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Discount </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #555;"> Amounts </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> $grand_subtotal </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> $grand_discount_amount </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #000;"> Total Tax </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right" colspan="2"> <span style="color: #000;"> $grand_tax </span> </td>
</tr>
<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #000;"> Grand Total </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right" colspan="2"> <span style="color: #000;"> $base_currency_code $grand_total </span> </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Customer Notes ---
if (!empty($customer_notes)) {
    $tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td><strong>Customer Notes</strong>: $customer_notes </td>
</tr>
</table>
EOD;
    $pdf->writeHTML($tbl, true, false, false, false, '');
}

// --- Terms & Conditions ---
$final_terms_and_conditions = '';
if (!empty($terms_and_conditions)){
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
<td width="670"><strong>Terms & Conditions:</strong> </td>
</tr>
<tr>
<td width="670">$final_terms_and_conditions </td>
</tr>
<tr>
<td width="670">
1. This sale order is subject to availability of space and rates may change without prior notice.<br />
2. The sale order is valid until the expiry date mentioned above.<br />
3. Payment terms as agreed will apply.<br />
</td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Output ---
$encrypted_filename = \App\Helper\PdfHelper::filename((int)$id);

// --- Output / persistence ---
$pdfs_dir = dirname(__DIR__) . '/pdfs_sale_orders';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';

if ($save_mode) {
    // File-only generation (used by send_email.php to attach the PDF)
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . DB::SALE_ORDERS . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

// Persist PDF to disk so the file + pdf column are always available
if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . DB::SALE_ORDERS . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');

