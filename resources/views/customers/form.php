<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $customer_type
 * @var string $salutation
 * @var string $first_name
 * @var string $last_name
 * @var string $display_name
 * @var string $address
 * @var string $email
 * @var string $phone
 * @var string $mobile
 * @var string $contacted_date
 * @var string $description
 * @var array $tagsList
 * @var array $tags_arr
 * @var array $statusesList
 * @var string $customer_status
 * @var array $sourcesList
 * @var string $customer_source
 * @var array $usersList
 * @var string $assigned_to
 * @var int $is_active
 * @var string $customer_owner
 * @var array $taxTreatmentsList
 * @var string $tax_treatment
 * @var string $trn
 * @var string $corporate_tax_number
 * @var string $license_number
 * @var string $license_expiry
 * @var string $opening_balance
 * @var string $receivable_account_id
 * @var array $arAccountsList
 * @var array $currencyList
 * @var string $currency
 * @var string $exchange_rate
 * @var string $sales_person
 * @var string $lead_category
 * @var string $cs_agent
 * @var string $rating
 * @var string $website
 * @var string $department
 * @var string $designation
 * @var string $x
 * @var string $facebook
 * @var string $instagram
 * @var bool $canCreate
 * @var bool $canView
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0) { ?>
                    <div class="form-check form-switch ms-3 mb-0">
                        <input type="checkbox" class="form-check-input form-check-input-success" name="publish" id="publish" form="frm<?php echo $module; ?>" <?php echo $is_active == 1 ? 'checked="checked"' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="publish">Active Status</label>
                    </div>
                <?php } ?>
            </div>
            <div class="my-1">
                <?php if ($canCreate) { ?>
                    <button type="submit" form="frm<?php echo $module; ?>" class="btn btn-primary btn-sm me-2">Save</button>
                <?php } ?>
                <?php if (!empty($id)) { ?>
                    <a href="customer_overview.php?customer_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
                <?php } else { ?>
                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="content-inner">
        <div class="content">
            <?php include 'admin_elements/breadcrumb.php'; ?>
            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" autocomplete="off" enctype="multipart/form-data">
                <?php if ($id > 0) { ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php } else { ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php } ?>
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">&nbsp;</label>
                                    <div class="col-lg-9">
                                        <div class="mt-2">
                                            <div class="form-check form-check-inline">
                                                <input type="radio" class="form-check-input" name="customer_type" id="customer_type" value="business" <?php echo $customer_type == 'business' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Business</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input type="radio" class="form-check-input" name="customer_type" id="customer_type" value="individual" <?php echo $customer_type == 'individual' ? 'checked' : ''; ?>>
                                                <label class="form-check-label">Individual</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-3 col-form-label">Primary Contact: <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="The name you enter here will be for your primary contact."></i></label>
                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <select class="form-select" name="salutation" id="salutation">
                                                <option value="0"></option>
                                                <option value="mr." <?php echo $salutation == 'mr.' ? 'selected' : ''; ?>>Mr.</option>
                                                <option value="ms." <?php echo $salutation == 'ms.' ? 'selected' : ''; ?>>Ms.</option>
                                                <option value="mrs." <?php echo $salutation == 'mrs.' ? 'selected' : ''; ?>>Mrs.</option>
                                                <option value="miss." <?php echo $salutation == 'miss.' ? 'selected' : ''; ?>>Miss.</option>
                                                <option value="dr." <?php echo $salutation == 'dr.' ? 'selected' : ''; ?>>Dr.</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <input type="text" class="form-control" name="first_name" id="first_name" value="<?php echo $first_name; ?>" placeholder="First Name">
                                        </div>
                                    </div>
                                    <div class="col-lg-3">
                                        <div class="mb-2">
                                            <input type="text" class="form-control" name="last_name" id="last_name" value="<?php echo $last_name; ?>" placeholder="Last Name">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Company Name:*</span> <i class="ph-info ms-2" data-bs-popup="tooltip" data-bs-placement="right" data-bs-original-title="This name will be displayed on the transactions"></i></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="display_name" id="display_name" value="<?php echo $display_name; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label"><span class="text-danger">Address:*</span></label>
                                    <div class="col-lg-9">
                                        <input required type="text" name="address" id="address" value="<?php echo $address; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Email Address:</label>
                                    <div class="col-lg-9">
                                        <input type="email" name="email" id="email" value="<?php echo $email; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Phone:</label>
                                    <div class="col-lg-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-phone"></i></span>
                                            <input type="text" class="form-control" name="phone" id="phone" value="<?php echo $phone; ?>" placeholder="Work Phone">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-device-mobile"></i></span>
                                            <input type="text" class="form-control" name="mobile" id="mobile" value="<?php echo $mobile; ?>" placeholder="Mobile">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Contacted:</label>
                                    <div class="col-lg-4">
                                        <input type="text" name="contacted_date" id="contacted_date" value="<?php echo $contacted_date; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-3 col-form-label">Description:</label>
                                    <div class="col-lg-9">
                                        <textarea class="form-control" name="description" id="description"><?php echo $description; ?></textarea>
                                    </div>
                                </div>
                                <div class="mb-2 row">
                                    <label class="col-lg-3 col-form-label">Tags:</label>
                                    <div class="col-lg-9">
                                        <select name="tags[]" id="tags[]" class="form-control select" multiple="multiple" data-tags="true">
                                            <?php foreach ($tagsList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo in_array($row['id'], $tags_arr) ? 'selected' : ''; ?>>
                                                    <?php echo $row['value']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="mt-2">
                                            <label class="form-label">Status:</label>
                                            <select class="form-select" name="customer_status" id="customer_status">
                                                <option value='0'></option>
                                                <?php foreach ($statusesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $customer_status ? 'selected' : ''; ?>>
                                                        <?php echo $row['value']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mt-2">
                                            <label class="form-label">Source:</label>
                                            <select class="form-select" name="customer_source" id="customer_source">
                                                <option value='0'></option>
                                                <?php foreach ($sourcesList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $customer_source ? 'selected' : ''; ?>>
                                                        <?php echo $row['value']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="mt-2">
                                            <label class="form-label">Assigned To:</label>
                                            <select class="form-select" name="assigned_to" id="assigned_to">
                                                <option value='0'></option>
                                                <?php foreach ($usersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $assigned_to ? 'selected' : ''; ?>>
                                                        <?php echo $row['full_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card">
                            <div class="card-header">
                                <span class="fw-semibold">Customer Owner</span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-lg-12">
                                        <div class="">
                                            <select class="form-select" name="customer_owner" id="customer_owner">
                                                <option value='0'></option>
                                                <?php foreach ($usersList as $row): ?>
                                                    <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $customer_owner ? 'selected' : ''; ?>>
                                                        <?php echo $row['full_name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body">
                                <input type="hidden" name="payment_term" id="payment_term" value="0">
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Accounts Receivable Account:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="receivable_account_id" id="receivable_account_id">
                                            <option value=""></option>
                                            <?php foreach ($arAccountsList as $acc): ?>
                                                <option value="<?php echo $acc['id']; ?>" <?php echo (string)$acc['id'] === $receivable_account_id ? 'selected' : ''; ?>>
                                                    <?php echo $acc['account_code'] . ' - ' . $acc['account_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Opening Balance:</label>
                                    <div class="col-lg-8">
                                        <div class="input-group">
                                            <span class="input-group-text">AED</span>
                                            <input type="number" step="0.01" name="opening_balance" id="opening_balance" value="<?php echo $opening_balance; ?>" class="form-control" placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">TAX:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="tax_treatment" id="tax_treatment">
                                            <option value='0'></option>
                                            <?php foreach ($taxTreatmentsList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $tax_treatment ? 'selected' : ''; ?>>
                                                    <?php echo $row['tax_treatment']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div id="vat_fields" style="display: none;">
                                    <div class="row mb-2">
                                        <label class="col-lg-4 col-form-label">TRN #:</label>
                                        <div class="col-lg-8">
                                            <input type="text" name="trn" id="trn" value="<?php echo $trn; ?>" class="form-control">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Corporate Tax #:</label>
                                    <div class="col-lg-8">
                                        <input type="text" name="corporate_tax_number" id="corporate_tax_number" value="<?php echo $corporate_tax_number; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">License #:</label>
                                    <div class="col-lg-8">
                                        <input type="text" name="license_number" id="license_number" value="<?php echo $license_number; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Expiry:</label>
                                    <div class="col-lg-8">
                                        <input type="text" name="license_expiry" id="license_expiry" value="<?php echo $license_expiry; ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Currency:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="currency" id="currency">
                                            <option value='0'></option>
                                            <?php foreach ($currencyList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $currency ? 'selected' : ''; ?>>
                                                    <?php echo $row['currency']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <label class="col-lg-4 col-form-label">Exchange Rate:</label>
                                    <div class="col-lg-8">
                                        <input type="text" name="exchange_rate" id="exchange_rate" value="<?php echo $exchange_rate; ?>" class="form-control">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3">
                        <div class="card">
                            <div class="card-header">
                                <span class="fw-semibold">Additional Information</span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Sales Person:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="sales_person" id="sales_person">
                                            <option value='0'></option>
                                            <?php foreach ($usersList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $sales_person ? 'selected' : ''; ?>>
                                                    <?php echo $row['full_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Lead Category:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="lead_category" id="lead_category">
                                            <option value='0'></option>
                                            <option value="lead" <?php echo $lead_category == 'lead' ? 'selected' : ''; ?>>Lead Customer</option>
                                            <option value="direct" <?php echo $lead_category == 'direct' ? 'selected' : ''; ?>>Direct Customer</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">CS Agent:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="cs_agent" id="cs_agent">
                                            <option value='0'></option>
                                            <?php foreach ($usersList as $row): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo (string)$row['id'] === $cs_agent ? 'selected' : ''; ?>>
                                                    <?php echo $row['full_name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Rating:</label>
                                    <div class="col-lg-8">
                                        <select class="form-select" name="rating" id="rating">
                                            <option value='0'></option>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <option value="<?php echo $i; ?>" <?php echo (string)$i === $rating ? 'selected' : ''; ?>>
                                                    <?php echo $i; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-2 divider border-top"></div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Website:</label>
                                    <div class="col-lg-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="ph-globe"></i></span>
                                            <input type="text" class="form-control" name="website" id="website" value="<?php echo $website; ?>" placeholder="https://www.example.com">
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Department:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="department" id="department" value="<?php echo $department; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Designation:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="designation" id="designation" value="<?php echo $designation; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">X(Twitter):</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="x" id="x" value="<?php echo $x; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Facebook:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="facebook" id="facebook" value="<?php echo $facebook; ?>">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label class="col-lg-4 col-form-label">Instagram:</label>
                                    <div class="col-lg-8">
                                        <input type="text" class="form-control" name="instagram" id="instagram" value="<?php echo $instagram; ?>">
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
    $(function() {
        function toggleVatFields() {
            var val = $('#tax_treatment').val();
            if (val === '2' || val === '4') {
                $('#vat_fields').show();
            } else {
                $('#vat_fields').hide();
            }
        }
        $('#tax_treatment').on('change', toggleVatFields);
        toggleVatFields();
    });
</script>
<?php if ($canView && !$canCreate && !$canEdit) { ?>
    <script>
        $(function() {
            toggleFormElements('true');
        });
    </script>
<?php } ?>
<?php
include 'admin_elements/admin_footer.php';
