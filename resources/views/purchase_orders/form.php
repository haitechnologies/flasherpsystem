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
 * @var string $vendor_id
 * @var string $purchase_order_no
 * @var string $purchase_order_status
 * @var string $purchase_order_date
 * @var string $reference_no
 * @var string $subject
 * @var string $warehouse_id
 * @var string $vendor_notes
 * @var string $terms_and_conditions
 * @var string $grand_subtotal
 * @var string $grand_discount_type
 * @var string $grand_discount_type_value
 * @var string $grand_discount_amount
 * @var string $grand_after_discount
 * @var string $grand_tax
 * @var string $grand_total
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
 * @var array $vendorOptions
 * @var array $warehouseOptions
 * @var array $itemsList
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';

$vendor_options_html = '';
foreach ($vendorOptions as $v) {
    $sel = ((string)$v['id'] === $vendor_id) ? 'selected' : '';
    $vendor_options_html .= '<option value="' . $v['id'] . '" ' . $sel . '>' . s__($v['display_name']) . '</option>';
}

$warehouse_options_html = '';
foreach ($warehouseOptions as $w) {
    $sel = ((string)$w['id'] === $warehouse_id) ? 'selected' : '';
    $warehouse_options_html .= '<option value="' . $w['id'] . '" ' . $sel . '>' . s__($w['warehouse_name']) . '</option>';
}
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0 && !empty($purchase_order_no)): ?>
                    <span class="badge bg-light text-black border ms-2"><?php echo s__($purchase_order_no); ?></span>
                <?php endif; ?>
                <span class="badge bg-light text-primary border-primary ms-2"><?php echo ((!empty($purchase_order_status)) ? ucwords($purchase_order_status) : ''); ?></span>
            </div>
            <div class="my-1">
                <?php if ($canCreate || $canEdit): ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm">Save</button>
                <?php endif; ?>
                <?php if ($id > 0): ?>
                    <a href="purchase_order_overview.php?purchase_order_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
                <?php else: ?>
                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Purchase orders are commitments to buy from vendors. They do not affect accounting until converted to a purchase or bill.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>
                <input type="hidden" name="purchase_order_status" id="purchase_order_status" value="<?php echo $purchase_order_status; ?>" />
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">
                                        <?php if ($id > 0): ?>Update<?php else: ?>New<?php endif; ?> Purchase Order
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $field = ['name' => 'vendor_id', 'label' => 'Vendor Name', 'options_html' => '<option value="0">Please select</option>' . $vendor_options_html, 'extra_class' => 'form-control select', 'empty_option' => 'Please select', 'required' => true];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name' => 'reference_no', 'label' => 'Reference no:', 'value' => $reference_no, 'placeholder' => 'Reference no'];
                                    include 'admin_elements/form_field_text.php';

                                    $field = ['name' => 'purchase_order_date', 'label' => 'Purchase Order Date:', 'required' => true, 'value' => $purchase_order_date, 'placeholder' => 'Purchase Order Date'];
                                    include 'admin_elements/form_field_date.php';

                                    $field = ['name' => 'warehouse_id', 'label' => 'Warehouse:', 'required' => true, 'options_html' => '<option value="0">Please select</option>' . $warehouse_options_html, 'selected' => $warehouse_id, 'empty_option' => false, 'extra_class' => 'form-select'];
                                    include 'admin_elements/form_field_select.php';

                                    $field = ['name' => 'subject', 'label' => 'Subject:', 'value' => $subject, 'placeholder' => 'Let your vendor know what this Purchase Order is for'];
                                    include 'admin_elements/form_field_text.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="col-xl-12">
                        <div class="row mb-2">
                            <div class="col-lg-2">
                                <label class="form-label ms-3"><span class="text-danger">ITEM DETAILS*</span></label>
                            </div>
                            <div class="col-lg-3">
                                <label class="form-label ms-4">DESCRIPTION</label>
                            </div>
                            <div class="col-lg-1">
                                <label class="form-label ms-3">QUANTITY</label>
                            </div>
                            <div class="col-lg-1">
                                <label class="form-label ms-4">RATE</label>
                            </div>
                            <div class="col-lg-1">
                                <label class="form-label ms-3">SUBTOTAL</label>
                            </div>
                            <div class="col-lg-1">
                                <label class="form-label ms-1">TAX</label>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label ms-2"><span class="text-danger">TOTAL*</span></label>
                            </div>
                        </div>

                        <div class="card">
                            <div class="row card-body">
                                <div class="col-lg-12">
                                    <?php
                                    for ($row_i = 1; $row_i <= $total_rows; $row_i++) {
                                        $index = $row_i - 1;
                                        $item_id_val = (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : '');
                                        $service_val = (!empty($service_arr[$index]) ? $service_arr[$index] : '');
                                        $description_val = (!empty($description_arr[$index]) ? $description_arr[$index] : '');
                                        $qty_val = (!empty($qty_arr[$index]) ? $qty_arr[$index] : '1');
                                        $rate_val = (!empty($rate_arr[$index]) ? $rate_arr[$index] : '0');
                                        $sub_total_val = (!empty($sub_total_arr[$index]) ? $sub_total_arr[$index] : '0');
                                        $tax_val = (!empty($tax_arr[$index]) ? $tax_arr[$index] : '0');
                                        $tax_amount_val = (!empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0');
                                        $total_val = (!empty($total_arr[$index]) ? $total_arr[$index] : '0');
                                    ?>
                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3" id="row_<?php echo $row_i; ?>">
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <input type="hidden" name="item_id[]" id="item_id<?php echo $row_i; ?>" value="<?php echo $item_id_val; ?>">
                                                        <div class="col-lg-2">
                                                             <select class="form-select" name="service[]" id="service<?php echo $row_i; ?>" onchange="setItemPrice('<?php echo $row_i; ?>'); calculateItemAmount('<?php echo $row_i; ?>');">
                                                                <option value="0">Please select</option>
                                                                <?php foreach ($itemsList as $item): ?>
                                                                    <option value="<?php echo $item['id']; ?>" <?php echo ((string)$item['id'] === (string)$service_val) ? 'selected="selected"' : ''; ?>>
                                                                        <?php echo s__((string)$item['item_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <textarea name="description[]" id="description<?php echo $row_i; ?>" rows="2" class="form-control" placeholder="Add a description to your item"><?php echo $description_val; ?></textarea>
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="1" name="qty[]" id="qty<?php echo $row_i; ?>" min="0" class="form-control text-center" value="<?php echo $qty_val; ?>" onkeyup="calculateItemAmount('<?php echo $row_i; ?>');" onchange="calculateItemAmount('<?php echo $row_i; ?>');">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="1" name="rate[]" id="rate<?php echo $row_i; ?>" min="0" class="form-control text-center" value="<?php echo $rate_val; ?>" onkeyup="calculateItemAmount('<?php echo $row_i; ?>');" onchange="calculateItemAmount('<?php echo $row_i; ?>');">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input readonly type="number" name="sub_total[]" id="sub_total<?php echo $row_i; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo $sub_total_val; ?>">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <select name="tax[]" id="tax<?php echo $row_i; ?>" class="form-select" onchange="calculateItemAmount(<?php echo $row_i; ?>, this.value);">
                                                                <option value="0" <?php echo ($tax_val == '0') ? 'selected' : ''; ?>>0%</option>
                                                                <option value="5" <?php echo ($tax_val == '5') ? 'selected' : ''; ?>>5%</option>
                                                                <option value="10" <?php echo ($tax_val == '10') ? 'selected' : ''; ?>>10%</option>
                                                                <option value="15" <?php echo ($tax_val == '15') ? 'selected' : ''; ?>>15%</option>
                                                            </select>
                                                            <div class="text-center mt-1">
                                                                <span class="badge bg-light text-black" style="font-weight: normal;" id="div_tax_amount<?php echo $row_i; ?>" style="display: <?php if ($tax_val > 0) { ?>block<?php } else { ?>none<?php } ?>;">
                                                                    <span id="span_tax_amount<?php echo $row_i; ?>"><?php echo $tax_amount_val; ?></span>
                                                                </span>
                                                            </div>
                                                            <input type="hidden" name="tax_amount[]" id="tax_amount<?php echo $row_i; ?>" value="<?php echo $tax_amount_val; ?>">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input readonly type="number" name="total[]" id="total<?php echo $row_i; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" placeholder="0" value="<?php echo $total_val; ?>" onchange="calculateGrand();" onkeyup="calculateGrand();">
                                                        </div>
                                                        <div class="col-lg-1 mt-1">
                                                            <?php if ($row_i > 1): ?>
                                                                <a href="#" onclick="calculateItemAmount('<?php echo $row_i; ?>'); clear_row(<?php echo $row_i; ?>)"><span class="badge bg-warning"> <i class="ph-x"></i> </span></a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php } ?>
                                    <div id="add_row_here"></div>
                                </div>

                                <div class="">
                                    <span id="span_add_item_row"><a href="#" onclick="add_item_row();"><span class="badge bg-primary"> Add New Row </span></a></span>
                                </div>

                                <script>
                                    window.itemPrices = {
                                        <?php foreach ($itemsList as $item): ?>
                                        <?php echo $item['id']; ?>: <?php echo (float)($item['cost_price'] ?? 0); ?>,
                                        <?php endforeach; ?>
                                    };
                                    function setItemPrice(rowNo) {
                                        var svc = document.getElementById('service' + rowNo);
                                        if (!svc) return;
                                        var itemId = svc.value;
                                        if (window.itemPrices[itemId] && parseFloat(window.itemPrices[itemId]) > 0) {
                                            document.getElementById('rate' + rowNo).value = window.itemPrices[itemId];
                                        }
                                    }
                                    function add_item_row() {
                                        var total_rows = document.getElementById('total_rows').value;
                                        total_rows++;

                                        var new_row = "";
                                        new_row += "<div class=\"row mb-3 pb-3\" id=\"row_" + total_rows + "\">";
                                        new_row += "<input type=\"hidden\" name=\"item_id[]\" id=\"item_id" + total_rows + "\">";
                                        new_row += "<div class=\"col-lg-2\">";
                                        new_row += "<select class=\"form-select\" name=\"service[]\" id=\"service" + total_rows + "\" onchange=\"setItemPrice('" + total_rows + "'); calculateItemAmount('" + total_rows + "');\">";
                                        new_row += "<option value=\"0\">Please select</option>";
                                        <?php foreach ($itemsList as $item): ?>
                                        new_row += "<option value=\"<?php echo $item['id']; ?>\"><?php echo s__((string)$item['item_name']); ?></option>";
                                        <?php endforeach; ?>
                                        new_row += "</select>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-3\">";
                                        new_row += "<textarea type=\"text\" name=\"description[]\" id=\"description" + total_rows + "\" rows=\"2\" placeholder=\"Add a description to your item\" class=\"form-control\"></textarea>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"1\" name=\"qty[]\" id=\"qty" + total_rows + "\" min=\"0\" class=\"form-control text-center\" value=\"1\" onkeyup=\"calculateItemAmount('" + total_rows + "');\" onchange=\"calculateItemAmount('" + total_rows + "');\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"1\" name=\"rate[]\" id=\"rate" + total_rows + "\" min=\"0\" class=\"form-control text-center\" value=\"0\" onkeyup=\"calculateItemAmount('" + total_rows + "');\" onchange=\"calculateItemAmount('" + total_rows + "');\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input readonly type=\"number\" name=\"sub_total[]\" id=\"sub_total" + total_rows + "\" min=\"0\" class=\"form-control bg-light bg-opacity-75 text-end\" value=\"0\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<select name=\"tax[]\" id=\"tax" + total_rows + "\" class=\"form-select\" onchange=\"calculateItemAmount(" + total_rows + ", this.value);\">";
                                        new_row += "<option value=\"0\">0%</option>";
                                        new_row += "<option value=\"5\">5%</option>";
                                        new_row += "<option value=\"10\">10%</option>";
                                        new_row += "<option value=\"15\">15%</option>";
                                        new_row += "</select>";
                                        new_row += "<div class=\"text-center mt-1\">";
                                        new_row += "<span class=\"badge bg-light text-black\" style=\"font-weight: normal;\" id=\"div_tax_amount" + total_rows + "\" style=\"display:none;\"></span>";
                                        new_row += "</div>";
                                        new_row += "<input type=\"hidden\" name=\"tax_amount[]\" id=\"tax_amount" + total_rows + "\" value=\"0\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input readonly type=\"number\" name=\"total[]\" id=\"total" + total_rows + "\" min=\"0\" class=\"form-control bg-light bg-opacity-75 text-end\" placeholder=\"0\" onchange=\"calculateGrand();\" onkeyup=\"calculateGrand();\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1 mt-1\">";
                                        new_row += "<a href=\"#\" onclick=\"clear_row(" + total_rows + ")\"><span class=\"badge bg-warning\"> <i class=\"ph-x\"></i> </span></a>";
                                        new_row += "</div>";
                                        new_row += "</div>";

                                        document.getElementById('add_row_here').insertAdjacentHTML("beforebegin", new_row);
                                        document.getElementById('total_rows').value = total_rows;
                                    }

                                    function clear_row(row_no) {
                                        calculateItemAmount(row_no);
                                        document.getElementById('service' + row_no).value = '0';
                                        document.getElementById('description' + row_no).value = '';
                                        document.getElementById('qty' + row_no).value = '';
                                        document.getElementById('rate' + row_no).value = '';
                                        document.getElementById('sub_total' + row_no).value = '';
                                        document.getElementById('tax' + row_no).value = '0';
                                        document.getElementById('tax_amount' + row_no).value = '';
                                        document.getElementById('total' + row_no).value = '';
                                        document.getElementById('div_tax_amount' + row_no).style.display = 'none';
                                        document.getElementById('row_' + row_no).style.display = 'none';
                                    }

                                    function percentage(num, percentage) {
                                        const result = num * (percentage / 100);
                                        return parseFloat(result.toFixed(3));
                                    }

                                    function calculateItemAmount(row_no) {
                                        clearGrandDiscountTypeValue();
                                        var service = document.getElementById('service' + row_no);
                                        var service_value = service.options[service.selectedIndex].value;

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
                                        var grand_subtotal = parseFloat(document.getElementById('grand_subtotal').value);
                                        var grand_discount_type_value = document.getElementById('grand_discount_type_value').value;

                                        if (grand_discount_type_value == '' || grand_discount_type_value == 'undefined') {
                                            grand_discount_type_value = '0';
                                        } else {
                                            grand_discount_type_value = parseFloat(grand_discount_type_value);
                                        }

                                        if (grand_discount_type == 'fixed') {
                                            if (grand_subtotal > 0 && grand_discount_type_value <= grand_subtotal) {
                                                document.getElementById('grand_discount_amount').value = parseFloat(grand_discount_type_value);
                                                apply_discount = true;
                                            } else if (grand_discount_type_value > grand_subtotal) {
                                                alert('Grand Discount cannot be greater than Grand Sub Total.');
                                                document.getElementById('grand_discount_type_value').value = '0';
                                            }
                                        } else if (grand_discount_type == 'percent') {
                                            if (grand_discount_type_value > 100) {
                                                document.getElementById('grand_discount_type_value').value = 0;
                                            } else {
                                                var percntVal = percentage(grand_subtotal, grand_discount_type_value);
                                                document.getElementById('grand_discount_amount').value = parseFloat(percntVal.toFixed(2));
                                                apply_discount = true;
                                            }
                                        } else {
                                            document.getElementById('grand_discount_type_value').value = '';
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

                                        var grand_total = parseFloat(final_total) + parseFloat(total_tax);
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
                                        <label class="col-lg-6 col-form-label">Vendor Notes:</label>
                                        <textarea class="form-control" name="vendor_notes" id="vendor_notes" style="field-sizing: content;" placeholder=""><?php echo $vendor_notes; ?></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="ms-sm-3 mb-3 mb-sm-0">
                                        <label class="col-lg-6 col-form-label">Terms & Conditions:</label>
                                        <textarea class="form-control" name="terms_and_conditions" id="terms_and_conditions" style="field-sizing: content;" placeholder=""><?php echo $terms_and_conditions; ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2"></div>
                        <div class="col-lg-4">
                            <div class="card">
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
                                        <label class="col-lg-3 col-form-label">Discount Type:</label>
                                        <div class="col-lg-3">
                                            <div class="mb-3 mb-sm-0">
                                                <select name="grand_discount_type" id="grand_discount_type" class="form-select" onchange="clearGrandDiscountTypeValue(); calculateGrand();">
                                                    <option value='0'></option>
                                                    <option value="percent" <?php if ($grand_discount_type == 'percent') { ?>selected<?php } ?>>Percent %</option>
                                                    <option value="fixed" <?php if ($grand_discount_type == 'fixed') { ?>selected<?php } ?>>Fixed</option>
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
            </form>
        </div>
    </div>
<?php include('admin_elements/copyright.php'); ?>
</div>
</div>
</div>
<?php include('admin_elements/admin_footer.php'); ?>
