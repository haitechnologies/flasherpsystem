<?php

declare(strict_types=1);

/**
 * Form Field: Textarea
 *
 * Renders a labeled <textarea> inside a .row.mb-2 wrapper.
 *
 * Required keys in $field:
 *   'name'  => string  (textarea name/id)
 *   'label' => string  (display label)
 *
 * Optional keys:
 *   'value'       => string  (default: '')
 *   'placeholder' => string  (default: label)
 *   'required'    => bool    (default: false)
 *   'col_label'   => int     (default: 3)
 *   'col_input'   => int     (default: 9)
 *   'rows'        => int     (default: 3)
 *   'readonly'    => bool    (default: false)
 *   'extra_class' => string  (extra textarea classes)
 *   'extra_attr'  => string  (extra HTML attributes)
 */

if (!isset($field) || !is_array($field)) {
    return;
}

$_ftName        = $field['name'] ?? '';
$_ftLabel       = $field['label'] ?? '';
$_ftValue       = $field['value'] ?? '';
$_ftPlaceholder = $field['placeholder'] ?? '';
$_ftRequired    = $field['required'] ?? false;
$_ftColLabel    = $field['col_label'] ?? 3;
$_ftColInput    = $field['col_input'] ?? 9;
$_ftRows        = $field['rows'] ?? 3;
$_ftReadonly    = $field['readonly'] ?? false;
$_ftExtraClass  = $field['extra_class'] ?? '';
$_ftExtraAttr   = $field['extra_attr'] ?? '';
?>

<div class="row mb-2">
    <label class="col-lg-<?php echo $_ftColLabel; ?> col-form-label" for="<?php echo htmlspecialchars($_ftName); ?>">
        <?php if ($_ftRequired): ?><span class="text-danger"><?php echo htmlspecialchars($_ftLabel); ?></span><?php else: ?><?php echo htmlspecialchars($_ftLabel); ?><?php endif; ?>
    </label>
    <div class="col-lg-<?php echo $_ftColInput; ?>">
        <textarea
            class="form-control <?php echo htmlspecialchars($_ftExtraClass); ?>"
            name="<?php echo htmlspecialchars($_ftName); ?>"
            id="<?php echo htmlspecialchars($_ftName); ?>"
            placeholder="<?php echo htmlspecialchars($_ftPlaceholder); ?>"
            rows="<?php echo (int) $_ftRows; ?>"
            <?php if ($_ftReadonly): ?>readonly<?php endif; ?>
            <?php echo $_ftExtraAttr; ?>><?php echo htmlspecialchars($_ftValue); ?></textarea>
    </div>
</div>
