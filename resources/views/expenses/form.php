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
 * @var string $expense_date
 * @var string $paid_through
 * @var string $vendor_id
 * @var string $reference_no
 * @var string $customer_id
 * @var int $billable
 * @var string $grand_total
 * @var int $total_rows
 * @var array $item_id_arr
 * @var array $expense_account_arr
 * @var array $description_arr
 * @var array $total_arr
 * @var array $vendorsList
 * @var array $customersList
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';

$vendor_options = '';
foreach ($vendorsList as $v) {
    $sel = ((string)$v['id'] === $vendor_id) ? 'selected' : '';
    $vendor_options .= '<option value="' . $v['id'] . '" ' . $sel . '>' . s__($v['display_name']) . '</option>';
}

$customer_options = '';
foreach ($customersList as $c) {
    $sel = ((string)$c['id'] === $customer_id) ? 'selected' : '';
    $customer_options .= '<option value="' . $c['id'] . '" ' . $sel . '>' . s__($c['display_name']) . '</option>';
}
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <div class="form-check form-check-inline form-switch mb-0 ms-2">
                    <input type="checkbox"
                        class="form-check-input form-check-input-success"
                        name="billable"
                        id="billable"
                        value="1"
                        form="frm<?php echo $module; ?>"
                        <?php if ($billable) echo 'checked'; ?>
                        <?php if (empty($customer_id) || (int)$customer_id === 0) echo 'disabled'; ?>>
                    <label class="form-check-label" for="billable">Billable</label>
                </div>
            </div>
            <div class="my-1">
                <?php if ($canCreate || $canEdit): ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm">Save</button>
                <?php endif; ?>
                <?php if ($id > 0): ?>
                    <a href="expense_overview.php?expense_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
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
                <strong>How this works:</strong> Expenses record costs you've incurred. They reduce your profit. Paid expenses affect your bank/cash balance. Affects: Profit &amp; Loss, General Ledger.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php
                                    $field = ['name' => 'expense_date', 'label' => 'Expense Date', 'value' => $expense_date, 'placeholder' => 'Expense Date', 'required' => true, 'extra_attr' => 'autocomplete="off"'];
                                    include 'admin_elements/form_field_date.php';
                                    ?>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Paid Through:*</span></label>
                                        <div class="col-lg-9">
                                            <select required class="form-select" name="paid_through" id="paid_through">
                                                <option value="0" class="fw-semibold text-black" disabled>Select</option>
                                                <?php echo fetchAccountsDropdown($account_type = array(1, 2, 3), $prefix = '', (int)$paid_through); ?>
                                            </select>
                                        </div>
                                    </div>
                                    <?php
                                    $field = ['name' => 'vendor_id', 'label' => 'Vendor Name', 'options_html' => '<option value="0">Please select</option>' . $vendor_options, 'selected' => $vendor_id, 'placeholder' => 'Please select'];
                                    include 'admin_elements/form_field_select.php';
                                    ?>
                                    <?php
                                    $field = ['name' => 'reference_no', 'label' => 'Reference no', 'value' => $reference_no, 'placeholder' => 'Reference no'];
                                    include 'admin_elements/form_field_text.php';
                                    ?>
                                    <?php
                                    $field = ['name' => 'customer_id', 'label' => 'Customer Name', 'options_html' => '<option value="0">Please select</option>' . $customer_options, 'selected' => $customer_id, 'placeholder' => 'Please select', 'extra_attr' => 'onchange="check_billable(this.value);"'];
                                    include 'admin_elements/form_field_select.php';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="col-xl-12">
                        <div class="row mb-2">
                            <div class="col-lg-2"><label class="form-label ms-3"><span class="text-danger">EXPENSE DETAILS*</span></label></div>
                            <div class="col-lg-4"><label class="form-label ms-4">DESCRIPTION</label></div>
                            <div class="col-lg-2"><label class="form-label ms-2"><span class="text-danger">TOTAL*</span></label></div>
                        </div>
                        <div class="card">
                            <div class="row card-body">
                                <div class="col-lg-12">
                                    <?php
                                    for ($expense_item = 1; $expense_item <= $total_rows; $expense_item++) {
                                        $index = $expense_item - 1;
                                        $item_id_val = (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : '');
                                        $expense_account_val = (!empty($expense_account_arr[$index]) ? $expense_account_arr[$index] : '');
                                        $description_val = (!empty($description_arr[$index]) ? $description_arr[$index] : '');
                                        $total_val = (!empty($total_arr[$index]) ? $total_arr[$index] : '');
                                    ?>
                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3" id="row_<?php echo $expense_item; ?>">
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <input type="hidden" name="item_id[]" id="item_id<?php echo $expense_item; ?>" value="<?php echo $item_id_val; ?>">
                                                        <div class="col-lg-2">
                                                            <select required class="form-select" name="expense_account[]" id="expense_account<?php echo $expense_item; ?>">
                                                                <option value="0" class="fw-semibold text-black" disabled>Expense</option>
                                                                <?php echo fetchAccountsDropdown($account_type = array(5), $prefix = '', (int)$expense_account_val); ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-4">
                                                            <textarea name="description[]" id="description<?php echo $expense_item; ?>" rows="2" class="form-control" placeholder="Add a description to your expense"><?php echo $description_val; ?></textarea>
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" name="total[]" id="total<?php echo $expense_item; ?>" min="0" class="form-control text-end" placeholder="0" value="<?php echo $total_val; ?>" onchange="calculateGrand();" onkeyup="calculateGrand();">
                                                        </div>
                                                        <div class="col-lg-2 mt-1">
                                                            <?php if ($expense_item > 1): ?>
                                                                <a href="#" onclick="calculateGrand(); clear_row(<?php echo $expense_item; ?>)"><span class="badge bg-warning"> <i class="ph-x"></i> </span></a>
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

                                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $total_rows; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
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

<script>
function check_billable(val) {
    const billableSwitch = document.getElementById('billable');
    if (val != "0" && val != "") {
        billableSwitch.disabled = false;
    } else {
        billableSwitch.disabled = true;
        billableSwitch.checked = false;
    }
}

function add_item_row() {
    var total_rows = document.getElementById('total_rows').value;
    total_rows++;

    var new_row = "";
    new_row += "<div class=\"row mb-3 pb-3\" id=\"row_" + total_rows + "\">";
    new_row += "<input type=\"hidden\" name=\"item_id[]\" id=\"item_id" + total_rows + "\">";
    new_row += "<div class=\"col-lg-2\">";
    new_row += "<select class=\"form-select\" name=\"expense_account[]\" id=\"expense_account" + total_rows + "\">";
    new_row += "<option value=\"0\">Please select</option>";
    new_row += "</select>";
    new_row += "</div>";
    new_row += "<div class=\"col-lg-4\">";
    new_row += "<textarea name=\"description[]\" id=\"description" + total_rows + "\" rows=\"2\" placeholder=\"Add a description to your expense\" class=\"form-control\"></textarea>";
    new_row += "</div>";
    new_row += "<div class=\"col-lg-1\">";
    new_row += "<input type=\"number\" name=\"total[]\" id=\"total" + total_rows + "\" min=\"0\" class=\"form-control text-end\" placeholder=\"0\" onchange=\"calculateGrand();\" onkeyup=\"calculateGrand();\">";
    new_row += "</div>";
    new_row += "<div class=\"col-lg-1 mt-1\"><span id=\"span_remove_item_row" + total_rows + "\"> <a href=\"#\" onclick=\"clear_row(" + total_rows + ")\"><span class=\"badge bg-warning\"> <i class=\"ph-x\"></i> </span></a></span></div>";
    new_row += "</div>";

    document.getElementById('add_row_here').insertAdjacentHTML("beforebegin", new_row);
    document.getElementById('total_rows').value = total_rows;

    ajax_populate_expense_coa();
}

function clear_row(row_no) {
    calculateGrand();
    document.getElementById('expense_account' + row_no).value = '0';
    document.getElementById('description' + row_no).value = '';
    document.getElementById('total' + row_no).value = '';
    document.getElementById('row_' + row_no).style.display = 'none';
}

function calculateGrand() {
    var total_rows = document.getElementById('total_rows').value;
    var final_total = 0;
    for (var i = 1; i <= total_rows; i++) {
        var el = document.getElementById('total' + i);
        if (el) {
            final_total += Number(el.value);
        }
    }
    document.getElementById('grand_total').value = parseFloat(final_total.toFixed(2));
}
</script>

<?php include('admin_elements/admin_footer.php'); ?>
