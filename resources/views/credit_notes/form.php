<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $credit_note_no
 * @var string $credit_note_date
 * @var string $credit_note_status
 * @var string $reference_no
 * @var string $customer_id
 * @var string $invoice_id
 * @var string $warehouse_id
 * @var string $subject
 * @var string $payment_term
 * @var string $expiry_date
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
 * @var string $customer_notes
 * @var string $terms_and_conditions
 * @var string $grand_subtotal
 * @var string $grand_discount_type
 * @var string $grand_discount_type_value
 * @var string $grand_discount_amount
 * @var string $grand_after_discount
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
 * @var array $warehousesList
 * @var array $itemsList
 * @var array $paymentTermsList
 * @var array $shippersList
 * @var array $consigneesList
 * @var array $countriesList
 * @var array $portsList
 * @var array $invoiceList
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
                    <span class="badge bg-success bg-opacity-10 text-success ms-2">Credit Note #: <?php echo $credit_note_no; ?></span>
                <?php endif; ?>
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?php echo !empty($credit_note_status) ? ucwords($credit_note_status) : ''; ?></span>
            </div>
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if ($canCreate): ?>
                    <?php if ($id > 0): ?>
                        <button type="button" form="frm<?php echo $module; ?>" class="submit-form btn btn-primary btn-sm">Save</button>
                    <?php else: ?>
                        <button type="button" form="frm<?php echo $module; ?>" class="save-draft-btn btn btn-primary btn-sm">Save as Draft</button>
                    <?php endif; ?>
                    <button type="button" form="frm<?php echo $module; ?>" class="save-and-send-btn btn btn-info btn-sm">Save and Send</button>
                <?php endif; ?>
                <?php if ($id > 0): ?>
                    <a href="credit_note_overview.php?credit_note_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
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
                <strong>How this works:</strong> Credit notes reduce a customer's invoice amount or correct an overcharge. They decrease your revenue and reduce the receivable. Affects: Profit &amp; Loss, Customer Aging.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="credit_note_status" id="credit_note_status" value="<?php echo $credit_note_status; ?>" />
                <input type="hidden" name="save_and_send" id="save_and_send" value="" />
                <input type="hidden" name="invoice_id" id="invoice_id" value="<?php echo $invoice_id; ?>" />
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
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Credit Note Date:*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Credit Note Date" name="credit_note_date" id="credit_note_date" value="<?php echo $credit_note_date; ?>">
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
                                        <label class="col-lg-3 col-form-label">Subject:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="subject" id="subject" value="<?php echo $subject; ?>">
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
                                        <label class="col-lg-3 col-form-label">Expected Shipment Date:</label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Expected Shipment Date" name="expected_shipment_date" id="expected_shipment_date" value="<?php echo $expected_shipment_date; ?>">
                                                <div class="form-control-feedback-icon"><i class="ph-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Payment Terms:</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="payment_term" id="payment_term">
                                                <option value='0'></option>
                                                <?php foreach ($paymentTermsList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $payment_term ? 'selected' : ''; ?>>
                                                        <?php echo $row['payment_term']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
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
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Warehouse:*</span></label>
                                        <div class="col-lg-9">
                                            <select name="warehouse_id" id="warehouse_id" class="form-select">
                                                <?php foreach ($warehousesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $warehouse_id ? 'selected' : ''; ?>>
                                                        <?php echo $row['warehouse_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Sales Person:</label>
                                        <div class="col-lg-9">
                                            <select name="sales_person" id="sales_person" class="form-select">
                                                <option value='0'>Please select</option>
                                                <?php foreach ($warehousesList as $row): ?>
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
                                            <select class="form-select" name="origin" id="origin">
                                                <option value="0">Please select</option>
                                                <?php foreach ($countriesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $origin ? 'selected' : ''; ?>>
                                                        <?php echo $row['abbr']; ?> - <?php echo $row['country']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Destination:</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="destination" id="destination">
                                                <option value="0">Please select</option>
                                                <?php foreach ($countriesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $destination ? 'selected' : ''; ?>>
                                                        <?php echo $row['abbr']; ?> - <?php echo $row['country']; ?>
                                                    </option>
                                                <?php endforeach; ?>
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
                    <div class="card">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><span class="text-danger">ITEM DETAILS*</span></h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-lg-2"><label class="form-label">SERVICE</label></div>
                                <div class="col-lg-3"><label class="form-label">DESCRIPTION</label></div>
                                <div class="col-lg-1"><label class="form-label">QTY</label></div>
                                <div class="col-lg-1"><label class="form-label">RATE</label></div>
                                <div class="col-lg-1"><label class="form-label">SUBTOTAL</label></div>
                                <div class="col-lg-1"><label class="form-label">TAX</label></div>
                                <div class="col-lg-1"><label class="form-label">TOTAL</label></div>
                                <div class="col-lg-2"></div>
                            </div>

                            <?php for ($index = 0; $index < $total_rows; $index++): $itemRow = $index + 1; ?>
                                <div class="row mb-3 pb-3" id="row_<?php echo $itemRow; ?>">
                                    <input type="hidden" name="item_id[]" id="item_id<?php echo $itemRow; ?>" value="<?php echo !empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''; ?>">

                                    <div class="col-lg-2">
                                        <select class="form-select" name="service[]" id="service<?php echo $itemRow; ?>" onchange="ajax_populate_item_rate(this.value, <?php echo $itemRow; ?>);">
                                            <option value="0">Please select</option>
                                            <?php foreach ($itemsList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo (!empty($service_arr[$index]) && (int)$service_arr[$index] === (int)$row['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($row['item_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-lg-3">
                                        <textarea name="description[]" id="description<?php echo $itemRow; ?>" rows="2" class="form-control" placeholder="Add a description to your item"><?php echo !empty($description_arr[$index]) ? htmlspecialchars($description_arr[$index]) : ''; ?></textarea>
                                    </div>

                                    <div class="col-lg-1">
                                        <input type="number" step="1" name="qty[]" id="qty<?php echo $itemRow; ?>" min="1" class="form-control text-center" onkeyup="calculateItemAmount('<?php echo $itemRow; ?>');" onchange="calculateItemAmount('<?php echo $itemRow; ?>');" value="<?php echo !empty($qty_arr[$index]) ? $qty_arr[$index] : '1'; ?>">
                                    </div>

                                    <div class="col-lg-1">
                                        <input type="number" step="any" name="rate[]" id="rate<?php echo $itemRow; ?>" min="0" class="form-control text-center" value="<?php echo !empty($rate_arr[$index]) ? $rate_arr[$index] : '0'; ?>">
                                    </div>

                                    <div class="col-lg-1">
                                        <input readonly type="number" name="sub_total[]" id="sub_total<?php echo $itemRow; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo !empty($sub_total_arr[$index]) ? $sub_total_arr[$index] : '0'; ?>">
                                    </div>

                                    <div class="col-lg-1">
                                        <select name="tax[]" id="tax<?php echo $itemRow; ?>" class="form-select" onchange="calculateItemAmount(<?php echo $itemRow; ?>, this.value);">
                                            <?php for ($t = 0; $t <= 100; $t++): ?>
                                                <option value="<?php echo $t; ?>" <?php echo (!empty($tax_arr[$index]) && (int)$tax_arr[$index] === $t) ? 'selected' : ''; ?>><?php echo $t; ?>%</option>
                                            <?php endfor; ?>
                                        </select>
                                        <div class="text-center mt-1">
                                            <span class="badge bg-light text-black" style="font-weight:normal;" id="div_tax_amount<?php echo $itemRow; ?>">
                                            </span>
                                        </div>
                                        <input type="hidden" name="tax_amount[]" id="tax_amount<?php echo $itemRow; ?>" value="<?php echo !empty($tax_amount_arr[$index]) ? $tax_amount_arr[$index] : '0'; ?>">
                                    </div>

                                    <div class="col-lg-1">
                                        <input readonly type="number" name="total[]" id="total<?php echo $itemRow; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo !empty($total_arr[$index]) ? $total_arr[$index] : ''; ?>">
                                    </div>

                                    <div class="col-lg-2 mt-1">
                                        <a href="#" onclick="clear_row(<?php echo $itemRow; ?>)"><span class="badge bg-warning"><i class="ph-x"></i></span></a>
                                    </div>
                                </div>
                            <?php endfor; ?>

                            <div id="add_row_here"></div>

                            <div>
                                <span id="span_add_item_row">
                                    <a href="#" onclick="add_item_row(); return false;">
                                        <span class="badge bg-primary">Add New Row</span>
                                    </a>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $total_rows; ?>">

                <div class="row">
                    <div class="col-lg-4">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="ms-sm-3 mb-3 mb-sm-0">
                                    <label class="col-lg-6 col-form-label">Customer Notes:</label>
                                    <textarea class="form-control" name="customer_notes" id="customer_notes" style="field-sizing: content;" placeholder=""><?php echo htmlspecialchars($customer_notes); ?></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="ms-sm-3 mb-3 mb-sm-0">
                                    <label class="col-lg-6 col-form-label">Terms & Conditions:</label>
                                    <textarea class="form-control text-wrap" name="terms_and_conditions" id="terms_and_conditions" style="field-sizing: content;" placeholder=""><?php echo htmlspecialchars($terms_and_conditions); ?></textarea>
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
                                        <select name="grand_discount_type" id="grand_discount_type" class="form-select" onchange="clearGrandDiscountTypeValue(); calculateGrand();">
                                            <option value="0"></option>
                                            <option value="percent" <?php echo $grand_discount_type === 'percent' ? 'selected' : ''; ?>>Percent %</option>
                                            <option value="fixed" <?php echo $grand_discount_type === 'fixed' ? 'selected' : ''; ?>>Fixed</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3">
                                        <input type="number" min="0" step="any" class="form-control" name="grand_discount_type_value" id="grand_discount_type_value" value="<?php echo $grand_discount_type_value; ?>" placeholder="0" onkeyup="calculateGrand();" onchange="calculateGrand();">
                                    </div>
                                </div>

                                <div class="row mb-1">
                                    <label class="col-lg-6 col-form-label">Discount Amount:</label>
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
                                    <label class="col-lg-6 col-form-label">Total Tax Amount:</label>
                                    <div class="col-lg-6">
                                        <div class="input-group">
                                            <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                            <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_tax" id="grand_tax" value="<?php echo $grand_tax; ?>" placeholder="0">
                                        </div>
                                    </div>
                                </div>

                                <div class="row mb-1">
                                    <label class="col-lg-6 col-form-label fw-semibold">Grand Total:</label>
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
        if (service_value != NaN && service_value != '' && service_value != 'undefined' && service_value != '0') {
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
        new_row += '<div class="col-lg-2"><select class="form-select" name="service[]" id="service' + total_rows + '" onchange="ajax_populate_item_rate(this.value, ' + total_rows + ');"><option value="0">Please select</option></select></div>';
        new_row += '<div class="col-lg-3"><textarea name="description[]" id="description' + total_rows + '" rows="2" class="form-control" placeholder="Add a description to your item"></textarea></div>';
        new_row += '<div class="col-lg-1"><input type="number" step="1" name="qty[]" id="qty' + total_rows + '" min="1" class="form-control text-center" onkeyup="calculateItemAmount(\'' + total_rows + '\');" onchange="calculateItemAmount(\'' + total_rows + '\');" placeholder="1"></div>';
        new_row += '<div class="col-lg-1"><input type="number" step="any" name="rate[]" id="rate' + total_rows + '" min="0" class="form-control text-center" placeholder="0"></div>';
        new_row += '<div class="col-lg-1"><input readonly type="number" name="sub_total[]" id="sub_total' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end" value="0"></div>';
        new_row += '<div class="col-lg-1"><select name="tax[]" id="tax' + total_rows + '" class="form-select" onchange="calculateItemAmount(' + total_rows + ', this.value);">';
        for (var t = 0; t <= 100; t++) { new_row += '<option value="' + t + '">' + t + '%</option>'; }
        new_row += '</select><div class="text-center mt-1"><span class="badge bg-light text-black" style="font-weight:normal;" id="div_tax_amount' + total_rows + '"></span></div><input type="hidden" name="tax_amount[]" id="tax_amount' + total_rows + '" value="0"></div>';
        new_row += '<div class="col-lg-1"><input readonly type="number" name="total[]" id="total' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end" value=""></div>';
        new_row += '<div class="col-lg-2 mt-1"><a href="#" onclick="clear_row(' + total_rows + ')"><span class="badge bg-warning"><i class="ph-x"></i></span></a></div>';
        new_row += '</div>';
        document.getElementById('add_row_here').insertAdjacentHTML('beforebegin', new_row);
        document.getElementById('total_rows').value = total_rows;
        ajax_populate_services();
    }

    function clear_row(row_no) {
        calculateItemAmount(row_no);
        document.getElementById('service' + row_no).value = '0';
        document.getElementById('description' + row_no).value = '';
        document.getElementById('qty' + row_no).value = '';
        document.getElementById('rate' + row_no).value = '';
        document.getElementById('sub_total' + row_no).value = '';
        document.getElementById('tax' + row_no).value = '';
        document.getElementById('tax_amount' + row_no).value = '';
        document.getElementById('total' + row_no).value = '';
        document.getElementById('row_' + row_no).style.display = 'none';
        calculateGrand();
    }

    $(document).ready(function() {
        $(document).on('change', '#grand_discount_type', function() {
            clearGrandDiscountTypeValue();
            calculateGrand();
        });
        $(document).on('click', '.submit-form', function(e) {
            e.preventDefault();
            let form = document.getElementById('frm<?php echo $module; ?>');
            let action = document.getElementById('action');
            if (!action) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'action';
                inp.id = 'action';
                inp.value = 'update_<?php echo $module; ?>';
                form.appendChild(inp);
            }
            form.submit();
        });
        $(document).on('click', '.save-draft-btn', function(e) {
            e.preventDefault();
            var form = document.getElementById('frm<?php echo $module; ?>');
            form.submit();
        });
        $(document).on('click', '.save-and-send-btn', function(e) {
            e.preventDefault();
            document.getElementById('save_and_send').value = '1';
            document.getElementById('frm<?php echo $module; ?>').submit();
        });

        <?php if (!empty($tax_amount_arr)): ?>
            <?php for ($i = 0; $i < count($tax_amount_arr); $i++): ?>
                <?php $itemRow = $i + 1; ?>
                (function() {
                    var tax<?php echo $itemRow; ?> = document.getElementById('tax<?php echo $itemRow; ?>');
                    if (tax<?php echo $itemRow; ?> && tax<?php echo $itemRow; ?>.value > 0 && document.getElementById('tax_amount<?php echo $itemRow; ?>').value > 0) {
                        document.getElementById('div_tax_amount<?php echo $itemRow; ?>').style.display = 'block';
                        document.getElementById('div_tax_amount<?php echo $itemRow; ?>').innerHTML = 'Tax ' + parseFloat(document.getElementById('tax_amount<?php echo $itemRow; ?>').value);
                    }
                })();
            <?php endfor; ?>
        <?php endif; ?>
    });
</script>
<?php
include 'admin_elements/admin_footer.php';
