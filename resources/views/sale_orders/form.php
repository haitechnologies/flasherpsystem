<?php

declare(strict_types=1);
global $mysqli;

/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $customer_id
 * @var string $sale_order_no
 * @var string $sale_order_status
 * @var string $sale_order_date
 * @var string $expiry_date
 * @var string $reference_no
 * @var string $warehouse_id
 * @var string $expected_shipment_date
 * @var string $payment_term
 * @var string $shipment_type
 * @var string $sales_person
 * @var string $job_reference_no
 * @var string $master_awb_no
 * @var string $hwb_hbol
 * @var string $shipper
 * @var string $consignee
 * @var string $origin
 * @var string $origin_country
 * @var string $destination
 * @var string $destination_country
 * @var string $no_of_packs
 * @var string $gross_weight
 * @var string $chargeable_weight
 * @var string $volume
 * @var string $cbm
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
 * @var array $paymentTermsList
 * @var array $countriesList
 * @var array $portsList
 * @var array $servicesList
 * @var bool $canCreate
 * @var bool $canEdit
 * @var string $error_message
 */
include 'admin_elements/admin_header.php';

$customersList = $customersList ?? [];
$orgList = $orgList ?? [];
$shippersList = $shippersList ?? [];
$consigneesList = $consigneesList ?? [];
$itemsList = $itemsList ?? [];
$paymentTermsList = $paymentTermsList ?? [];
$countriesList = $countriesList ?? [];
$portsList = $portsList ?? [];
$servicesList = $servicesList ?? [];
?>
<div class="content-wrapper">

    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success ms-2">Sale Order #: <?php echo $sale_order_no; ?></span>
                <?php endif; ?>
                <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?php echo !empty($sale_order_status) ? ucwords($sale_order_status) : ''; ?></span>
            </div>
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if ($canCreate): ?>
                    <?php if ($id > 0): ?>
                        <?php if ($canEdit): ?>
                            <button type="button" form="frm<?php echo $module; ?>" class="submit-form btn btn-primary btn-sm">Save</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button type="button" form="frm<?php echo $module; ?>" class="save-draft-sale-order btn btn-primary btn-sm">Save as Draft</button>
                    <?php endif; ?>
                    <button type="button" form="frm<?php echo $module; ?>" class="save-and-send-sale-order btn btn-info btn-sm">Save and Send</button>
                <?php endif; ?>
                <?php if ($id > 0): ?>
                    <a href="sale_order_overview.php?sale_order_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
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
                <strong>How this works:</strong> Sale orders confirm a customer's request for goods or services. They do not affect accounting directly until converted to an Invoice.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="sale_order_status" id="sale_order_status" value="<?php echo $sale_order_status; ?>" />
                <input type="hidden" name="save_and_send" id="save_and_send" value="" />
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">

                            <div class="card">
                                <div class="card-body">

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Customer Name:*</span></label>
                                        <div class="col-lg-9">
                                            <select name="customer_id" id="customer_id" class="form-select" onchange="toggleSaleOrderPartySelectors()">
                                                <option value="0">Please select</option>
                                                <?php foreach ($customersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $customer_id ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['display_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Sale Order Date:*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Sale Order Date" name="sale_order_date" id="sale_order_date" value="<?php echo $sale_order_date; ?>">
                                                <div class="form-control-feedback-icon"><i class="ph-calendar"></i></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Job Reference no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="job_reference_no" id="job_reference_no" value="<?php echo htmlspecialchars($job_reference_no); ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Reference no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="reference_no" id="reference_no" value="<?php echo htmlspecialchars($reference_no); ?>">
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
                                            <select name="payment_term" id="payment_term" class="form-select">
                                                <option value="0">Please select</option>
                                                <?php foreach ($paymentTermsList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $payment_term ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['payment_term'] ?? $row['title'] ?? $row['name'] ?? ''); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Delivery Method:</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="shipment_type" id="shipment_type">
                                                <option value="0">Please select</option>
                                                <option value="export" <?php echo $shipment_type === 'export' ? 'selected' : ''; ?>>Export</option>
                                                <option value="import" <?php echo $shipment_type === 'import' ? 'selected' : ''; ?>>Import</option>
                                                <option value="transit_in" <?php echo $shipment_type === 'transit_in' ? 'selected' : ''; ?>>Transit In</option>
                                                <option value="transit_out" <?php echo $shipment_type === 'transit_out' ? 'selected' : ''; ?>>Transit Out</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Sales Person:</label>
                                        <div class="col-lg-9">
                                            <select name="sales_person" id="sales_person" class="form-select">
                                                <option value="0">Please select</option>
                                                <?php foreach ($orgList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $sales_person ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['warehouse_name']); ?>
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
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Warehouse:*</span></label>
                                        <div class="col-lg-9">
                                            <select name="warehouse_id" id="warehouse_id" class="form-select">
                                                <?php foreach ($orgList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo ($warehouse_id !== '0' && (string)$row['id'] === $warehouse_id) || ($warehouse_id === '0' && trim($row['warehouse_name']) === 'Flash Logistics FZCO') ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['warehouse_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">MAWB/BOL:</label>
                                        <div class="col-lg-3">
                                            <input type="text" class="form-control" name="master_awb_no" id="master_awb_no" value="<?php echo htmlspecialchars($master_awb_no); ?>">
                                        </div>
                                        <label class="col-lg-1 col-form-label text-end">HWB/HBOL:</label>
                                        <div class="col-lg-5">
                                            <input type="text" class="form-control" name="hwb_hbol" id="hwb_hbol" value="<?php echo htmlspecialchars($hwb_hbol); ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Shipper:</label>
                                        <div class="col-lg-7">
                                            <select name="shipper_id" id="shipper_id" class="form-select">
                                                <option value="0">Please select</option>
                                                <?php foreach ($shippersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $shipper ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['shipper_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-lg-2">
                                            <a href="#" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#shipperModal">
                                                <i class="ph-plus"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Consignee:</label>
                                        <div class="col-lg-7">
                                            <select name="consignee_id" id="consignee_id" class="form-select">
                                                <option value="0">Please select</option>
                                                <?php foreach ($consigneesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $consignee ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['consignee_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-lg-2">
                                            <a href="#" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#consigneeModal">
                                                <i class="ph-plus"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Origin Port:</label>
                                        <div class="col-lg-3">
                                            <select name="origin_port" id="origin_port" class="form-select select2-port-ajax">
                                                <option value="0">Please select</option>
                                                <?php foreach ($portsList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $origin ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['port_name']); ?> <?php echo !empty($row['port_code']) ? '(' . htmlspecialchars($row['port_code']) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <label class="col-lg-2 col-form-label text-end">Country:</label>
                                        <div class="col-lg-4">
                                            <select name="origin_country" id="origin_country" class="form-select" onchange="onCountryChange('origin_port','origin_country');">
                                                <option value="0">Please select</option>
                                                <?php foreach ($countriesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $origin_country ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['country']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Destination Port:</label>
                                        <div class="col-lg-3">
                                            <select name="destination_port" id="destination_port" class="form-select select2-port-ajax">
                                                <option value="0">Please select</option>
                                                <?php foreach ($portsList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $destination ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['port_name']); ?> <?php echo !empty($row['port_code']) ? '(' . htmlspecialchars($row['port_code']) . ')' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <label class="col-lg-2 col-form-label text-end">Country:</label>
                                        <div class="col-lg-4">
                                            <select name="destination_country" id="destination_country" class="form-select" onchange="onCountryChange('destination_port','destination_country');">
                                                <option value="0">Please select</option>
                                                <?php foreach ($countriesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $destination_country ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($row['country']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="border-top pt-3 mt-2 mb-2">
                                        <div class="row mb-2">
                                            <label class="col-lg-3 col-form-label">Dimensions:</label>
                                            <div class="col-lg-9">
                                                <a href="#" class="badge bg-primary bg-opacity-10 text-primary text-decoration-none p-2" data-bs-toggle="modal" data-bs-target="#dimensionModal">
                                                    <i class="ph-cube me-1"></i>Dimensions
                                                </a>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Gross Weight:</label>
                                        <div class="col-lg-3">
                                            <input type="number" step="any" class="form-control" name="gross_weight" id="gross_weight" value="<?php echo $gross_weight; ?>" onkeyup="calculateChargeableWeight();" onchange="calculateChargeableWeight();">
                                        </div>
                                        <label class="col-lg-2 col-form-label text-end">Volume:</label>
                                        <div class="col-lg-4">
                                            <input readonly type="number" step="any" class="form-control bg-light bg-opacity-75" name="volume" id="volume" value="<?php echo $volume; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Chargeable Weight:</label>
                                        <div class="col-lg-3">
                                            <input readonly type="number" step="any" class="form-control bg-light bg-opacity-75" name="chargeable_weight" id="chargeable_weight" value="<?php echo $chargeable_weight; ?>">
                                        </div>
                                        <label class="col-lg-1 col-form-label text-end">CBM:</label>
                                        <div class="col-lg-5">
                                            <input readonly type="number" step="any" class="form-control bg-light bg-opacity-75" name="cbm" id="cbm" value="<?php echo $cbm; ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">No of Packs:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" name="no_of_packs" id="no_of_packs" value="<?php echo $no_of_packs; ?>">
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

            <?php include('admin_elements/copyright.php'); ?>
        </div>
    </div>
</div>

<!-- Shipper Modal -->
<div class="modal fade" id="shipperModal" tabindex="-1" aria-labelledby="shipperModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shipperModalLabel">Add New Shipper</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ajax_shipper_error_message" class="alert alert-danger" style="display:none;"></div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Shipper Name: <span class="text-danger">*</span></label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_name" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Address Line 1:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_address_line1" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Address Line 2:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_address_line2" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">City:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_city" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Zip Code:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_zipcode" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Province:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_province" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Country:</label>
                    <div class="col-lg-9">
                        <select class="form-select" id="shipper_country">
                            <option value="0">Please select</option>
                            <?php foreach ($countriesList as $row): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['country']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Email:</label>
                    <div class="col-lg-9"><input type="email" class="form-control" id="shipper_email" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Telephone:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_telephone" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Mobile:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_mobile" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Fax:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="shipper_fax" /></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="create_shipper()">Save Shipper</button>
            </div>
        </div>
    </div>
</div>

<!-- Consignee Modal -->
<div class="modal fade" id="consigneeModal" tabindex="-1" aria-labelledby="consigneeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="consigneeModalLabel">Add New Consignee</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ajax_consignee_error_message" class="alert alert-danger" style="display:none;"></div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Consignee Name: <span class="text-danger">*</span></label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_name" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Address Line 1:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_address_line1" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Address Line 2:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_address_line2" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">City:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_city" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Zip Code:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_zipcode" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Province:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_province" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Country:</label>
                    <div class="col-lg-9">
                        <select class="form-select" id="consignee_country">
                            <option value="0">Please select</option>
                            <?php foreach ($countriesList as $row): ?>
                                <option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['country']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Email:</label>
                    <div class="col-lg-9"><input type="email" class="form-control" id="consignee_email" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Telephone:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_telephone" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Mobile:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_mobile" /></div>
                </div>
                <div class="row mb-2">
                    <label class="col-lg-3 col-form-label">Fax:</label>
                    <div class="col-lg-9"><input type="text" class="form-control" id="consignee_fax" /></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="create_consignee()">Save Consignee</button>
            </div>
        </div>
    </div>
</div>

<!-- Dimension Modal -->
<div class="modal fade" id="dimensionModal" tabindex="-1" aria-labelledby="dimensionModalLabel" aria-hidden="true">
    <div class="modal-dialog" style="max-width:1200px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="dimensionModalLabel">Dimension Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table" id="dim_table">
                        <thead>
                            <tr>
                                <th width="80">PCS</th>
                                <th width="80">Units</th>
                                <th width="80">Length (cm)</th>
                                <th width="80">Width (cm)</th>
                                <th width="80">Height (cm)</th>
                                <th width="80">Formula</th>
                                <th width="80">CBM</th>
                                <th width="80">Volume (kg)</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="dimension_table_body">
                            <tr id="dim_row_1">
                                <td>
                                    <input type="hidden" id="dim_item_id_1" value="">
                                    <input type="number" class="form-control form-control-sm text-center dim-calc" id="dim_pcs_1" value="1" min="1" onchange="calculateDim(1)" onkeyup="calculateDim(1)">
                                </td>
                                <td>
                                    <select class="form-select form-select-sm dim-calc" id="dim_units_1" onchange="calculateDim(1)">
                                        <option value="cm">cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </td>
                                <td><input type="number" class="form-control form-control-sm dim-calc" id="dim_length_1" value="0" step="any" onchange="calculateDim(1)" onkeyup="calculateDim(1)"></td>
                                <td><input type="number" class="form-control form-control-sm dim-calc" id="dim_width_1" value="0" step="any" onchange="calculateDim(1)" onkeyup="calculateDim(1)"></td>
                                <td><input type="number" class="form-control form-control-sm dim-calc" id="dim_height_1" value="0" step="any" onchange="calculateDim(1)" onkeyup="calculateDim(1)"></td>
                                <td>
                                    <select class="form-select form-select-sm dim-calc" id="dim_formula_1" onchange="calculateDim(1)">
                                        <option value="6000">6000</option>
                                        <option value="5000">5000</option>
                                    </select>
                                </td>
                                <td><input readonly type="text" class="form-control form-control-sm bg-light text-end" id="dim_cbm_1" value="0"></td>
                                <td><input readonly type="text" class="form-control form-control-sm bg-light text-end" id="dim_volume_1" value="0"></td>
                                <td><a href="#" onclick="clear_dim_row(1); return false;"><span class="badge bg-warning"><i class="ph-x"></i></span></a></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end fw-semibold">Total:</td>
                                <td><input readonly type="text" class="form-control form-control-sm bg-light text-end fw-bold" id="dim_grand_cbm" value="0"></td>
                                <td><input readonly type="text" class="form-control form-control-sm bg-light text-end fw-bold" id="dim_grand_volume" value="0"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <input type="hidden" id="dim_total_rows" value="1">
                <div>
                    <a href="#" onclick="add_dim_item_row(); return false;"><span class="badge bg-primary">Add New Dimension</span></a>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="saveDimensions()">Save Dimensions</button>
            </div>
        </div>
    </div>
</div>

<script>
    function percentage(num, percentage) {
        const result = num * (percentage / 100);
        return parseFloat(result.toFixed(3));
    }

    function toggleSaleOrderPartySelectors() {
        const customerSelect = document.getElementById('customer_id');
        if (!customerSelect) return;
        customerSelect.disabled = false;
    }

    function calculateChargeableWeight() {
        var volume = parseFloat(document.getElementById('volume').value) || 0;
        var gross_weight = parseFloat(document.getElementById('gross_weight').value) || 0;
        document.getElementById('chargeable_weight').value = Math.max(volume, gross_weight);
    }

    function calculateDim(row_no) {
        var pcs = parseFloat(document.getElementById('dim_pcs_' + row_no).value) || 0;
        var length = parseFloat(document.getElementById('dim_length_' + row_no).value) || 0;
        var width = parseFloat(document.getElementById('dim_width_' + row_no).value) || 0;
        var height = parseFloat(document.getElementById('dim_height_' + row_no).value) || 0;
        var units = document.getElementById('dim_units_' + row_no).value;
        var formula = parseInt(document.getElementById('dim_formula_' + row_no).value) || 6000;

        if (units === 'inch') {
            length = length * 2.54;
            width = width * 2.54;
            height = height * 2.54;
        }

        var cbm = ((length * width * height) / 1000000) * pcs;
        var volume = ((length * width * height) / formula) * pcs;

        document.getElementById('dim_cbm_' + row_no).value = parseFloat(cbm.toFixed(4));
        document.getElementById('dim_volume_' + row_no).value = parseFloat(volume.toFixed(2));

        calculateDimGrand();
    }

    function calculateDimGrand() {
        var total_rows = parseInt(document.getElementById('dim_total_rows').value) || 1;
        var grand_cbm = 0, grand_volume = 0;
        for (var i = 1; i <= total_rows; i++) {
            var row = document.getElementById('dim_row_' + i);
            if (!row || row.style.display === 'none') continue;
            grand_cbm += parseFloat(document.getElementById('dim_cbm_' + i).value) || 0;
            grand_volume += parseFloat(document.getElementById('dim_volume_' + i).value) || 0;
        }
        document.getElementById('dim_grand_cbm').value = parseFloat(grand_cbm.toFixed(4));
        document.getElementById('dim_grand_volume').value = parseFloat(grand_volume.toFixed(2));
    }

    function add_dim_item_row() {
        var total_rows = parseInt(document.getElementById('dim_total_rows').value) + 1;
        document.getElementById('dim_total_rows').value = total_rows;

        var new_row = '<tr id="dim_row_' + total_rows + '">';
        new_row += '<td><input type="hidden" id="dim_item_id_' + total_rows + '" value=""><input type="number" class="form-control form-control-sm text-center dim-calc" id="dim_pcs_' + total_rows + '" value="1" min="1" onchange="calculateDim(' + total_rows + ')" onkeyup="calculateDim(' + total_rows + ')"></td>';
        new_row += '<td><select class="form-select form-select-sm dim-calc" id="dim_units_' + total_rows + '" onchange="calculateDim(' + total_rows + ')"><option value="cm">cm</option><option value="inch">inch</option></select></td>';
        new_row += '<td><input type="number" class="form-control form-control-sm dim-calc" id="dim_length_' + total_rows + '" value="0" step="any" onchange="calculateDim(' + total_rows + ')" onkeyup="calculateDim(' + total_rows + ')"></td>';
        new_row += '<td><input type="number" class="form-control form-control-sm dim-calc" id="dim_width_' + total_rows + '" value="0" step="any" onchange="calculateDim(' + total_rows + ')" onkeyup="calculateDim(' + total_rows + ')"></td>';
        new_row += '<td><input type="number" class="form-control form-control-sm dim-calc" id="dim_height_' + total_rows + '" value="0" step="any" onchange="calculateDim(' + total_rows + ')" onkeyup="calculateDim(' + total_rows + ')"></td>';
        new_row += '<td><select class="form-select form-select-sm dim-calc" id="dim_formula_' + total_rows + '" onchange="calculateDim(' + total_rows + ')"><option value="6000">6000</option><option value="5000">5000</option></select></td>';
        new_row += '<td><input readonly type="text" class="form-control form-control-sm bg-light text-end" id="dim_cbm_' + total_rows + '" value="0"></td>';
        new_row += '<td><input readonly type="text" class="form-control form-control-sm bg-light text-end" id="dim_volume_' + total_rows + '" value="0"></td>';
        new_row += '<td><a href="#" onclick="clear_dim_row(' + total_rows + '); return false;"><span class="badge bg-warning"><i class="ph-x"></i></span></a></td>';
        new_row += '</tr>';

        document.getElementById('dimension_table_body').insertAdjacentHTML('beforeend', new_row);
    }

    function clear_dim_row(row_no) {
        var dimItemId = document.getElementById('dim_item_id_' + row_no);
        if (dimItemId && dimItemId.value && dimItemId.value !== '') {
            $.ajax({
                url: 'internal_request.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax_action: 'delete_dimension_item',
                    id: dimItemId.value,
                    row_no: row_no,
                    csrf_token: $('input[name="csrf_token"]').val()
                }
            });
        }
        var row = document.getElementById('dim_row_' + row_no);
        if (row) row.style.display = 'none';
        calculateDimGrand();
    }

    function saveDimensions() {
        var total_rows = parseInt(document.getElementById('dim_total_rows').value) || 1;
        var grand_cbm = parseFloat(document.getElementById('dim_grand_cbm').value) || 0;
        var grand_volume = parseFloat(document.getElementById('dim_grand_volume').value) || 0;
        var so_id = document.getElementById('id') ? document.getElementById('id').value : '0';
        var dimensions = [];

        for (var i = 1; i <= total_rows; i++) {
            var row = document.getElementById('dim_row_' + i);
            if (!row || row.style.display === 'none') continue;
            var pcs = parseFloat(document.getElementById('dim_pcs_' + i).value) || 0;
            if (pcs <= 0) continue;

            var dimItemId = document.getElementById('dim_item_id_' + i);
            dimensions.push({
                module_type: 'sale_orders',
                record_id: so_id,
                sale_order_id: so_id,
                item_id: (dimItemId && dimItemId.value) ? dimItemId.value : '0',
                row_no: i,
                pcs: pcs,
                unit: document.getElementById('dim_units_' + i).value,
                length: parseFloat(document.getElementById('dim_length_' + i).value) || 0,
                width: parseFloat(document.getElementById('dim_width_' + i).value) || 0,
                height: parseFloat(document.getElementById('dim_height_' + i).value) || 0,
                formula: parseInt(document.getElementById('dim_formula_' + i).value) || 6000,
                cbm: parseFloat(document.getElementById('dim_cbm_' + i).value) || 0,
                volume: parseFloat(document.getElementById('dim_volume_' + i).value) || 0
            });
        }

        if (dimensions.length > 0) {
            $.ajax({
                url: 'internal_request.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    ajax_action: 'save_dimensions',
                    dimensions: dimensions,
                    csrf_token: $('input[name="csrf_token"]').val()
                },
                success: function(response) {
                    if (response.status === 'success' && response.item_ids) {
                        for (var j = 0; j < response.item_ids.length; j++) {
                            var item = response.item_ids[j];
                            var el = document.getElementById('dim_item_id_' + item.row_no);
                            if (el) el.value = item.id;
                        }
                    }
                }
            });
        }

        document.getElementById('volume').value = parseFloat(grand_volume.toFixed(2));
        document.getElementById('cbm').value = parseFloat(grand_cbm.toFixed(4));
        calculateChargeableWeight();
        $('#dimensionModal').modal('hide');
    }

    function create_shipper() {
        var shipper_name = document.getElementById('shipper_name').value;
        var shipper_address_line1 = document.getElementById('shipper_address_line1').value;
        var shipper_address_line2 = document.getElementById('shipper_address_line2').value;
        var shipper_city = document.getElementById('shipper_city').value;
        var shipper_zipcode = document.getElementById('shipper_zipcode').value;
        var shipper_province = document.getElementById('shipper_province').value;
        var shipper_country = document.getElementById('shipper_country').value;
        var shipper_email = document.getElementById('shipper_email').value;
        var shipper_telephone = document.getElementById('shipper_telephone').value;
        var shipper_mobile = document.getElementById('shipper_mobile').value;
        var shipper_fax = document.getElementById('shipper_fax').value;

        ajax_add_shipper(shipper_name, shipper_address_line1, shipper_address_line2,
            shipper_city, shipper_zipcode, shipper_province, shipper_country,
            shipper_email, shipper_telephone, shipper_mobile, shipper_fax);
    }

    function create_consignee() {
        var consignee_name = document.getElementById('consignee_name').value;
        var consignee_address_line1 = document.getElementById('consignee_address_line1').value;
        var consignee_address_line2 = document.getElementById('consignee_address_line2').value;
        var consignee_city = document.getElementById('consignee_city').value;
        var consignee_zipcode = document.getElementById('consignee_zipcode').value;
        var consignee_province = document.getElementById('consignee_province').value;
        var consignee_country = document.getElementById('consignee_country').value;
        var consignee_email = document.getElementById('consignee_email').value;
        var consignee_telephone = document.getElementById('consignee_telephone').value;
        var consignee_mobile = document.getElementById('consignee_mobile').value;
        var consignee_fax = document.getElementById('consignee_fax').value;

        ajax_add_consignee(consignee_name, consignee_address_line1, consignee_address_line2,
            consignee_city, consignee_zipcode, consignee_province, consignee_country,
            consignee_email, consignee_telephone, consignee_mobile, consignee_fax);
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

    function onCountryChange(portSelectId, countrySelectId) {
        const $port = $('#' + portSelectId);
        if ($port.length && $port.hasClass('select2-hidden-accessible')) {
            $port.val('').trigger('change');
        }
    }

    function initPortSelect2(portSelectId, countrySelectId) {
        const $port = $('#' + portSelectId);
        const $country = $('#' + countrySelectId);
        if (!$port.length || typeof $port.select2 !== 'function') {
            return;
        }
        $port.select2({
            placeholder: 'Please select a port',
            minimumInputLength: 0,
            ajax: {
                url: 'internal_request.php',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    const countryId = $country.length ? ($country.val() || '0') : '0';
                    return {
                        ajax_action: 'select2_ports',
                        q: params.term || '',
                        country_id: countryId
                    };
                },
                processResults: function (data) {
                    return { results: data.results || [] };
                }
            }
        });

        const portKey = portSelectId.replace('_port', '');
        $port.on('select2:select', function () {
            ajax_select_port_country(portKey, $port.val());
        });
        $port.on('select2:unselect', function () {
            const $countrySel = $('#' + portKey + '_country');
            if ($countrySel.length) {
                $countrySel.prop('disabled', false)
                    .removeClass('bg-light')
                    .css('pointerEvents', '')
                    .val('0');
                const hidden = document.getElementById(portKey + '_country_hidden');
                if (hidden) {
                    hidden.remove();
                }
            }
        });
    }

    $(document).ready(function() {
        document.addEventListener('DOMContentLoaded', toggleSaleOrderPartySelectors);

        initPortSelect2('origin_port', 'origin_country');
        initPortSelect2('destination_port', 'destination_country');

        $(document).on('click', '.submit-form', function(e) {
            e.preventDefault();
            document.getElementById('frmsale_orders').submit();
        });

        $(document).on('click', '.save-draft-sale-order', function(e) {
            e.preventDefault();
            document.getElementById('sale_order_status').value = 'draft';
            document.getElementById('frmsale_orders').submit();
        });

        $(document).on('click', '.save-and-send-sale-order', function(e) {
            e.preventDefault();
            document.getElementById('save_and_send').value = '1';
            document.getElementById('sale_order_status').value = 'sent';
            document.getElementById('frmsale_orders').submit();
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

