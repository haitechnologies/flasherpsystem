<?php

declare(strict_types=1);

/**
 * Form Field: Select Dropdown
 *
 * Renders a labeled <select> inside a .row.mb-2 wrapper.
 *
 * Required keys in $field:
 *   'name'    => string  (select name/id)
 *   'label'   => string  (display label)
 *
 * Optional keys:
 *   'options'       => array   ([value => label, ...] — simple options)
 *   'options_html'  => string  (raw <option> HTML — for DB-driven selects)
 *   'selected'      => string  (currently selected value)
 *   'placeholder'   => string  (default empty option text, default: 'Please select')
 *   'required'      => bool    (default: false)
 *   'col_label'     => int     (default: 3)
 *   'col_input'     => int     (default: 9)
 *   'extra_class'   => string  (extra select classes, default: 'form-select')
 *   'extra_attr'    => string  (extra HTML attributes)
 *   'multiple'      => bool    (default: false)
 *   'empty_option'  => bool|string (false to hide, or string for custom empty text)
 */

if (!isset($field) || !is_array($field)) {
    return;
}

$_fsName         = $field['name'] ?? '';
$_fsLabel        = $field['label'] ?? '';
$_fsOptions      = $field['options'] ?? [];
$_fsOptionsHtml  = $field['options_html'] ?? '';
$_fsSelected     = $field['selected'] ?? '';
$_fsPlaceholder  = $field['placeholder'] ?? 'Please select';
$_fsRequired     = $field['required'] ?? false;
$_fsColLabel     = $field['col_label'] ?? 3;
$_fsColInput     = $field['col_input'] ?? 9;
$_fsExtraClass   = $field['extra_class'] ?? 'form-select';
$_fsExtraAttr    = $field['extra_attr'] ?? '';
$_fsMultiple     = $field['multiple'] ?? false;
$_fsEmptyOption  = $field['empty_option'] ?? null;
?>

<div class="row mb-2">
    <label class="col-lg-<?php echo $_fsColLabel; ?> col-form-label" for="<?php echo htmlspecialchars($_fsName); ?>">
        <?php if ($_fsRequired): ?><span class="text-danger"><?php echo htmlspecialchars($_fsLabel); ?></span><?php else: ?><?php echo htmlspecialchars($_fsLabel); ?><?php endif; ?>
    </label>
    <div class="col-lg-<?php echo $_fsColInput; ?>">
        <select
            class="<?php echo htmlspecialchars($_fsExtraClass); ?>"
            name="<?php echo htmlspecialchars($_fsName); ?>"
            id="<?php echo htmlspecialchars($_fsName); ?>"
            <?php if ($_fsMultiple): ?>multiple<?php endif; ?>
            <?php echo $_fsExtraAttr; ?>>
            <?php if ($_fsEmptyOption !== false): ?>
                <option value=""><?php echo htmlspecialchars($_fsEmptyOption ?? $_fsPlaceholder); ?></option>
            <?php endif; ?>
            <?php if (!empty($_fsOptionsHtml)): ?>
                <?php echo $_fsOptionsHtml; ?>
            <?php elseif (!empty($_fsOptions)): ?>
                <?php foreach ($_fsOptions as $_fsVal => $_fsLbl): ?>
                    <option value="<?php echo htmlspecialchars((string)$_fsVal); ?>" <?php echo ((string)$_fsVal === (string)$_fsSelected) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$_fsLbl); ?>
                    </option>
                <?php endforeach; ?>
            <?php endif; ?>
        </select>
    </div>
</div>
