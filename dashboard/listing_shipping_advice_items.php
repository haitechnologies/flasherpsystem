<?php

use App\Core\DB;
include('admin_elements/admin_header.php');
$module = 'shipping_advice_items';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Advice Items';
$tbl_name = DB::SHIPPING_ADVICE_ITEMS;
$error_message = '';
$success_message = '';


// // Array ( [action] => add_shipping_stocks [selected_items] => Array ( [0] => 62 [1] => 59 [2] => 57 ) )

// Retrieve data from POST
$selected_items = $_POST['selected_items'] ?? [];

// Proceed only if selected_items is not empty
if (!empty($selected_items)) {

    $id_list_str = implode(',', $selected_items);
    header("Location: shipping_stocks.php?action=select_shipping_stocks&id_list=$id_list_str");
    exit;
} else {
    $error_message = "No item selected.";
    // echo $error_message; 
}

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
| 	GET ALL VARIABLES ADD/UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" || $action == "add_$module") {
    $invoice_date               = e_s__($_POST['invoice_date']);
    $customer_id                = e_s__($_POST['customer_id']);
    $invoice_status             = e_s__($_POST['invoice_status']);
    $invoice_no                 = e_s__($_POST['invoice_no']);
    $warehouse_id               = e_s__($_POST['warehouse_id']);

    $pkgs                       = e_s__($_POST['pkgs']);
    $weight                     = e_s__($_POST['weight']);
    $awb                        = e_s__($_POST['awb']);

    $grand_total                = e_s__($_POST['grand_total']);
} else {
    $invoice_date               = date('d-m-Y', time());
    $invoice_status             = '';
    $invoice_no                 = '';
    $warehouse_id               = '';

    $pkgs                       = '';
    $weight                     = '';
    $awb                        = '';

    $grand_total                = '';
}



/*
|--------------------------------------------------------------------------
| 	UPDATE
|--------------------------------------------------------------------------
|
*/
if ($action == "update_$module" && !empty($id)) {

    if (empty($customer_id) || $customer_id == 'Please select') {
        $error_message = 'Please select Customer.';
    } else if (empty($invoice_date)) {
        $error_message = 'Please select Invoice Date.';
    } else if (empty($warehouse_id) || $warehouse_id == 'Please select') {
        $error_message = 'Please select warehouse.';
    } else if (empty($pkgs)) {
        $error_message = 'PLT/BOX/PKGs is mandatory.';
    } else if (empty($weight)) {
        $error_message = 'Weight is mandatory.';
    } else if (empty($awb)) {
        $error_message = 'AWB is mandatory.';
    } else {

        if ($grand_total == '')                         $grand_total = '0.00';

        $invoice_date     = processDateDtoY($invoice_date);

        // ---------------------------------------------
        // UPDATE SHIPPPING INVOICE
        // ---------------------------------------------
        $update_row = $mysqli->query("
                                        UPDATE `$tbl_name` SET
                                            invoice_date		        = '" . $invoice_date . "',
                                            customer_id					= '" . $customer_id . "',
                                            invoice_status		        = '" . $invoice_status . "',
                                            invoice_no		            = '" . $invoice_no . "',
                                            warehouse_id		        = '" . $warehouse_id . "',
                                            
                                            pkgs		                = '" . $pkgs . "',
                                            weight		                = '" . $weight . "',
                                            awb		                    = '" . $awb . "',
                                            
                                            grand_total		            = '" . $grand_total . "',
                                            
                                            is_active 					= '" . $publish . "'
                                        WHERE id=$id");

        if ($update_row) {
            $success_message = "The $module_caption has been updated successfully.";
            fp__($tbl_name, $id);
            $invoice_id = $id;
            ///////////////////////////////////////////////////////////

            // -- PROCESS SHIPPING INVOICE ITEMS - ITNS
            if ($total_rows > 0) {

                $updated_row    = 0;
                $inserted_row   = 0;

                for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_rows; $shipping_invoice_item++) {

                    $index = $shipping_invoice_item;
                    $index = $index - 1;

                    $item_id                        = e_s__($_POST['item_id'][$index]);
                    $item_description               = e_s__($_POST['description'][$index]);
                    $item_coo                       = e_s__($_POST['coo'][$index]);
                    $item_declaration_no            = e_s__($_POST['declaration_no'][$index]);
                    $item_hscode                    = e_s__($_POST['hscode'][$index]);
                    $item_qty                       = e_s__($_POST['qty'][$index]);
                    $item_rate                      = e_s__($_POST['rate'][$index]);
                    $item_total                     = e_s__($_POST['total'][$index]);


                    // ---------------------------------------------
                    // UPDATE SHIPPING INVOICE ITEMS
                    // ---------------------------------------------

                    $item_qty           = (($item_qty == '') ? 1 : $item_qty);
                    $item_rate          = (($item_rate == '') ? 0 : $item_rate);
                    $item_total         = (($item_total == '') ? 0 : $item_total);

                    // Process Updated Shipping Invoice Items
                    if (!empty($item_id) && !empty($item_description) && !empty($item_coo) && !empty($item_declaration_no) && !empty($item_hscode) && !empty($item_qty) && !empty($item_rate) && !empty($item_total)) {

                        $update_row = $mysqli->query("UPDATE `" . DB::SHIPPING_INVOICE_ITEMS . "` SET 
                                                            description     = '" . $item_description . "',
                                                            coo             = '" . $item_coo . "',
                                                            declaration_no  = '" . $item_declaration_no . "',
                                                            hscode          = '" . $item_hscode . "',
                                                            qty             = '" . $item_qty . "',
                                                            rate            = '" . $item_rate . "',
                                                            total           = '" . $item_total . "' 
                                                        WHERE id=$item_id");

                        if ($update_row) $updated_row++;
                        fp__(DB::SHIPPING_INVOICE_ITEMS, $item_id);

                        // Process New Shipping Invoice Items
                    } else if (empty($item_id) && !empty($item_description) && !empty($item_coo) && !empty($item_declaration_no) && !empty($item_hscode) && !empty($item_qty) && !empty($item_rate) && !empty($item_total)) {

                        $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_INVOICE_ITEMS . "`(invoice_id, description, coo, declaration_no, hscode, qty, rate, total) VALUES ('" . $invoice_id . "', '" . $item_description . "', '" . $item_coo . "', '" . $item_declaration_no . "', '" . $item_hscode . "', '" . $item_qty . "', '" . $item_rate . "', '" . $item_total . "'); ");

                        if ($insert_row) $inserted_row++;
                        fp__(DB::SHIPPING_INVOICE_ITEMS, $mysqli->insert_id);

                        // Process Deleted Shipping Invoice Items
                    } else if (!empty($item_id) && empty($item_description) && empty($item_coo) && empty($item_rate) && empty($item_total)) {

                        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE id=$item_id");
                    }
                    // ---------------------------------------------

                } //for 

            }
            ///////////////////////////////////////////////////////////

            // CHECK IF AT LEAST ONE SHIPPING INVOICE ITEM IS ADDED
            if ($updated_row == 0 && $inserted_row == 0) {
                $success_message = '';
                $invoice_date = processDateYtoD($invoice_date);
                $error_message = "Please add at least one Invoice Item.";
            } else {
                flash_success($success_message);
                header("Location:listing_$module.php");
            }
        } else {
            $error_message = "The $module_caption could not be updated. Please try again.";
            //header("Location:$module.php?action=edit_$module&id=$id&error_message=$error_message");
        }

        // CHECK IF AT LEAST ONE SHIPPING INVOICE ITEM IS ADDED
        // if ($inserted_row == 0) {
        //     $success_message = '';
        //     $invoice_date = processDateYtoD($invoice_date);
        //     $error_message = "Please add at least one Shipping Invoice Item.";
        // } else {
        //     header("Location:listing_$module.php?success_message=$success_message");
        // }
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
        <div class="page-header-content d-lg-flex border-top carriers-page-header-content py-2 px-3 carriers-page-header-content py-2 px-3">
            <div class="row mt-2">
                <div class="col-lg-12">
                    <h5 class="ms-2 mb-2"> <a href="listing_<?php echo $module; ?>.php" class="text-dark">All <?php echo ucwords(str_ireplace('_', " ", $module)); ?></a></h5>
                </div>

                <a href="#breadcrumb_elements" class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto" data-bs-toggle="collapse">
                    <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
                </a>
            </div>

        </div>
    </div>
    <!-- /page header -->



    <div class="content datatable-enhanced">

            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="row">

                <div class="col-lg-12">
                    <div class="row">
                        <label class="col-lg-6 col-form-label">
                            <span>Manage Stocks - Select Available Stock to Create Invoice</span>
                            <h5>All <?php echo ucwords(str_ireplace('_', ' ', $module)); ?></h6>
                        </label>

                        <div class="col-lg-6">
                            <!-- <input type="hidden" name="selected_hs_code" id="selected_hs_code"> -->
                            <!-- <button type="submit" class="btn btn-info my-1 me-2">Submit Selected</button> -->


                            <form id="dataForm" action="listing_shipping_advice_items.php" method="POST">
                                <input type="hidden" name="action" id="action" value="add_shipping_stocks" />
                                <button type="submit" class="btn btn-primary">Select & Proceed to STOCK-OUT</button>
                            </form>

                        </div>
                    </div>
                </div>


                <div class="card">
                    <div class="card-body">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                        <div class="table-responsive">
<table id="grid-<?php echo $module; ?>" class="custom_datatables datatable-professional display responsive no-wrap table-hover" width="100%">
                            <thead>
                                <tr>
                                    <th width="100"><input type="checkbox" class="form-check-input" id="select-all" title="Select all"></th>
                                    <th width="100">ID</th>
                                    <th>HS CODE</th>
                                    <th>DESCRIPTION</th>
                                    <th>TOTAL QTY</th>
                                    <th>REMAINING QTY</th>
                                    <th>ORIGIN</th>
                                    <th>VALUE</th>
                                    <th>WEIGHT(KG)</th>
                                    <th>INVOICE #</th>
                                </tr>
                            </thead>
                        </table>
</div>
                    </div>
                </div>

            </div>

        </div>

        <?php include('admin_elements/copyright.php'); ?>
</div>

<script>
$(document).ready(function() {
    var selectedItems = new Set();

    window.HAIDatatableInitializer.init('#grid-<?php echo $module; ?>', '<?php echo $module; ?>', {
        columns: [
            { data: 0, orderable: false, searchable: false },
            { data: 1 },
            { data: 2 },
            { data: 3 },
            { data: 4 },
            { data: 5 },
            { data: 6 },
            { data: 7 },
            { data: 8 },
            { data: 9 }
        ],
        order: [[1, 'asc']],
        pageLength: 25,
        dom: "<'dt-header'<'dt-head-left'fl><'dt-head-right'>>rt<'dt-footer'<'dt-foot-left'i><'dt-foot-right'p>>",
        language: { search: '', searchPlaceholder: 'Search items...', lengthMenu: '_MENU_' }
    });

    // Handle individual checkbox changes
    $(document).on('change', '.item-checkbox', function() {
        var id = $(this).val();
        if (this.checked) {
            selectedItems.add(id);
        } else {
            selectedItems.delete(id);
        }
        updateSelectAllState();
    });

    // Handle Select All checkbox change
    $(document).on('change', '#select-all', function() {
        var checked = this.checked;
        $('.item-checkbox').each(function() {
            this.checked = checked;
            var id = $(this).val();
            if (checked) {
                selectedItems.add(id);
            } else {
                selectedItems.delete(id);
            }
        });
    });

    // Restore checkbox state on DataTable draw
    $('#grid-<?php echo $module; ?>').on('draw.dt', function() {
        $('.item-checkbox').each(function() {
            var id = $(this).val();
            if (selectedItems.has(id)) {
                this.checked = true;
            } else {
                this.checked = false;
            }
        });
        updateSelectAllState();
    });

    function updateSelectAllState() {
        var total = $('.item-checkbox').length;
        var checked = $('.item-checkbox:checked').length;
        $('#select-all').prop('checked', total > 0 && total === checked);
    }

    // Intercept form submission to inject selected items
    $('#dataForm').on('submit', function(e) {
        // Remove old dynamic inputs
        $(this).find('.dynamic-selected-item').remove();

        if (selectedItems.size === 0) {
            e.preventDefault();
            alert('Please select at least one item.');
            return false;
        }

        // Add hidden inputs for each selected item
        var form = this;
        selectedItems.forEach(function(id) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_items[]';
            input.value = id;
            input.className = 'dynamic-selected-item';
            form.appendChild(input);
        });
    });
});
</script>

<?php include('admin_elements/admin_footer.php'); ?>