<?php

declare(strict_types=1);

use App\Core\DB;

include('admin_elements/admin_header.php');

$module = 'shipping_stocks';
$GLOBALS['module'] = $module;
$module_caption = 'Shipping Stock';
$tbl_name = DB::SHIPPING_STOCKS;
$error_message = '';
$success_message = '';

include('admin_elements/permissions.php');

$activeOrganizationId = dashboardRequireActiveOrganization();

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
        if ($grand_total == '') $grand_total = '0.00';

        $invoice_date     = processDateDtoY($invoice_date);

        $update_row = $mysqli->query("
            UPDATE `$tbl_name` SET
                invoice_date = '" . $invoice_date . "',
                customer_id = '" . $customer_id . "',
                invoice_status = '" . $invoice_status . "',
                invoice_no = '" . $invoice_no . "',
                warehouse_id = '" . $warehouse_id . "',
                pkgs = '" . $pkgs . "',
                weight = '" . $weight . "',
                awb = '" . $awb . "',
                grand_total = '" . $grand_total . "',
                is_active = '" . $publish . "'
            WHERE id=$id");

        if ($update_row) {
            $success_message = "The $module_caption has been updated successfully.";
            fp__($tbl_name, $id);
            $invoice_id = $id;

            if ($total_rows > 0) {
                $updated_row    = 0;
                $inserted_row   = 0;

                for ($shipping_invoice_item = 1; $shipping_invoice_item <= $total_rows; $shipping_invoice_item++) {
                    $index = $shipping_invoice_item - 1;

                    $item_id                        = e_s__($_POST['item_id'][$index]);
                    $item_description               = e_s__($_POST['description'][$index]);
                    $item_coo                       = e_s__($_POST['coo'][$index]);
                    $item_declaration_no            = e_s__($_POST['declaration_no'][$index]);
                    $item_hscode                    = e_s__($_POST['hscode'][$index]);
                    $item_qty                       = e_s__($_POST['qty'][$index]);
                    $item_rate                      = e_s__($_POST['rate'][$index]);
                    $item_total                     = e_s__($_POST['total'][$index]);

                    $item_qty           = (($item_qty == '') ? 1 : $item_qty);
                    $item_rate          = (($item_rate == '') ? 0 : $item_rate);
                    $item_total         = (($item_total == '') ? 0 : $item_total);

                    if (!empty($item_id) && !empty($item_description) && !empty($item_coo) && !empty($item_declaration_no) && !empty($item_hscode) && !empty($item_qty) && !empty($item_rate) && !empty($item_total)) {
                        $update_row = $mysqli->query("UPDATE `" . DB::SHIPPING_INVOICE_ITEMS . "` SET
                            description = '" . $item_description . "',
                            coo = '" . $item_coo . "',
                            declaration_no = '" . $item_declaration_no . "',
                            hscode = '" . $item_hscode . "',
                            qty = '" . $item_qty . "',
                            rate = '" . $item_rate . "',
                            total = '" . $item_total . "'
                        WHERE id=$item_id");

                        if ($update_row) $updated_row++;
                        fp__(DB::SHIPPING_INVOICE_ITEMS, $item_id);
                    } else if (empty($item_id) && !empty($item_description) && !empty($item_coo) && !empty($item_declaration_no) && !empty($item_hscode) && !empty($item_qty) && !empty($item_rate) && !empty($item_total)) {
                        $insert_row = $mysqli->query("INSERT INTO `" . DB::SHIPPING_INVOICE_ITEMS . "`(invoice_id, description, coo, declaration_no, hscode, qty, rate, total) VALUES ('" . $invoice_id . "', '" . $item_description . "', '" . $item_coo . "', '" . $item_declaration_no . "', '" . $item_hscode . "', '" . $item_qty . "', '" . $item_rate . "', '" . $item_total . "')");

                        if ($insert_row) $inserted_row++;
                        fp__(DB::SHIPPING_INVOICE_ITEMS, $mysqli->insert_id);
                    } else if (!empty($item_id) && empty($item_description) && empty($item_coo) && empty($item_rate) && empty($item_total)) {
                        $mysqli->query("DELETE FROM `" . DB::SHIPPING_INVOICE_ITEMS . "` WHERE id=$item_id");
                    }
                }
            }

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
        }
    }
}

$listingConfig = [
    'module' => $module,
    'module_caption' => 'Shipping Stocks - History',
    'thead' => '
        <th>INVOICE DATE</th>
        <th>CONSIGNEE</th>
        <th>DESTINATION PORT</th>
        <th>COUNTRY</th>
        <th>INCOTERM</th>
        <th>ACTIONS</th>
    ',
    'columns' => [
        ['data' => 0, 'name' => 'invoice_date', 'title' => 'INVOICE DATE'],
        ['data' => 1, 'name' => 'consignee', 'title' => 'CONSIGNEE'],
        ['data' => 2, 'name' => 'destination_port', 'title' => 'DESTINATION PORT'],
        ['data' => 3, 'name' => 'destination_country', 'title' => 'COUNTRY'],
        ['data' => 4, 'name' => 'incoterm', 'title' => 'INCOTERM'],
        ['data' => 5, 'title' => 'ACTION', 'orderable' => false, 'searchable' => false],
    ],
    'order' => [[0, 'desc']],
    'page_length' => 25,
];

include('admin_elements/listing_template.php');
include('admin_elements/admin_footer.php');
