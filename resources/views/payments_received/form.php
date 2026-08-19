<?php

declare(strict_types=1);
/**
 * @var int $id
 * @var string $module
 * @var string $moduleCaption
 * @var string $payment_no
 * @var string $payment_status
 * @var string $customer_id
 * @var string $total_amount_received
 * @var string $bank_charges
 * @var string $payment_date
 * @var string $payment_method
 * @var string $deposit_to
 * @var string $reference_no
 * @var int $is_active
 * @var int $total_rows
 * @var array $item_id_arr
 * @var array $amount_received_on_arr
 * @var array $amount_received_arr
 * @var array $customersList
 * @var array $paymentMethodsList
 * @var array $depositAccountsList
 * @var array $invoicesList
 * @var int $post_invoice_id
 * @var bool $canCreate
 * @var bool $canEdit
 */
include 'admin_elements/admin_header.php';
?>

<div class="content-wrapper">

    <!-- Page header -->
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1">
                <h5 class="mb-0"><?php if ($id > 0) { ?>Edit<?php } else { ?>New<?php } ?> <?php echo $moduleCaption; ?></h5>
            </div>

            <div class="my-1 d-flex align-items-center gap-2">
                <?php if ($id > 0 && $canEdit): ?>
                    <button type="button" onclick="document.getElementById('frm<?php echo $module; ?>').submit();" class="btn btn-primary btn-sm">Save</button>
                <?php elseif ($id == 0 && $canCreate): ?>
                    <button type="button" onclick="document.getElementById('frm<?php echo $module; ?>').submit();" class="btn btn-primary btn-sm">Save</button>
                <?php endif; ?>

                <?php if ($id > 0): ?>
                    <a href="payment_received_overview.php?payment_received_id=<?php echo $id; ?>" class="btn btn-light btn-sm">Cancel</a>
                <?php else: ?>
                    <a href="listing_<?php echo $module; ?>.php" class="btn btn-light btn-sm">Cancel</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- /page header -->

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>

            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Payments record money received from customers against outstanding invoices. They reduce the receivable and increase your bank/cash balance. Affects: Customer Aging, General Ledger.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo htmlspecialchars((string)$error_message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="customer_id" id="customer_id" value="<?php echo htmlspecialchars($customer_id, ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="post_invoice_id" id="post_invoice_id" value="<?php echo (int)$post_invoice_id; ?>" />
                <input type="hidden" name="payment_status" id="payment_status" value="<?php echo htmlspecialchars($payment_status, ENT_QUOTES, 'UTF-8'); ?>" />
                <input type="hidden" name="save_and_send" id="save_and_send" value="" />
                <?php if ($id > 0): ?>
                    <input type="hidden" name="action" id="action" value="update_<?php echo $module; ?>" />
                    <input type="hidden" name="id" id="id" value="<?php echo $id; ?>" />
                <?php else: ?>
                    <input type="hidden" name="action" id="action" value="add_<?php echo $module; ?>" />
                <?php endif; ?>

                <div class="col-xl-12">
                    <div class="row">
                        <div class="col-lg-7">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Customer Name:*</span> </label>
                                        <div class="col-lg-9">
                                            <?php if ($id > 0 || $post_invoice_id > 0): ?>
                                                <input type="text" readonly class="form-control bg-light" name="" id="" value="<?php echo htmlspecialchars((string)getTableAttr('display_name', \App\Core\DB::CUSTOMERS, $customer_id), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php else: ?>
                                                <select name="customer_id" id="customer_id" class="form-control select" onchange="if(this.value > 0) { window.location.href='?mod=<?php echo $module; ?>&customer_id=' + this.value; }">
                                                    <option value='0'>Please select</option>
                                                    <?php foreach ($customersList as $row): ?>
                                                        <option value="<?php echo (int)$row['id']; ?>" <?php if ((string)$row['id'] === $customer_id) { ?>selected<?php } ?>>
                                                            <?php echo htmlspecialchars((string)($row['display_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label fw-semibold">Total Amount Received:</label>
                                        <div class="col-lg-9">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control fw-semibold bg-light opacity-50" placeholder="0" name="total_amount_received" id="total_amount_received" value="<?php echo htmlspecialchars($total_amount_received, ENT_QUOTES, 'UTF-8'); ?>" />
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Bank Charges (if any):</label>
                                        <div class="col-lg-9">
                                            <input type="number" class="form-control" name="bank_charges" id="bank_charges" value="<?php echo htmlspecialchars($bank_charges, ENT_QUOTES, 'UTF-8'); ?>">
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Payment Date:*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" name="payment_date" id="payment_date" value="<?php echo htmlspecialchars($payment_date, ENT_QUOTES, 'UTF-8'); ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-lg-3 col-form-label">Payment Mode: </label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="payment_method" id="payment_method">
                                                <option value="" <?php if ($payment_method === '') { ?>selected<?php } ?> class="fw-semibold text-black">Select Payment Mode</option>
                                                <?php foreach ($paymentMethodsList as $row): ?>
                                                    <option value="<?php echo (int)$row['id']; ?>" <?php if ((string)$row['id'] === $payment_method) { ?>selected<?php } ?>>
                                                        <?php echo htmlspecialchars((string)($row['payment_method'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-1">
                                        <label class="col-lg-3 col-form-label"><span class="text-danger">Deposit To:*</span></label>
                                        <div class="col-lg-9">
                                            <select required class="form-select" name="deposit_to" id="deposit_to">
                                                <option value="0" class="fw-semibold text-black" disabled>Select Deposit Account</option>
                                                <?php echo fetchAccountsDropdown(array(1), '', $deposit_to); ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Reference#:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="Reference no" name="reference_no" id="reference_no" value="<?php echo htmlspecialchars($reference_no, ENT_QUOTES, 'UTF-8'); ?>">
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
                            <div class="col-lg-2">
                                <label class="form-label ms-3">DATE</label>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label">INVOICE NUMBER</label>
                            </div>
                            <div class="col-lg-2 text-end">
                                <label class="form-label">INVOICE AMOUNT</label>
                            </div>
                            <div class="col-lg-2 text-end">
                                <label class="form-label">AMOUNT DUE</label>
                            </div>
                            <div class="col-lg-2 text-end">
                                <label class="form-label pe-4"><span class="text-danger">AMOUNT RECEIVED ON*</span></label>
                            </div>
                            <div class="col-lg-2 text-end">
                                <label class="form-label pe-4"><span class="text-danger">PAYMENT*</span></label>
                            </div>
                        </div>

                        <div class="card">
                            <div class="row card-body">
                                <div class="col-lg-12">
                                    <?php if (!empty($invoicesList)): ?>
                                        <?php $counter = 0; ?>
                                        <?php foreach ($invoicesList as $index => $inv): ?>
                                            <?php $payment_item = $index + 1; $counter++; ?>
                                            <?php $invoiceDate = $inv['invoice_date']; ?>
                                            <?php $invoiceNo = $inv['invoice_no']; ?>
                                            <?php $grandTotal = $inv['grand_total']; ?>
                                            <?php $amountDue = $inv['balance_due']; ?>
                                            <?php $savedOnRaw = $amount_received_on_arr[$index] ?? ''; $savedOn = (!empty($savedOnRaw) && $savedOnRaw !== '1970-01-01') ? $savedOnRaw : ''; ?>
                                            <?php $defaultAmount = !empty($amount_received_arr[$index]) ? $amount_received_arr[$index] : '0.00'; ?>

                                            <div class="mb-2">
                                                <div class="row mb-3 pb-3" id="row_<?php echo $payment_item; ?>">
                                                    <div class="col-lg-12">
                                                        <div class="row">
                                                            <input type="hidden" name="item_id[]" id="item_id<?php echo $payment_item; ?>" value="<?php echo (int)$inv['id']; ?>">

                                                            <?php
                                                            $customerTerm = getTableAttr('payment_term', \App\Core\DB::CUSTOMERS, $customer_id);
                                                            $termDuration = getTableAttr('payment_term', \App\Core\DB::PAYMENT_TERMS, $customerTerm);
                                                            $displayDueDate = calculateInvoiceDueDate('', $invoiceDate, $termDuration);
                                                            ?>

                                                            <div class="col-lg-2">
                                                                <?php echo dd_((string)$invoiceDate); ?>
                                                                <div class="small text-muted">Due Date: <?php echo dd_((string)$displayDueDate); ?></div>
                                                            </div>
                                                            <div class="col-lg-2">
                                                                <a href="invoice_overview.php?invoice_id=<?php echo (int)$inv['id']; ?>" target="_blank"><?php echo htmlspecialchars((string)$invoiceNo, ENT_QUOTES, 'UTF-8'); ?></a>
                                                            </div>
                                                            <div class="col-lg-2 text-end">
                                                                <?php echo number_format((float)$grandTotal, 2); ?>
                                                            </div>
                                                            <div class="col-lg-2 text-end">
                                                                <?php echo number_format((float)$amountDue, 2); ?>
                                                            </div>
                                                            <div class="col-lg-2 text-end">
                                                                <div class="form-control-feedback form-control-feedback-start">
                                                                    <input type="text" name="amount_received_on[]" id="amount_received_on<?php echo $payment_item; ?>" class="form-control text-end datepicker-dd-mm-yy" value="<?php echo htmlspecialchars(!empty($savedOn) ? dd_((string)$savedOn, 'd-m-Y') : date('d-m-Y'), ENT_QUOTES, 'UTF-8'); ?>" onchange="calculateGrand();" onkeyup="calculateGrand();">
                                                                    <div class="form-control-feedback-icon">
                                                                        <i class="ph-calendar"></i>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="col-lg-2 text-end">
                                                                <input type="number" name="amount_received[]" id="amount_received<?php echo $payment_item; ?>" min="0" step="any" value="<?php echo htmlspecialchars(!empty($defaultAmount) ? number_format((float)$defaultAmount, 2, '.', '') : '0.00', ENT_QUOTES, 'UTF-8'); ?>" class="form-control text-end" onchange="calculateGrand();" onkeyup="calculateGrand();">
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted text-center py-3">No invoices found for the selected customer.</div>
                                    <?php endif; ?>
                                </div>

                                <script>
                                    $(function() {
                                        $('.datepicker-dd-mm-yy').each(function() {
                                            if (!$(this).hasClass('hasDatepicker')) {
                                                $(this).datepicker({
                                                    dateFormat: 'dd-mm-yy',
                                                    changeMonth: true,
                                                    changeYear: true
                                                });
                                            }
                                        });
                                    });

                                    function calculateGrand() {
                                        var totalRows = document.getElementById('total_rows').value;
                                        var finalTotal = 0;
                                        for (var i = 1; i <= totalRows; i++) {
                                            var el = document.getElementById('amount_received' + i);
                                            if (el) {
                                                finalTotal += Number(el.value || 0);
                                            }
                                        }
                                        document.getElementById('grand_total').value = parseFloat(finalTotal.toFixed(2));
                                        document.getElementById('total_amount_received').value = parseFloat(finalTotal.toFixed(2));
                                    }
                                </script>

                                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo count($invoicesList) > 0 ? count($invoicesList) : $total_rows; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <span class="text-muted">**List contains only UNPAID invoices (excludes paid, written off and void)</span>
                        </div>
                        <div class="col-lg-4">
                            <div class="card ">
                                <div class="card-body">
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Total</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" name="grand_total" id="grand_total" value="<?php echo htmlspecialchars($total_amount_received, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                                            </div>
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
</div>

<?php include 'admin_elements/admin_footer.php'; ?>
