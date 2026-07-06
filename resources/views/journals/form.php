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
 * @var string $journal_status
 * @var string $journal_date
 * @var string $journal_no
 * @var string $reference_no
 * @var string $notes
 * @var string $reporting_method
 * @var string $currency
 * @var string $grand_subtotal
 * @var string $grand_total
 * @var int $publish
 * @var int $total_rows
 * @var array $item_id_arr
 * @var array $account_arr
 * @var array $description_arr
 * @var array $debit_arr
 * @var array $credit_arr
 * @var bool $canCreate
 * @var bool $canEdit
 * @var string $account_tree_options
 * @var string $currency_options
 */
use App\Core\DB;

include 'admin_elements/admin_header.php';

function buildAccountTreeOptionsView($db, $parent_id = 0, $level = 0, $selected_id = 0) {
    $options = '';
    $prefix = str_repeat('— ', $level);
    $rows = $db->fetchAll("SELECT * FROM `" . DB::ACCOUNTS . "` WHERE parent_id = :pid AND is_active = 1 ORDER BY account_code ASC, account_name ASC", ['pid' => $parent_id]);
    foreach ($rows as $row) {
        $account_id = $row['id'];
        $display_name = (!empty($row['account_code']) ? $row['account_code'] . ' - ' . $row['account_name'] : $row['account_name']);
        $display_name = $prefix . $display_name;
        $sel = ($account_id == $selected_id) ? ' selected="selected"' : '';
        $options .= '<option value="' . $account_id . '" ' . $sel . '>' . htmlspecialchars($display_name, ENT_QUOTES) . '</option>';
        $options .= buildAccountTreeOptionsView($db, $account_id, $level + 1, $selected_id);
    }
    return $options;
}

if (!isset($db) || $db === null) {
    $db = \App\Core\Container::getInstance()->get(\App\Core\Database::class);
}
$treeOptions = buildAccountTreeOptionsView($db, 0, 0, 0);
$treeOptionsJs = addslashes($treeOptions);
?>
<div class="content-wrapper">
    <div class="page-header page-header-light shadow carriers-page-header">
        <div class="page-header-content border-top py-2 px-3 carriers-page-header-content">
            <div class="my-1 d-flex align-items-center gap-2">
                <h5 class="mb-0"><?php echo ($id > 0) ? 'Edit' : 'New'; ?> <?php echo $moduleCaption; ?></h5>
                <?php if ($id > 0): ?>
                    <span class="text-muted small fw-semibold ms-2">Journal #: <?php echo $journal_no; ?></span>
                <?php endif; ?>
                <span class="badge bg-light text-primary border-primary ms-2"><?php echo ((!empty($journal_status)) ? ucwords($journal_status) : ''); ?></span>
            </div>
            <div class="my-1">
                <?php if ($canCreate || $canEdit): ?>
                    <button type="button" onclick="if(validateJournalEntry()) { document.getElementById('journal_status').value='draft'; document.getElementById('frm<?php echo $module; ?>').submit(); }" class="btn btn-primary btn-sm me-2"><?php echo ($id > 0) ? 'Update' : 'Save as Draft'; ?></button>
                    <button type="button" onclick="if(validateJournalEntry()) { document.getElementById('frm<?php echo $module; ?>').submit(); }" class="btn btn-info btn-sm me-2">Save and Publish</button>
                <?php endif; ?>
                <a href="listing_journals.php" class="btn btn-light btn-sm">Cancel</a>
            </div>
        </div>
    </div>

    <div class="content-inner">
        <div class="content">
            <?php include('admin_elements/breadcrumb.php'); ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="ph-info me-2"></i>
                <strong>How this works:</strong> Journals let you make direct adjustments to any account. Every entry must balance (total debits = total credits). Affects all reports: General Ledger, Trial Balance, P&amp;L, Balance Sheet.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>

            <form class="steps-basic clearfix" method="post" id="frm<?php echo $module; ?>" name="frm<?php echo $module; ?>" action="<?php echo $module; ?>.php" enctype="multipart/form-data">
                <input type="hidden" name="journal_status" id="journal_status" value="<?php echo $journal_status; ?>" />
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
                                <div class="card-header d-flex align-items-center">
                                    <h6 class="mb-0"><?php echo ($id > 0) ? 'Update' : 'New'; ?> Journal</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Date: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <div class="form-control-feedback form-control-feedback-start">
                                                <input type="text" class="form-control" placeholder="Journal Date" name="journal_date" id="journal_date" value="<?php echo $journal_date; ?>">
                                                <div class="form-control-feedback-icon">
                                                    <i class="ph-calendar"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Journal#: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control text-bg-light" name="journal_no" id="journal_no" readonly value="<?php echo $journal_no; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Reference no:</label>
                                        <div class="col-lg-9">
                                            <input type="text" class="form-control" placeholder="Reference no" name="reference_no" id="reference_no" value="<?php echo $reference_no; ?>">
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Notes: <span class="text-danger">*</span></label>
                                        <div class="col-lg-9">
                                            <textarea name="notes" id="notes" rows="2" class="form-control" placeholder="Maximum 500 Characters" maxlength="500"><?php echo $notes; ?></textarea>
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <label class="col-lg-3 col-form-label">Reporting Method:</label>
                                        <div class="col-lg-9">
                                            <div class="mt-2">
                                                <div class="form-check form-check-inline">
                                                    <input type="radio" class="form-check-input" name="reporting_method" id="reporting_method_accrual_cash" value="accrual_cash" <?php if ($reporting_method == 'accrual_cash') echo 'checked'; ?>>
                                                    <label class="form-check-label">Accrual and Cash</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input type="radio" class="form-check-input" name="reporting_method" id="reporting_method_accrual" value="accrual" <?php if ($reporting_method == 'accrual') echo 'checked'; ?>>
                                                    <label class="form-check-label">Accrual Only</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input type="radio" class="form-check-input" name="reporting_method" id="reporting_method_cash" value="cash" <?php if ($reporting_method == 'cash') echo 'checked'; ?>>
                                                    <label class="form-check-label">Cash Only</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-2">
                                        <label class="col-lg-3 col-form-label">Currency: </label>
                                        <div class="col-lg-9">
                                            <select class="form-select" name="currency" id="currency">
                                                <?php
                                                $currencies = $db->fetchAll("SELECT * FROM `" . DB::CURRENCIES . "` WHERE is_active=1 ORDER BY id ASC");
                                                foreach ($currencies as $c) {
                                                    $sel = ($c['currency'] == $currency) ? ' selected' : '';
                                                    if (empty($currency) && $c['currency'] == 'AED') $sel = ' selected';
                                                    echo '<option value="' . $c['currency'] . '"' . $sel . '>' . $c['currency'] . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="col-xl-12">
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <strong>Accounting Standards for Manual Journal Entry:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Each line item can have <strong>either</strong> a Debit <strong>or</strong> a Credit (not both)</li>
                                <li>Total Debits must equal Total Credits (balanced entry)</li>
                                <li>Minimum 2 line items required (at least one debit and one credit)</li>
                                <li>Each line with an amount must have an account selected</li>
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <div class="row mb-2">
                            <div class="col-lg-2"><label class="form-label ms-3 fw-semibold">ACCOUNT <span class="text-danger">*</span></label></div>
                            <div class="col-lg-3"><label class="form-label ms-4 fw-semibold">DESCRIPTION</label></div>
                            <div class="col-lg-1"><label class="form-label ms-3 fw-semibold">DEBITS </label></div>
                            <div class="col-lg-1"><label class="form-label ms-4 fw-semibold">CREDITS </label></div>
                        </div>
                        <div class="card">
                            <div class="row card-body">
                                <div class="col-lg-12">
                                    <?php for ($journal_item = 1; $journal_item <= $total_rows; $journal_item++):
                                        $index = $journal_item - 1;
                                    ?>
                                        <div class="mb-2">
                                            <div class="row mb-3 pb-3" id="row_<?php echo $journal_item; ?>">
                                                <div class="col-lg-12">
                                                    <div class="row">
                                                        <input type="hidden" name="item_id[]" id="item_id<?php echo $journal_item; ?>" value="<?php echo (!empty($item_id_arr[$index]) ? $item_id_arr[$index] : ''); ?>">
                                                        <div class="col-lg-2">
                                                            <select class="form-select" name="account[]" id="account<?php echo $journal_item; ?>">
                                                                <option value="0">Please select</option>
                                                                <?php echo buildAccountTreeOptionsView($db, 0, 0, (!empty($account_arr[$index]) ? (int)$account_arr[$index] : 0)); ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-lg-3">
                                                            <textarea name="description[]" id="description<?php echo $journal_item; ?>" rows="2" class="form-control" placeholder="Add a description to your item"><?php echo (!empty($description_arr[$index]) ? $description_arr[$index] : ''); ?></textarea>
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="0.01" name="debit[]" id="debit<?php echo $journal_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($debit_arr[$index]) ? $debit_arr[$index] : ''); ?>" onkeyup="handleDebitCreditEntry('<?php echo $journal_item; ?>', 'debit');" onchange="handleDebitCreditEntry('<?php echo $journal_item; ?>', 'debit');" placeholder="0.00">
                                                        </div>
                                                        <div class="col-lg-1">
                                                            <input type="number" step="0.01" name="credit[]" id="credit<?php echo $journal_item; ?>" min="0" class="form-control text-center" value="<?php echo (!empty($credit_arr[$index]) ? $credit_arr[$index] : ''); ?>" onkeyup="handleDebitCreditEntry('<?php echo $journal_item; ?>', 'credit');" onchange="handleDebitCreditEntry('<?php echo $journal_item; ?>', 'credit');" placeholder="0.00">
                                                        </div>
                                                        <div class="col-lg-2 mt-1">
                                                            <?php if ($journal_item > 1): ?>
                                                                <a href="#" onclick="calculateItemAmount('<?php echo $journal_item; ?>'); clear_row(<?php echo $journal_item; ?>)"><span class="badge bg-warning"> <i class="ph-x"></i> </span></a>
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
                                    var accountOptionsHTML = '<?php echo $treeOptionsJs; ?>';

                                    function add_item_row() {
                                        var div_add_here = document.getElementById('div_add_here');
                                        var total_rows = document.getElementById('total_rows').value;
                                        total_rows++;

                                        var new_row = "";
                                        new_row += "<div class=\"row mb-3 pb-3\" id=\"row_" + total_rows + "\">";
                                        new_row += "<input type=\"hidden\" name=\"item_id[]\" id=\"item_id" + total_rows + "\">";
                                        new_row += "<div class=\"col-lg-2\">";
                                        new_row += "<select class=\"form-select\" name=\"account[]\" id=\"account" + total_rows + "\">";
                                        new_row += "<option value=\"0\">Please select</option>";
                                        new_row += accountOptionsHTML;
                                        new_row += "</select>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-3\">";
                                        new_row += "<textarea type=\"text\" name=\"description[]\" id=\"description" + total_rows + "\" rows=\"2\" min=\"0\" placeholder=\"Add a description to your item\" class=\"form-control\"></textarea>";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"0.01\" name=\"debit[]\" id=\"debit" + total_rows + "\" min=\"0\" class=\"form-control text-center\" placeholder=\"0.00\" onkeyup=\"handleDebitCreditEntry('" + total_rows + "', 'debit');\" onchange=\"handleDebitCreditEntry('" + total_rows + "', 'debit');\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1\">";
                                        new_row += "<input type=\"number\" step=\"0.01\" name=\"credit[]\" id=\"credit" + total_rows + "\" min=\"0\" class=\"form-control text-center\" placeholder=\"0.00\" onkeyup=\"handleDebitCreditEntry('" + total_rows + "', 'credit');\" onchange=\"handleDebitCreditEntry('" + total_rows + "', 'credit');\">";
                                        new_row += "</div>";
                                        new_row += "<div class=\"col-lg-1 mt-1\"><span id=\"span_remove_item_row" + total_rows + "\"> <a href=\"#\" onclick=\"clear_row(" + total_rows + ")\"><span class=\"badge bg-warning\"> <i class=\"ph-x\"></i> </span></a></span> </div>";
                                        new_row += "</div>";

                                        document.getElementById('add_row_here').insertAdjacentHTML("beforebegin", new_row);
                                        document.getElementById('total_rows').value = total_rows;
                                    }

                                    function clear_row(row_no) {
                                        document.getElementById('account' + row_no).value = '0';
                                        document.getElementById('account' + row_no).text = 'Please select';
                                        document.getElementById('description' + row_no).value = '';
                                        document.getElementById('debit' + row_no).value = '';
                                        document.getElementById('credit' + row_no).value = '';
                                        document.getElementById('row_' + row_no).style.display = 'none';
                                        calculateGrand();
                                    }

                                    function clearGrandDiscountTypeValue() {
                                    }

                                    function handleDebitCreditEntry(row_no, field_type) {
                                        var debit_elem = document.getElementById('debit' + row_no);
                                        var credit_elem = document.getElementById('credit' + row_no);
                                        if (!debit_elem || !credit_elem) return;
                                        var debit_val = parseFloat(debit_elem.value) || 0;
                                        var credit_val = parseFloat(credit_elem.value) || 0;
                                        if (field_type === 'debit' && debit_val > 0) {
                                            credit_elem.value = '';
                                            credit_elem.classList.remove('border-danger');
                                            debit_elem.classList.remove('border-danger');
                                        } else if (field_type === 'credit' && credit_val > 0) {
                                            debit_elem.value = '';
                                            debit_elem.classList.remove('border-danger');
                                            credit_elem.classList.remove('border-danger');
                                        }
                                        if (debit_val < 0) debit_elem.value = '';
                                        if (credit_val < 0) credit_elem.value = '';
                                        calculateGrand();
                                    }

                                    function calculateItemAmount(row_no) {
                                        calculateGrand();
                                    }

                                    function calculateGrand() {
                                        var total_rows = document.getElementById('total_rows').value;
                                        var total_debits = 0;
                                        var total_credits = 0;
                                        var has_entries = false;
                                        for (var i = 1; i <= total_rows; i++) {
                                            var debit_elem = document.getElementById('debit' + i);
                                            var credit_elem = document.getElementById('credit' + i);
                                            var row_elem = document.getElementById('row_' + i);
                                            if (row_elem && row_elem.style.display === 'none') continue;
                                            if (debit_elem && credit_elem) {
                                                var debit_val = parseFloat(debit_elem.value) || 0;
                                                var credit_val = parseFloat(credit_elem.value) || 0;
                                                if (debit_val < 0) { debit_elem.value = ''; debit_val = 0; }
                                                if (credit_val < 0) { credit_elem.value = ''; credit_val = 0; }
                                                total_debits += debit_val;
                                                total_credits += credit_val;
                                                if (debit_val > 0 || credit_val > 0) {
                                                    has_entries = true;
                                                }
                                            }
                                        }
                                        document.getElementById('subtotal_debits').value = total_debits.toFixed(2);
                                        document.getElementById('subtotal_credits').value = total_credits.toFixed(2);
                                        document.getElementById('grand_subtotal').value = total_debits.toFixed(2);
                                        document.getElementById('grand_total').value = total_credits.toFixed(2);
                                        var difference = total_debits - total_credits;
                                        var diff_elem = document.getElementById('difference');
                                        diff_elem.value = difference.toFixed(2);
                                        if (Math.abs(difference) > 0.01 && has_entries) {
                                            diff_elem.classList.add('border-danger');
                                            diff_elem.classList.add('fw-bold');
                                        } else {
                                            diff_elem.classList.remove('border-danger');
                                            diff_elem.classList.remove('fw-bold');
                                        }
                                        return {
                                            balanced: Math.abs(difference) < 0.01,
                                            difference: difference,
                                            has_entries: has_entries
                                        };
                                    }

                                    function validateJournalEntry() {
                                        var result = calculateGrand();
                                        var total_rows = document.getElementById('total_rows').value;
                                        var valid_entries = 0;
                                        for (var i = 1; i <= total_rows; i++) {
                                            var account_elem = document.getElementById('account' + i);
                                            var debit_elem = document.getElementById('debit' + i);
                                            var credit_elem = document.getElementById('credit' + i);
                                            var row_elem = document.getElementById('row_' + i);
                                            if (row_elem && row_elem.style.display === 'none') continue;
                                            if (account_elem && account_elem.value != '0') {
                                                var debit_val = parseFloat(debit_elem.value) || 0;
                                                var credit_val = parseFloat(credit_elem.value) || 0;
                                                if (debit_val > 0 || credit_val > 0) {
                                                    valid_entries++;
                                                }
                                            }
                                        }
                                        if (valid_entries < 2) {
                                            alert('Accounting Standard Error:\n\nA journal entry must have at least 2 line items (one debit and one credit).\n\nPlease add more entries.');
                                            return false;
                                        }
                                        if (!result.balanced && result.has_entries) {
                                            alert('Accounting Standard Error:\n\nDebits and Credits must be equal (balanced entry).\n\nCurrent Difference: ' + Math.abs(result.difference).toFixed(2) + '\n\nPlease adjust your entries to balance the journal.');
                                            return false;
                                        }
                                        for (var i = 1; i <= total_rows; i++) {
                                            var account_elem = document.getElementById('account' + i);
                                            var debit_elem = document.getElementById('debit' + i);
                                            var credit_elem = document.getElementById('credit' + i);
                                            var row_elem = document.getElementById('row_' + i);
                                            if (row_elem && row_elem.style.display === 'none') continue;
                                            var debit_val = parseFloat(debit_elem.value) || 0;
                                            var credit_val = parseFloat(credit_elem.value) || 0;
                                            if ((debit_val > 0 || credit_val > 0) && account_elem.value == '0') {
                                                alert('Accounting Standard Error:\n\nRow ' + i + ' has an amount but no account selected.\n\nPlease select an account for each line item with an amount.');
                                                account_elem.focus();
                                                return false;
                                            }
                                        }
                                        return true;
                                    }

                                    window.addEventListener('DOMContentLoaded', function() {
                                        calculateGrand();
                                    });
                                </script>

                                <input type="hidden" name="total_rows" id="total_rows" value="<?php echo $total_rows; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-4">
                            <div class="card ">
                                <div class="card-body">
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Subtotal Debits:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="subtotal_debits" id="subtotal_debits" value="0.00" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Subtotal Credits:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="subtotal_credits" id="subtotal_credits" value="0.00" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Total (AED) Debits:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input readonly type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" placeholder="0" name="grand_subtotal" id="grand_subtotal" value="<?php echo $grand_subtotal; ?>" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Total (AED) Credits:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end" name="grand_total" id="grand_total" value="<?php echo $grand_total; ?>" readonly>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row mb-1">
                                        <label class="col-lg-6 col-form-label fw-semibold">Difference:</label>
                                        <div class="col-lg-6">
                                            <div class="input-group">
                                                <span class="input-group-text"><?php echo BASE_CURRENCY['code']; ?></span>
                                                <input type="number" class="form-control fw-semibold bg-light bg-opacity-50 text-end text-danger" name="difference" id="difference" value="0.00" readonly>
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
    <?php include('admin_elements/copyright.php'); ?>
</div>
<?php include('admin_elements/admin_footer.php'); ?>
