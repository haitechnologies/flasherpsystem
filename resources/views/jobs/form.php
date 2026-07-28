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
 * @var string $currency
 * @var string $exchange_rate
 * @var string $transport_mode
 * @var string $shipment_type
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
 * @var int $is_active
 * @var string $customer_type
 * @var array $warehousesList
 * @var array $customersList
 * @var array $usersList
 * @var array $currenciesList
 * @var array $jobStatusesList
 * @var array $incotermsList
 * @var array $carriersList
 * @var array $commodityTypesList
 * @var array $containerTypesList
 * @var array $tagsList
 * @var array $servicesList
 * @var array $countriesList
 * @var array $quotesList
 * @var bool $canCreate
 * @var bool $canEdit
 */

// Pre-build option HTML for selects
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

$commodity_type_options_html = '';
foreach ($commodityTypesList as $row) {
    $sel = ((string)$row['id'] === $commodity_type) ? 'selected' : '';
    $commodity_type_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['commodity_type']) . '</option>';
}

$container_type_options_html = '';
foreach ($containerTypesList as $row) {
    $sel = ((string)$row['id'] === $container_type) ? 'selected' : '';
    $container_type_options_html .= '<option value="' . $row['id'] . '" ' . $sel . '>' . htmlspecialchars($row['container_type']) . '</option>';
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
                    <button type="submit" form="frmjobs" class="btn btn-primary btn-sm">Save</button>
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

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Header Information</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'warehouse_id', 'label'=>'Warehouse:', 'required'=>true, 'options'=>$warehouse_options, 'selected'=>$warehouse_id, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'customer_id', 'label'=>'Customer:', 'required'=>true, 'options_html'=>$customer_options_html, 'selected'=>$customer_id, 'empty_option'=>'', 'extra_class'=>'form-control select']; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'quotation_id', 'label'=>'Quotation:', 'options_html'=>$quotes_options_html, 'selected'=>$quotation_id, 'empty_option'=>'']; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'job_ref_no', 'label'=>'Job Ref No:', 'value'=>$job_ref_no]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'job_no', 'label'=>'Job No:', 'value'=>$job_no, 'readonly'=>true, 'extra_class'=>'bg-light bg-opacity-75']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'job_date', 'label'=>'Job Date:', 'required'=>true, 'value'=>$job_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'job_status', 'label'=>'Job Status:', 'options_html'=>$job_status_options_html, 'selected'=>$job_status, 'empty_option'=>false, 'col_label'=>3, 'col_input'=>9]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'sales_person', 'label'=>'Sales Person:', 'options'=>$users_options, 'selected'=>$sales_person]; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Additional Details</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'currency', 'label'=>'Currency:', 'options_html'=>$currency_options_html, 'selected'=>$currency]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'exchange_rate', 'label'=>'Exchange Rate:', 'value'=>$exchange_rate, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'transport_mode', 'label'=>'Transport Mode:', 'options'=>[''=>'', 'air'=>'Air', 'sea'=>'Sea', 'land'=>'Land'], 'selected'=>$transport_mode]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'shipment_type', 'label'=>'Type of Shipment:', 'options'=>[''=>'', 'export'=>'Export', 'import'=>'Import', 'transit'=>'Transit'], 'selected'=>$shipment_type]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'job_owner', 'label'=>'Job Owner:', 'required'=>true, 'options'=>$users_options, 'selected'=>$job_owner]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'tags[]', 'label'=>'Tags:', 'options_html'=>$tags_options_html, 'extra_class'=>'form-control select', 'extra_attr'=>'data-tags="true"', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'incoterm', 'label'=>'Incoterm:', 'options_html'=>$incoterm_options_html, 'selected'=>$incoterm, 'extra_class'=>'form-control']; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'carrier', 'label'=>'Carrier:', 'options_html'=>$carrier_options_html, 'selected'=>$carrier]; include 'admin_elements/form_field_select.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Job Information</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'cs_agent', 'label'=>'CS Agent:', 'options'=>$users_options, 'selected'=>$cs_agent]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'services[]', 'label'=>'Type of Services:', 'options_html'=>$services_options_html, 'extra_class'=>'form-control select', 'extra_attr'=>'data-tags="true"', 'multiple'=>true, 'empty_option'=>false]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'email', 'label'=>'Email:', 'value'=>$email, 'type'=>'email']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'supplier_rate', 'label'=>'Supplier Rate:', 'value'=>$supplier_rate, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'estimated_net_profit', 'label'=>'Est. Net Profit:', 'value'=>$estimated_net_profit, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'estimated_invoice_amount', 'label'=>'Est. Invoice Amount:', 'value'=>$estimated_invoice_amount, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'estimated_cost_amount', 'label'=>'Est. Cost Amount:', 'value'=>$estimated_cost_amount, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'declaration_no', 'label'=>'Declaration No:', 'required'=>true, 'value'=>$declaration_no]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Dates &amp; Transport</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'etd', 'label'=>'ETD:', 'value'=>$etd]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'eta', 'label'=>'ETA:', 'value'=>$eta]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'vessel_name', 'label'=>'Vessel Name:', 'value'=>$vessel_name]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'vessel_departure_date', 'label'=>'Vessel Departure:', 'value'=>$vessel_departure_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'flight_no', 'label'=>'Flight No:', 'value'=>$flight_no]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'flight_departure_date', 'label'=>'Flight Departure:', 'value'=>$flight_departure_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'job_completion_date', 'label'=>'Completion Date:', 'value'=>$job_completion_date]; include 'admin_elements/form_field_date.php'; ?>

                                    <?php $field = ['name'=>'payment_terms', 'label'=>'Payment Terms:', 'value'=>$payment_terms]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'hawb', 'label'=>'HAWB:', 'value'=>$hawb]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'mawb', 'label'=>'MAWB:', 'value'=>$mawb]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 mt-3">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h6 class="mb-0">Shipping &amp; Cargo Details</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-6">
                                    <?php $field = ['name'=>'commodity_type', 'label'=>'Commodity Type:', 'options_html'=>$commodity_type_options_html, 'selected'=>$commodity_type]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'container_type', 'label'=>'Container Type:', 'options_html'=>$container_type_options_html, 'selected'=>$container_type]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'no_of_containers', 'label'=>'No of Containers:', 'value'=>$no_of_containers, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'gross_weight', 'label'=>'Gross Weight:', 'value'=>$gross_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'volume_weight', 'label'=>'Volume Weight:', 'value'=>$volume_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'chargeable_weight', 'label'=>'Chargeable Weight:', 'value'=>$chargeable_weight, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'no_of_pieces', 'label'=>'No of Pieces:', 'value'=>$no_of_pieces, 'type'=>'number']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'container_number', 'label'=>'Container Number:', 'value'=>$container_number]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                                <div class="col-lg-6">
                                    <?php $field = ['name'=>'landing_country', 'label'=>'Landing Country:', 'options'=>$countries_options, 'selected'=>$landing_country]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'landing_port', 'label'=>'Landing Port:', 'value'=>$landing_port]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'destination_country', 'label'=>'Dest. Country:', 'options'=>$countries_options, 'selected'=>$destination_country]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'destination_port', 'label'=>'Dest. Port:', 'value'=>$destination_port]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_country', 'label'=>'Billing Country:', 'options'=>$countries_options, 'selected'=>$billing_country]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'billing_city', 'label'=>'Billing City:', 'value'=>$billing_city]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_state', 'label'=>'Billing State:', 'value'=>$billing_state]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'billing_code', 'label'=>'Billing Code:', 'value'=>$billing_code]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_country', 'label'=>'Shipping Country:', 'options'=>$countries_options, 'selected'=>$shipping_country]; include 'admin_elements/form_field_select.php'; ?>

                                    <?php $field = ['name'=>'shipping_city', 'label'=>'Shipping City:', 'value'=>$shipping_city]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_state', 'label'=>'Shipping State:', 'value'=>$shipping_state]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'shipping_code', 'label'=>'Shipping Code:', 'value'=>$shipping_code]; include 'admin_elements/form_field_text.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-12 mt-3">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Notes &amp; Status</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'subject', 'label'=>'Subject:', 'value'=>$subject]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'terms_and_conditions', 'label'=>'Terms &amp; Conditions:', 'value'=>$terms_and_conditions]; include 'admin_elements/form_field_textarea.php'; ?>

                                    <?php $field = ['name'=>'customer_notes', 'label'=>'Customer Notes:', 'value'=>$customer_notes]; include 'admin_elements/form_field_textarea.php'; ?>

                                    <?php $field = ['name'=>'special_comments', 'label'=>'Special Comments:', 'value'=>$special_comments]; include 'admin_elements/form_field_textarea.php'; ?>

                                    <?php $field = ['name'=>'notes', 'label'=>'Notes:', 'value'=>$notes]; include 'admin_elements/form_field_textarea.php'; ?>

                                    <div class="row mb-2">
                                        <div class="col-lg-12">
                                            <div class="form-check form-switch mt-2">
                                                <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" <?php echo $is_active == 1 ? 'checked="checked"' : ''; ?>>
                                                <label class="form-check-label fw-semibold" for="publish">Active</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0">Totals</h6>
                                </div>
                                <div class="card-body">
                                    <?php $field = ['name'=>'grand_subtotal', 'label'=>'Grand Subtotal:', 'value'=>$grand_subtotal, 'readonly'=>true, 'extra_class'=>'bg-light bg-opacity-75 text-end']; include 'admin_elements/form_field_text.php'; ?>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Grand Discount:</label>
                                        <div class="col-lg-3">
                                            <select name="grand_discount_type" id="grand_discount_type" class="form-select">
                                                <option value="">Select</option>
                                                <option value="fixed" <?php echo ($grand_discount_type === 'fixed') ? 'selected' : ''; ?>>Fixed</option>
                                                <option value="percent" <?php echo ($grand_discount_type === 'percent') ? 'selected' : ''; ?>>Percent</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-3">
                                            <input type="text" name="grand_discount_type_value" id="grand_discount_type_value" class="form-control calc-grand" value="<?php echo htmlspecialchars($grand_discount_type_value); ?>">
                                        </div>
                                        <div class="col-lg-3">
                                            <input readonly type="text" name="grand_discount_amount" id="grand_discount_amount" class="form-control bg-light bg-opacity-75 text-end" value="<?php echo htmlspecialchars($grand_discount_amount); ?>">
                                        </div>
                                    </div>

                                    <?php $field = ['name'=>'grand_after_discount', 'label'=>'After Discount:', 'value'=>$grand_after_discount, 'readonly'=>true, 'extra_class'=>'bg-light bg-opacity-75 text-end', 'col_label'=>3, 'col_input'=>9]; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'grand_tax', 'label'=>'Grand Tax:', 'value'=>$grand_tax, 'readonly'=>true, 'extra_class'=>'bg-light bg-opacity-75 text-end']; include 'admin_elements/form_field_text.php'; ?>

                                    <?php $field = ['name'=>'grand_total', 'label'=>'Grand Total:', 'value'=>$grand_total, 'readonly'=>true, 'extra_class'=>'bg-light bg-opacity-75 text-end fw-bold']; include 'admin_elements/form_field_text.php'; ?>
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

<?php
include 'admin_elements/admin_footer.php';
