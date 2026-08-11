<?php

require_once __DIR__ . '/admin_elements/error_handler_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\DB;
use App\Core\Session;
use App\Core\Container;
use App\Core\Database;
use App\Repository\CreditNoteRepository;
use App\Repository\CustomerRepository;
use App\Service\CreditNoteService;

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
$pdf->setTitle('Credit Note');
$pdf->setSubject('Credit Note');

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

$base_currency_code = BASE_CURRENCY['code'];

if (isset($_REQUEST['credit_note_id']) && !empty($_REQUEST['credit_note_id'])) {
    $id = e_s__($_REQUEST['credit_note_id']);
} elseif (isset($_REQUEST['id']) && !empty($_REQUEST['id'])) {
    $id = e_s__($_REQUEST['id']);
} else {
    $id = 0;
}

if (isset($_REQUEST['token']) && !empty($_REQUEST['token'])) {
    $token = e_s__($_REQUEST['token']);
} else {
    $token = '';
}

if (!isset($_REQUEST['token']) || empty($_REQUEST['token'])) {
    header("Location:index.php");
}

$sent_token = hash("sha512", 'bushogai' . $id);

if ($token != $sent_token) die('');

if (empty($id)) die('');

$credit_note_no      = '';
$credit_note_date    = '';
$credit_note_status  = '';
$reference_no        = '';
$expiry_date         = '';
$expected_shipment_date = '';
$payment_term        = 0;
$shipment_type       = '';
$subject             = '';
$job_reference_no    = '';
$master_awb_no       = '';
$shipper             = 0;
$consignee           = 0;
$origin              = 0;
$destination         = 0;
$no_of_packs         = 0;
$gross_weight        = '0.00';
$chargeable_weight   = '0.00';
$volume              = '0.00';
$customer_id         = 0;
$warehouse_id        = 0;
$created_by          = 0;
$created_at          = '';
$item_row            = '';
$total_rows          = 0;
$grand_subtotal      = '0.00';
$grand_discount_type = '';
$grand_discount_amount = '0.00';
$grand_after_discount = '0.00';
$grand_tax           = '0.00';
$grand_total         = '0.00';

if (!empty($id)) {
    $container = Container::getInstance();
    if (!$container->has(Database::class)) {
        $container->register(Database::class, fn() => new Database());
    }
    if (!$container->has(CustomerRepository::class)) {
        $container->register(CustomerRepository::class, fn($c) => new CustomerRepository($c->get(Database::class)));
    }
    if (!$container->has(CreditNoteRepository::class)) {
        $container->register(CreditNoteRepository::class, fn($c) => new CreditNoteRepository($c->get(Database::class)));
    }
    if (!$container->has(CreditNoteService::class)) {
        $container->register(CreditNoteService::class, fn($c) => new CreditNoteService(
            $c->get(CreditNoteRepository::class),
            $c->get(CustomerRepository::class),
            $c->get(\App\Repository\JournalRepository::class),
            $c->get(\App\Service\JournalService::class),
            $c->get(Database::class)
        ));
    }
    $creditNoteService = $container->get(CreditNoteService::class);

    try {
        $creditNote = $creditNoteService->getCreditNote((int)$id, (int)$activeOrgId);
    } catch (\Throwable $e) {
        die('Credit Note not found.');
    }

    $customer_id         = $creditNote->customerId;
    $credit_note_no      = $creditNote->creditNoteNo;
    $credit_note_date    = $creditNote->creditNoteDate;
    $credit_note_status  = $creditNote->creditNoteStatus;
    $reference_no        = $creditNote->referenceNo;
    $expiry_date         = $creditNote->expiryDate;
    $expected_shipment_date = $creditNote->expectedShipmentDate;
    $payment_term        = $creditNote->paymentTerm;
    $shipment_type       = $creditNote->shipmentType;
    $subject             = $creditNote->subject;
    $job_reference_no    = $creditNote->jobReferenceNo;
    $master_awb_no       = $creditNote->masterAwbNo;
    $shipper             = $creditNote->shipper;
    $consignee           = $creditNote->consignee;
    $origin              = $creditNote->origin;
    $destination         = $creditNote->destination;
    $no_of_packs         = $creditNote->noOfPacks;
    $gross_weight        = $creditNote->grossWeight;
    $chargeable_weight   = $creditNote->chargeableWeight;
    $volume              = $creditNote->volume;
    $warehouse_id        = $creditNote->warehouseId;
    $created_at          = $creditNote->createdAt;
    $created_by          = $creditNote->createdBy;
    $grand_subtotal      = $creditNote->grandSubtotal;
    $grand_discount_type = $creditNote->grandDiscountType;
    $grand_discount_amount = $creditNote->grandDiscountAmount;
    $grand_after_discount = $creditNote->grandAfterDiscount;
    $grand_tax           = $creditNote->grandTax;
    $grand_total         = $creditNote->grandTotal;

    $credit_note_date = ddm_($credit_note_date);
    $expiry_date      = (!empty($expiry_date) && $expiry_date !== '1970-01-01') ? ddm_($expiry_date) : '';
    $expected_shipment_date = (!empty($expected_shipment_date) && $expected_shipment_date !== '1970-01-01') ? ddm_($expected_shipment_date) : '';

    $customer_name  = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
    $customer_trn   = getTableAttr('trn', DB::CUSTOMERS, $customer_id);
    $customer_phone = getTableAttr('phone', DB::CUSTOMERS, $customer_id);

    $created_time = !empty($created_at) ? date('h:i:s', strtotime($created_at)) : '';
    $created_date = !empty($created_at) ? date('d M Y', strtotime($created_at)) : '';
    $created_by   = getUsernameByID($created_by);

    $payment_term_text = '';
    if (!empty($payment_term)) {
        $payment_term_text = getTableAttr('payment_term', DB::PAYMENT_TERMS, $payment_term);
    }
    $shipper_text      = !empty($shipper) ? getTableAttr('shipper_name', DB::SHIPPERS, $shipper) : '';
    $consignee_text    = !empty($consignee) ? getTableAttr('consignee_name', DB::CONSIGNEES, $consignee) : '';
    $origin_text       = !empty($origin) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $origin) : '';
    $destination_text  = !empty($destination) ? getTableAttr('country_name', DB::GEO_COUNTRIES, $destination) : '';

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

    $credit_note_items = $creditNoteService->getCreditNoteItems((int)$id, (int)$activeOrgId);
    $total_rows = count($credit_note_items);

    if ($total_rows > 0) {
        foreach ($credit_note_items as $row_items) {
            $service_name = $row_items->service;
            $description  = $row_items->description;
            $qty          = $row_items->qty;
            $rate         = $row_items->rate;
            $total        = $row_items->total;

            if (!empty($service_name)) {
                $service_name = getTableAttr('item_name', DB::ITEMS, $service_name);
            }

            $item_row .= '<tr>';
            $item_row .= '<td style="border:1px solid #ddd; color:#555;">' . $service_name . '</td>';
            $item_row .= '<td style="border:1px solid #ddd; color:#555;">' . $description . '</td>';
            $item_row .= '<td style="border:1px solid #ddd; color:#555; text-align:right;">' . number_format((!empty($qty) ? $qty : 0), 2) . '</td>';
            $item_row .= '<td style="border:1px solid #ddd; color:#555; text-align:right;">' . $base_currency_code . ' ' . number_format((!empty($rate) ? $rate : 0), 2) . '</td>';
            $item_row .= '<td style="border:1px solid #ddd; color:#555; text-align:right;">' . $base_currency_code . ' ' . number_format((!empty($total) ? $total : 0), 2) . '</td>';
            $item_row .= '</tr>';
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

$warehouse_information = '';

if (!empty($warehouse_id)) {
    $rs_warehouse = $mysqli->query("SELECT * FROM `" . DB::ORGANIZATIONS . "` WHERE id = $warehouse_id");
    if ($rs_warehouse && $rs_warehouse->num_rows > 0) {
        $row_warehouse = $rs_warehouse->fetch_array();

        $warehouse_no   = s__($row_warehouse['warehouse_no'] ?? '');
        $warehouse_name = s__($row_warehouse['warehouse_name'] ?? '');
        $street1        = s__($row_warehouse['street1'] ?? '');
        $street2        = s__($row_warehouse['street2'] ?? '');

        $country        = s__($row_warehouse['country'] ?? '');
        $country        = getTableAttr('country', DB::GEO_COUNTRIES, $country);

        $state          = s__($row_warehouse['state'] ?? '');
        $state          = getTableAttr('state', DB::GEO_STATES, $state);

        $phone          = s__($row_warehouse['phone'] ?? '');
        $email          = s__($row_warehouse['email'] ?? '');
        $trn            = s__($row_warehouse['trn'] ?? '');

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
}

// --- Header ---
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

// --- Credit Note Title ---
$status_badge = '';
if (!empty($credit_note_status)) {
    $status_label = strtoupper($credit_note_status);
    if ($credit_note_status === 'void') {
        $status_badge = ' <span style="color:#dc3545;">- VOID</span>';
    } elseif ($credit_note_status === 'open') {
        $status_badge = ' <span style="color:#28a745;">- OPEN</span>';
    } else {
        $status_badge = ' <span style="color:#666;">- ' . $status_label . '</span>';
    }
}

$tbl = <<<EOD
<br />
<table cellpadding="4" cellspacing="0" border="0">
<tr>
    <td width="664" align="center">
        <span style="font-size: 16px; color:#102B44; font-weight: bold;">CREDIT NOTE$status_badge</span>
    </td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Credit Note Details ---
$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="1" style="width:100%; border-collapse:collapse;">
<tr>
    <td width="55%" style="border:1px solid #ddd; padding:8px; vertical-align:top;">
        <table cellpadding="2" cellspacing="0" style="width:100%;">
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555; width:40%;">Credit Note Number:</td>
                <td style="border:none; color:#333; font-weight:bold;">$credit_note_no</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555; width:40%;">Credit Note Date:</td>
                <td style="border:none; color:#333; font-weight:bold;">$credit_note_date</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555;">Reference Number:</td>
                <td style="border:none; color:#333; font-weight:bold;">$reference_no</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555;">Payment Term:</td>
                <td style="border:none; color:#333; font-weight:bold;">$payment_term_text</td>
            </tr>
            <tr style="border:none;">
                <td style="border:none; font-weight:bold; color:#555;">Subject:</td>
                <td style="border:none; color:#333; font-weight:bold;">$subject</td>
            </tr>
        </table>
    </td>
    <td width="45%" style="border:1px solid #28a745; background-color:#28a745; padding:12px; text-align:center; vertical-align:middle;">
        <div style="color:#ffffff; font-size:9px; margin-bottom:4px;">
            CREDIT NOTE AMOUNT
        </div>
        <div style="color:#ffffff; font-size:16px; font-weight:bold;">$base_currency_code $grand_total</div>
    </td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Customer Information ---
$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="0" style="width:100%;">
<tr>
    <td style="color: #666; font-size:10px; font-weight:bold;">Bill To</td>
</tr>
<tr>
    <td style="color: #333; font-size:12px; font-weight:bold;">$customer_name</td>
</tr>
<tr>
    <td style="color: #555; font-size:10px;">$customer_trn</td>
</tr>
<tr>
    <td style="color: #555; font-size:10px;">$customer_phone</td>
</tr>
</table>
<br />
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Items Table ---
$item_table = '';
if ($total_rows > 0) {
    $item_table = '
<table border="1" cellpadding="4" cellspacing="0" style="width:100%; border-collapse:collapse;">
<tr style="background-color:#f0f0f0; font-weight:bold;">
<td style="border:1px solid #ddd; color:#333;">Service</td>
<td style="border:1px solid #ddd; color:#333;">Description</td>
<td style="border:1px solid #ddd; color:#333; text-align:right;">Qty</td>
<td style="border:1px solid #ddd; color:#333; text-align:right;">Rate</td>
<td style="border:1px solid #ddd; color:#333; text-align:right;">Amount</td>
</tr>';
    $item_table .= $item_row;
    $item_table .= '</table>';
} else {
    $item_table = '<div style="text-align:center; color:#999; font-size:10px; padding:20px;">No items found</div>';
}

// --- Totals Summary ---
$totals_table = '
<table cellpadding="4" cellspacing="0" border="0" style="width:100%;">
<tr>
    <td style="border:none; color:#555; font-weight:bold; text-align:right;">Subtotal</td>
    <td style="border:none; color:#333; text-align:right; width:150px;">' . $base_currency_code . ' ' . number_format((float)$grand_subtotal, 2) . '</td>
</tr>';
if (!empty($grand_discount_type) && (float)$grand_discount_amount > 0) {
    $totals_table .= '
<tr>
    <td style="border:none; color:#555; font-weight:bold; text-align:right;">Discount</td>
    <td style="border:none; color:#333; text-align:right;">' . $base_currency_code . ' ' . number_format((float)$grand_discount_amount, 2) . '</td>
</tr>';
}
$totals_table .= '
<tr>
    <td style="border:none; color:#555; font-weight:bold; text-align:right;">After Discount</td>
    <td style="border:none; color:#333; text-align:right;">' . $base_currency_code . ' ' . number_format((float)$grand_after_discount, 2) . '</td>
</tr>
<tr>
    <td style="border:none; color:#555; font-weight:bold; text-align:right;">Tax</td>
    <td style="border:none; color:#333; text-align:right;">' . $base_currency_code . ' ' . number_format((float)$grand_tax, 2) . '</td>
</tr>
<tr>
    <td style="border:none; color:#102B44; font-weight:bold; font-size:12px; text-align:right;">Grand Total</td>
    <td style="border:none; color:#102B44; font-weight:bold; font-size:12px; text-align:right;">' . $base_currency_code . ' ' . number_format((float)$grand_total, 2) . '</td>
</tr>
</table>';

$tbl = <<<EOD
$item_table
<br />
$totals_table
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Amount in Words ---
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

// --- Footer / Signature ---
$tbl = <<<EOD
<table cellpadding="4" cellspacing="0" border="0">
<tr>
    <td width="332" align="center" style="border-top:1px solid #333; color: #555;">
        <br />
        <strong>Authorized Signature</strong>
    </td>
    <td width="332" align="center" style="border-top:1px solid #333; color: #555;">
        <br />
        <strong>Customer Signature</strong>
    </td>
</tr>
</table>
EOD;

$pdf->writeHTML($tbl, true, false, false, false, '');

// --- Output / persistence ---
$encrypted_filename = \App\Helper\PdfHelper::filename((int)$id);
$pdfs_dir = dirname(__DIR__) . '/pdfs_credit_notes';
if (!is_dir($pdfs_dir)) {
    @mkdir($pdfs_dir, 0755, true);
}
$pdf_path = $pdfs_dir . '/' . $encrypted_filename . '.pdf';

$save_mode = isset($_GET['mode']) && $_GET['mode'] === 'save';

if ($save_mode) {
    // File-only generation (used by send_email.php to attach the PDF)
    $pdf->Output($pdf_path, 'F');
    $mysqli->query("UPDATE `" . DB::CREDIT_NOTES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'filename' => $encrypted_filename . '.pdf', 'path' => $pdf_path]);
    exit;
}

// Persist PDF to disk so the file + pdf column are always available
if (!is_file($pdf_path)) {
    $pdf->Output($pdf_path, 'F');
}
$mysqli->query("UPDATE `" . DB::CREDIT_NOTES . "` SET pdf = '" . $encrypted_filename . "' WHERE id=$id");

$pdf->Output($encrypted_filename, 'I');
