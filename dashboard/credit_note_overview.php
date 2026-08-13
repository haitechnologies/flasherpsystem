<?php

use App\Core\DB;
use App\Core\Container;
use App\Core\Session;
use App\Service\CreditNoteService;
include('admin_elements/admin_header.php');

$module = 'credit_notes';
$module_caption = 'Credit Note';
$tbl_name = DB::CREDIT_NOTES;
$error_message = '';
$success_message = '';

/*
|--------------------------------------------------------------------------
| PERMISSIONS
|--------------------------------------------------------------------------
|
*/
include('admin_elements/permissions.php');


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/

$credit_note_id = '';
if (isset($_REQUEST['credit_note_id']))        $credit_note_id     = e_s__($_REQUEST['credit_note_id']);
if (isset($_POST['credit_note_id']))           $credit_note_id     = e_s__($_POST['credit_note_id']);
if (empty($credit_note_id) && isset($_REQUEST['id'])) $credit_note_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID (org-scoped prepared statement)
$credit_note_id = (int)$credit_note_id;
$db = Container::getInstance()->get(\App\Core\Database::class);
$exists = $db->fetchOne("SELECT id FROM `{DB::CREDIT_NOTES}` WHERE id=:id AND organization_id=:org_id", ['id' => $credit_note_id, 'org_id' => (int)Session::orgId()]);
if ($exists === null) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_credit_notes.php");
    exit;
}

// ------------------ VIEW GATE ----------------
if (!granted('view', $module_id)) {
    log_error("Unauthorized view access: User " . Session::userId() . " tried to view credit note $credit_note_id", 'WARNING', __FILE__, __LINE__);
    flash_error('You do not have permission to view this credit note.');
    header("Location:listing_credit_notes.php");
    exit;
}

$creditNoteService = Container::getInstance()->get(CreditNoteService::class);
$note = $creditNoteService->getCreditNote($credit_note_id, (int)Session::orgId());
$canManageRecord = \App\Core\Roles::hasFullAccess((int)Session::roleId()) || (int)$note->createdBy === (int)Session::userId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !empty($action)) {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        flash_error('Invalid CSRF token.');
        header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
        exit;
    }
}


/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$credit_note_status = 0;
if (isset($_REQUEST['credit_note_status']) && !empty($_REQUEST['credit_note_status'])) {
    $credit_note_status   = e_s__($_REQUEST['credit_note_status']);
}


/*
|--------------------------------------------------------------------------
| CONVERT
|--------------------------------------------------------------------------
|
*/

if (($action == "convert_$module" && !empty($credit_note_id))) {

    if (!granted('edit', $module_id) || !$canManageRecord) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to convert credit note $credit_note_id", 'WARNING', __FILE__, __LINE__);
        flash_error('You do not have permission to perform this action.');
        header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
        exit;
    }

    $creditNoteService = Container::getInstance()->get(CreditNoteService::class);
    $newInvoiceId = $creditNoteService->convertToInvoice((int)$credit_note_id, (int)Session::orgId(), (int)Session::userId());
    if ($newInvoiceId > 0) {
        $invoice_no = getTableAttr('invoice_no', DB::INVOICES, $newInvoiceId);
        $success_message = 'This Invoice has been Converted to Invoice Successfully. Please click here to view. <a href="invoice_overview.php?invoice_id=' . $newInvoiceId . '"> ' . $invoice_no . '</a>';
    } else {
        $error_message = 'Credit Note could not be converted to an Invoice.';
    }




    /*
|--------------------------------------------------------------------------
| CLONE
|--------------------------------------------------------------------------
|
*/
} else if (($action == "clone_$module" && !empty($credit_note_id))) {

    if (!granted('create', $module_id) || !$canManageRecord) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to clone credit note $credit_note_id", 'WARNING', __FILE__, __LINE__);
        flash_error('You do not have permission to perform this action.');
        header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
        exit;
    }

    $creditNoteService = Container::getInstance()->get(CreditNoteService::class);
    $newCloned = $creditNoteService->cloneNote((int)$credit_note_id, (int)Session::orgId(), (int)Session::userId());
    if ($newCloned !== null) {
        $new_cloned_id = (int)$newCloned->id;
        $credit_note_no = $newCloned->creditNoteNo;
        $success_message = 'Credit Note has been cloned successfully. Please click here to view. <a href="credit_note_overview.php?credit_note_id=' . $new_cloned_id . '"> ' . $credit_note_no . '</a>';
        flash_success($success_message);
        header("Location:credit_note_overview.php?credit_note_id=$new_cloned_id");
        exit;
    }
    $error_message = 'Credit Note could not be cloned.';





    /*
|--------------------------------------------------------------------------
| UPDATE Credit Note STATUS
|--------------------------------------------------------------------------
|
*/
} else if (($action == "update_$module" && !empty($credit_note_id) && !empty($credit_note_status))) {

    if (!granted('edit', $module_id) || !$canManageRecord) {
        log_error("IDOR attempt: User " . Session::userId() . " tried to update status of credit note $credit_note_id", 'WARNING', __FILE__, __LINE__);
        flash_error('You do not have permission to perform this action.');
        header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
        exit;
    }

    $creditNoteService = Container::getInstance()->get(CreditNoteService::class);
    $orgId = (int)Session::orgId();
    $userId = (int)Session::userId();

    try {
        if ($credit_note_status === 'void') {
            $creditNoteService->voidNote((int)$credit_note_id, $orgId, $userId);
        } elseif ($credit_note_status === 'open') {
            $creditNoteService->openNote((int)$credit_note_id, $orgId, $userId);
        } else {
            $creditNoteService->updateStatus((int)$credit_note_id, (string)$credit_note_status, $orgId);
        }
        $success_message = "The $module_caption status has been updated successfully.";
    } catch (\Throwable $e) {
        log_error($e->getMessage(), 'ERROR', $e->getFile(), $e->getLine(), ['module' => 'credit_notes', 'action' => 'update_status', 'credit_note_id' => (int)$credit_note_id]);
        $success_message = "The $module_caption status could not be updated: " . $e->getMessage();
    }

    flash_success($success_message);
    header("Location:credit_note_overview.php?credit_note_id=$credit_note_id");
    exit;
}
/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/



if (isset($_POST['total_rows']) && !empty($_POST['total_rows'])) {
    $total_rows            = e_s__($_POST['total_rows']);
    // if ($total_rows == 0 || $total_rows == '') $total_rows = 1;
} else {
    $total_rows            = 1;
}



/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/
?>

<div class="sidebar sidebar-secondary sidebar-expand-lg">

    <!-- Expand button -->
    <button type="button" class="btn btn-sidebar-expand sidebar-control sidebar-secondary-toggle h-100">
        <i class="ph-caret-right"></i>
    </button>
    <!-- /expand button -->


    <!-- Sidebar content -->
    <?php include('admin_elements/sidebar_credit_note.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
         
        <?php include('admin_elements/page_header_credit_note.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <?php
            /*
                |--------------------------------------------------------------------------
                | EDIT
                |--------------------------------------------------------------------------
                |
                */
            if (!empty($credit_note_id)) {

                $customer_id            = (int)$note->customerId;
                $credit_note_no          = (string)$note->creditNoteNo;
                $credit_note_status      = (string)$note->creditNoteStatus;
                $credit_note_date        = (string)$note->creditNoteDate;
                $expiry_date            = $note->expiryDate !== null ? (string)$note->expiryDate : '';
                $reference_no           = $note->referenceNo !== null ? (string)$note->referenceNo : '';

                $expected_shipment_date = $note->expectedShipmentDate !== null ? (string)$note->expectedShipmentDate : '';
                $payment_term           = $note->paymentTerm !== null ? (string)$note->paymentTerm : '0';

                $shipment_type          = $note->shipmentType !== null ? (string)$note->shipmentType : '';
                $sales_person           = $note->salesPerson !== null ? (string)$note->salesPerson : '0';
                $job_reference_no       = $note->jobReferenceNo !== null ? (string)$note->jobReferenceNo : '';
                $master_awb_no          = $note->masterAwbNo !== null ? (string)$note->masterAwbNo : '';
                $shipper                = $note->shipper !== null ? (string)$note->shipper : '0';
                $consignee              = $note->consignee !== null ? (string)$note->consignee : '0';
                $origin                 = $note->origin !== null ? (string)$note->origin : '0';
                $destination            = $note->destination !== null ? (string)$note->destination : '0';
                $no_of_packs            = $note->noOfPacks !== null ? (string)$note->noOfPacks : '';
                $gross_weight           = $note->grossWeight !== null ? (string)$note->grossWeight : '';
                $chargeable_weight      = $note->chargeableWeight !== null ? (string)$note->chargeableWeight : '';
                $volume                 = $note->volume !== null ? (string)$note->volume : '';

                $customer_notes         = $note->customerNotes !== null ? (string)$note->customerNotes : '';
                $terms_and_conditions   = $note->termsAndConditions !== null ? (string)$note->termsAndConditions : '';
                // Seprate Line Number on base of Space new line
                $final_terms_and_conditions = '';

                if (!empty($terms_and_conditions)) {
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



                $grand_subtotal             = (string)$note->grandSubtotal;
                $grand_discount_type        = (string)$note->grandDiscountType;
                $grand_discount_type_value  = (string)$note->grandDiscountTypeValue;
                $grand_discount_amount      = (string)$note->grandDiscountAmount;
                $grand_after_discount       = (string)$note->grandAfterDiscount;
                $grand_tax                  = (string)$note->grandTax;
                $grand_total                = (string)$note->grandTotal;

                $publish                = (int)$note->isActive;



                // --- Customer Information
                $salutation     = '';
                $first_name     = '';
                $last_name      = '';
                $company_name   = '';
                $display_name   = '';
                $email          = '';
                $phone          = '';
                $mobile         = '';
                $trn            = '';
                $row_customer = $db->fetchOne("SELECT salutation, first_name, last_name, company_name, display_name, email, phone, mobile, trn FROM `{DB::CUSTOMERS}` WHERE id=:id AND organization_id=:org_id", ['id' => $customer_id, 'org_id' => (int)Session::orgId()]);
                if ($row_customer) {
                    $salutation             = s__($row_customer['salutation']);
                    $first_name             = s__($row_customer['first_name']);
                    $last_name              = s__($row_customer['last_name']);
                    $company_name           = s__($row_customer['company_name']);
                    $display_name           = s__($row_customer['display_name']);
                    $email                  = s__($row_customer['email']);
                    $phone                  = s__($row_customer['phone']);
                    $mobile                 = s__($row_customer['mobile']);
                    $trn                    = s__($row_customer['trn']);
                }

                // Customer Billing Address 
                $row_billing     = $db->fetchOne("SELECT attention, country, address_line1, address_line2, city, state, zipcode, phone, fax FROM `{DB::CUSTOMER_ADDRESSES}` WHERE addressable_type='Customer' AND addressable_id=:id AND type='billing' LIMIT 1", ['id' => $customer_id]);

                $billing_attention      = (!empty($row_billing['attention']) ? s__($row_billing['attention']) : '');
                $billing_country        = (!empty($row_billing['country']) ? s__($row_billing['country']) : '');
                $billing_address_line1  = (!empty($row_billing['address_line1']) ? s__($row_billing['address_line1']) : '');
                $billing_address_line2  = (!empty($row_billing['address_line2']) ? s__($row_billing['address_line2']) : '');
                $billing_city           = (!empty($row_billing['city']) ? s__($row_billing['city']) : '');
                $billing_state          = (!empty($row_billing['state']) ? s__($row_billing['state']) : '');
                $billing_zipcode        = (!empty($row_billing['zipcode']) ? s__($row_billing['zipcode']) : '');
                $billing_phone          = (!empty($row_billing['phone']) ? s__($row_billing['phone']) : '');
                $billing_fax            = (!empty($row_billing['fax']) ? s__($row_billing['fax']) : '');


                $credit_note_date         = ddm_($credit_note_date);
                $expiry_date            = ($expiry_date == '1970-01-01' || empty($expiry_date)) ? '' : ddm_($expiry_date);
                $expected_shipment_date = ($expected_shipment_date == '1970-01-01' || empty($expected_shipment_date)) ? '' : ddm_($expected_shipment_date);


                // Initialize all arrays to avoid the "null given" error
                $credit_note_item_id_arr = [];
                $service_arr             = [];
                $description_arr         = [];
                $qty_arr                 = [];
                $rate_arr                = [];
                $sub_total_arr           = [];
                $tax_arr                 = [];
                $tax_amount_arr          = [];
                $total_arr               = [];

                // ------------------ TOTAL ITEMS ------------------
                $note_items = $creditNoteService->getCreditNoteItems($credit_note_id, (int)Session::orgId());
                $total_rows = count($note_items);

                if ($total_rows > 0) {
                    foreach ($note_items as $item) {
                        array_push($credit_note_item_id_arr,    $item->id);
                        array_push($service_arr,                $item->service);
                        array_push($description_arr,            $item->description);
                        array_push($qty_arr,                    $item->qty);
                        array_push($rate_arr,                   $item->rate);
                        array_push($sub_total_arr,              $item->subTotal);
                        array_push($tax_arr,                    $item->tax);
                        array_push($tax_amount_arr,             $item->taxAmount);
                        array_push($total_arr,                  $item->total);
                    }
                }
            }

            if ($total_rows == 0)           $total_rows = 1;

            ?>


            <div class="row">

                <div class="row p-lg-2">

                    <div class="col-lg-1">
                    </div>


                    <div class="card col-lg-10">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="mb-4">

                                        <span class="text-muted">Credit Note To:</span>
                                        <ul class="list list-unstyled mb-0">
                                            <li>
                                                <h5 class="my-2"><?php echo $display_name; ?></h5>
                                            </li>
                                            <li><span class="fw-semibold"><?php echo $company_name; ?></span></li>
                                            <li><?php echo $billing_attention; ?></li>
                                            <li><?php echo $billing_country; ?></li>
                                            <li><?php echo $billing_address_line1; ?></li>
                                            <li><?php echo $billing_address_line2; ?></li>
                                            <li><?php echo $billing_city; ?></li>
                                            <li><?php echo $billing_state; ?></li>
                                            <li><?php echo $billing_zipcode; ?></li>
                                            <li><?php echo $billing_phone; ?></li>
                                            <li><?php echo $billing_fax; ?></li>
                                        </ul>

                                    </div>
                                </div>

                                <?php
                                $warehouse_information = '';
                                $row_warehouse  = $db->fetchOne("SELECT warehouse_no, warehouse_name, street1, street2, country, state, phone, email, trn FROM `{DB::WAREHOUSES}` WHERE id=:id LIMIT 1", ['id' => 1]);

                                $warehouse_no       = s__($row_warehouse['warehouse_no']);
                                $warehouse_name     = s__($row_warehouse['warehouse_name']);
                                $street1            = s__($row_warehouse['street1']);
                                $street2            = s__($row_warehouse['street2']);

                                $country            = s__($row_warehouse['country']);
                                $country            = getTableAttr('country_name', DB::GEO_COUNTRIES, $country);

                                $state              = s__($row_warehouse['state']);
                                $state            = getTableAttr('state_name', DB::GEO_STATES, $state);

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
                                $warehouse_information .= (!empty($trn) ? $trn : '');
                                ?>
                                <div class="col-sm-6">
                                    <div class="text-sm-end mb-4">
                                        <?php echo $warehouse_information; ?>
                                        <h6 class="text-primary mb-2 mt-lg-2">Credit Note #<?php echo $credit_note_no; ?></h6>
                                        <ul class="list list-unstyled mb-0">
                                            <li>Date: <span class="fw-semibold"><?php echo $credit_note_date; ?></span></li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                        </div>



                        <div class="table-responsive">
                            <table class="table table-lg">
                                <thead>
                                    <tr>
                                        <th>ITEM DETAILS</th>
                                        <th>DESCRIPTION</th>
                                        <th class="text-center">QUANTITY</th>
                                        <th class="text-center">RATE</th>
                                        <th class="text-center">SUBTOTAL</th>
                                        <th class="text-center">TAX</th>
                                        <th class="text-center">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    /*
                                    |------------------------------------------------------ Credit Note ITEMS  ----------------------------------------------------------|
                                    */
                                    // echo $total_rows;

                                    for ($credit_note_item = 1; $credit_note_item <= $total_rows; $credit_note_item++) {
                                        $index = $credit_note_item;
                                        $index = $index - 1;
                                        $credit_note_item_id                = $credit_note_item_id_arr[$index];
                                        //--------------------------------------------------------------------------------------------------------------------------------|
                                    ?>

                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo getTableAttr('item_name', DB::ITEMS, $service_arr[$index]); ?></div>
                                                <span class="text-muted">
                                                    <?php
                                                    // ----------------------------------------------
                                                    // Seprate Line Number on base of Space new line
                                                    // ----------------------------------------------
                                                    $desc = explode("\r", $description_arr[$index]);
                                                    // print_r($desc);
                                                    $d_counter = 1;
                                                    if (count($desc) > 0) {
                                                        foreach ($desc as $d) {
                                                            if (!empty($d)) {
                                                                echo $d_counter++ . '. ' . $d;
                                                                echo '<br />';
                                                            }
                                                        }
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                            <td><?php echo $description_arr[$index]; ?></td>
                                            <td class="text-center"><?php echo $qty_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $rate_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $sub_total_arr[$index]; ?></td>
                                            <td class="text-end"><?php echo $tax_arr[$index]; ?>% (<?php echo $tax_amount_arr[$index]; ?>)</td>
                                            <td class="text-end"><span class="fw-semibold"><?php echo $total_arr[$index]; ?></span></td>
                                        </tr>
                                    <?php
                                    } // for
                                    /*
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    */
                                    ?>


                                </tbody>
                            </table>
                        </div>

                        <div class="card-body border-top">
                            <div class="d-lg-flex flex-lg-wrap">

                                <div class="pt-2 mb-3">
                                    <ul class="list-unstyled text-muted">
                                        <li class="mb-3">Customer Notes: <br /><?php echo $customer_notes; ?></li>
                                        <li class="mb-3"><span class="fw-semibold">Terms and Conditions: </span> <br /><?php echo $final_terms_and_conditions; ?></li>
                                    </ul>
                                </div>

                                <div class="pt-2 mb-3 wmin-lg-400 ms-auto">
                                    <!-- <h6 class="mb-3">Total due</h6> -->
                                    <div class="table-responsive">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <td>Grand Subtotal:</td>
                                                    <td class="text-end"><?php echo $grand_subtotal; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Discount Type: <?php echo $grand_discount_type; ?></td>
                                                    <td class="text-end"><?php echo $grand_discount_type_value; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Discount Amount: </td>
                                                    <td class="text-end"><?php echo $grand_discount_amount; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Subtotal: (Discounted): </td>
                                                    <td class="text-end"><?php echo $grand_after_discount; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Total Tax Amount:</td>
                                                    <td class="text-end"><?php echo $grand_tax; ?></td>
                                                </tr>
                                                <tr>
                                                    <td>Grand Total:</td>
                                                    <td class="text-end text-primary">
                                                        <h5 class="fw-semibold"><?php echo $grand_total; ?></h5>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>

                                    <!-- <div class="text-end mt-3">
                                        <button type="button" class="btn btn-primary">
                                            Send invoice
                                            <i class="ph-paper-plane-tilt ms-2"></i>
                                        </button>
                                    </div> -->

                                </div>
                            </div>
                        </div>

                        <div class="card-footer">
                            <span class="text-muted">Thank you for your Business.</span>
                        </div>
                    </div>


                    <div class="col-lg-1">
                    </div>

                </div>


                <?php
                // ---------------------------------------------------------------------------------------------------------------------------------------
                $journal_row = $db->fetchOne("SELECT id FROM `{DB::JOURNALS}` WHERE reference_type IN ('credit_note','credit_note_void') AND reference_id=:rid AND organization_id=:org_id ORDER BY id ASC LIMIT 1", ['rid' => $credit_note_id, 'org_id' => (int)Session::orgId()]);
                $journal_id = $journal_row ? (int)$journal_row['id'] : 0;
                // ---------------------------------------------------------------------------------------------------------------------------------------

                if (!empty($journal_id)) {
                ?>

                    <p class="mb-0 opacity-50" id="journal">JOURNAL</p>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <!-- <p class="mb-0 fw-semibold"></p> -->

                            <div class="ms-auto small text-muted">
                                Amount is displayed in your base currency <span class="badge bg-success"><?php echo BASE_CURRENCY['code']; ?></span>
                            </div>
                        </div>

                        <!-- <div class="card-header">
                        <h5 class="mb-0">Basic table</h5>
                    </div> -->

                        <!-- <div class="card-body">
                        Example of a <code>basic</code> table. For basic styling (light padding and only horizontal dividers) add the base class <code>.table</code> to any <code>&lt;table&gt;</code>. It may seem super redundant, but given the widespread use of tables for other plugins like calendars and date pickers, we've opted to isolate our custom table styles.
                    </div> -->

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th class="opacity-50">ACCOUNT</th>
                                        <th class="text-end opacity-50">DEBIT</th>
                                        <th class="text-end opacity-50">CREDIT</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_debit = 0;
                                    $total_credit = 0;

                                    //-------------------------------------------------------------------
                                    // -------- JOURNAL ENTRIES 
                                    //-------------------------------------------------------------------

                                    $journal_items_rows = $db->fetchAll("SELECT account, debit, credit FROM `{DB::JOURNAL_ITEMS}` WHERE journal_id=:jid", ['jid' => $journal_id]);
                                    foreach ($journal_items_rows as $row_journal_items) {

                                        $account    = $row_journal_items['account'];
                                        $account    = getTableAttr('account_name', DB::ACCOUNTS, $account);
                                        $debit      = $row_journal_items['debit'];
                                        $credit     = $row_journal_items['credit'];

                                        $total_debit += $debit;
                                        $total_credit += $credit;
                                    ?>
                                        <tr>
                                            <td><?php echo $account; ?></td>
                                            <td class="text-end"><?php echo number_format((float)$debit, 2); ?></td>
                                            <td class="text-end"><?php echo number_format((float)$credit, 2); ?></td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((float)$total_debit, 2); ?></td>
                                        <td class="text-end fw-semibold"><?php echo number_format((float)$total_credit, 2); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php }  // JOURNAL 
                ?>

            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>



<?php include('admin_elements/admin_footer.php'); ?>