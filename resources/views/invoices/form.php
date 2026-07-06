<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $customer_id
 * @var string $invoice_no
 * @var string $invoice_status
 * @var string $invoice_date
 * @var string $expiry_date
 * @var string $reference_no
 * @var string $warehouse_id
 * @var string $expected_shipment_date
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
 * @var string $terms_and_conditions
 * @var string $grand_subtotal
 * @var string $grand_discount_type
 * @var string $grand_discount_type_value
 * @var string $grand_discount_amount
 * @var string $grand_after_discount
 * @var string $customer_notes
 * @var string $grand_tax
 * @var string $grand_total
 * @var int $is_active
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
 * @var array $customersList
 * @var array $orgList
 * @var array $shippersList
 * @var array $consigneesList
 * @var array $itemsList
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success ms-2">Invoice #: <?php echo $invoice_no; ?></span>
                <?php endif; ?>
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?php echo !empty($invoice_status) ? ucwords($invoice_status) : ''; ?></span>
            </div>
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if ($canCreate): ?>
                    <?php if ($id > 0): ?>
                        <button type="button" form="frminvoices" class="submit-form btn btn-primary btn-sm">Save</button>
                    <?php else: ?>
                        <button type="button" form="frminvoices" class="save-draft-invoice btn btn-primary btn-sm">Save as Draft</button>
                    <?php endif; ?>
                    <button type="button" form="frminvoices" class="save-and-send-invoice btn btn-info btn-sm">Save and Send</button>
                <?php endif; ?>
                <?php if ($id > 0): ?>
                    <a href="invoice_overview.php?invoice_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
                <?php else: ?>
                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Invoices record sales to customers. They increase your revenue and create an outstanding receivable. Affects: Profit &amp; Loss, Customer Aging, General Ledger, and VAT reports.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="invoice_status" id="invoice_status" value="<?php echo $invoice_status; ?>" />
                <input type="hidden" name="save_and_send" id="save_and_send" value="" />
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>
                <?php echo csrf_field(); ?>

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Customer Name:*</span></label>
                                        <div class="col-lg-9">
                                            <select name="customer_id" id="customer_id" class="form-control select">
                                                <option value='0'>Please select</option>
                                                <?php foreach ($customersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $customer_id ? 'selected' : ''; ?>>
                                                        <?php echo $row['display_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Invoice Date:*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Invoice Date" name="invoice_date" id="invoice_date" value="<?php echo $invoice_date; ?>">
                                                <div class="form-control-feedback-icon"><i class="ph-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Reference no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="reference_no" id="reference_no" value="<?php echo $reference_no; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Expiry Date:</label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Expiry Date" name="expiry_date" id="expiry_date" value="<?php echo $expiry_date; ?>">
                                                <div class="form-control-feedback-icon"><i class="ph-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Organizations:*</span></label>
                                        <div class="col-lg-9">
                                            <select name="warehouse_id" id="warehouse_id" class="form-select">
                                                <?php foreach ($orgList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $warehouse_id ? 'selected' : ''; ?>>
                                                        <?php echo $row['warehouse_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Expected Shipment Date:</label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Expected Shipment Date" name="expected_shipment_date" id="expected_shipment_date" value="<?php echo $expected_shipment_date; ?>">
                                                <div class="form-control-feedback-icon"><i class="ph-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="payment_term" id="payment_term" value="0">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Delivery Method:</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="shipment_type" id="shipment_type">
                                                <option value='0'>Please select</option>
                                                <option value="export" <?php echo $shipment_type === 'export' ? 'selected' : ''; ?>>Export</option>
                                                <option value="import" <?php echo $shipment_type === 'import' ? 'selected' : ''; ?>>Import</option>
                                                <option value="transit" <?php echo $shipment_type === 'transit' ? 'selected' : ''; ?>>Transit</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Sales Person:</label>
                                        <div class="col-lg-9">
                                            <select name="sales_person" id="sales_person" class="form-select">
                                                <option value='0'>Please select</option>
                                                <?php foreach ($orgList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $sales_person ? 'selected' : ''; ?>>
                                                        <?php echo $row['warehouse_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Job Reference no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="job_reference_no" id="job_reference_no" value="<?php echo $job_reference_no; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Master AWB no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="master_awb_no" id="master_awb_no" value="<?php echo $master_awb_no; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Shipper:</label>
                                        <div class="col-lg-9">
                                            <select name="shipper" id="shipper" class="form-select">
                                                <option value='0'>Please select</option>
                                                <?php foreach ($shippersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $shipper ? 'selected' : ''; ?>>
                                                        <?php echo $row['shipper_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Consignee:</label>
                                        <div class="col-lg-9">
                                            <select name="consignee" id="consignee" class="form-select">
                                                <option value='0'>Please select</option>
                                                <?php foreach ($consigneesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $consignee ? 'selected' : ''; ?>>
                                                        <?php echo $row['consignee_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Origin:</label>
                                        <div class="col-lg-9">
                                            <select required class="form-select" name="origin" id="origin">
                                                <option value="0">Please select</option>
                                                <option value="<?php echo UAE_COUNTRY_ID; ?>" <?php echo ($origin == UAE_COUNTRY_ID || $origin == 1) ? 'selected' : ''; ?>>
                                                    <?php echo UAE_COUNTRY_ALPHA3_CODE; ?> - <?php echo UAE_COUNTRY_NAME; ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Destination:</label>
                                        <div class="col-lg-9">
                                            <select required class="form-select" name="destination" id="destination">
                                                <option value="0">Please select</option>
                                                <option value="<?php echo UAE_COUNTRY_ID; ?>" <?php echo ($destination == UAE_COUNTRY_ID || $destination == 1) ? 'selected' : ''; ?>>
                                                    <?php echo UAE_COUNTRY_ALPHA3_CODE; ?> - <?php echo UAE_COUNTRY_NAME; ?>
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">No of Packs:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="no_of_packs" id="no_of_packs" value="<?php echo $no_of_packs; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Gross Weight:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="gross_weight" id="gross_weight" value="<?php echo $gross_weight; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Chargeable Weight:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="chargeable_weight" id="chargeable_weight" value="<?php echo $chargeable_weight; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Volume (CBM):</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="volume" id="volume" value="<?php echo $volume; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12">
                    <div class="row mb-2">
                        <div class="col-lg-2"><label class="form-label ms-3"><span class="text-danger">ITEM DETAILS*</span></label></div>
                        <div class="col-lg-3"><label class="form-label ms-4">DESCRIPTION</label></div>
                        <div class="col-lg-1"><label class="form-label ms-3">QUANTITY</label></div>
                        <div class="col-lg-1"><label class="form-label ms-4">RATE</label></div>
                        <div class="col-lg-1"><label class="form-label ms-3">SUBTOTAL</label></div>
                        <div class="col-lg-1"><label class="form-label ms-1">TAX</label></div>
                        <div class="col-lg-2"><label class="form-label ms-2"><span class="text-danger">TOTAL*</span></label></div>
                    </div>
                    <div class="card">
                        <div class="row card-body">
                            <div class="col-lg-12">
                                <?php for ($index = 0; $index < $total_rows; $index++): $itemRow = $index + 1; ?>
                                    <div class="mb-2">
                                        <div class="row mb-3 pb-3" id="row_<?php echo $itemRow; ?>">
                                            <div class="col-lg-12">
                                                <div class="row">
                                                    <input type="hidden" name="item_id[]" id="item_id<?php echo $itemRow; ?>" value="<?php echo !empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''; ?>">
                                                    <div class="col-lg-2">
                                                        <select class="form-select item-selector" name="service[]" id="service<?php echo $itemRow; ?>" data-item-id="<?php echo $itemRow; ?>">
                                                            <option value="0">Please select</option>
                                                            <?php foreach ($itemsList as $row): ?>
                                                                <option value="<?php echo $row['id']; ?>" <?php echo (!empty($service_arr[$index]) && (int)$service_arr[$index] === (int)$row['id']) ? 'selected' : ''; ?>>
                                                                    <?php echo $row['item_name']; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <textarea name="description[]" id="description<?php echo $itemRow; ?>" rows="2" class="form-control" placeholder="Add a description to your item"><?php echo !empty($description_arr[$index]) ? $description_arr[$index] : ''; ?></textarea>
                                                    </div>
                                                    <div class="col-lg-1">
                                                        <input type="number" step="1" name="qty[]" id="qty<?php echo $itemRow; ?>" min="0" class="form-control text-center calc-item" data-item-id="<?php echo $itemRow; ?>" value="<?php echo !empty($qty_arr[$index]) ? $qty_arr[$index] : '1'; ?>">
                                                    </div>
                                                    <div class="col-lg-1">
                                                        <input type="number" step="1" name="rate[]" id="rate<?php echo $itemRow; ?>" min="0" class="form-control text-center calc-item" data-item-id="<?php echo $itemRow; ?>" value="<?php echo !empty($rate_arr[$index]) ? $rate_arr[$index] : '0'; ?>">
                                                    </div>
                                                    <div class="col-lg-1">
                                                        <input readonly type="number" name="sub_total[]" id="sub_total<?php echo $itemRow; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo !empty($sub_total_arr[$index]) ? $sub_total_arr[$index] : '0'; ?>">
                                                    </div>
                                                    <div class="col-lg-1">
                                                        <select name="tax[]" id="tax<?php echo $itemRow; ?>" class="form-select calc-item" data-item-id="<?php echo $itemRow; ?>">
                                                            <?php for ($t = 0; $t <= 100; $t++): ?>
                                                                <option value="<?php echo $t; ?>" <?php echo (!empty($tax_arr[$index]) && (int)$tax_arr[$index] === $t) ? 'selected' : ''; ?>><?php echo $t; ?>%</option>
                                                            <?php endfor; ?>
                                                        </select>
                                                        <div class="text-center mt-1">
                                                            <span class="badge bg-light text-black" style="font-weight:normal;" id="div_tax_amount<?php echo $itemRow; ?>">
                                                                <span id="span_tax_amount<?php echo $itemRow; ?>"><?php echo !empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0'; ?></span>
                                                            </span>
                                                        </div>
                                                        <input type="hidden" name="tax_amount[]" id="tax_amount<?php echo $itemRow; ?>" value="<?php echo !empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0'; ?>">
                                                    </div>
                                                    <div class="col-lg-1">
                                                        <input readonly type="number" name="total[]" id="total<?php echo $itemRow; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end calc-grand" data-item-id="<?php echo $itemRow; ?>" value="<?php echo !empty($total_arr[$index]) ? $total_arr[$index] : ''; ?>">
                                                    </div>
                                                    <div class="col-lg-2 mt-1">
                                                        <?php if ($itemRow > 1): ?>
                                                            <a href="#" class="clear-row-item" data-item-id="<?php echo $itemRow; ?>"><span class="badge bg-warning"><i class="ph-x"></i></span></a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                                <div id="add_row_here"></div>
                                <div class="">
                                    <span id="span_add_item_row"><a href="#" class="add-item-row"><span class="badge bg-primary">Add New Row</span></a></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $total_rows; ?>">

                <div class="row">
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row mb-2">
                                            <label class="col-lg-4 col-form-label">Terms & Conditions:</label>
                                            <div class="col-lg-8">
                                                <textarea name="terms_and_conditions" id="terms_and_conditions" class="form-control"><?php echo $terms_and_conditions; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <label class="col-lg-4 col-form-label">Customer Notes:</label>
                                            <div class="col-lg-8">
                                                <textarea name="customer_notes" id="customer_notes" class="form-control"><?php echo $customer_notes; ?></textarea>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-lg-12">
                                                <div class="form-check form-switch mt-2">
                                                    <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" <?php echo $is_active == 1 ? 'checked="checked"' : ''; ?>>
                                                    <label class="form-check-label fw-semibold" for="publish">Active Status</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label text-end">Grand Subtotal:</label>
                                    <div class="col-lg-3">
                                        <input readonly type="text" name="grand_subtotal" id="grand_subtotal" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo $grand_subtotal; ?>">
                                    </div>
                                    <label class="col-lg-2 col-form-label text-end">Grand Discount:</label>
                                    <div class="col-lg-2">
                                        <select name="grand_discount_type" id="grand_discount_type" class="form-select">
                                            <option value="">Select</option>
                                            <option value="fixed" <?php echo $grand_discount_type === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                            <option value="percent" <?php echo $grand_discount_type === 'percent' ? 'selected' : ''; ?>>Percent</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-2">
                                        <input type="text" name="grand_discount_type_value" id="grand_discount_type_value" class="form-control calc-grand" value="<?php echo $grand_discount_type_value; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label text-end">Grand Discount Amount:</label>
                                    <div class="col-lg-3">
                                        <input readonly type="text" name="grand_discount_amount" id="grand_discount_amount" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo $grand_discount_amount; ?>">
                                    </div>
                                    <label class="col-lg-2 col-form-label text-end">After Discount:</label>
                                    <div class="col-lg-4">
                                        <input readonly type="text" name="grand_after_discount" id="grand_after_discount" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo $grand_after_discount; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label text-end">Grand Tax:</label>
                                    <div class="col-lg-3">
                                        <input readonly type="text" name="grand_tax" id="grand_tax" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo $grand_tax; ?>">
                                    </div>
                                    <label class="col-lg-2 col-form-label text-end"><span class="text-danger fw-bold">Grand Total:</span></label>
                                    <div class="col-lg-4">
                                        <input readonly type="text" name="grand_total" id="grand_total" class="form-control bg-light bg-opacity-75 text-end fw-bold text-danger" value="<?php echo $grand_total; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php include 'admin_elements/copyright.php'; ?>
    </div>
</div>

<script>
    function percentage(num, percentage) {
        const result = num * (percentage / 100);
        return parseFloat(result.toFixed(3));
    }

    function calculateItemAmount(row_no) {
        clearGrandDiscountTypeValue();
        let service = document.getElementById('service' + row_no);
        if (!service) return;
        let service_value = service.options[service.selectedIndex].value;
        if (service_value && service_value !== '0') {
            var qty = Number(document.getElementById('qty' + row_no).value);
            var rate = Number(document.getElementById('rate' + row_no).value);
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
            var total = document.getElementById('total' + i);
            if (total) final_total += Number(total.value);
        }
        document.getElementById('grand_subtotal').value = parseFloat(final_total.toFixed(2));
        var apply_discount = false;
        var grand_discount_type = document.getElementById('grand_discount_type').value;
        var grand_subtotal = parseFloat(document.getElementById('grand_subtotal').value);
        var grand_discount_type_value = document.getElementById('grand_discount_type_value').value;
        if (!grand_discount_type_value || grand_discount_type_value === 'undefined' || grand_discount_type_value === 'NULL') {
            grand_discount_type_value = '0';
        } else {
            grand_discount_type_value = parseFloat(grand_discount_type_value);
        }
        if (grand_discount_type === 'fixed') {
            if (grand_subtotal !== 0 && grand_discount_type_value <= grand_subtotal) {
                document.getElementById('grand_discount_amount').value = parseFloat(grand_discount_type_value);
                apply_discount = true;
            }
        } else if (grand_discount_type === 'percent') {
            if (grand_discount_type_value <= 100) {
                var percntVal = percentage(grand_subtotal, grand_discount_type_value);
                document.getElementById('grand_discount_amount').value = parseFloat(percntVal.toFixed(2));
                var grand_after_discount = parseFloat(grand_subtotal.toFixed(2)) - parseFloat(percntVal.toFixed(2));
                document.getElementById('grand_total').value = parseFloat(grand_after_discount.toFixed(2));
                apply_discount = true;
            }
        } else {
            document.getElementById('grand_discount_type_value').value = '';
            var grand_tax_val = parseFloat(document.getElementById('grand_tax').value || 0);
            document.getElementById('grand_total').value = parseFloat(grand_subtotal + grand_tax_val).toFixed(2);
        }
        if (apply_discount) {
            var grand_discount_amount = parseFloat(document.getElementById('grand_discount_amount').value || 0);
            final_total = parseFloat(final_total) - grand_discount_amount;
            document.getElementById('grand_after_discount').value = parseFloat(final_total.toFixed(2));
        }
        var total_tax = 0;
        for (var i = 1; i <= total_rows; i++) {
            var tax_amount = document.getElementById('tax_amount' + i);
            if (tax_amount) total_tax += Number(tax_amount.value);
        }
        document.getElementById('grand_tax').value = parseFloat(total_tax.toFixed(2));
        var grand_subtotal_final = Number(final_total);
        var grand_total_final = parseFloat(grand_subtotal_final) + parseFloat(total_tax);
        document.getElementById('grand_total').value = parseFloat(grand_total_final.toFixed(2));
    }

    function clearGrandDiscountTypeValue() {
        document.getElementById('grand_discount_type_value').value = '';
        document.getElementById('grand_discount_amount').value = '';
        document.getElementById('grand_after_discount').value = '';
    }

    function add_item_row() {
        var total_rows = document.getElementById('total_rows').value;
        total_rows++;
        var new_row = '<div class="row mb-3 pb-3" id="row_' + total_rows + '">';
        new_row += '<input type="hidden" name="item_id[]" id="item_id' + total_rows + '">';
        new_row += '<div class="col-lg-2"><select class="form-select item-selector" data-item-id="' + total_rows + '" name="service[]" id="service' + total_rows + '"><option value="0">Please select</option></select></div>';
        new_row += '<div class="col-lg-3"><textarea name="description[]" id="description' + total_rows + '" rows="2" class="form-control" placeholder="Add a description to your item"></textarea></div>';
        new_row += '<div class="col-lg-1"><input type="number" step="1" name="qty[]" id="qty' + total_rows + '" min="1" class="form-control text-center calc-item" data-item-id="' + total_rows + '" placeholder="1"></div>';
        new_row += '<div class="col-lg-1"><input type="number" step="1" name="rate[]" id="rate' + total_rows + '" min="0" class="form-control text-center calc-item" data-item-id="' + total_rows + '" placeholder="0"></div>';
        new_row += '<div class="col-lg-1"><input readonly type="number" name="sub_total[]" id="sub_total' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end" value="0"></div>';
        new_row += '<div class="col-lg-1"><select name="tax[]" id="tax' + total_rows + '" class="form-select calc-item" data-item-id="' + total_rows + '">';
        for (var t = 0; t <= 100; t++) {
            new_row += '<option value="' + t + '">' + t + '%</option>';
        }
        new_row += '</select><div class="text-center mt-1"><span class="badge bg-light text-black" style="font-weight:normal;" id="div_tax_amount' + total_rows + '"><span id="span_tax_amount' + total_rows + '">0</span></span></div><input type="hidden" name="tax_amount[]" id="tax_amount' + total_rows + '" value="0"></div>';
        new_row += '<div class="col-lg-1"><input readonly type="number" name="total[]" id="total' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end calc-grand" data-item-id="' + total_rows + '" value=""></div>';
        new_row += '<div class="col-lg-2 mt-1"><a href="#" class="clear-row-item" data-item-id="' + total_rows + '"><span class="badge bg-warning"><i class="ph-x"></i></span></a></div>';
        new_row += '</div></div></div>';
        document.getElementById('add_row_here').insertAdjacentHTML('beforebegin', new_row);
        document.getElementById('total_rows').value = total_rows;
    }

    $(document).ready(function() {
        $(document).on('change', '.calc-item', function() {
            var row_no = $(this).data('item-id');
            calculateItemAmount(row_no);
        });
        $(document).on('change', '.calc-grand', function() {
            calculateGrand();
        });
        $(document).on('change', '#grand_discount_type', function() {
            calculateGrand();
        });
        $(document).on('click', '.add-item-row', function(e) {
            e.preventDefault();
            add_item_row();
            loadItemSelectOptions();
        });
        $(document).on('click', '.clear-row-item', function(e) {
            e.preventDefault();
            var row = $(this).data('item-id');
            $('#row_' + row).remove();
            calculateGrand();
        });
        $(document).on('click', '.submit-form', function(e) {
            e.preventDefault();
            let form = document.getElementById('frminvoices');
            let action = document.getElementById('action');
            if (!action) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'action';
                inp.id = 'action';
                inp.value = 'update_invoices';
                form.appendChild(inp);
            }
            form.submit();
        });
        $(document).on('click', '.save-draft-invoice', function(e) {
            e.preventDefault();
            var form = document.getElementById('frminvoices');
            form.submit();
        });
        $(document).on('click', '.save-and-send-invoice', function(e) {
            e.preventDefault();
            document.getElementById('save_and_send').value = '1';
            document.getElementById('frminvoices').submit();
        });
        $(document).on('change', '.item-selector', function() {
            var item_id = $(this).val();
            var row_no = $(this).data('item-id');
            if (item_id && item_id !== '0') {
                // Populate description from selected item
            }
            calculateItemAmount(row_no);
        });
    });

    function loadItemSelectOptions() {
        var total_rows = document.getElementById('total_rows').value;
        <?php foreach ($itemsList as $row): ?>
            for (var i = 1; i <= total_rows; i++) {
                var sel = document.getElementById('service' + i);
                if (sel && !sel.querySelector('option[value="<?php echo $row['id']; ?>"]')) {
                    var opt = document.createElement('option');
                    opt.value = '<?php echo $row['id']; ?>';
                    opt.text = '<?php echo addslashes($row['item_name']); ?>';
                    sel.appendChild(opt);
                }
            }
        <?php endforeach; ?>
    }
</script>
<?php
include 'admin_elements/admin_footer.php';
