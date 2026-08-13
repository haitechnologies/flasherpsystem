<?php


use App\Core\DB;
use App\Core\Database;
use App\Helper\PdfGeneratorHelper;
use App\Repository\EmailProviderRepository;
use App\Service\EmailProviderService;
use App\Service\SMTPMailer;
include('admin_elements/admin_header.php');

// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/EmailProviderManager.php';
// Removed legacy require for autoloader compatibility: require_once __DIR__ . '/../classes/SMTPMailer.php';

$current_module = isset($_REQUEST['current_module']) ? e_s__($_REQUEST['current_module']) : 'invoices';
$module = $current_module;
$module_caption = '';

$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

/*
|--------------------------------------------------------------------------
| CSRF TOKEN VALIDATION
|--------------------------------------------------------------------------
| Validate CSRF token for all POST requests
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token. Please refresh the page and try again.';
        log_error('CSRF token validation failed in send_email.php', 'WARNING', __FILE__, __LINE__);
    }
}

// print_r($_REQUEST);



/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/


if (empty($id)) exit;



/**
 * 📂 MODULE CONFIGURATION
 * ---------------------------------------------------------
 * Sets table names and captions based on the active module.
 */

// 1. Capture and Sanitize Module
// Already set above before permissions check

/**
 * 🛠️ DYNAMIC MODULE CONFIGURATION & CONTACT MAPPING
 * ---------------------------------------------------------
 */

// 1. Define Module Mapping with Contact Types
$modules_config = [
    'invoices'          => ['caption' => 'Invoice',          'prefix' => 'invoice',        'type' => 'customer'],
    'sale_orders'       => ['caption' => 'Sale Order',       'prefix' => 'sale_order',     'type' => 'customer'],
    'credit_notes'      => ['caption' => 'Credit Note',      'prefix' => 'credit_note',    'type' => 'customer'],
    'purchase_orders'   => ['caption' => 'Purchase Order',   'prefix' => 'purchase_order', 'type' => 'vendor'],
    'purchases'         => ['caption' => 'Purchase',         'prefix' => 'purchase',       'type' => 'vendor'],
    'quotations'        => ['caption' => 'Quotation',        'prefix' => 'quotation',      'type' => 'customer'],
    'debit_notes'       => ['caption' => 'Debit Note',       'prefix' => 'debit_note',     'type' => 'vendor'],
    'payments_received' => ['caption' => 'Payment Received', 'prefix' => 'payment',        'type' => 'customer'],
    'recurring_invoices' => ['caption' => 'Recurring Invoice', 'prefix' => 'invoice',       'type' => 'customer'],
    'payments_made'     => ['caption' => 'Payment Made',     'prefix' => 'payment_made',   'type' => 'vendor', 'no_column' => 'reference_no'],
    'expenses'          => ['caption' => 'Expense',          'prefix' => 'expense',        'type' => 'vendor', 'no_column' => 'reference_no'],
];

// 2. Resolve Current Module Attributes
$config         = $modules_config[$current_module] ?? $modules_config['invoices'];
$module_caption = $config['caption'];
$pfx            = $config['prefix'];
$type           = $config['type']; // 'customer' or 'vendor'
$tbl_name       = $tbl_prefix . $current_module;

// Recurring invoices are stored in the invoices table (recurring = 1),
// not in an erp_recurring_invoices table.
if ($current_module === 'recurring_invoices') {
    $tbl_name = DB::INVOICES;
}

// 3. Dynamic Data Extraction
$no_column  = $config['no_column'] ?? ($pfx . '_no');
$doc_no     = getTableAttr($no_column, $tbl_name, $id) ?: $id;

// 1. Determine Identity Keys based on Module Type ('customer' or 'vendor')
$contact_id_col = ($type === 'vendor') ? 'vendor_id' : 'customer_id';
$vendors_table_name = defined('DB::VENDORS') ? constant('DB::VENDORS') : ($tbl_prefix . 'vendors');
$customers_table_name = defined('DB::CUSTOMERS') ? constant('DB::CUSTOMERS') : ($tbl_prefix . 'customers');
$contact_table  = ($type === 'vendor') ? $vendors_table_name : $customers_table_name;

// 2. Fetch the Primary ID from the Module Table (e.g., fetch vendor_id from DB::PURCHASE_ORDERS)
$contact_id = getTableAttr($contact_id_col, $tbl_name, $id);

// 3. Retrieve Name and Email from the Target Table
$display_name = getTableAttr('display_name', $contact_table, $contact_id);
$send_to      = getTableAttr('email', $contact_table, $contact_id);

// Lead fallback: lead-only quotations have customer_id = 0
if ($type === 'customer' && (empty($contact_id) || $contact_id == '0')) {
    $lead_id = getTableAttr('lead_id', $tbl_name, $id);
    if (!empty($lead_id)) {
        $rs_lead = $mysqli->query("SELECT display_name, email FROM `" . DB::LEADS . "` WHERE id=$lead_id LIMIT 1");
        if ($rs_lead && ($row_lead = $rs_lead->fetch_array())) {
            $display_name = s__($row_lead['display_name']);
            $send_to      = s__($row_lead['email']);
        }
    }
}

// 4. Enhanced Subject Line (Optional: include the name for better context)
$subject = "$module_caption $doc_no is awaiting your approval";



$description_default = '';
if (empty($action)) {
    $doc_query = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id AND organization_id=$activeOrganizationId");
    if ($doc_row = $doc_query->fetch_assoc()) {
        $content = [];
        if (!empty($doc_row['subject'] ?? '')) {
            $content[] = $doc_row['subject'];
        }
        if (!empty($doc_row['customer_notes'] ?? '')) {
            $content[] = $doc_row['customer_notes'];
        } elseif (!empty($doc_row['notes'] ?? '')) {
            $content[] = $doc_row['notes'];
        }
        $description_default = implode("\n\n", $content);
    }
}

/*
|--------------------------------------------------------------------------
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "send_email") {

    $from           = e_s__($_POST['from']);
    $send_to        = e_s__($_POST['send_to']);
    $cc             = e_s__($_POST['cc'] ?? '');
    $bcc            = e_s__($_POST['bcc'] ?? '');
    $subject        = e_s__($_POST['subject']);
    $description    = e_s__($_POST['description']);
} else {

    $from           = '';
    $cc             = '';
    $bcc            = '';
    // $subject        = '';
    $description    = $description_default;
}


/*
|--------------------------------------------------------------------------
| 	SEND EMAIL
|--------------------------------------------------------------------------
|
*/

if ($action == 'send_email' && !empty($id)) {


    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$id AND organization_id=$activeOrganizationId");
    $row = $result->fetch_array();

    $customer_id            = s__($row['customer_id']);
    $display_name           = getTableAttr('display_name', DB::CUSTOMERS, $customer_id);
    // $email                  = getTableAttr('email', DB::CUSTOMERS, $customer_id);
    // $send_to                = $email;

    // Lead fallback: lead-only quotations have customer_id = 0
    if ((empty($customer_id) || $customer_id == '0') && !empty($row['lead_id'])) {
        $rs_lead = $mysqli->query("SELECT display_name, email FROM `" . DB::LEADS . "` WHERE id=" . (int)$row['lead_id'] . " LIMIT 1");
        if ($rs_lead && ($row_lead = $rs_lead->fetch_array())) {
            $display_name = s__($row_lead['display_name']);
            if (empty($send_to)) {
                $send_to = s__($row_lead['email']);
            }
        }
    }

    $doc_no                 = s__($row[$pfx . '_no'] ?? $id);
    $doc_date               = s__($row[$pfx . '_date'] ?? '');
    $grand_total            = s__($row['grand_total'] ?? '');
    $doc_date_display       = !empty($doc_date) ? dd_($doc_date) : '';
    // $agent_email    = 'imrangconnect@gmail.com';

    $email_body = "
                    Dear " . $display_name . ",<br /><br />

                    Thank you for contacting us. Your " . strtolower($module_caption) . " can be viewed, printed and downloaded as PDF from the link below.<br /><br />
					
                    " . strtoupper($module_caption) . " AMOUNT<br />
                    '". BASE_CURRENCY['code']."' $grand_total<br />
                    ".$module_caption." No $doc_no<br /><br />

                    " . $module_caption . " Date <br />
                    $doc_date_display<br /><br />

                    VIEW " . strtoupper($module_caption) . "<br /><br />";

    if (!empty($description)) {
        $email_body .= "
                    " . nl2br(htmlspecialchars($description)) . "<br /><br />";
    }

    $email_body .= "
                    Regards,<br />
                    <br />
                    ";



    // Resolve SMTP credentials strictly from selected email_providers account.
    $epm = new EmailProviderService(new EmailProviderRepository(new Database()));
    $provider = $epm->getByEmail($from);

    if (!$provider) {
        $error_message .= '<br /> Selected sender account is not active or not found in Email Providers.';
    } else {
        $provider_id = (int)$provider->id;
        $from = trim((string)$provider->email);
        $sender_name = trim((string)$provider->providerName);
    }

    // Ensure the document PDF is saved to disk so it can be attached.
    // Each module has its own PDF generator page (singular filename) and id
    // parameter, resolved centrally via App\Helper\PdfHelper.
    $attachments = [];
    $pdf_link = '';
    $pdf_page = \App\Helper\PdfHelper::pageFor($current_module);
    $pdf_id_param = \App\Helper\PdfHelper::idParamFor($current_module);

    if ($pdf_page !== '' && is_file(dirname(__DIR__) . '/dashboard/' . $pdf_page)) {
        $pdf_token = hash('sha512', 'bushogai' . $id);
        $pdf_link = rtrim($admin_base_url, '/') . '/' . $pdf_page . '?' . $pdf_id_param . '=' . $id . '&token=' . $pdf_token;

        PdfGeneratorHelper::ensure($current_module, (int)$id);

        $pdf_path = \App\Helper\PdfHelper::storageDirFor($current_module) . '/' . \App\Helper\PdfHelper::filenameWithExt((int)$id);

        if (is_file($pdf_path)) {
            $attachments[] = [
                'path' => $pdf_path,
                'name' => $module_caption . '_' . $doc_no . '.pdf',
                'mime' => 'application/pdf',
            ];
        }
    }

    $email_body = str_replace(
        'VIEW ' . strtoupper($module_caption),
        '<a href="' . htmlspecialchars($pdf_link, ENT_QUOTES, 'UTF-8') . '">VIEW ' . strtoupper($module_caption) . '</a>',
        $email_body
    );



    // Send using centralized SMTPMailer.
    if (!empty($provider) && empty($error_message)) {
        $mailer = new SMTPMailer();
        $headers = [
            'provider_id' => $provider_id,
            'from' => $from,
            'from_name' => $sender_name,
            'Reply-To' => $from,
            'CC' => $cc,
            'BCC' => $bcc,
            'attachments' => $attachments,
        ];

        $sendSuccess = $mailer->send(
            $send_to,
            $subject,
            $email_body,
            $headers
        );

        if ($sendSuccess) {
            $success_message .= '<br /> Email Sent Successfully to ' . $send_to . '.';
        } else {
            $mailerError = $mailer->getLastError();
            log_error('Document send_email failed', 'ERROR', __FILE__, __LINE__, [
                'module' => $current_module,
                'id' => $id,
                'from' => $from,
                'to' => $send_to,
                'error' => $mailerError,
            ]);
            $error_message .= '<br /> Failed to send email: ' . htmlspecialchars((string)$mailerError, ENT_QUOTES, 'UTF-8');
        }
    }

}

/*
|--------------------------------------------------------------------------
| ATTACHMENT DISPLAY (form view)
|--------------------------------------------------------------------------
| Resolve the PDF that will be attached so it can be shown/verified
| on the send-email form before sending.
*/
$attachment_display_name = '';
$attachment_display_link = '';
$attachment_available = false;

$pdf_display_page = \App\Helper\PdfHelper::pageFor($current_module);
$pdf_display_id_param = \App\Helper\PdfHelper::idParamFor($current_module);

if ($pdf_display_page !== '' && is_file(dirname(__DIR__) . '/dashboard/' . $pdf_display_page)) {
    PdfGeneratorHelper::ensure($current_module, (int)$id);

    $pdf_display_path = \App\Helper\PdfHelper::storageDirFor($current_module) . '/' . \App\Helper\PdfHelper::filenameWithExt((int)$id);

    if (is_file($pdf_display_path)) {
        $pdf_display_token = hash('sha512', 'bushogai' . $id);
        $attachment_display_link = rtrim($admin_base_url, '/') . '/' . $pdf_display_page . '?' . $pdf_display_id_param . '=' . $id . '&token=' . $pdf_display_token;
        $attachment_display_name = $module_caption . '_' . $doc_no . '.pdf';
        $attachment_available = true;
    }
}

/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

?>
<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0"><?php if (!empty($id)) { ?>Send <?php echo $module_caption; ?> #<?php echo $doc_no; } else { ?>New <?php echo $module_caption; } ?></h5>
            </div>

            <div class="my-1">
                <?php if (empty($id) || (isset($module_id) && granted('create', $module_id)) || (isset($module_id) && granted('edit', $module_id)) || $file === 'profile.php' || $file === 'change_password.php') { ?>
                    <button type="submit" form="frmsend_email" class="btn btn-primary btn-sm me-2">Send</button>
                <?php } ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frmsend_email" name="frmsend_email" action="send_email.php" autocomplete="off" enctype="multipart/form-data">
        <input type="hidden" name="action" id="action" value="send_email" />
        <input type="hidden" name="current_module" id="current_module" value="<?php echo $current_module; ?>" />
        <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
        <?php echo csrf_field(); ?>


        <!-- Page header -->


                <div class="row">

                    <div class="col-lg-6">
                        <div class="card">

                            <?php $from = getTableAttrV('email', DB::EMAIL_PROVIDERS, "is_primary = 1"); ?>
                            <div class="card-body">

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">From: <span class="text-danger">*</span> <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="This email address is fetched from the Organization Profile under Settings. You can edit it from Settings anytime you wish."></i> </label>
                                    <div class="col-lg-9">
                                        <input required type="email" name="from" id="from" value="<?php echo $from; ?>" class="form-control bg-light" readonly>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Send To: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="email" name="send_to" id="send_to" value="<?php echo $send_to; ?>" class="form-control">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">CC: </label>
                                    <div class="col-lg-9">
                                        <input type="email" name="cc" id="cc" value="<?php echo $cc; ?>" class="form-control">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">BCC: </label>
                                    <div class="col-lg-9">
                                        <input type="email" name="bcc" id="bcc" value="<?php echo $bcc; ?>" class="form-control">
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Subject: <span class="text-danger">*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="subject" id="subject" value="<?php echo $subject; ?>" class="form-control">
                                    </div>
                                </div>


                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Description: </label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" rows="10" name="description" id="description"><?php echo $description; ?></textarea>
                                    </div>
                                </div>


                            </div>


                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Attachment</h6>
                            </div>
                            <div class="card-body">
                                <?php if ($attachment_available) { ?>
                                    <div class="d-flex align-items-center mb-2">
                                        <i class="ph-file-pdf me-2 text-danger"></i>
                                        <span class="fw-semibold"><?php echo $attachment_display_name; ?></span>
                                    </div>
                                    <p class="text-muted mb-0">This PDF will be attached to the email when sent.</p>
                                    <a href="<?php echo $attachment_display_link; ?>" target="_blank" class="btn btn-outline-primary btn-sm mt-2">
                                        <i class="ph-eye me-1"></i> View / Download PDF
                                    </a>
                                <?php } else { ?>
                                    <p class="text-muted mb-0">No PDF attachment available for this document.</p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>


        </div>


        </form>
    <?php include('admin_elements/copyright.php'); ?>
</div>

</div>


<!-- 
    // ---------------------------------------------------------
    // ENABLE VIEW ONLY MODE FOR FORM ELEMENTS
    // ---------------------------------------------------------
-->
<?php if (isset($module_id) && granted('view', $module_id) && !granted('create', $module_id) && !granted('edit', $module_id)) { ?>
    <script>
        $(function() {
            toggleFormElements('true');
        });
    </script>
<?php } ?>

<?php include('admin_elements/admin_footer.php'); ?>