<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var int $moduleId
 * @var int $session_user_id
 * @var int $session_role_id
 * @var string $error_message
 * @var string $customer_id
 * @var string $invoice_date
 * @var string $expiry_date
 * @var string $invoice_status
 * @var string $reference_no
 * @var string $warehouse_id
 * @var string $profile_name
 * @var string $frequency
 * @var string $start_date
 * @var string $end_date
 * @var string $expected_shipment_date
 * @var string $payment_term
 * @var string $shipment_type
 * @var string $sales_person
 * @var string $job_reference_no
 * @var string $master_awb_no
 * @var string $shipper
 * @var string $consignee
 * @var string $origin
 * @var string $destination
 * @var string $no_of_packs
 * @var string $gross_weight
 * @var string $chargeable_weight
 * @var string $volume
 * @var string $customer_notes
 * @var string $terms_and_conditions
 * @var string $grand_subtotal
 * @var string $grand_discount_type
 * @var string $grand_discount_type_value
 * @var string $grand_discount_amount
 * @var string $grand_after_discount
 * @var string $grand_tax
 * @var string $grand_total
 * @var int $publish
 * @var int $total_rows
 * @var array $item_id_arr
 * @var array $service_arr
 * @var array $description_arr
 * @var array $qty_arr
 * @var array $rate_arr
 * @var array $sub_total_arr
 * @var array $tax_arr
 * @var array $tax_amount_arr
 * @var array $total_arr
 * @var bool $canCreate
 * @var bool $canEdit
 */
use App\Core\DB;

include 'admin_elements/admin_header.php';

$db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);

$customer_options = '';
$customers = $db->fetchAll("SELECT id, display_name FROM `" . DB::CUSTOMERS . "` WHERE is_active=1 AND approved=1 ORDER BY id DESC");
foreach ($customers as $c) {
    $sel = ((string)$c['id'] === $customer_id) ? 'selected' : '';
    $customer_options .= '<option value="' . $c['id'] . '" ' . $sel . '>' . s__($c['display_name']) . '</option>';
}

$warehouse_options = '';
$warehouses = $db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1");
foreach ($warehouses as $w) {
    $sel = ((string)$w['id'] === $warehouse_id) ? 'selected' : '';
    $warehouse_options .= '<option value="' . $w['id'] . '" ' . $sel . '>' . $w['warehouse_name'] . '</option>';
}

$payment_terms_options = '';
$payment_terms = $db->fetchAll("SELECT id, payment_term FROM `" . DB::PAYMENT_TERMS . "` WHERE is_active=1 ORDER BY id ASC");
foreach ($payment_terms as $pt) {
    $sel = ((string)$pt['id'] === $payment_term) ? 'selected' : '';
    $payment_terms_options .= '<option value="' . $pt['id'] . '" ' . $sel . '>' . $pt['payment_term'] . '</option>';
}

$sales_person_options = '';
$sales_persons = $db->fetchAll("SELECT id, warehouse_name FROM `" . DB::ORGANIZATIONS . "` WHERE is_active=1");
foreach ($sales_persons as $sp) {
    $sel = ((string)$sp['id'] === $sales_person) ? 'selected' : '';
    $sales_person_options .= '<option value="' . $sp['id'] . '" ' . $sel . '>' . $sp['warehouse_name'] . '</option>';
}

$shipper_options = '';
$shippers = $db->fetchAll("SELECT id, shipper_name FROM `" . DB::SHIPPERS . "` WHERE is_active=1");
foreach ($shippers as $s) {
    $sel = ((string)$s['id'] === $shipper) ? 'selected' : '';
    $shipper_options .= '<option value="' . $s['id'] . '" ' . $sel . '>' . $s['shipper_name'] . '</option>';
}

$consignee_options = '';
$consignees = $db->fetchAll("SELECT id, consignee_name FROM `" . DB::CONSIGNEES . "` WHERE is_active=1");
foreach ($consignees as $c) {
    $sel = ((string)$c['id'] === $consignee) ? 'selected' : '';
    $consignee_options .= '<option value="' . $c['id'] . '" ' . $sel . '>' . $c['consignee_name'] . '</option>';
}

$origin_options = '';
$origins = $db->fetchAll("SELECT id, alpha3_code, country FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
foreach ($origins as $o) {
    $sel = ((string)$o['id'] === $origin) ? 'selected' : '';
    $origin_options .= '<option value="' . $o['id'] . '" ' . $sel . '>' . $o['alpha3_code'] . ' - ' . $o['country'] . '</option>';
}

$destination_options = '';
$destinations = $db->fetchAll("SELECT id, alpha3_code, country FROM `" . DB::GEO_COUNTRIES . "` WHERE is_active=1 ORDER BY country");
foreach ($destinations as $d) {
    $sel = ((string)$d['id'] === $destination) ? 'selected' : '';
    $destination_options .= '<option value="' . $d['id'] . '" ' . $sel . '>' . $d['alpha3_code'] . ' - ' . $d['country'] . '</option>';
}
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
            </div>
            <div class="my-1 d-inline-flex align-items-center me-2">
                <div class="form-check form-check-inline form-switch mb-0">
                    <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" <?php if ($publish == '1') echo 'checked="checked"'; ?> form="frminvoices">
                    <label class="form-check-label" for="publish">Publish</label>
                </div>
            </div>
            <div class="my-1">
                <?php if ($canCreate || $canEdit): ?>
                    <button type="submit" form="frminvoices" class="btn btn-primary btn-sm me-2">Save</button>
                    <button type="button" onclick="document.getElementById('save_and_send').value='1'; document.getElementById('frminvoices').submit();" class="btn btn-info btn-sm me-2">Save & Send</button>
                <?php endif; ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <form class="steps-basic clearfix" method="post" id="frminvoices" name="frminvoices" action="recurring_invoices.php" enctype="multipart/form-data">
                <input type="hidden" name="invoice_status" id="invoice_status" value="draft" />
                <input type="hidden" name="save_and_send" id="save_and_send" value="0" />
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_invoices" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_invoices" />
                <?php endif; ?>

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php
                                    $field = ['name'=>'customer_id', 'label'=>'Customer Name:', 'required'=>true, 'options_html'=>'<option value="0">Please select</option>' . $customer_options, 'selected'=>$customer_id, 'empty_option'=>false, 'extra_class'=>'form-control select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'invoice_date', 'label'=>'Invoice Date:', 'required'=>true, 'value'=>$invoice_date, 'placeholder'=>'Requested Date'];
                                    include 'admin_elements/form_field_date.php';

                                    $field = ['name'=>'reference_no', 'label'=>'Reference no:', 'value'=>$reference_no];
                                    include 'admin_elements/form_field_text.php';

                                    $field = ['name'=>'expiry_date', 'label'=>'Expiry Date:', 'value'=>$expiry_date, 'placeholder'=>'Expiry Date'];
                                    include 'admin_elements/form_field_date.php';

                                    $field = ['name'=>'warehouse_id', 'label'=>'Warehouses:', 'required'=>true, 'options_html'=>$warehouse_options, 'selected'=>$warehouse_id, 'empty_option'=>false];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'expected_shipment_date', 'label'=>'Expected Shipment Date:', 'value'=>$expected_shipment_date, 'placeholder'=>'Expected Shipment Date'];
                                    include 'admin_elements/form_field_date.php';

                                    $field = ['name'=>'payment_term', 'label'=>'Payment Terms:', 'options_html'=>$payment_terms_options, 'selected'=>$payment_term, 'empty_option'=>false];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'shipment_type', 'label'=>'Delivery Method:', 'options'=>['export'=>'Export','import'=>'Import','transit'=>'Transit'], 'selected'=>$shipment_type, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'sales_person', 'label'=>'Sales Person:', 'options_html'=>$sales_person_options, 'selected'=>$sales_person, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">SET Schedule</h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $field = ['name'=>'profile_name', 'label'=>'Profile Name:', 'required'=>true, 'value'=>$profile_name];
                                    include 'admin_elements/form_field_text.php';

                                    $field = ['name'=>'frequency', 'label'=>'Repeat Every:', 'required'=>true, 'options'=>['week'=>'Week','2_weeks'=>'2 Weeks','month'=>'Month','6_months'=>'6 Months','year'=>'Year'], 'selected'=>$frequency, 'empty_option'=>false, 'extra_class'=>'form-control'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'start_date', 'label'=>'Start On:', 'value'=>$start_date];
                                    include 'admin_elements/form_field_date.php';

                                    $field = ['name'=>'end_date', 'label'=>'Ends On:', 'value'=>$end_date];
                                    include 'admin_elements/form_field_date.php';
                                    ?>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <?php
                                    $field = ['name'=>'job_reference_no', 'label'=>'Job Reference no:', 'value'=>$job_reference_no];
                                    include 'admin_elements/form_field_text.php';

                                    $field = ['name'=>'master_awb_no', 'label'=>'Master AWB no:', 'value'=>$master_awb_no];
                                    include 'admin_elements/form_field_text.php';

                                    $field = ['name'=>'shipper', 'label'=>'Shipper:', 'options_html'=>$shipper_options, 'selected'=>$shipper, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'consignee', 'label'=>'Consignee:', 'options_html'=>$consignee_options, 'selected'=>$consignee, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'origin', 'label'=>'Origin:', 'required'=>true, 'options_html'=>$origin_options, 'selected'=>$origin, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name'=>'destination', 'label'=>'Destination:', 'required'=>true, 'options_html'=>$destination_options, 'selected'=>$destination, 'empty_option'=>'Please select'];
                                    include 'admin_elements/form_field_select.php';
                                    ?>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">No of Packs:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="" name="no_of_packs" id="no_of_packs" value="<?php echo $no_of_packs; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Gross Weight:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="" name="gross_weight" id="gross_weight" value="<?php echo $gross_weight; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Chargeable Weight:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="" name="chargeable_weight" id="chargeable_weight" value="<?php echo $chargeable_weight; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Volume (CBM):</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="" name="volume" id="volume" value="<?php echo $volume; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="col-xl-12">
                        <div class="row mb-2">
                            <div class="col-lg-2"><label class="form-label ms-3"><span class="text-danger">ITEM DETAILS*</span></label></div>
                            <div class="col-lg-3"><label class="form-label ms-4">DESCRIPTION</label></div>
                            <div class="col-lg-1"><label class="form-label ms-3">QUANTITY </label></div>
                            <div class="col-lg-1"><label class="form-label ms-4">RATE </label></div>
                            <div class="col-lg-1"><label class="form-label ms-3">SUBTOTAL </label></div>
                            <div class="col-lg-1"><label class="form-label ms-1">TAX </label></div>
                            <div class="col-lg-2"><label class="form-label ms-2"><span class="text-danger">TOTAL*</span></label></div>
                        </div>
                        <div class="card">
                            <div class="row card-body">
                                <div class="col-lg-12">
                                    <?php for ($inv_item = 1; $inv_item <= $total_rows; $inv_item++):
                                        $index = $inv_item - 1;
                                    ?>
                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3" id="row_<?php echo $inv_item; ?>">
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <input type="hidden" name="item_id[]" id="item_id<?php echo $inv_item; ?>" value="<?php echo (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''); ?>">
                                                        <div class="col-lg-2">
                                                            <select class="form-select" name="service[]" id="service<?php echo $inv_item; ?>" onchange="ajax_populate_item_rate(this.value, <?php echo $inv_item; ?>); ">
                                                                <option value="0">Please select</option>
                                                                <?php
                                                                $services = $db->fetchAll("SELECT id, item_name FROM `" . DB::ITEMS . "` WHERE is_active=1 AND item_type='services' ORDER BY item_name");
                                                                foreach ($services as $svc) {
                                                                    $sel = (!empty($service_arr[$index]) && $service_arr[$index] == $svc['id']) ? ' selected="selected"' : '';
                                                                    echo '<option value="' . $svc['id'] . '"' . $sel . '>' . $svc['item_name'] . '</option>';
                                                                }
                                                                ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <textarea name="description[]" id="description<?php echo $inv_item; ?>" rows="2" class="form-control" placeholder="Add a description to your item"><?php echo (!empty($description_arr[$index]) ? $description_arr[$index] : ''); ?></textarea>
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="1" name="qty[]" id="qty<?php echo $inv_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($qty_arr[$index]) ? $qty_arr[$index] : '1'); ?>" onkeyup="calculateItemAmount('<?php echo $inv_item; ?>');" onchange=" calculateItemAmount('<?php echo $inv_item; ?>');">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="1" name="rate[]" id="rate<?php echo $inv_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($rate_arr[$index]) ? $rate_arr[$index] : '0'); ?>" onkeyup="calculateItemAmount('<?php echo $inv_item; ?>');" onchange=" calculateItemAmount('<?php echo $inv_item; ?>');">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input readonly type="number" name="sub_total[]" id="sub_total<?php echo $inv_item; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo (!empty($sub_total_arr[$index]) ? $sub_total_arr[$index] : '0'); ?>">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <select name="tax[]" id="tax<?php echo $inv_item; ?>" class="form-select" onchange="calculateItemAmount(<?php echo $inv_item; ?>, this.value); ">
                                                                <?php for ($i = 0; $i <= 100; $i++): ?>
                                                                    <option value="<?php echo $i; ?>" <?php echo ((!empty($tax_arr[$index]) && $tax_arr[$index] == $i) ? 'selected="selected"' : ''); ?>><?php echo $i; ?>%</option>
                                                                <?php endfor; ?>
                                                            </select>
                                                            <div class="text-center mt-1">
                                                                <span class="badge bg-light text-black" style="font-weight: normal;" id="div_tax_amount<?php echo $inv_item; ?>">
                                                                    <span id="span_tax_amount<?php echo $inv_item; ?>"><?php echo (!empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0'); ?></span>
                                                                </span>
                                                            </div>
                                                            <input type="hidden" name="tax_amount[]" id="tax_amount<?php echo $inv_item; ?>" class="form-control" placeholder="0" value="<?php echo (!empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0'); ?>">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input readonly type="number" name="total[]" id="total<?php echo $inv_item; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" placeholder="0" value="<?php echo (!empty($total_arr[$index]) ? $total_arr[$index] : ''); ?>" onchange="calculateGrand(<?php echo $inv_item; ?>);" onkeyup="calculateGrand(<?php echo $inv_item; ?>);">
                                                        </div>
                                                        <div class="col-lg-2 mt-1">
                                                            <?php if ($inv_item > 1): ?>
                                                                <a href="#" onclick="calculateItemAmount('<?php echo $inv_item; ?>'); clear_row(<?php echo $inv_item; ?>)"><span class="badge bg-warning"> <i class="ph-x"></i> </span></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endfor; ?>
                                    <div id="add_row_here"></div>
                                </div>
                                <div class="">
                                    <span id="span_add_item_row"><a href="#" onclick="add_item_row(); "><span class="badge bg-primary"> Add New Row </a></span></span>
                                </div>

                                <script>
                                    function add_item_row() {
                                        var total_rows = document.getElementById('total_rows').value;
                                        total_rows++;

                                        var new_row = "";
                                        new_row += "<div class=\"row mb-3 pb-3\" id=\"row_" + total_rows + "\">";
                                        new_row += "<input type=\"hidden\" name=\"item_id[]\" id=\"item_id" + total_rows + "\">";
                                        new_row += "<div class=\"col-lg-2\">";
                                        new_row += "<select class=\"form-select\" onchange=\"ajax_populate_item_rate(this.value, " + total_rows + "); \" name=\"service[]\" id=\"service" + total_rows + "\">";
                                        new_row += "<option value=\"0\">Please select</option>";
                                        new_row += "</select>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-3\">";
                                        new_row += "<textarea type=\"text\" name=\"description[]\" id=\"description" + total_rows + "\" rows=\"2\" min=\"0\" placeholder=\"Add a description to your item\" class=\"form-control\"></textarea>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"1\" name=\"qty[]\" id=\"qty" + total_rows + "\" min=\"1\" onkeyup=\"calculateItemAmount('" + total_rows + "');\" onchange=\"calculateItemAmount('" + total_rows + "');\" placeholder=\"1\" class=\"form-control text-center\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"1\" name=\"rate[]\" id=\"rate" + total_rows + "\" min=\"0\" placeholder=\"0\" class=\"form-control text-center\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input readonly type=\"number\" name=\"sub_total[]\" id=\"sub_total" + total_rows + "\" min=\"0\" placeholder=\"0\" class=\"form-control bg-light bg-opacity-75 text-end\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<select name=\"tax[]\" id=\"tax" + total_rows + "\" class=\"form-select\" onchange=\"calculateItemAmount(" + total_rows + ", this.value);\">";
                                        for (i = 0; i <= 100; i++) {
                                            new_row += "<option value=" + i + ">" + i + "%</option>";
                                        }
                                        new_row += "</select>";
                                        new_row += "<div class=\"text-center mt-1\">";
                                        new_row += "<span class=\"badge bg-light text-black\" style=\"font-weight: normal;\" id=\"div_tax_amount" + total_rows + "\"></span>";
                                        new_row += "</div>";
                                        new_row += "<input type=\"hidden\" name=\"tax_amount[]\" id=\"tax_amount" + total_rows + "\" class=\"form-control\" placeholder=\"0\" value=\"0\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input readonly type=\"number\" name=\"total[]\" id=\"total" + total_rows + "\" min=\"0\" class=\"form-control bg-light bg-opacity-75 text-end\" placeholder=\"0\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1 mt-1\"><span id=\"span_remove_item_row" + total_rows + "\"> <a href=\"#\" onclick=\"clear_row(" + total_rows + ")\"><span class=\"badge bg-warning\"> <i class=\"ph-x\"></i> </span></a></span> </div>";
                                        new_row += "</div>";

                                        document.getElementById('add_row_here').insertAdjacentHTML("beforebegin", new_row);
                                        document.getElementById('total_rows').value = total_rows;
                                        ajax_populate_services();
                                    }

                                    function clear_row(row_no) {
                                        calculateItemAmount(row_no);
                                        document.getElementById('service' + row_no).value = '0';
                                        document.getElementById('service' + row_no).text = 'Please select';
                                        document.getElementById('description' + row_no).value = '';
                                        document.getElementById('qty' + row_no).value = '';
                                        document.getElementById('rate' + row_no).value = '';
                                        document.getElementById('sub_total' + row_no).value = '';
                                        document.getElementById('tax' + row_no).value = '';
                                        document.getElementById('tax_amount' + row_no).value = '';
                                        document.getElementById('total' + row_no).value = '';
                                        document.getElementById('row_' + row_no).style.display = 'none';
                                    }

                                    function percentage(num, percentage) {
                                        const result = num * (percentage / 100);
                                        return parseFloat(result.toFixed(3));
                                    }

                                    function calculateItemAmount(row_no) {
                                        clearGrandDiscountTypeValue();
                                        let service = document.getElementById('service' + row_no);
                                        let service_value = service.options[service.selectedIndex].value;
                                        if (service_value != NaN && service_value != '' && service_value != 'undefined' && service_value != '0') {
                                            var qty = document.getElementById('qty' + row_no).value;
                                            qty = Number(qty);
                                            var rate = document.getElementById('rate' + row_no).value;
                                            var sub_total = parseFloat(rate * qty).toFixed(2);
                                            document.getElementById('sub_total' + row_no).value = parseFloat(sub_total);
                                            var tax = document.getElementById('tax' + row_no).value;
                                            let tax_amount = percentage(sub_total, tax).toFixed(2);
                                            if (rate > 0 && tax > 0) {
                                                document.getElementById('div_tax_amount' + row_no).style.display = 'block';
                                                document.getElementById('div_tax_amount' + row_no).innerHTML = 'Tax ' + parseFloat(tax_amount);
                                                document.getElementById('tax_amount' + row_no).value = parseFloat(tax_amount);
                                                document.getElementById('total' + row_no).value = parseFloat(sub_total) + parseFloat(tax_amount);
                                            } else {
                                                document.getElementById('div_tax_amount' + row_no).style.display = 'none';
                                                document.getElementById('tax_amount' + row_no).value = '0';
                                                document.getElementById('total' + row_no).value = parseFloat(sub_total);
                                            }
                                            calculateGrand();
                                        }
                                    }

                                    function calculateGrand() {
                                        var total_rows = document.getElementById('total_rows').value;
                                        var final_total = 0;
                                        for (var i = 1; i <= total_rows; i++) {
                                            var total = document.getElementById('total' + i).value;
                                            final_total += Number(total);
                                        }
                                        document.getElementById('grand_subtotal').value = parseFloat(final_total.toFixed(2));

                                        var apply_discount = false;
                                        var e = document.getElementById('grand_discount_type');
                                        var grand_discount_type = e.value;
                                        var grand_subtotal = document.getElementById('grand_subtotal').value;
                                        grand_subtotal = parseFloat(grand_subtotal);
                                        var grand_discount_type_value = document.getElementById('grand_discount_type_value').value;
                                        if (grand_discount_type_value == '' || grand_discount_type_value == 'undefined' || grand_discount_type_value == 'NULL') {
                                            grand_discount_type_value = '0';
                                        } else {
                                            grand_discount_type_value = parseFloat(grand_discount_type_value);
                                        }

                                        if (grand_discount_type == 'fixed') {
                                            if (grand_subtotal == 0) {
                                            } else if (grand_discount_type_value > grand_subtotal) {
                                                alert('Grand Discount cannot be greater than Grand Sub Total.');
                                                document.getElementById('grand_discount_type_value').value = '0';
                                                document.getElementById('grand_total').value = document.getElementById('grand_subtotal').value;
                                            } else if (grand_discount_type_value <= grand_subtotal) {
                                                var recalculated_grand_total = (parseFloat(grand_subtotal) - parseFloat(grand_discount_type_value));
                                                document.getElementById('grand_discount_amount').value = parseFloat(grand_discount_type_value);
                                                apply_discount = true;
                                            }
                                        } else if (grand_discount_type == 'percent') {
                                            if (grand_discount_type_value > 100) {
                                                document.getElementById('grand_discount_type_value').value = 0;
                                            } else if (grand_discount_type_value <= 100) {
                                                var percntVal = percentage(grand_subtotal, grand_discount_type_value);
                                                document.getElementById('grand_discount_amount').value = parseFloat(percntVal.toFixed(2));
                                                var recalculated_total = (parseFloat(grand_subtotal) - parseFloat(grand_discount_type_value));
                                                var grand_after_discount = parseFloat(grand_subtotal.toFixed(2)) - parseFloat(percntVal.toFixed(2));
                                                document.getElementById('grand_total').value = parseFloat(grand_after_discount.toFixed(2));
                                                apply_discount = true;
                                            }
                                        } else {
                                            document.getElementById('grand_discount_type_value').value = '';
                                            var grand_tax = document.getElementById('grand_tax').value;
                                            var grand_subtotal = document.getElementById('grand_subtotal').value;
                                            var grand_total = parseFloat(grand_subtotal) + parseFloat(grand_tax);
                                            document.getElementById('grand_total').value = parseFloat(grand_total.toFixed(2));
                                        }

                                        if (apply_discount == true) {
                                            var grand_discount_amount = document.getElementById('grand_discount_amount').value;
                                            final_total = parseFloat(final_total) - parseFloat(grand_discount_amount);
                                            document.getElementById('grand_after_discount').value = parseFloat(final_total.toFixed(2));
                                        }

                                        var total_tax = 0;
                                        for (var i = 1; i <= total_rows; i++) {
                                            var tax_amount = document.getElementById('tax_amount' + i).value;
                                            total_tax += Number(tax_amount);
                                        }
                                        document.getElementById('grand_tax').value = parseFloat(total_tax.toFixed(2));
                                        var grand_subtotal = Number(final_total);
                                        var grand_total = parseFloat(grand_subtotal) + parseFloat(total_tax);
                                        document.getElementById('grand_total').value = parseFloat(grand_total.toFixed(2));
                                    }

                                    function clearGrandDiscountTypeValue() {
                                        document.getElementById('grand_discount_type_value').value = '';
                                        document.getElementById('grand_discount_amount').value = '';
                                        document.getElementById('grand_after_discount').value = '';
                                    }
                                </script>

                                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $total_rows; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="ms-sm-3 mb-3 mb-sm-0">
                                        <label class="col-lg-6 col-form-label">Customer Notes:</label>
                                        <textarea class="form-control" name="customer_notes" id="customer_notes" style="field-sizing: content;" placeholder="Enter any notes to be displayed in your transaction"><?php echo $customer_notes; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="ms-sm-3 mb-3 mb-sm-0">
                                        <label class="col-lg-6 col-form-label">Terms & Conditions: </label>
                                        <textarea class="form-control" name="terms_and_conditions" id="terms_and_conditions" style="field-sizing: content;" placeholder="Enter the terms and conditions of your business to be displayed in your transaction"><?php echo $terms_and_conditions; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2"></div>
                        <div class="col-lg-4">
                            <div class="card ">
                                <div class="card-body">
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Grand Subtotal:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="grand_subtotal" id="grand_subtotal" value="<?php echo $grand_subtotal; ?>" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-3 col-form-label">Discount Type: </label>
                                        <div class="col-lg-3">
                                            <div class="mb-3 mb-sm-0">
                                                <select name="grand_discount_type" id="grand_discount_type" class="form-select" onchange="clearGrandDiscountTypeValue(); calculateGrand();">
                                                    <option value='0'></option>
                                                    <option value="percent" <?php if ($grand_discount_type == 'percent') echo 'selected'; ?>>Percent %</option>
                                                    <option value="fixed" <?php if ($grand_discount_type == 'fixed') echo 'selected'; ?>>Fixed</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-lg-3">
                                            <input type="number" min="0" step="any" class="form-control" name="grand_discount_type_value" id="grand_discount_type_value" value="<?php echo $grand_discount_type_value; ?>" placeholder="0" onkeyup="calculateGrand();" onchange="calculateGrand();">
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label">Discount Amount</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_discount_amount" id="grand_discount_amount" value="<?php echo $grand_discount_amount; ?>" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label">Subtotal: (Discounted)</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_after_discount" id="grand_after_discount" value="<?php echo $grand_after_discount; ?>" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label">Total Tax Amount</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_tax" id="grand_tax" value="<?php echo $grand_tax; ?>" placeholder="0">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Grand Total</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" name="grand_total" id="grand_total" value="<?php echo $grand_total; ?>" readonly>
                                            </div>
                                        </div>
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
</div>
<?php include('admin_elements/admin_footer.php'); ?>
