<?php

declare(strict_types=1);

/**
 * Form Field: Text Input
 *
 * Renders a labeled text input inside a .row.mb-2 wrapper.
 *
 * Required keys in $field:
 *   'name'  => string  (input name/id)
 *   'label' => string  (display label)
 *
 * Optional keys:
 *   'value'        => string  (default: '')
 *   'placeholder'  => string  (default: label)
 *   'required'     => bool    (default: false — wraps label in <span class="text-danger">)
 *   'col_label'    => int     (default: 3)
 *   'col_input'    => int     (default: 9)
 *   'type'         => string  (default: 'text')
 *   'readonly'     => bool    (default: false)
 *   'extra_class'  => string  (extra input classes)
 *   'extra_attr'   => string  (extra HTML attributes)
 *   'prefix'       => string  (input-group prefix text/icon HTML)
 *   'suffix'       => string  (input-group suffix text/icon HTML)
 *   'help_text'    => string  (small text below input)
 */

if (!isset($field) || !is_array($field)) {
    return;
}

$_ffName        = $field['name'] ?? '';
$_ffLabel       = $field['label'] ?? '';
$_ffValue       = $field['value'] ?? '';
$_ffPlaceholder = $field['placeholder'] ?? '';
$_ffRequired    = $field['required'] ?? false;
$_ffColLabel    = $field['col_label'] ?? 3;
$_ffColInput    = $field['col_input'] ?? 9;
$_ffType        = $field['type'] ?? 'text';
$_ffReadonly    = $field['readonly'] ?? false;
$_ffExtraClass  = $field['extra_class'] ?? '';
$_ffExtraAttr   = $field['extra_attr'] ?? '';
$_ffPrefix      = $field['prefix'] ?? '';
$_ffSuffix      = $field['suffix'] ?? '';
$_ffHelpText    = $field['help_text'] ?? '';
?>

<div class="row mb-2">
    <label class="col-lg-<?php echo $_ffColLabel; ?> col-form-label" for="<?php echo htmlspecialchars($_ffName); ?>">
        <?php if ($_ffRequired): ?><span class="text-danger"><?php echo htmlspecialchars($_ffLabel); ?></span><?php else: ?><?php echo htmlspecialchars($_ffLabel); ?><?php endif; ?>
    </label>
    <div class="col-lg-<?php echo $_ffColInput; ?>">
        <?php if (!empty($_ffPrefix) || !empty($_ffSuffix)): ?>
            <div class="input-group">
                <?php if (!empty($_ffPrefix)): ?>
                    <span class="input-group-text"><?php echo $_ffPrefix; ?></span>
                <?php endif; ?>
                <input
                    type="<?php echo htmlspecialchars($_ffType); ?>"
                    class="form-control <?php echo htmlspecialchars($_ffExtraClass); ?>"
                    name="<?php echo htmlspecialchars($_ffName); ?>"
                    id="<?php echo htmlspecialchars($_ffName); ?>"
                    value="<?php echo htmlspecialchars($_ffValue); ?>"
                    placeholder="<?php echo htmlspecialchars($_ffPlaceholder); ?>"
                    <?php if ($_ffReadonly): ?>readonly<?php endif; ?>
                    <?php echo $_ffExtraAttr; ?>>
                <?php if (!empty($_ffSuffix)): ?>
                    <span class="input-group-text"><?php echo $_ffSuffix; ?></span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <input
                type="<?php echo htmlspecialchars($_ffType); ?>"
                class="form-control <?php echo htmlspecialchars($_ffExtraClass); ?>"
                name="<?php echo htmlspecialchars($_ffName); ?>"
                id="<?php echo htmlspecialchars($_ffName); ?>"
                value="<?php echo htmlspecialchars($_ffValue); ?>"
                placeholder="<?php echo htmlspecialchars($_ffPlaceholder); ?>"
                <?php if ($_ffReadonly): ?>readonly<?php endif; ?>
                <?php echo $_ffExtraAttr; ?>>
        <?php endif; ?>
        <?php if (!empty($_ffHelpText)): ?>
            <small class="text-muted"><?php echo $_ffHelpText; ?></small>
        <?php endif; ?>
    </div>
</div>
