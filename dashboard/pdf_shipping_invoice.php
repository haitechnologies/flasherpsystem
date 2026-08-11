<?php
require_once __DIR__ . '/admin_elements/error_handler_init.php';
session_start();
header("Content-Type: text/html; charset=utf-8");
use App\Core\DB;
require('../config/globals.php');
require('../config/database.php');
include('../config/images.php');
include('admin_elements/timeout.php');
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

    $result = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_INVOICES . "` WHERE id=$id");
    $row = $result->fetch_array();

    $customer_id                = s__($row['customer_id']);

    $display_name           = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
    $customer_trn           = getTableAttr('trn', DB::CUSTOMERS, $customer_id);
    $customer_phone         = getTableAttr('phone', DB::CUSTOMERS, $customer_id);

    $invoice_qrcode       = '';
    // $invoice_qrcode       = s__($row['qrcode']);
    $invoice_no           = s__($row['invoice_no']);

    // $invoice_status       = s__($row['invoice_status']);
    // $invoice_status       = colorfulinvoicestatus($invoice_status);

    $pkgs                   = s__($row['pkgs']);
    $weight                 = s__($row['weight']);
    $awb                    = s__($row['awb']);

    $invoice_date           = s__($row['invoice_date']);
    $invoice_date           = ddm_($invoice_date);

    $grand_total            = s__($row['grand_total']);

    $grand_total            = (($grand_total == 0)  ? '0' : $grand_total);

    $created_at             = s__($row['created_at']);
    $created_time           = date('h:i:s', strtotime($created_at));
    $created_date           = date('d M Y', strtotime($created_at));
    $created_by             = s__($row['created_by']);
    $created_by             = getUsernameByID($created_by);

    // $grand_total_in_words  = ucwords(convert_number_to_words($grand_total));

    $spell_out = '';
    // Need to enable Extension intl in php.ini
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);

    if (!empty($grand_total)){

        $spell_out = $f->format($grand_total);
        $spell_out = str_ireplace(' point ', '.', ucwords($spell_out));
    
        if (str_contains($spell_out, '.')) {
            // $spell_out .= ' AED';
        }
    }

    // if (Str::contains($haystack, 'needles'))
    // $grand_total_in_words  = ucwords($spell_out);
    $grand_total_in_words  = '';

    $publish                = s__($row['publish']);


//     $company_name       = getTableAttr('company_name', tbl_companies, $company_id);
//     $company_code       = getTableAttr('company_code', tbl_companies, $company_id);
//     $company_email      = getTableAttr('email', tbl_companies, $company_id);
//     $company_mobile     = getTableAttr('mobile', tbl_companies, $company_id);
//     $company_address    = getTableAttr('address', tbl_companies, $company_id);


    $row_no = 1;
    $item_row = '';

    // ------------------ TOTAL SHIPPING INVOICE ITEMS ------------------
    $result_shipping_invoice_items  = $mysqli->query("SELECT * FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE invoice_id=$id");
    $total_rows                     = $result_shipping_invoice_items->num_rows;

    if ($total_rows > 0) {
        while ($row_shipping_invoice_items = $result_shipping_invoice_items->fetch_array()) {

            $description    = $row_shipping_invoice_items['description'];
            $coo            = $row_shipping_invoice_items['coo'];
            $coo            = getTableAttr('alpha2_code', tbl_geo_countries, $coo);
            $declaration_no = $row_shipping_invoice_items['declaration_no'];
            $hscode         = $row_shipping_invoice_items['hscode'];

            $qty            = $row_shipping_invoice_items['qty'];
            $rate           = $row_shipping_invoice_items['rate'];
            $total          = $row_shipping_invoice_items['total'];

            $qty            = (($qty == 1)  ? '': $qty);
            $rate           = (($rate == 0)  ? '': $rate);

            if ($row_no % 2 == 0) {
                // $row_bg = 'background-color: #dce9f7;';
            } else {
                // $row_bg = 'background-color: #ffffff;';
            }

            $item_row .= '
            <tr>
                <td align="center" style="' . $row_bg . ' border:1px solid silver"> ' . $row_no++ . ' </td>
                <td align="left" style="' . $row_bg . ' border:1px solid silver">' . $description . ' </td>
                <td align="center" style="' . $row_bg . ' border:1px solid silver">' . $coo . ' </td>
                <td align="center" style="' . $row_bg . ' border:1px solid silver">' . $declaration_no . ' </td>
                <td align="center" style="' . $row_bg . ' border:1px solid silver">' . $hscode . ' </td>
                <td align="center" style="' . $row_bg . ' border:1px solid silver">' . $qty . ' </td>
                <td align="center" style="' . $row_bg . ' border:1px solid silver">' . $rate . ' </td>
                <td align="right" style="' . $row_bg . ' border:1px solid silver">' . $total . ' </td>
            </tr>';
        } // while
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


$tbl = <<<EOD
<table cellpadding="0" cellspacing="2" border="0">
<tr>

<td width="272" style="background-color: #fff;" align="center">
    <img src="$display_logo" height="80"><br />
    <span style="font-size: 18px; color:#102B44"> $company_name </span>
</td>

<td width="120" align="center">  <br /><br /><br /><br /> <img src="../qrcodes_invoices/$invoice_qrcode.png" width="65"></td>

<td width="272" align="right">
    <span style="font-size: 18px;"> INVOICE </span><br /><br />

    <table cellpadding="5" cellspacing="0" border="0" style="border:1px solid silver;">
        <tr>
            <td align="left" width="109">Invoice No:</td>
            <td align="left" width="160">$invoice_no</td>
        </tr>
        <tr>
            <td align="left">Invoice Date:</td>
            <td align="left">$invoice_date</td>
        </tr>
    </table>
</td>

</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');


// -----------------------------------------------------------------------------


// $tbl = <<<EOD
// <table cellpadding="5" cellspacing="0" border="0" style="border:1px solid silver;">

// <tr>
// <th width="220" style="border:1px solid silver" align="center"> <span style="font-size:14px;border:1px solid silver"> QUOTATION </span> </th>
// <th rowspan="6" width="450">  

// <span> Customer </span> 
// <span> company_name </span> <br /><br />

// <span> &nbsp; Code  </span>
// <span> &nbsp; &nbsp; &nbsp; company_code </span>  <br /><br />


// </th>
// </tr>

// <tr>
// <td style="background-color: #eeeef0; border:1px solid silver" align="center">  cash_credit </td>
// <td></td>
// </tr>
// <tr>
// <td style="background-color: #eeeef0; border:1px solid silver">  Quotation No: id </td>
// <td></td>
// </tr>
// <tr>
// <td style="background-color: #eeeef0; border:1px solid silver">  Date: $quotation_date </td>
// <td></td>
// </tr>
// <tr>
// <td style="background-color: #eeeef0; border:1px solid silver">&nbsp; Mobile: mobile   </td>
// <td></td>
// </tr>
// <tr>
// <td style="background-color: #eeeef0; border:1px solid silver">&nbsp; Email: email  </td>
// <td></td>
// </tr>
// </table>
// EOD;

// $pdf->writeHTML($tbl, true, false, false, false, '');




// -----------------------------------------------------------------------------

// if (str_contains($row_bg, '#ffffff')) {
//     $row_bg_odd = 'background-color: #dce9f7;';
//     $row_bg_even = 'background-color: #ffffff;';
// } else {
//     $row_bg_odd = 'background-color: #ffffff;';
//     $row_bg_even = 'background-color: #dce9f7;';
// }


$tbl = <<<EOD
<table cellpadding="5" cellspacing="0" border="0">

<tr><td></td></tr>


<tr>
<td width="40" style="background-color: #ccc; border:1px solid silver;" align="center"> <span style="color: #000;"> No. </span> </td>
<td width="220" style="background-color: #ccc; border:1px solid silver;"> <span style="color: #000;"> Description </span> </td>
<td width="50" style="background-color: #ccc; border:1px solid silver;" align="center"> <span style="color: #000;"> Origin </span> </td>
<td width="100" style="background-color: #ccc; border:1px solid silver;" align="center"> <span style="color: #000;"> Declaration No </span> </td>
<td width="80" style="background-color: #ccc; border:1px solid silver;" align="center"> <span style="color: #000;"> HS Code </span> </td>
<td width="50" style="background-color: #ccc; border:1px solid silver;" align="center"> <span style="color: #000;"> Qty </span> </td>
<td width="75" style="background-color: #ccc; border:1px solid silver" align="center"> <span style="color: #000;"> Rate </span> </td>
<td width="60" style="background-color: #ccc; border:1px solid silver" align="center"> <span style="color: #000;"> Amount </span> </td>
</tr>

$item_row

<tr>
<td></td>
</tr>


<tr>
<td colspan="3"></td>
<td colspan="4" style="' . row_bg_odd . ' border:1px solid silver; " align="right"> Grand Total </td>
<td style="' . row_bg_odd . ' border:1px solid silver; " align="right"> $grand_total  </td>
</tr>

<tr>
<td colspan="7"  align="right"> $grand_total_in_words</td>
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
<td width="100">PLT/BOX/PKG's:</td>
<td width="570">$pkgs </td>
</tr>
<tr>
<td width="100">WEIGHT:</td>
<td width="570">$weight </td>
</tr>
<tr>
<td width="100">AWB:</td>
<td width="570">$awb </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');



// -----------------------------------------------------------------------------



    // -----------------------------------------------------------------------------

    // $filename = 'Booking_' . $id . '.pdf';

    // $salt =  '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN'; // random string 
    // $encrypted_filename = crc32($id . $salt);
    // $encrypted_filename = sha($id . $salt);

    $salt =  '}#f4ga~g%7hjg4&jokho!bj30ab-wi=6gia^7-$^R9F|GaK5Jzxs#E6WT;IOJN'; // random string 
    $encrypted_filename = hash('sha256', $salt . $id);


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
//     $pdf->Output($_SERVER['DOCUMENT_ROOT'] . '/thezphoenix/pdfs_invoices/' . $encrypted_filename . '.pdf', 'F');
// }

$pdf->Output($encrypted_filename, 'I');  // Flag - I (show file)
    //============================================================+
    // END OF FILE
    //============================================================+



    // https://stackoverflow.com/questions/29121375/fpdf-outputfilename-pdf-f-downloading-file-on-browser-instead-of-saving-i
    // I: send the file inline to the browser. The plug-in is used if available. The name given by name is used when one selects the "Save as" option on the link generating the PDF.
    // D: send to the browser and force a file download with the name given by name.
    // F: save to a local file with the name given by name (may include a path).
    // S: return the document as a string. name is ignored.



    // ---------------------------------------------
    // UPDATE PDF DB 
    // ---------------------------------------------
    $mysqli->query("UPDATE `" . tbl_quotations . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");




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
