<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $warehouse_id
 * @var string $customer_id
 * @var string $quotation_id
 * @var string $job_date
 * @var string $job_status
 * @var string $job_seq
 * @var string $job_no
 * @var string $job_ref_no
 * @var string $sales_person
 * @var string $sales_person_from_lead
 * @var string $currency
 * @var string $exchange_rate
 * @var string $transport_mode
 * @var string $shipment_type
 * @var array $shipment_type_arr
 * @var string $job_owner
 * @var string $tags
 * @var array $tags_arr
 * @var string $services
 * @var array $services_arr
 * @var string $cs_agent
 * @var string $incoterm
 * @var string $email
 * @var string $supplier_rate
 * @var string $estimated_net_profit
 * @var string $estimated_invoice_amount
 * @var string $etd
 * @var string $eta
 * @var string $carrier
 * @var string $vessel_name
 * @var string $vessel_departure_date
 * @var string $flight_no
 * @var string $flight_departure_date
 * @var string $job_completion_date
 * @var string $payment_terms
 * @var string $hawb
 * @var string $mawb
 * @var string $estimated_cost_amount
 * @var string $declaration_no
 * @var string $gross_weight
 * @var string $volume_weight
 * @var string $chargeable_weight
 * @var string $no_of_pieces
 * @var string $commodity_type
 * @var string $no_of_containers
 * @var string $insurance_needed
 * @var string $container_type
 * @var string $temperature_control_required
 * @var string $container_number
 * @var string $special_comments
 * @var string $landing_country
 * @var string $landing_port
 * @var string $loading_place
 * @var string $billing_city
 * @var string $billing_state
 * @var string $billing_code
 * @var string $billing_country
 * @var string $destination_country
 * @var string $destination_port
 * @var string $fdp
 * @var string $shipping_city
 * @var string $shipping_state
 * @var string $shipping_code
 * @var string $shipping_country
 * @var string $subject
 * @var string $terms_and_conditions
 * @var string $grand_subtotal
 * @var string $grand_discount_type
 * @var string $grand_discount_type_value
 * @var string $grand_discount_amount
 * @var string $grand_after_discount
 * @var string $customer_notes
 * @var string $grand_tax
 * @var string $grand_total
 * @var string $happy_customer
 * @var string $unhappy_reason
 * @var string $shipment_on_time
 * @var string $referral
 * @var string $notes
 * @var string $quote_id
 * @var string $project_id
 * @var string $customer_type
 * @var string $created_by
 * @var string $modified_by
 * @var string $books_customer_id
 * @var string $approved_time
 * @var string $approved_time_resubmission
 * @var string $project_created
 * @var string $qrcode
 * @var array $item_dim_id_arr
 * @var array $dim_length_arr
 * @var array $dim_width_arr
 * @var array $dim_height_arr
 * @var array $dim_pcs_arr
 * @var array $dim_volume_arr
 * @var array $dim_cbm_arr
 * @var int $total_rows
 * @var array $warehousesList
 * @var array $customersList
 * @var array $usersList
 * @var array $currenciesList
 * @var array $jobStatusesList
 * @var array $incotermsList
 * @var array $carriersList

 * @var array $containerTypesList
 * @var array $tagsList
 * @var array $servicesList
 * @var array $countriesList
 * @var array $quotesList
 * @var bool $canCreate
 * @var bool $canEdit
 */

$warehouse_options = [];
foreach ($warehousesList as $row) {
    $warehouse_options[$row['id']] = $row['warehouse_name'];
}

$customer_options_html = '';
foreach ($customersList as $row) {
    $sel = ((string)$row['id'] === $customer_id) ? 'selected' : '';
    $customer_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['display_name']) . '</option>';
}

$users_options = [];
foreach ($usersList as $row) {
    $users_options[$row['id']] = $row['full_name'];
}

$currency_options_html = '';
foreach ($currenciesList as $row) {
    $sel = ((string)$row['id'] === $currency) ? 'selected' : '';
    $currency_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['currency']) . '</option>';
}

$job_status_options_html = '';
$draftJobStatusId = '';
foreach ($jobStatusesList as $row) {
    if (strtolower($row['job_status']) === 'draft') {
        $draftJobStatusId = $row['id'];
    }
}
foreach ($jobStatusesList as $row) {
    $sel = ((string)$row['id'] === $job_status) ? 'selected' : '';
    if (empty($id) && (string)$row['id'] === (string)$draftJobStatusId) {
        $sel = 'selected';
    }
    $job_status_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['job_status']) . '</option>';
}

$incoterm_options_html = '';
foreach ($incotermsList as $row) {
    $sel = ((string)$row['id'] === $incoterm) ? 'selected' : '';
    $incoterm_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['incoterm']) . '</option>';
}

$carrier_options_html = '';
foreach ($carriersList as $row) {
    $sel = ((string)$row['id'] === $carrier) ? 'selected' : '';
    $carrier_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['carrier_name']) . '</option>';
}

$container_type_options_html = '';
foreach ($containerTypesList as $row) {
    $sel = ((string)$row['id'] === $container_type) ? 'selected' : '';
    $container_type_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['container_type']) . '</option>';
}

$shipmentTypeOptions = ['export'=>'Export', 'import'=>'Import', 'transit'=>'Transit', 'local_jobs'=>'Local Jobs', 'import_export'=>'Import / Export'];
$shipment_type_options_html = '';
foreach ($shipmentTypeOptions as $val => $lbl) {
    $sel = (in_array($val, $shipment_type_arr, true)) ? 'selected' : '';
    $shipment_type_options_html .= '<option value="' . $val . '" ' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
}

$transport_mode_arr = !empty($transport_mode) ? explode(', ', $transport_mode) : [];
$transport_modeOptions = ['air'=>'Air', 'sea'=>'Sea', 'land'=>'Land'];
$transport_mode_options_html = '';
foreach ($transport_modeOptions as $val => $lbl) {
    $sel = (in_array($val, $transport_mode_arr, true)) ? 'selected' : '';
    $transport_mode_options_html .= '<option value="' . $val . '" ' . $sel . '>' . htmlspecialchars($lbl) . '</option>';
}

$tags_options_html = '';
foreach ($tagsList as $row) {
    $sel = (in_array($row['id'], $tags_arr) || in_array((string)$row['id'], $tags_arr)) ? 'selected' : '';
    $tags_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['value']) . '</option>';
}

$services_options_html = '';
foreach ($servicesList as $row) {
    $sel = (in_array($row['id'], $services_arr) || in_array((string)$row['id'], $services_arr)) ? 'selected' : '';
    $services_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['item_name']) . '</option>';
}

$countries_options = [];
foreach ($countriesList as $row) {
    $countries_options[$row['id']] = $row['country'];
}

$quotes_options_html = '';
foreach ($quotesList as $row) {
    $sel = ((string)$row['id'] === $quotation_id) ? 'selected' : '';
    $quotes_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['quotation_no']) . '</option>';
}

include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0): ?>
                    <span class="badge bg-success bg-opacity-10 text-success ms-2">Job #: <?php echo htmlspecialchars($job_no); ?></span>
                <?php endif; ?>
                <?php if (!empty($job_status)): ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary ms-2"><?php echo ucwords($job_status); ?></span>
                <?php endif; ?>
            </div>
            <div class="my-1 d-flex align-items-center gap-2">
                <?php if ($canCreate || $canEdit): ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm">Save</button>
                <?php endif; ?>
                <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>
                <?php echo csrf_field(); ?>

                <!-- Row 1: Basic Info -->
                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'warehouse_id', 'label'=>'Warehouse:', 'required'=>true, 'options'=>$warehouse_options, 'selected'=>$warehouse_id, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'customer_id', 'label'=>'Customer Name:', 'required'=>true, 'options_html'=>$customer_options_html, 'selected'=>$customer_id, 'empty_option'=>'Please select', 'extra_class'=>'form-control select select2-enable']; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'sales_person_from_lead', 'label'=>'Sales Person from Lead:', 'value'=>$sales_person_from_lead]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'job_seq', 'label'=>'Job Seq:', 'value'=>$job_seq]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'sales_person', 'label'=>'Sales Person:', 'options'=>$users_options, 'selected'=>$sales_person]; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'currency', 'label'=>'Currency:', 'options_html'=>$currency_options_html, 'selected'=>$currency]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'exchange_rate', 'label'=>'Exchange Rate:', 'value'=>$exchange_rate, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'transport_mode[]', 'label'=>'Transport Mode:', 'options_html'=>$transport_mode_options_html, 'extra_class'=>'form-control select select2-enable', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'shipment_type[]', 'label'=>'Type of Shipment:', 'options_html'=>$shipment_type_options_html, 'extra_class'=>'form-control select select2-enable', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'job_owner', 'label'=>'Job Owner:', 'required'=>true, 'options'=>$users_options, 'selected'=>$job_owner]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'tags[]', 'label'=>'Tag:', 'options_html'=>$tags_options_html, 'extra_class'=>'form-control select', 'extra_attr'=>'data-tags="true"', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 2: Job Status -->
                <div class="col-xl-12 mt-3">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h6 class="mb-0">Job Status Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <label class="col-lg-2 col-form-label">Job Status:</label>
                                <div class="col-lg-4">
                                    <select class="form-select" name="job_status" id="job_status">
                                        <?php echo $job_status_options_html; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 3: Job Info + Dates -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Detailed Job Information</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'job_no', 'label'=>'Job No:', 'required'=>true, 'value'=>$job_no, 'extra_attr'=>'data-check-duplicate="1"']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'job_ref_no', 'label'=>'Job Ref No:', 'required'=>true, 'value'=>$job_ref_no, 'extra_attr'=>'data-check-duplicate="1"']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'cs_agent', 'label'=>'CS Agent:', 'options'=>$users_options, 'selected'=>$cs_agent]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'services[]', 'label'=>'Type of Services:', 'options_html'=>$services_options_html, 'extra_class'=>'form-control select select2-enable', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'incoterm', 'label'=>'Incoterms:', 'options_html'=>$incoterm_options_html, 'selected'=>$incoterm, 'extra_class'=>'form-control']; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'email', 'label'=>'Email:', 'value'=>$email, 'type'=>'email']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'supplier_rate', 'label'=>'Supplier Rates:', 'value'=>$supplier_rate, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'estimated_net_profit', 'label'=>'Estimated Net Profit:', 'value'=>$estimated_net_profit, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'job_date', 'label'=>'Job Date:', 'value'=>$job_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'estimated_invoice_amount', 'label'=>'Estimated Invoice Amount:', 'value'=>$estimated_invoice_amount, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'etd', 'label'=>'ETD:', 'value'=>$etd]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'eta', 'label'=>'ETA:', 'value'=>$eta]; include 'admin_elements/form_field_date.php'; ?>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label" for="carrier">Carrier Name:</label>
                                        <div class="col-lg-9">
                                            <div class="input-group">
                                                <select class="form-control select select2-enable" name="carrier" id="carrier">
                                                    <option value="">Please select</option>
                                                    <?php echo $carrier_options_html; ?>
                                                </select>
                                                <button type="button" class="btn btn-outline-primary" onclick="showAddCarrierModal();" title="Add New Carrier">
                                                    <i class="ph-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <?php $field = ['name'=>'vessel_name', 'label'=>'Vessel Name:', 'value'=>$vessel_name]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'vessel_departure_date', 'label'=>'Vessel Departure Date:', 'value'=>$vessel_departure_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'flight_no', 'label'=>'Flight No:', 'value'=>$flight_no]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'flight_departure_date', 'label'=>'Flight Departure Date:', 'value'=>$flight_departure_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'job_completion_date', 'label'=>'Job Completed Date:', 'value'=>$job_completion_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'payment_terms', 'label'=>'Payment Terms:', 'value'=>$payment_terms]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'hawb', 'label'=>'HAWB / HBL:', 'value'=>$hawb]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'mawb', 'label'=>'MAWB / MBL:', 'value'=>$mawb]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'estimated_cost_amount', 'label'=>'Estimated Cost Amount:', 'value'=>$estimated_cost_amount, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'declaration_no', 'label'=>'Customs Declaration No:', 'required'=>true, 'value'=>$declaration_no]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Dimensions (L x W x H) -->
                <div class="col-xl-12 mt-3">
                    <div class="row mb-2">
                        <div class="col-lg-2">
                            <label class="form-label ms-3 fw-semibold">LENGTH <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label ms-4 fw-semibold">WIDTH <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label ms-3 fw-semibold">HEIGHT <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-lg-1">
                            <label class="form-label ms-4 fw-semibold">NO OF PCS <span class="text-danger">*</span></label>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label ms-3 fw-semibold">VOLUME WEIGHT</label>
                        </div>
                        <div class="col-lg-2">
                            <label class="form-label ms-2 fw-semibold">CBM</label>
                        </div>
                    </div>

                    <div class="card">
                        <div class="row card-body">
                            <div class="col-lg-12">
                                <?php
                                $total_cbm = 0;
                                $total_volume = 0;
                                $total_pcs = 0;

                                $loop_rows = max($total_rows, 1);
                                for ($dim_item = 1; $dim_item <= $loop_rows; $dim_item++):
                                    $index = $dim_item - 1;

                                    $dim_length = !empty($dim_length_arr[$index]) ? $dim_length_arr[$index] : '0';
                                    $dim_width = !empty($dim_width_arr[$index]) ? $dim_width_arr[$index] : '0';
                                    $dim_height = !empty($dim_height_arr[$index]) ? $dim_height_arr[$index] : '0';
                                    $dim_pcs = !empty($dim_pcs_arr[$index]) ? $dim_pcs_arr[$index] : '1';
                                    $dim_volume = !empty($dim_volume_arr[$index]) ? $dim_volume_arr[$index] : '0';
                                    $dim_cbm_val = !empty($dim_cbm_arr[$index]) ? $dim_cbm_arr[$index] : '0';

                                    $total_cbm += (float)$dim_cbm_val;
                                    $total_volume += (float)$dim_volume;
                                    $total_pcs += (int)$dim_pcs;
                                ?>
                                    <div class="mb-2">
                                        <div class="row mb-3 pb-3" id="row_<?php echo $dim_item; ?>">
                                            <div class="col-lg-12">
                                                <div class="row">
                                                    <input type="hidden" name="item_dim_id[]" id="item_dim_id<?php echo $dim_item; ?>" value="<?php echo (!empty($item_dim_id_arr[$index]) ? htmlspecialchars($item_dim_id_arr[$index]) : ''); ?>">

                                                    <div class="col-lg-2">
                                                        <input type="number" step="any" name="dim_length[]" id="dim_length<?php echo $dim_item; ?>" min="0" class="form-control text-center" value="<?php echo htmlspecialchars($dim_length); ?>" onkeyup="calculateItemCBM('<?php echo $dim_item; ?>');" onchange="calculateItemCBM('<?php echo $dim_item; ?>');">
                                                    </div>

                                                    <div class="col-lg-2">
                                                        <input type="number" step="any" name="dim_width[]" id="dim_width<?php echo $dim_item; ?>" min="0" class="form-control text-center" value="<?php echo htmlspecialchars($dim_width); ?>" onkeyup="calculateItemCBM('<?php echo $dim_item; ?>');" onchange="calculateItemCBM('<?php echo $dim_item; ?>');">
                                                    </div>

                                                    <div class="col-lg-2">
                                                        <input type="number" step="any" name="dim_height[]" id="dim_height<?php echo $dim_item; ?>" min="0" class="form-control text-center" value="<?php echo htmlspecialchars($dim_height); ?>" onkeyup="calculateItemCBM('<?php echo $dim_item; ?>');" onchange="calculateItemCBM('<?php echo $dim_item; ?>');">
                                                    </div>

                                                    <div class="col-lg-1">
                                                        <div class="input-group">
                                                            <button type="button" class="btn btn-light btn-icon" onclick="this.parentNode.querySelector('input[type=number]').stepDown(); calculateItemCBM('<?php echo $dim_item; ?>');">
                                                                <i class="ph-minus ph-sm"></i>
                                                            </button>
                                                            <input class="form-control form-control-number text-center" type="number" name="dim_pcs[]" id="dim_pcs<?php echo $dim_item; ?>" value="<?php echo htmlspecialchars($dim_pcs); ?>" min="1" onkeyup="calculateItemCBM('<?php echo $dim_item; ?>');" onchange="calculateItemCBM('<?php echo $dim_item; ?>');">
                                                            <button type="button" class="btn btn-light btn-icon" onclick="this.parentNode.querySelector('input[type=number]').stepUp(); calculateItemCBM('<?php echo $dim_item; ?>');">
                                                                <i class="ph-plus ph-sm"></i>
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <div class="col-lg-2">
                                                        <input readonly type="number" name="dim_volume[]" id="dim_volume<?php echo $dim_item; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo htmlspecialchars($dim_volume); ?>">
                                                    </div>

                                                    <div class="col-lg-2">
                                                        <input readonly type="number" name="dim_cbm[]" id="dim_cbm<?php echo $dim_item; ?>" min="0" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo htmlspecialchars($dim_cbm_val); ?>">
                                                    </div>

                                                    <div class="col-lg-2 mt-1">
                                                        <?php if ($dim_item > 1): ?>
                                                            <a href="#" onclick="calculateItemCBM('<?php echo $dim_item; ?>'); clear_row(<?php echo $dim_item; ?>); return false;">
                                                                <span class="badge bg-warning"><i class="ph-x"></i></span>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>

                                <div id="add_row_here"></div>
                            </div>

                            <div>
                                <span id="span_add_item_row"><a href="#" onclick="add_item_row(); return false;"><span class="badge bg-primary">Add New Row</span></a></span>
                            </div>

                            <div class="mt-3">
                                <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 mb-0 py-2 px-3">
                                    <div class="d-flex align-items-start">
                                        <div class="me-2 mt-1"><i class="ph-info ph-lg text-info"></i></div>
                                        <div class="small lh-lg">
                                            <span class="fw-semibold">Volume Weight</span> = L &times; W &times; H (cm&sup3;)<br>
                                            <span class="fw-semibold">CBM</span> = (L &times; W &times; H &times; Pcs) &divide; 1,000,000<br>
                                            <span class="fw-semibold">Total CBM</span> = Sum of all row CBMs
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $loop_rows; ?>">

                    <div class="row">
                        <div class="col-lg-7"></div>
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">TOTAL CBM:</label>
                                        <div class="col-lg-6">
                                            <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="grand_subtotal" id="grand_subtotal" value="<?php echo $total_cbm; ?>" />
                                        </div>
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label">Total Volume Weight:</label>
                                        <div class="col-lg-6">
                                            <input readonly type="number" class="form-control bg-light bg-opacity-50 text-end" name="grand_tax" id="grand_tax" value="<?php echo $total_volume; ?>" placeholder="0">
                                        </div>
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Total Pieces:</label>
                                        <div class="col-lg-6">
                                            <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" name="grand_total" id="grand_total" value="<?php echo $total_pcs; ?>" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 5: Commodity Details + By Sea -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Commodity Details</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'gross_weight', 'label'=>'Gross Weight:', 'value'=>$gross_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'volume_weight', 'label'=>'Volume Weight:', 'value'=>$volume_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'chargeable_weight', 'label'=>'Chargeable Weight:', 'value'=>$chargeable_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'no_of_pieces', 'label'=>'No. of Pieces:', 'value'=>$no_of_pieces, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'commodity_type', 'label'=>'Commodity Type:', 'value'=>$commodity_type]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'no_of_containers', 'label'=>'No. of Containers:', 'options'=>array_combine(range(0,100), range(0,100)), 'selected'=>$no_of_containers]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'insurance_needed', 'label'=>'Insurance Needed?:', 'options'=>['0'=>'No', '1'=>'Yes'], 'selected'=>$insurance_needed]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'container_type', 'label'=>'Container Type:', 'options_html'=>$container_type_options_html, 'selected'=>$container_type]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'temperature_control_required', 'label'=>'Temperature Control Required:', 'options'=>['0'=>'No', '1'=>'Yes'], 'selected'=>$temperature_control_required]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'container_number', 'label'=>'Container Number:', 'value'=>$container_number]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'special_comments', 'label'=>'Special Comments:', 'value'=>$special_comments]; include 'admin_elements/form_field_textarea.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 6: Port Details (Loading + Destination) -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Port Details</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'landing_country', 'label'=>'Loading Country:', 'options'=>$countries_options, 'selected'=>$landing_country, 'extra_class'=>'form-select select2-enable', 'extra_attr'=>'onchange="ajax_populate_landing_ports(this.value);"']; include 'admin_elements/form_field_select.php'; ?>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label" for="landing_port">Port of Loading (POL):</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="landing_port" id="landing_port">
                                                <option value="0"></option>
                                                <?php
                                                if (!empty($landing_country) && $landing_country !== '0') {
                                                    try {
                                                        $ports = \App\Core\DB::pdo()->prepare("SELECT id, port_name FROM `" . \App\Core\DB::PORTS . "` WHERE country_id = ? AND is_active = 1 ORDER BY port_name");
                                                        $ports->execute([$landing_country]);
                                                        while ($p = $ports->fetch(\PDO::FETCH_ASSOC)) {
                                                            $sel = ((string)$p['id'] === $landing_port) ? 'selected' : '';
                                                            echo '<option value="' . $p['id'] . '" ' . $sel . '>' . htmlspecialchars($p['port_name']) . '</option>';
                                                        }
                                                    } catch (\Throwable $e) {}
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <?php $field = ['name'=>'loading_place', 'label'=>'Place of Loading:', 'value'=>$loading_place]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_city', 'label'=>'Billing City:', 'value'=>$billing_city]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_state', 'label'=>'Billing State:', 'value'=>$billing_state]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_code', 'label'=>'Billing Code:', 'value'=>$billing_code]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_country', 'label'=>'Billing Country:', 'options'=>$countries_options, 'selected'=>$billing_country, 'extra_class'=>'form-select select2-enable']; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'destination_country', 'label'=>'Destination Country:', 'options'=>$countries_options, 'selected'=>$destination_country, 'extra_class'=>'form-select select2-enable', 'extra_attr'=>'onchange="ajax_populate_destination_ports(this.value);"']; include 'admin_elements/form_field_select.php'; ?>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label" for="destination_port">Port of Destination (POD):</label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="destination_port" id="destination_port">
                                                <option value="0"></option>
                                                <?php
                                                if (!empty($destination_country) && $destination_country !== '0') {
                                                    try {
                                                        $ports = \App\Core\DB::pdo()->prepare("SELECT id, port_name FROM `" . \App\Core\DB::PORTS . "` WHERE country_id = ? AND is_active = 1 ORDER BY port_name");
                                                        $ports->execute([$destination_country]);
                                                        while ($p = $ports->fetch(\PDO::FETCH_ASSOC)) {
                                                            $sel = ((string)$p['id'] === $destination_port) ? 'selected' : '';
                                                            echo '<option value="' . $p['id'] . '" ' . $sel . '>' . htmlspecialchars($p['port_name']) . '</option>';
                                                        }
                                                    } catch (\Throwable $e) {}
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <?php $field = ['name'=>'fdp', 'label'=>'Final Destination (FDP):', 'value'=>$fdp]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_city', 'label'=>'Shipping City:', 'value'=>$shipping_city]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_state', 'label'=>'Shipping State:', 'value'=>$shipping_state]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_code', 'label'=>'Shipping Code:', 'value'=>$shipping_code]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_country', 'label'=>'Shipping Country:', 'options'=>$countries_options, 'selected'=>$shipping_country, 'extra_class'=>'form-select select2-enable']; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 7: After Service -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">After Service</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'happy_customer', 'label'=>'Customer happy with service:', 'options'=>['yes'=>'Yes', 'no'=>'No'], 'selected'=>$happy_customer]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'unhappy_reason', 'label'=>'Reason for Customer Unsatisfaction:', 'value'=>$unhappy_reason]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <?php $field = ['name'=>'shipment_on_time', 'label'=>'Shipment Delivered on Time:', 'options'=>['yes'=>'Yes', 'no'=>'No'], 'selected'=>$shipment_on_time]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'referral', 'label'=>'Referral:', 'value'=>$referral]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 8: System Fields (Read Only) -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">System Fields</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Created By:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars((string)$created_by); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Modified By:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($modified_by); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Customer Type:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($customer_type); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Quote:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($quote_id); ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Books Customer ID:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($books_customer_id); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Approved Time:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($approved_time); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Project ID:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($project_id); ?>" disabled>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Approved Time ReSubmission:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($approved_time_resubmission); ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 9: Notes -->
                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-4">
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="ms-sm-3 mb-3 mb-sm-0">
                                        <label class="col-form-label">Notes:</label>
                                        <textarea class="form-control" name="notes" id="notes" style="field-sizing: content;" placeholder=""><?php echo htmlspecialchars($notes); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Row 10: Additional Details (haizon extras) -->
                <div class="col-xl-12 mt-3">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h6 class="mb-0">Additional Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <?php $field = ['name'=>'subject', 'label'=>'Subject:', 'value'=>$subject]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'customer_notes', 'label'=>'Customer Notes:', 'value'=>$customer_notes]; include 'admin_elements/form_field_textarea.php'; ?>
                                </div>
                                <div class="col-lg-6">
                                    <?php $field = ['name'=>'terms_and_conditions', 'label'=>'Terms & Conditions:', 'value'=>$terms_and_conditions]; include 'admin_elements/form_field_textarea.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>

            <!-- Add Carrier Modal -->
            <div class="modal fade" id="addCarrierModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Add New Carrier</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new_carrier_name" class="form-label">Carrier Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="new_carrier_name" placeholder="Enter carrier name">
                                <div class="invalid-feedback" id="carrier_name_error"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="saveCarrierBtn" onclick="saveNewCarrier();">Save Carrier</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include 'admin_elements/copyright.php'; ?>
    </div>
</div>

<script>
function showAddCarrierModal() {
    $('#new_carrier_name').val('').removeClass('is-invalid');
    $('#carrier_name_error').text('');
    $('#addCarrierModal').modal('show');
}

function saveNewCarrier() {
    var name = $('#new_carrier_name').val().trim();
    if (!name) {
        $('#new_carrier_name').addClass('is-invalid');
        $('#carrier_name_error').text('Please enter a carrier name.');
        return;
    }
    $('#saveCarrierBtn').prop('disabled', true).text('Saving...');
    $.post('<?php echo $module; ?>.php', {
        action: 'ajax_add_carrier',
        carrier_name: name,
        csrf_token: '<?php echo csrf_token(); ?>'
    }, function(res) {
        if (res.success) {
            var $carrierSelect = $('#carrier');
            if ($carrierSelect.hasClass('select2-hidden-accessible')) {
                var newOption = new Option(res.carrier_name, res.id, true, true);
                $carrierSelect.append(newOption).trigger('change');
            } else {
                $carrierSelect.append('<option value="' + res.id + '" selected>' + res.carrier_name + '</option>');
            }
            $('#addCarrierModal').modal('hide');
        } else {
            $('#new_carrier_name').addClass('is-invalid');
            $('#carrier_name_error').text(res.message || 'Failed to save carrier.');
        }
    }, 'json').always(function() {
        $('#saveCarrierBtn').prop('disabled', false).text('Save Carrier');
    });
}

$(document).ready(function() {
    // MAWB dynamic label
    var $mawbField = $('input[name="mawb"]');
    var $mawbLabel = $mawbField.closest('.row').find('label');
    var $shipmentSelect = $('select[name="shipment_type[]"]');

    function updateMawbLabel() {
        var vals = $shipmentSelect.val() || [];
        var parts = ['MAWB'];
        if (vals.includes('import')) parts.push('IMP');
        if (vals.includes('export')) parts.push('EXP');
        if (vals.includes('import_export')) { parts.push('IMP'); parts.push('EXP'); }
        $mawbLabel.text(parts.join('/') + ':');
    }

    $shipmentSelect.on('change', updateMawbLabel);
    if ($shipmentSelect.length) updateMawbLabel();

    // Duplicate check
    $('[data-check-duplicate]').on('blur', function() {
        var $input = $(this);
        var field = $input.attr('name');
        var value = $input.val().trim();
        if (!value) return;

        $.get('<?php echo $module; ?>.php', {
            action: 'check_duplicate',
            field: field,
            value: value,
            id: <?php echo $id ?? 0; ?>
        }, function(res) {
            $input.removeClass('is-invalid');
            $input.next('.invalid-feedback').remove();
            if (res.duplicate) {
                $input.addClass('is-invalid');
                $input.after('<div class="invalid-feedback">This ' + field.replace('_', ' ') + ' already exists.</div>');
            }
        }, 'json');
    });

    // Calculate all CBM rows on page load
    var totalRows = parseInt($('#total_rows').val()) || 1;
    for (var i = 1; i <= totalRows; i++) {
        calculateItemCBM(i);
    }
});

// --- Dimension Row Functions ---
function add_item_row() {
    var total_rows = document.getElementById('total_rows').value;
    total_rows++;

    var new_row = "";
    new_row += '<div class="row mb-3 pb-3" id="row_' + total_rows + '">';
    new_row += '<div class="col-lg-12"><div class="row">';
    new_row += '<input type="hidden" name="item_dim_id[]" id="item_dim_id' + total_rows + '">';

    new_row += '<div class="col-lg-2">';
    new_row += '<input type="number" step="1" name="dim_length[]" id="dim_length' + total_rows + '" min="0" class="form-control text-center" onkeyup="calculateItemCBM(\'' + total_rows + '\');" onchange="calculateItemCBM(\'' + total_rows + '\');">';
    new_row += '</div>';

    new_row += '<div class="col-lg-2">';
    new_row += '<input type="number" step="1" name="dim_width[]" id="dim_width' + total_rows + '" min="0" class="form-control text-center" onkeyup="calculateItemCBM(\'' + total_rows + '\');" onchange="calculateItemCBM(\'' + total_rows + '\');">';
    new_row += '</div>';

    new_row += '<div class="col-lg-2">';
    new_row += '<input type="number" step="1" name="dim_height[]" id="dim_height' + total_rows + '" min="0" class="form-control text-center" onkeyup="calculateItemCBM(\'' + total_rows + '\');" onchange="calculateItemCBM(\'' + total_rows + '\');">';
    new_row += '</div>';

    new_row += '<div class="col-lg-1">';
    new_row += '<div class="input-group">';
    new_row += '<button type="button" class="btn btn-light btn-icon" onclick="this.parentNode.querySelector(\'input[type=number]\').stepDown(); calculateItemCBM(\'' + total_rows + '\');"><i class="ph-minus ph-sm"></i></button>';
    new_row += '<input class="form-control form-control-number text-center" type="number" name="dim_pcs[]" id="dim_pcs' + total_rows + '" value="1" min="1" onkeyup="calculateItemCBM(\'' + total_rows + '\');" onchange="calculateItemCBM(\'' + total_rows + '\');">';
    new_row += '<button type="button" class="btn btn-light btn-icon" onclick="this.parentNode.querySelector(\'input[type=number]\').stepUp(); calculateItemCBM(\'' + total_rows + '\');"><i class="ph-plus ph-sm"></i></button>';
    new_row += '</div></div>';

    new_row += '<div class="col-lg-2">';
    new_row += '<input readonly type="number" step="1" name="dim_volume[]" id="dim_volume' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end">';
    new_row += '</div>';

    new_row += '<div class="col-lg-2">';
    new_row += '<input readonly type="number" name="dim_cbm[]" id="dim_cbm' + total_rows + '" min="0" class="form-control bg-light bg-opacity-75 text-end" placeholder="0">';
    new_row += '</div>';

    new_row += '<div class="col-lg-1 mt-1"><span id="span_remove_item_row' + total_rows + '"> <a href="#" onclick="clear_row(' + total_rows + '); return false;"><span class="badge bg-warning"><i class="ph-x"></i></span></a></span></div>';

    new_row += '</div></div></div>';

    document.getElementById('add_row_here').insertAdjacentHTML("beforebegin", new_row);
    document.getElementById('total_rows').value = total_rows;
}

function clear_row(row_no) {
    calculateItemCBM(row_no);
    document.getElementById('dim_length' + row_no).value = '';
    document.getElementById('dim_width' + row_no).value = '';
    document.getElementById('dim_height' + row_no).value = '';
    document.getElementById('dim_pcs' + row_no).value = '';
    document.getElementById('dim_volume' + row_no).value = '';
    document.getElementById('dim_cbm' + row_no).value = '';
    document.getElementById('row_' + row_no).style.display = 'none';
}

function calculateItemCBM(row_no) {
    var dim_length = document.getElementById('dim_length' + row_no);
    var dim_length_value = dim_length ? dim_length.value : '';

    if (dim_length_value && dim_length_value !== '0') {
        var dim_pcs = document.getElementById('dim_pcs' + row_no).value;
        dim_pcs = Number(dim_pcs);

        var dim_width_value = document.getElementById('dim_width' + row_no).value;
        var dim_height_value = document.getElementById('dim_height' + row_no).value;

        var sum = parseFloat(dim_length_value) * parseFloat(dim_width_value) * parseFloat(dim_height_value);
        var final_total = parseFloat(sum) * parseFloat(dim_pcs);

        var final_volume = parseFloat(final_total) / 1.66;
        document.getElementById('dim_volume' + row_no).value = parseFloat(final_volume).toFixed(2);

        var final_cbm = parseFloat(final_total).toFixed(2);
        document.getElementById('dim_cbm' + row_no).value = parseFloat(final_cbm);

        calculateGrand();
    }
}

function calculateGrand() {
    var total_rows = document.getElementById('total_rows').value;
    var total_cbm = 0;
    var total_volume = 0;
    var total_pcs = 0;

    for (var i = 1; i <= total_rows; i++) {
        var row = document.getElementById('row_' + i);
        if (row && row.style.display !== 'none') {
            var cbm = parseFloat(document.getElementById('dim_cbm' + i).value) || 0;
            var vol = parseFloat(document.getElementById('dim_volume' + i).value) || 0;
            var pcs = parseInt(document.getElementById('dim_pcs' + i).value) || 0;
            total_cbm += cbm;
            total_volume += vol;
            total_pcs += pcs;
        }
    }

    document.getElementById('grand_subtotal').value = total_cbm.toFixed(2);
    document.getElementById('grand_tax').value = total_volume.toFixed(2);
    document.getElementById('grand_total').value = total_pcs;
}

// Port AJAX functions
function ajax_populate_landing_ports(country_id) {
    if (!country_id || country_id === '0') {
        var sel = document.getElementById('landing_port');
        sel.options.length = 0;
        sel.options[sel.options.length] = new Option('', '0');
        return;
    }

    $.get('<?php echo $module; ?>.php', {
        action: 'ajax_ports',
        country_id: country_id
    }, function(data) {
        var sel = document.getElementById('landing_port');
        sel.options.length = 0;
        sel.options[sel.options.length] = new Option('', '0');
        if (data && data.length) {
            for (var i = 0; i < data.length; i++) {
                sel.options[sel.options.length] = new Option(data[i].port, data[i].id);
            }
        }
    }, 'json');
}

function ajax_populate_destination_ports(country_id) {
    if (!country_id || country_id === '0') {
        var sel = document.getElementById('destination_port');
        sel.options.length = 0;
        sel.options[sel.options.length] = new Option('', '0');
        return;
    }

    $.get('<?php echo $module; ?>.php', {
        action: 'ajax_ports',
        country_id: country_id
    }, function(data) {
        var sel = document.getElementById('destination_port');
        sel.options.length = 0;
        sel.options[sel.options.length] = new Option('', '0');
        if (data && data.length) {
            for (var i = 0; i < data.length; i++) {
                sel.options[sel.options.length] = new Option(data[i].port, data[i].id);
            }
        }
    }, 'json');
}
</script>
<?php
include 'admin_elements/admin_footer.php';
