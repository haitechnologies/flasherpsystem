<?php

declare(strict_types=1);

/**
 * Form Field: Date Input
 *
 * Renders a labeled date input with calendar icon inside a .row.mb-2 wrapper.
 *
 * Required keys in $field:
 *   'name'  => string  (input name/id)
 *   'label' => string  (display label)
 *
 * Optional keys:
 *   'value'       => string  (default: '')
 *   'placeholder' => string  (default: label)
 *   'required'    => bool    (default: false)
 *   'col_label'   => int     (default: 3)
 *   'col_input'   => int     (default: 9)
 *   'readonly'    => bool    (default: false)
 *   'extra_class' => string  (extra input classes)
 *   'extra_attr'  => string  (extra HTML attributes)
 */

if (!isset($field) || !is_array($field)) {
    return;
}

$_fdName        = $field['name'] ?? '';
$_fdLabel       = $field['label'] ?? '';
$_fdValue       = $field['value'] ?? '';
$_fdPlaceholder = $field['placeholder'] ?? $_fdLabel;
$_fdRequired    = $field['required'] ?? false;
$_fdColLabel    = $field['col_label'] ?? 3;
$_fdColInput    = $field['col_input'] ?? 9;
$_fdReadonly    = $field['readonly'] ?? false;
$_fdExtraClass  = $field['extra_class'] ?? '';
$_fdExtraAttr   = $field['extra_attr'] ?? '';
?>

<div class="row mb-2">
    <label class="col-lg-<?php echo $_fdColLabel; ?> col-form-label" for="<?php echo htmlspecialchars($_fdName); ?>">
        <?php if ($_fdRequired): ?><span class="text-danger"><?php echo htmlspecialchars($_fdLabel); ?></span><?php else: ?><?php echo htmlspecialchars($_fdLabel); ?><?php endif; ?>
    </label>
    <div class="col-lg-<?php echo $_fdColInput; ?>">
        <div class="form-control-feedback form-control-feedback-start">
            <input
                type="text"
                class="form-control <?php echo htmlspecialchars($_fdExtraClass); ?>"
                name="<?php echo htmlspecialchars($_fdName); ?>"
                id="<?php echo htmlspecialchars($_fdName); ?>"
                value="<?php echo htmlspecialchars($_fdValue); ?>"
                placeholder="<?php echo htmlspecialchars($_fdPlaceholder); ?>"
                <?php if ($_fdReadonly): ?>readonly<?php endif; ?>
                <?php echo $_fdExtraAttr; ?>>
            <div class="form-control-feedback-icon">
                <i class="ph-calendar"></i>
            </div>
        </div>
    </div>
</div>
