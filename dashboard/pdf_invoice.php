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
// include('admin_elements/timeout.php');  // File doesn't exist
// include('admin_elements/security.php');
include('admin_elements/grab_vars.php');





//============================================================+
// File name   : example_001.php
// Begin       : 2008-03-04
// Last Update : 2013-05-14
//
// Description : Example 001 for TCPDF class
//               Default Header and Footer
//
// Author: Nicola Asuni
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//============================================================+

/**
 * Creates an example PDF TEST document using TCPDF
 * @package com.tecnick.tcpdf
 * @abstract TCPDF - Example: Default Header and Footer
 * @author Nicola Asuni
 * @since 2008-03-04
 * @group header
 * @group footer
 * @group page
 * @group pdf
 */

// Include the main TCPDF library (search for installation path).
require_once('../tcpdf/examples/tcpdf_include.php');

// create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// set document information
$pdf->setCreator(PDF_CREATOR);
$pdf->setAuthor('HaiTechnologiesLLC');
$pdf->setTitle('Invoice');
$pdf->setSubject('na');
// $pdf->setKeywords('TCPDF, PDF, example, test, guide');

// set default header data
// $pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE . ' 001', PDF_HEADER_STRING, array(0, 64, 255), array(0, 64, 128));
// $pdf->setFooterData(array(0, 64, 0), array(0, 64, 128));

// set header and footer fonts
$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// set default monospaced font
$pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// set margins
// $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
// $pdf->setHeaderMargin(PDF_MARGIN_HEADER);

$pdf->SetMargins(10, 3, 10, true);


// $pdf->setFooterMargin(PDF_MARGIN_FOOTER);
$pdf->setFooterMargin(5);

// set auto page breaks
$pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
    require_once(dirname(__FILE__) . '/lang/eng.php');
    $pdf->setLanguageArray($l);
}


// // set some language dependent data:
// $lg = array();
// $lg['a_meta_charset'] = 'UTF-8';
// $lg['a_meta_dir'] = 'rtl';
// $lg['a_meta_language'] = 'fa';
// $lg['w_page'] = 'page';

// // set some language-dependent strings (optional)
// $pdf->setLanguageArray($lg);

// ---------------------------------------------------------

// set default font subsetting mode
$pdf->setFontSubsetting(true);

// Set font
// dejavusans is a UTF-8 Unicode font, if you only need to
// print standard ASCII chars, you can use core fonts like
// helvetica or times to reduce file size.
$pdf->setFont('dejavusans', '', 14, '', true);

// remove default header
$pdf->setPrintHeader(false);

/*
|--------------------------------------------------------------------------
| 	ARABIC SUPPORT
|--------------------------------------------------------------------------
*/
// set some language dependent data:
// $lg = array();
// $lg['a_meta_charset'] = 'UTF-8';
// $lg['a_meta_dir'] = 'rtl';
// $lg['a_meta_language'] = 'fa';
// $lg['w_page'] = 'page';

// set some language-dependent strings (optional)
// $pdf->setLanguageArray($lg);

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


// Add a page
// This method has several options, check the source code documentation for more information.
$pdf->AddPage();

// set text shadow effect
// $pdf->setTextShadow(array('enabled' => true, 'depth_w' => 0.2, 'depth_h' => 0.2, 'color' => array(196, 196, 196), 'opacity' => 1, 'blend_mode' => 'Normal'));

// $pdf->Write(0, 'Example of HTML tables', '', 0, 'L', true, 0, false, false, 0);

$pdf->setFont('helvetica', '', 8);

// -----------------------------------------------------------------------------

$pdf_background          = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="pdf_background"'));

// -- set new background ---

// get the current page break margin
$bMargin = $pdf->getBreakMargin();
// get current auto-page-break mode
$auto_page_break = $pdf->getAutoPageBreak();
// disable auto-page-break
$pdf->SetAutoPageBreak(false, 0);
// set bacground image
// $img_file = K_PATH_IMAGES . 'image_demo.jpg';
// $img_file = '../images/background.jpg';
// $img_file = '../uploads/global_settings/'. $pdf_background.'';
$img_file = '';

$pdf->Image($img_file, 0, 0, 210, 297, '', '', '', false, 300, '', false, false, 0);
// restore auto-page-break status
$pdf->SetAutoPageBreak($auto_page_break, $bMargin);
// set the starting point for the page content
$pdf->setPageMark();

// -----------------------------------------------------------------------------


/*
|--------------------------------------------------------------------------
| 	ARABIC
|--------------------------------------------------------------------------
*/
// set some language dependent data:
$lg = array();
$lg['a_meta_charset'] = 'UTF-8';
$lg['a_meta_dir'] = 'rtl';
$lg['a_meta_language'] = 'fa';
$lg['w_page'] = 'page';

// // set some language-dependent strings (optional)
// $pdf->setLanguageArray($lg);

// $pdf->SetFont('aealarabiya', '', 8);

// ---------------------------------------------------------






/*
|--------------------------------------------------------------------------
| 	SECURITY
|--------------------------------------------------------------------------
|
*/










/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/

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

// $row_bg = 'background-color: #dce9f7;';
$row_bg = '';


if (!empty($id)) {
    $container = \App\Core\Container::getInstance();
    if (!$container->has(\App\Core\Database::class)) {
        $container->register(\App\Core\Database::class, fn() => new \App\Core\Database());
    }
    if (!$container->has(\App\Repository\InvoiceRepository::class)) {
        $container->register(\App\Repository\InvoiceRepository::class, fn($c) => new \App\Repository\InvoiceRepository($c->get(\App\Core\Database::class)));
    }
    if (!$container->has(\App\Repository\CustomerRepository::class)) {
        $container->register(\App\Repository\CustomerRepository::class, fn($c) => new \App\Repository\CustomerRepository($c->get(\App\Core\Database::class)));
    }
    if (!$container->has(\App\Service\InvoiceService::class)) {
        $container->register(\App\Service\InvoiceService::class, fn($c) => new \App\Service\InvoiceService(
            $c->get(\App\Repository\InvoiceRepository::class),
            $c->get(\App\Repository\CustomerRepository::class),
            $c->get(\App\Core\Database::class)
        ));
    }
    $invoiceService = $container->get(\App\Service\InvoiceService::class);
    $db = $container->get(\App\Core\Database::class);

    try {
        $invoice = $invoiceService->getInvoicePublic((int)$id);
    } catch (\Throwable $e) {
        die('Invoice not found');
    }

    $customer_id            = $invoice->customerId;
    $display_name           = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
    $customer_trn           = getTableAttr('trn', DB::CUSTOMERS, $customer_id);
    $customer_phone         = getTableAttr('phone', DB::CUSTOMERS, $customer_id);

    $invoice_no             = $invoice->invoiceNo;
    $invoice_status         = $invoice->invoiceStatus;
    $invoice_date           = $invoice->invoiceDate;
    $expiry_date            = $invoice->expiryDate;
    $reference_no           = $invoice->referenceNo;
    $warehouse_id           = $invoice->warehouseId;

    $expected_shipment_date = $invoice->expectedShipmentDate;
    $payment_term           = $invoice->paymentTerm;

    $job_reference_no       = $invoice->jobReferenceNo;
    $master_awb_no          = $invoice->masterAwbNo;

    $shipper                = $invoice->shipper;
    $shipper_text           = !empty($shipper) ? getTableAttr('shipper_name', DB::SHIPPERS, $shipper) : '';
    $consignee              = $invoice->consignee;
    $consignee_text         = !empty($consignee) ? getTableAttr('consignee_name', DB::CONSIGNEES, $consignee) : '';

    $origin                 = $invoice->origin;
    $origin_text            = '';
    if (!empty($origin)) {
        $origin_text = getTableAttr('port_name', DB::PORTS, $origin);
        $origin_country = getTableAttr('country', DB::GEO_COUNTRIES, $invoice->originCountry);
        $origin_text = trim($origin_text . ' - ' . $origin_country, ' -');
    }

    $destination            = $invoice->destination;
    $destination_text       = '';
    if (!empty($destination)) {
        $destination_text = getTableAttr('port_name', DB::PORTS, $destination);
        $destination_country = getTableAttr('country', DB::GEO_COUNTRIES, $invoice->destinationCountry);
        $destination_text = trim($destination_text . ' - ' . $destination_country, ' -');
    }

    $hwb_hbol               = $invoice->hwbHbol;
    $master_awb_display     = $master_awb_no . (!empty($hwb_hbol) ? ' / ' . $hwb_hbol : '');

    $no_of_packs            = $invoice->noOfPacks;
    $gross_weight           = $invoice->grossWeight;
    $chargeable_weight      = $invoice->chargeableWeight;
    $volume                 = $invoice->volume;

    $customer_notes         = $invoice->customerNotes;
    $terms_and_conditions   = $invoice->termsAndConditions;

    $grand_subtotal             = $invoice->grandSubtotal;
    $grand_discount_type        = $invoice->grandDiscountType;
    $grand_discount_type_value  = $invoice->grandDiscountTypeValue;
    $grand_discount_amount      = $invoice->grandDiscountAmount;
    $grand_after_discount       = $invoice->grandAfterDiscount;
    $grand_tax                  = $invoice->grandTax;
    $grand_total                = $invoice->grandTotal;

    $is_active = $invoice->isActive ? 1 : 0;

    $invoice_date               = ddm_($invoice_date);
    $expiry_date                = ($expiry_date === '1970-01-01' || empty($expiry_date) ? '' : ddm_($expiry_date));
    $expected_shipment_date     = ($expected_shipment_date === '1970-01-01' || empty($expected_shipment_date) ? '' : ddm_($expected_shipment_date));

    $created_at             = $invoice->createdAt;
    $created_time           = $created_at ? date('h:i:s', strtotime($created_at)) : '';
    $created_date           = $created_at ? date('d M Y', strtotime($created_at)) : '';
    $created_by             = $invoice->createdBy;
    $created_by             = getUsernameByID($created_by);

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

    $is_active = $invoice->isActive ? 1 : 0;

    // Customer Billing Address 
    $row_billing = $db->fetchOne("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type = 'Customer' AND addressable_id = :customer_id AND type = 'billing'", [
        'customer_id' => $customer_id
    ]);

    $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');
    $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
    $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
    $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
    $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
    $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
    $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
    $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
    $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');

    // Customer Shipping Address
    $row_shipping = $db->fetchOne("SELECT * FROM `" . DB::CUSTOMER_ADDRESSES . "` WHERE addressable_type = 'Customer' AND addressable_id = :customer_id AND type = 'shipping'", [
        'customer_id' => $customer_id
    ]);

    $shipping_attention      = (!empty($row_shipping['attention']) ? s__($row_shipping['attention']) : '');
    $shipping_country        = (!empty($row_shipping['country']) ? s__($row_shipping['country']) : '');
    $shipping_address_line1  = (!empty($row_shipping['address_line1']) ? s__($row_shipping['address_line1']) : '');
    $shipping_address_line2  = (!empty($row_shipping['address_line2']) ? s__($row_shipping['address_line2']) : '');
    $shipping_city           = (!empty($row_shipping['city']) ? s__($row_shipping['city']) : '');
    $shipping_state          = (!empty($row_shipping['state']) ? s__($row_shipping['state']) : '');
    $shipping_zipcode        = (!empty($row_shipping['zipcode']) ? s__($row_shipping['zipcode']) : '');
    $shipping_phone          = (!empty($row_shipping['phone']) ? s__($row_shipping['phone']) : '');
    $shipping_fax            = (!empty($row_shipping['fax']) ? s__($row_shipping['fax']) : '');

    $row_no = 1;
    $item_row = '';

    // ------------------ TOTAL ITEMS ------------------
    $invoice_items = $invoiceService->getInvoiceItemsPublic($id);
    $total_rows = count($invoice_items);

    if ($total_rows > 0) {
        foreach ($invoice_items as $item) {
            $service        = $item->service;
            $service_name   = getTableAttr('item_name', DB::ITEMS, $service);

            $description    = $item->description;

            $qty            = $item->qty;
            $rate           = $item->rate;
            $tax            = $item->tax;
            $tax_amount     = $item->taxAmount;
            $total          = $item->total;

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


// -----------------------------------------------------------------------------

/*
|--------------------------------------------------------------------------|
|------------ PAGE WIDTH = 670---------------------------------------------|
|--------------------------------------------------------------------------|
*/



// -----------------------------------------------------------------------------





// ---------------------------------- LOGO ---------------------------------- 
$logo        = getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="logo"');

if (!empty($logo) && file_exists('../uploads/global_settings/thumbs/' . $logo)) {
    $display_logo = '../uploads/global_settings/' . s__($logo);
} else {
    $display_logo = $base_url . '../images/default_logo.png';
}
// ----------------------------------------------------------------------------- 

$company_name        = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="company_name"'));


    $warehouse_information = '';
    $row_warehouse = $db->fetchOne("SELECT * FROM `erp_organizations` WHERE id = :id", ['id' => $warehouse_id]);

    $warehouse_no       = s__($row_warehouse['warehouse_no'] ?? '');
    $warehouse_name     = s__($row_warehouse['warehouse_name'] ?? '');
    $street1            = s__($row_warehouse['street1'] ?? '');
    $street2            = s__($row_warehouse['street2'] ?? '');

    $country            = s__($row_warehouse['country'] ?? '');
    $country            = getTableAttr('country', DB::GEO_COUNTRIES, $country);
    
    $state              = s__($row_warehouse['state'] ?? '');
    $state            = getTableAttr('state', DB::GEO_STATES, $state);
    
    $phone              = s__($row_warehouse['phone'] ?? '');
    $email              = s__($row_warehouse['email'] ?? '');
    $trn                = s__($row_warehouse['trn'] ?? '');

    $warehouse_information .= (!empty($warehouse_name) ? '<strong>'.$warehouse_name . '</strong><br />' : '');
    $warehouse_information .= (!empty($warehouse_no) ? $warehouse_no . '<br />' : '');
    $warehouse_information .= (!empty($street1) ? $street1 . '<br />' : '');
    $warehouse_information .= (!empty($street2) ? $street2 . '<br />' : '');
    $warehouse_information .= (!empty($state) ? $state . ', ' : '');
    $warehouse_information .= (!empty($country) ? $country . '<br />' : '');
    $warehouse_information .= (!empty($phone) ? $phone . '<br />' : '');
    $warehouse_information .= (!empty($email) ? $email . '<br />' : '');
    $warehouse_information .= (!empty($trn) ? $trn : '');
    

// <img src="$display_logo" height="80" alt="Logo Image"><br />

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

 

// -----------------------------------------------------------------------------


$tbl = <<<EOD

<table width="100%" border="0" cellpadding="0" cellspacing="0">
<tr>
    <td width="275" style="background-color: #f1f1f1;"></td>
    <td width="120" align="center"><span style="color: #007B8B; font-size: 16px; font-weight: bold;">TAX INVOICE</span></td>
    <td width="275" style="background-color: #f1f1f1;"></td>
</tr>
</table>

EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


// -----------------------------------------------------------------------------

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">

<tr>
    <td width="335"><strong>INVOICE TO</strong><br />$display_name</td>
    <td width="335" align="rigth"><strong>INVOICE #</strong><br /> $invoice_no</td>
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
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


// -----------------------------------------------------------------------------


$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Invoice Date </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Terms </span> </td>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Due Date </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Job No </span> </td>
    <td width="105" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Master AWB / HWB </span> </td>
    <td width="123" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Shipper </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$invoice_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$payment_term </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$expiry_date </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$reference_no </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$master_awb_display </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$shipper_text </span> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


// -----------------------------------------------------------------------------


$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td></td></tr>
<tr>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Consignee </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Origin </span> </td>
    <td width="90" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">Destination </span> </td>
    <td width="80" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"><span style="color: #555;">No of Packs </span> </td>
    <td width="105" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Gross Weight </span> </td>
    <td width="100" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Chargeable Weight </span> </td>
    <td width="123" style="background-color: #e8f7f4; border:1px solid #f1f1f1"><span style="color: #555;">Volume (cbm) </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$consignee_text </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$origin_text </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$destination_text </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$no_of_packs </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$gross_weight </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$chargeable_weight </span> </td>
    <td style="border:1px solid #f1f1f1;"><span style="color: #555;">$volume </span> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// -----------------------------------------------------------------------------


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
<td colspan="3"><span style="color: #555;">Thanks for your business.</span></td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" " align="right"> Sub Total </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" align="right"> Standard Rate (5%) </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" align="right"> Zero Rate (0%) </td>
<td align="right"> $grand_subtotal  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> TOTAL </strong> </td>
<td style=" border-top:1px solid silver; border-bottom:1px solid silver" align="right"> <strong> $base_currency_code$grand_total </strong>  </td>
</tr>

<tr>
<td colspan="3"></td>
<td colspan="3" style=" border-bottom:1px solid silver; " align="right"> <strong> BALANCE DUE </strong> </td>
<td style=" border-bottom:1px solid silver; " align="right"> <strong> $base_currency_code$grand_total </strong> </td>
</tr>

<tr>
<td colspan="7" align="right"> Total in Words: <strong> UAE Dirham  $grand_total_in_words </strong> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');



// -----------------------------------------------------------------------------


$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">
<tr><td>Tax Summary</td></tr>
<tr>
    <td width="370" style="background-color: #e8f7f4; border:1px solid #f1f1f1;"> <span style="color: #555;"> Tax Details </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Taxable Amount ($base_currency_code) </span> </td>
    <td width="150" style="background-color: #e8f7f4; border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> Tax Amount ($base_currency_code) </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #555;"> Zero Rate (0%) </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> 365.00 </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> 0.00 </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #555;"> Standard Rate (5%) </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> 375.00 </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #555;"> 18.75 </span> </td>
</tr>

<tr>
    <td style="border:1px solid #f1f1f1;"> <span style="color: #000;"> Total </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #000;"> $base_currency_code 740.00 </span> </td>
    <td style="border:1px solid #f1f1f1;" align="right"> <span style="color: #000;"> $base_currency_code 18.75 </span> </td>
</tr>

</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


// -----------------------------------------------------------------------------


if (!empty($customer_notes)){

$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td><strong>Customer Notes</strong>: $customer_notes </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

}


// -----------------------------------------------------------------------------


$tbl = <<<EOD
<table cellpadding="2" cellspacing="2" border="0">
<tr>
<td width="670"><strong>Bank Details</strong></td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// -----------------------------------------------------------------------------

$bank_name      = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="bank_name"'));
$Beneficiary    = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="Beneficiary"'));
$account_number = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="account_number"'));
$iban           = s__(getTableAttrv('setting_value', DB::SYSTEM_SETTINGS, 'setting_slug ="iban"'));
$currency       = $base_currency_code;


// <tr>
// <td colspan="2"> NOTES: Pleasure having your business. We look forward to serve you again. </td>
// </tr>

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


// -----------------------------------------------------------------------------
// Seprate Line Number on base of Space new line
$final_terms_and_conditions = '';
if (!empty($terms_and_conditions)){
    $desc = explode("\r", $terms_and_conditions);
    $d_counter = 1;
    if (count($desc) > 0) {
        foreach ($desc as $d) {
            if (!empty($d)) {
                // $final_terms_and_conditions .= $d_counter++ . '. ' . $d . '<br />';
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
2. The customer will notify $warehouse_name in writing, if there are any discrepancy in this invoice within the period of 5 working days from the date of invoice. Failing which, the invoice stands payable in full.<br />
3. Discrepancies do not include any kind of claim.<br />
4. Claims, if any, would not be adjusted against the payable invoices. Invoices shall be settled in full.<br />
5. Cheques (crossed) should be in favor of $warehouse_name.<br />
</td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


    // -----------------------------------------------------------------------------

    // $filename = 'Booking_' . $id . '.pdf';

    // $salt =  '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN'; // random string 
    // $encrypted_filename = crc32($id . $salt);
    // $encrypted_filename = sha($id . $salt);

    $encrypted_filename = \App\Helper\PdfHelper::filename((int)$id);


//============================================================+
// SHOW File
//============================================================+
// if ($flag == 'i') {

// //Close and output PDF document
// // $pdf->Output('invoice_'.$id.'.pdf', 'I');  // Flag - I (show file)
// $pdf->Output($filename, 'I');  // Flag - I (show file)


// //============================================================+
// // SAVE File
// //============================================================+
// } else if ($flag == 'f') {
//     //Close and save PDF document
//     $pdf->Output(__DIR__ . '/pdfs/'.$filename.'', 'F');
// }


// $pdf->Output(__DIR__ . '/pdfs/'.$filename.'', 'F');

// if (isRemote()) {
//     $pdf->Output($_SERVER['DOCUMENT_ROOT'] . '/pdfs_invoices/' . $encrypted_filename . '.pdf', 'F');
// } else {
//     $pdf->Output($_SERVER['DOCUMENT_ROOT'] . '/haipulse/pdfs_invoices/' . $encrypted_filename . '.pdf', 'F');
// }

// --- Output / persistence ---
$pdfs_dir = dirname(__DIR__) . '/pdfs_invoices';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';

if ($save_mode) {
    // File-only generation (used by send_email.php to attach the PDF)
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . DB::INVOICES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

// Persist PDF to disk so the file + pdf column are always available
if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . DB::INVOICES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');  // Flag - I (show file)
    //============================================================+
    // END OF FILE
    //============================================================+




    // } // while
// } // if



// if (isset($_REQUEST['id']) && !empty($_REQUEST['id']))          $id     = e_s__($_REQUEST['id']);
// else $id = 0;

// if (isset($_REQUEST['flag']) && !empty($_REQUEST['flag']))      $flag     = e_s__($_REQUEST['flag']);
// else $flag = 0;

// if (isset($_REQUEST['token']) && !empty($_REQUEST['token']))    $token     = e_s__($_REQUEST['token']);
// else $token = '';


// if (!isset($_REQUEST['token']) || empty($_REQUEST['token'])) {
//     // header("Location:index.php");
// }

// $sent_token = hash( "sha512", 'bushogai' . $id);


// if ($token != $sent_token) die('');
