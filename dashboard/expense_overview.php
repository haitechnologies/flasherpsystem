<?php

use App\Core\Session;
use App\Service\JournalService;

include('admin_elements/admin_header.php');

$module = 'expenses';
$module_caption = 'Expense';
$tbl_name = $tbl_prefix . $module;

$file_upload_path           = '../uploads/expense_attachments/';
$allowed_file_size          = $GLOBALS['DOCUMENT']['MAX_UPLOAD_SIZE'] ?? '5MB';
$allowed_file_formats       = $GLOBALS['DOCUMENT']['FORMATS'] ?? '.doc, .docx, .pdf, .txt, .rtf, .xls, .xlsx, .ppt, .pptx, .jpeg, .jpg, .png';

$error_message = '';
$success_message = '';

$activeOrganizationId = dashboardRequireActiveOrganization();



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

$expense_id = '';
if (isset($_REQUEST['expense_id']))        $expense_id     = e_s__($_REQUEST['expense_id']);
if (isset($_POST['expense_id']))           $expense_id     = e_s__($_POST['expense_id']);
if (empty($expense_id) && isset($_REQUEST['id'])) $expense_id = e_s__($_REQUEST['id']);


// ------------------ CHECK IF EXISTS ----------------
//VERIFY IF IS VALID
$rs_expense_valid  = $mysqli->query("SELECT id FROM `" . tbl_expenses . "` WHERE id='" . $expense_id . "'");
if ($rs_expense_valid->num_rows == 0) {
    flash_error('Invalid Record in the database.');
    header("Location:listing_expenses.php");
    exit;
}




/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/

$publish = 1;


$expense_status = 0;
if (isset($_REQUEST['expense_status']) && !empty($_REQUEST['expense_status'])) {
    $expense_status   = e_s__($_REQUEST['expense_status']);
}





// ------------------ IF ID DOES NOT EXIST - REDIRECT TO LISTING ----------------
$rs_exists     = $mysqli->query("SELECT id FROM `" . tbl_expenses . "` WHERE id ='" . $expense_id . "' ");
if ($rs_exists->num_rows == 0) {
    // header("Location:listing_$module.php?error_message=You are not Autorized to view the Record you're trying to access.");
}




/*
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
|--------------------------------------------------------------------------|
*/


$publish = 1;


$expense_status = 0;
if (isset($_REQUEST['expense_status']) && !empty($_REQUEST['expense_status'])) {
    $expense_status   = e_s__($_REQUEST['expense_status']);
}


// IF EMPTY ID - EXIT
if (isset($_REQUEST['expense_id']))     $expense_id     = e_s__($_REQUEST['expense_id']);
if (empty($expense_id)) header("Location:listing_$module.php");;



$expense_item_id = 0;
if (isset($_REQUEST['expense_item_id']) && !empty($_REQUEST['expense_item_id'])) {
    $expense_item_id     = e_s__($_REQUEST['expense_item_id']);
}





$attachment_id = 0;
if (isset($_REQUEST['attachment_id']))        $attachment_id     = e_s__($_REQUEST['attachment_id']);
if (isset($_POST['attachment_id']))           $attachment_id     = e_s__($_POST['attachment_id']);




/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
|
*/
if (($action == "delete_$module" && !empty($expense_id)) && granted('delete', $module_id)) {

    if (is_SystemAdmin() || is_SuperAdmin()) {

        $filename = getTableAttr('filename', tbl_expense_attachments, $attachment_id);
        $result = $mysqli->query("DELETE FROM `" . tbl_expense_attachments . "` WHERE id=$attachment_id AND organization_id=" . (int)$activeOrganizationId);
        if (!empty($filename)) unlink($file_upload_path  . $filename);

        // expense Logs
        // updateExpenseLogs($expense_id, 'file', 'deleted');


    } else {
        $filename = getTableAttr('filename', tbl_expense_attachments, $attachment_id);
        $result = $mysqli->query("DELETE FROM `" . tbl_expense_attachments . "` WHERE id=$attachment_id AND organization_id=" . (int)$activeOrganizationId . " AND created_by='" . Session::userId() . "'");
        if (!empty($filename)) unlink($file_upload_path  . $filename);

        // expense Logs
        // updateexpenseLogs($expense_id, 'file', 'deleted');
    }


    if ($result) {
        $success_message = "$module_caption Deleted Successfully.";
        // header("Location:listing_$module.php?page=$page&success_message=$success_message");
    } else {
        $error_message = "Sorry! $module Could Not Be Deleted.";
    }
}



/*
|--------------------------------------------------------------------------
| 	ADD
|--------------------------------------------------------------------------
|
*/
if ($action == "add_$module" && granted('create', $module_id)) {


    // $target_dir = '../uploads/expense_attachments/';
    $target_file = $file_upload_path . basename($_FILES["document"]["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // .DOC, .DOCX, .PDF, .TXT, .RTF, .XLS, .XLSX, .PPT, .PPTX, JPEG, JPG, PNG
    $extensions = array("doc", "docs", "pdf", "txt", "rtf", "xls", "xlsx", "ppt", "pptx", "jpeg", "jpg", "png");


    if (empty($_FILES['document']['tmp_name'])) {
        $error_message = "Attachment is mandatory.";

        // To check extensions are correct or not
    } else if (!in_array($imageFileType, $extensions) === true) {
        $error_message = "No file selected or Invalid file extension...";
    } else if ($_FILES["document"]["size"] > 5242880) {
        $error_message = "Sorry, your file is too large.";
    } else {

        $filename        = rename_file_name($_FILES['document']['name']);
        if (move_uploaded_file($_FILES['document']['tmp_name'], $file_upload_path . $filename)) {

            /* ---------------------- QUERY ---------------------- */
            $insert_row = $mysqli->query("INSERT INTO `" . tbl_expense_attachments . "`(expense_id, filename, organization_id, created_by) VALUES ('" . $expense_id . "', '" . $filename . "', " . (int)$activeOrganizationId . ", '" . Session::userId() . "'); ");

            $filename = '';

            $attachment_id = $mysqli->insert_id;
            $success_message = "The $module_caption has been saved successfully.";
            fp__(tbl_expense_attachments, $attachment_id);

            // expense Logs
            // updateExpenseLogs($expense_id, 'file', 'added');
        } else {
            $error_message = "The $module_caption could not be saved. Please try again.";
            //header("Location:$module.php?error_message=$error_message");
        }
    } // endif

}



/*
|--------------------------------------------------------------------------
| CLONE
|--------------------------------------------------------------------------
|
*/
if (($action == "clone_$module" && !empty($expense_id))) {

    // 1. Clone the Parent Expense
    $result = $mysqli->query("INSERT INTO `" . tbl_expenses . "` (expense_date, paid_through, vendor_id, reference_no, customer_id, grand_total, expense_status, is_active, organization_id, created_at, updated_at, created_by)
    SELECT expense_date, paid_through, vendor_id, reference_no, customer_id, grand_total, 'draft', is_active, organization_id, NOW(), NOW(), '" . Session::userId() . "'
    FROM `" . tbl_expenses . "`
    WHERE id = $expense_id;");

    $new_cloned_id = $mysqli->insert_id;
    // If you have a fingerprint function like in your example:
    // fp__('fls_expenses', $new_cloned_id);


    // 2. Clone the Expense Items
    $result = $mysqli->query("INSERT INTO `" . tbl_expense_items . "` (expense_id, expense_account, description, total, organization_id, created_at, updated_at, created_by)
    SELECT $new_cloned_id, expense_account, description, total, organization_id, NOW(), NOW(), '" . Session::userId() . "'
    FROM `" . tbl_expense_items . "`
    WHERE expense_id = $expense_id");

    // If you have a fingerprint function:
    // fp__('fls_expense_items', $mysqli->insert_id);

    $success_message = 'Expense has been cloned Successfully. Please click here to view. <a href="expense_overview.php?expense_id=' . $new_cloned_id . '"> ' . $new_cloned_id . '</a>';
}


/*
|--------------------------------------------------------------------------
| CONVERT TO INVOICE
|--------------------------------------------------------------------------
|
*/
if (($action == "convert_to_invoice" && !empty($expense_id))) {

    // Get expense details
    $expense_result = $mysqli->query("SELECT * FROM `" . tbl_expenses . "` WHERE id=$expense_id");
    $expense_row = $expense_result->fetch_assoc();

    if (!$expense_row) {
        $error_message = "Expense not found.";
    } else {
        // Extract expense details
        $customer_id = $expense_row['customer_id'];
        $vendor_id = $expense_row['vendor_id'];
        $expense_date = $expense_row['expense_date'];
        $grand_total = $expense_row['grand_total'];
        $reference_no = $expense_row['reference_no'];

        // Verify there is a customer (billable expenses can be converted to invoices)
        if (empty($customer_id) || $customer_id == 0) {
            $error_message = "Cannot convert non-billable expense to invoice. A customer must be assigned.";
        } else {
            // Generate Invoice Number
            $invoice_prefix = 'INV';
            $sql_inv_no = "SELECT invoice_no FROM `" . tbl_invoices . "` WHERE invoice_no LIKE '{$invoice_prefix}-%' ORDER BY invoice_no DESC LIMIT 1";
            $result_inv_no = $mysqli->query($sql_inv_no);
            $row_inv = $result_inv_no->fetch_assoc();

            if ($row_inv) {
                $last_invoice_no = $row_inv['invoice_no'];
                $parts = explode('-', $last_invoice_no);
                $last_number = (int) $parts[1];
                $new_number = $last_number + 1;
            } else {
                $new_number = 1;
            }

            $invoice_no = $invoice_prefix . '-' . str_pad($new_number, 5, '0', STR_PAD_LEFT);

            // Create Invoice
            $insert_invoice = $mysqli->query("INSERT INTO `" . tbl_invoices . "`
                (invoice_no, customer_id, invoice_status, invoice_date, reference_no, warehouse_id, grand_total, is_active, organization_id)
                VALUES
                ('$invoice_no', '$customer_id', 'draft', '$expense_date', '$reference_no', 0, '$grand_total', 1, '$activeOrganizationId')");

            if (!$insert_invoice) {
                $error_message = "Failed to create invoice: " . $mysqli->error;
            } else {
                $new_invoice_id = $mysqli->insert_id;
                fp__(tbl_invoices, $new_invoice_id);

                // Get all expense items
                $expense_items_result = $mysqli->query("SELECT * FROM `" . tbl_expense_items . "` WHERE expense_id=$expense_id");

                if ($expense_items_result->num_rows > 0) {
                    $items_created = 0;
                    $total_invoice_amount = 0;

                    while ($item = $expense_items_result->fetch_assoc()) {
                        // Convert expense item to invoice item
                        $description = $item['description'];
                        $total = (float) $item['total'];

                        $insert_item = $mysqli->query("INSERT INTO `" . tbl_invoice_items . "`
                            (invoice_id, service, description, qty, rate, sub_total, tax, tax_amount, total, organization_id)
                            VALUES
                            ('$new_invoice_id', 0, '$description', 1, '$total', '$total', 0, 0, '$total', '$activeOrganizationId')");

                        if ($insert_item) {
                            $items_created++;
                            $total_invoice_amount += $total;
                            fp__(tbl_invoice_items, $mysqli->insert_id);
                        }
                    }

                    // Create Journal Entry for Invoice
                    try {
                        // Get customer display name
                        $customer_display_name = getTableAttr('display_name', tbl_customers, $customer_id);

                        // Initialize Journal Manager
                        $journal = \App\Core\Container::getInstance()->get(JournalService::class);

                        // Prepare journal entries for INVOICE
                        $journal_entries = array();

                        // Get accounts for AR and Sales
                        $ar_account_result = $mysqli->query("SELECT id FROM `" . tbl_accounts . "` WHERE account_code IN ('1200','1210','1100') OR account_name LIKE '%Receivable%' LIMIT 1");
                        $ar_account = $ar_account_result->fetch_assoc();

                        $sales_account_result = $mysqli->query("SELECT id FROM `" . tbl_accounts . "` WHERE account_code='4100' OR account_name LIKE '%Sales Revenue%' LIMIT 1");
                        $sales_account = $sales_account_result->fetch_assoc();

                        if ($ar_account && $sales_account) {
                            // DR: Accounts Receivable
                            $journal_entries[] = array(
                                'account' => (int)$ar_account['id'],
                                'debit' => (float)$total_invoice_amount,
                                'credit' => 0.0,
                                'description' => 'Converted from Expense #' . $expense_id
                            );

                            // CR: Sales Revenue
                            $journal_entries[] = array(
                                'account' => (int)$sales_account['id'],
                                'debit' => 0.0,
                                'credit' => (float)$total_invoice_amount,
                                'description' => 'Converted from Expense #' . $expense_id
                            );

                            // Create journal entry
                            $journal->createJournal(
                                array(
                                    'journal_date' => date('Y-m-d', strtotime($expense_date)),
                                    'journal_status' => 'posted',
                                    'reference_no' => $invoice_no,
                                    'notes' => 'Invoice from Expense - ' . $customer_display_name,
                                    'reporting_method' => 'accrual',
                                    'reference_type' => 'invoice',
                                    'reference_id' => (int)$new_invoice_id,
                                    'currency' => 'AED',
                                    'warehouse_id' => 0,
                                ),
                                $journal_entries,
                                (int)$activeOrganizationId,
                                (int)Session::userId()
                            );
                            $success_message = "Expense converted to Invoice successfully. Invoice #<a href='invoice_overview.php?invoice_id={$new_invoice_id}'>{$invoice_no}</a>";
                        } else {
                            $error_message = "Required accounts not found. Cannot create journal entry.";
                        }
                    } catch (\Throwable $e) {
                        log_error("Exception converting expense to invoice: " . $e->getMessage(), 'ERROR', __FILE__, __LINE__, backend_runtime_log_context([
                            'module' => $module,
                            'module_slug' => $module,
                            'expense_id' => $expense_id ?? null,
                        ]));
                        $error_message = "Error creating journal entry: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Expense has no items to convert.";
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
|
*/

$expense_item_id_arr        = array();
$item_id_arr                = array();
$expense_account_arr        = array();
$description_arr            = array();
$total_arr                  = array();



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
    <?php include('admin_elements/sidebar_expense.php'); ?>
    <!-- /sidebar content -->

</div>

<div class="content-wrapper">

    <!-- Inner content -->
    <div class="content-inner">

        <!-- Page header -->
        <?php include('admin_elements/page_header_expense.php'); ?>
        <!-- /page header -->


        <!-- Content area -->
        <div class="content">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <?php

                /*
                |--------------------------------------------------------------------------
                | EDIT
                |--------------------------------------------------------------------------
                |
                */
                $billable = '';
                $expense_date = '';
                $paid_through = '';
                $vendor_id = '';
                $reference_no = '';
                $customer_id = '';
                $grand_total = 0;
                $total_rows = 0;

                if (!empty($expense_id)) {

                    $result = $mysqli->query("SELECT * FROM `$tbl_name` WHERE id=$expense_id");
                    $row = $result->fetch_array();

                    $billable               = s__($row['billable']);
                    $billable               = (($billable == 1) ? 'BILLABLE' : 'NON-BILLABLE');
                    $expense_date           = s__($row['expense_date']);
                    $paid_through           = s__($row['paid_through']);
                    $vendor_id              = s__($row['vendor_id']);
                    $reference_no           = s__($row['reference_no']);
                    $customer_id            = s__($row['customer_id']);
                    $grand_total            = s__($row['grand_total']);
                    $publish                = s__($row['is_active']);

                    // ------------------ TOTAL ITEMS ------------------
                    $result_expense_items     = $mysqli->query("SELECT * FROM `" . tbl_expense_items . "` WHERE expense_id=$expense_id ORDER BY id");
                    $total_rows                 = $result_expense_items->num_rows;

                    if ($total_rows > 0) {
                        while ($row_expense_items = $result_expense_items->fetch_array()) {

                            array_push($expense_item_id_arr,        $row_expense_items['id']);
                            array_push($expense_account_arr,        $row_expense_items['expense_account']);
                            array_push($description_arr,            $row_expense_items['description']);
                            array_push($total_arr,                  $row_expense_items['total']);
                        }
                    }
                }

                if ($total_rows == 0)           $total_rows = 1;

                ?>

                <div class="row p-lg-2">

                    <div class="col-lg-2">
                    </div>

                    <div class="card col-lg-8">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <div class="mb-4">
                                        <span class="text-muted">Expense Amount</span><br />
                                        <span class="text-danger mt-lg-2 fs-2"><?php echo BASE_CURRENCY['code']; ?><?php echo $grand_total; ?></span><span class="text-muted"> on <?php echo ddm_($expense_date); ?></span><br />
                                        <span class="text-muted fs-6"><?php echo $billable; ?></span>
                                    </div>

                                    <!-- <div class="badge bg-info fw-normal opacity-50 mb-4">COST OF GOODS SOLD</div> -->


                                    <div class="text-muted">Paid Through</div>
                                    <div class=""><?php echo getTableAttr("account_name", tbl_accounts, $paid_through); ?></div>


                                    <?php if (!empty($customer_id)) { ?>
                                        <div class="text-muted mt-4">Customer</div>
                                        <div class="">
                                            <a href="customer_overview.php?customer_id=<?php echo $customer_id; ?>"><?php echo getTableAttr("display_name", tbl_customers, $customer_id); ?></a>
                                        </div>
                                    <?php } ?>

                                    <?php if (!empty($vendor_id)) { ?>
                                        <div class="text-muted  mt-4">Paid To</div>
                                        <div class="">
                                            <a href="vendor_overview.php?vendor_id=<?php echo $vendor_id; ?>"><?php echo getTableAttr("display_name", tbl_vendors, $vendor_id); ?></a>
                                        </div>
                                    <?php } ?>

                                </div>

                                <div class="col-lg-6 text-right">


                                    <!-- Upload your files / receipts
                                    Maximum file size allowed is 10MB -->


                                    <form method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="expense_overview.php" autocomplete="off" enctype="multipart/form-data">
                                        <input type="hidden" name="expense_id" id="expense_id" value="<?php echo $expense_id; ?>" />

                                        <?php if (($action == "edit_$module" || $action == "update_$module") && !empty($expense_id)) { ?>
                                            <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                                            <input type="hidden" name="attachment_id" id="attachment_id" value="<?php echo $attachment_id; ?>" />
                                        <?php } else { ?>
                                            <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                                        <?php } ?>

                                        <div class="row">
                                            <label class="col-form-label"><span class="text-danger">Upload your files / receipts: *</span></label><br />
                                            <?php if (!empty($filename) && file_exists('../uploads/expense_attachments/' . $filename)) { ?>
                                                <div class="form-group">
                                                    <h5>
                                                        <a href="<?php echo $file_upload_path; ?><?php echo $filename; ?>" target="_blank">
                                                            <small><?php echo $filename; ?></small>
                                                        </a>
                                                    </h5>
                                                </div>
                                            <?php } else { ?>
                                                <div class="row mb-3">
                                                    <input type="file" name="document" id="document" class="form-control">
                                                </div>
                                                <button type="submit" class="btn btn-light btn-sm my-1">
                                                    Upload
                                                </button>

                                                <div class="form-text text-muted"><?php echo $allowed_file_formats; ?> <br /><?php echo $allowed_file_size; ?></div>

                                            <?php } ?>


                                            <?php
                                            /*
                                            |--------------------------------------------------------------------------
                                            | ATTACHMENTS
                                            |--------------------------------------------------------------------------
                                            |
                                            // */
                                            if (!empty($expense_id)) {
                                            $rs     = $mysqli->query("SELECT * FROM `" . tbl_expense_attachments . "` WHERE expense_id=$expense_id AND organization_id=" . (int)$activeOrganizationId);
                                            while ($row    = $rs->fetch_array()) {
                                                $attachment_id          = $row['id'];
                                                $filename               = $row['filename'];
                                                $created_at             = $row['created_at'];
                                            ?>

                                                <div class="bg-light rounded p-3 d-flex justify-content-between align-items-center mb-1">
                                                    <span class="text-dark">
                                                        <a href="<?php echo $file_upload_path; ?><?php echo $filename; ?>" target="_blank">
                                                            <small><?php echo $filename; ?></small>
                                                        </a>
                                                    </span>

                                                    <button type="button"
                                                        class="btn btn-link text-muted p-0"
                                                        onclick="if(confirm('Are you sure?')) window.location.href='expense_overview.php?action=delete_expense_attachments&attachment_id=<?php echo $attachment_id; ?>&expense_id=<?php echo $expense_id; ?>';">
                                                        <i class="ph-trash"></i>
                                                    </button>

                                                </div>

                                            <?php
                                            } // while
                                            } // if
                                            //----------------------------------------------------------------------------------------
                                            ?>
                                        </div>


                                    </form>

                                </div>
                            </div>


                        </div>


                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Expense Account</th>
                                        <th class="text-center">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    /*
                                    |------------------------------------------------------ ITEMS  ----------------------------------------------------------|
                                    */
                                    $grand_total = 0;

                                    for ($expense_item = 1; $expense_item <= $total_rows; $expense_item++) {
                                        $index = $expense_item - 1;
                                        if (!isset($expense_item_id_arr[$index])) continue;
                                        $expense_item_id                = $expense_item_id_arr[$index];

                                        $total          = $total_arr[$index];
                                        $grand_total    += $total;
                                        //--------------------------------------------------------------------------------------------------------------------------------|
                                    ?>

                                        <tr>
                                            <td><?php echo getTableAttr('account_name', tbl_accounts, $expense_account_arr[$index]); ?></td>
                                            <td class="text-center"><?php echo BASE_CURRENCY['code']; ?><?php echo $total; ?></td>
                                        </tr>
                                    <?php
                                    } // for
                                    /*
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    |--------------------------------------------------------------------------------------------------------------------------------
                                    */
                                    ?>
                                    <tr>
                                        <td>Expense Total</th>
                                        <td class="text-center"><?php echo BASE_CURRENCY['code']; ?><?php echo $grand_total; ?></th>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="col-lg-4">
                        <!-- upload receipts -->
                    </div>

                </div>


                <?php
                // ---------------------------------------------------------------------------------------------------------------------------------------
                $journal_id = getTableAttrV('id', tbl_journals, " reference_type='expense' AND reference_id='$expense_id' ");
                // ---------------------------------------------------------------------------------------------------------------------------------------

                if (!empty($journal_id)) {
                ?>

                    <p class="mb-0 opacity-50" id="journal">JOURNAL</p>
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <p class="mb-0 fw-semibold">EXPENSE</p>

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

                                    $result_journal_items     = $mysqli->query("SELECT * FROM `" . tbl_journal_items . "` WHERE journal_id=$journal_id");
                                    while ($row_journal_items = $result_journal_items->fetch_array()) {

                                        $account    = $row_journal_items['account'];
                                        $account    = getTableAttr('account_name', tbl_accounts, $account);
                                        $debit      = $row_journal_items['debit'];
                                        $credit     = $row_journal_items['credit'];

                                        $total_debit += $debit;
                                        $total_credit += $credit;
                                    ?>
                                        <tr>
                                            <td><?php echo $account; ?></td>
                                            <td class="text-end"><?php echo $debit; ?>.00</td>
                                            <td class="text-end"><?php echo $credit; ?>.00</td>
                                        </tr>
                                    <?php } ?>
                                    <tr>
                                        <td></td>
                                        <td class="text-end fw-semibold"><?php echo $total_debit; ?>.00</td>
                                        <td class="text-end fw-semibold"><?php echo $total_credit; ?>.00</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php } // JOURNAL
                ?>


            </div>

        </div>


    </div>
    <!-- /content area -->

    <?php include('admin_elements/copyright.php'); ?>
</div>
</div>

<?php include('admin_elements/admin_footer.php'); ?>
