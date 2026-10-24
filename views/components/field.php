<?php
/**
 * Champ de formulaire accessible : libellé, aide, message d'erreur relié (aria-describedby).
 *
 * @var \App\Core\View\Template      $this
 * @var string                       $name
 * @var string                       $label
 * @var string|null                  $type
 * @var string|null                  $value
 * @var string|null                  $autocomplete
 * @var string|null                  $placeholder
 * @var string|null                  $hint
 * @var string|null                  $inputmode
 * @var int|null                     $maxlength
 * @var bool|null                    $autofocus
 * @var bool|null                    $required
 * @var bool|null                    $readonly
 * @var array<string, list<string>>  $errors
 * @var string|null                  $asideHref  lien optionnel affiché à droite du libellé (chemin interne)
 * @var string|null                  $asideLabel
 */
$type ??= 'text';
$value ??= null;
$hint ??= null;
$autocomplete ??= null;
$placeholder ??= null;
$inputmode ??= null;
$maxlength ??= null;
$autofocus ??= false;
$required ??= false;
$readonly ??= false;
$asideHref ??= null;
$asideLabel ??= null;
$errors ??= [];
$id = 'f-' . $name;
$error = $errors[$name][0] ?? null;
$describedBy = trim(($hint !== null && $hint !== '' ? $id . '-hint ' : '') . ($error !== null ? $id . '-error' : ''));
$isPassword = $type === 'password';
?>
<div>
    <div class="flex items-baseline justify-between gap-3">
        <label for="<?= e($id) ?>" class="label"><?= e($label) ?></label>
        <?php if (!empty($asideHref) && !empty($asideLabel)): ?>
        <a href="<?= $this->url($asideHref) ?>" class="text-sm link"><?= e($asideLabel) ?></a>
        <?php endif; ?>
    </div>
    <div class="relative">
        <input id="<?= e($id) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>"
            class="input<?= e($isPassword ? ' pr-12' : '') ?>"
            <?php if (!$isPassword && $value !== null): ?>value="<?= e($value) ?>"<?php endif; ?>
            <?php if (!empty($autocomplete)): ?>autocomplete="<?= e($autocomplete) ?>"<?php endif; ?>
            <?php if (!empty($placeholder)): ?>placeholder="<?= e($placeholder) ?>"<?php endif; ?>
            <?php if (!empty($inputmode)): ?>inputmode="<?= e($inputmode) ?>"<?php endif; ?>
            <?php if (!empty($maxlength)): ?>maxlength="<?= e($maxlength) ?>"<?php endif; ?>
            <?php if (!empty($autofocus)): ?>autofocus<?php endif; ?>
            <?php if (!empty($required)): ?>required<?php endif; ?>
            <?php if (!empty($readonly)): ?>readonly<?php endif; ?>
            <?php if ($describedBy !== ''): ?>aria-describedby="<?= e($describedBy) ?>"<?php endif; ?>
            <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>>
        <?php if ($isPassword): ?>
        <button type="button" class="absolute inset-y-0 right-0 flex w-11 cursor-pointer items-center justify-center rounded-r-lg text-muted hover:text-fg" data-password-toggle="<?= e($id) ?>" aria-label="Afficher le mot de passe" aria-pressed="false">
            <span data-eye><?= $this->icon('eye', 'size-[18px]') ?></span>
            <span data-eye-off hidden><?= $this->icon('eye-off', 'size-[18px]') ?></span>
        </button>
        <?php endif; ?>
    </div>
    <?php if ($hint !== null && $hint !== ''): ?>
    <p id="<?= e($id) ?>-hint" class="field-hint"><?= e($hint) ?></p>
    <?php endif; ?>
    <?php if ($error !== null): ?>
    <p id="<?= e($id) ?>-error" class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($error) ?></span></p>
    <?php endif; ?>
</div>
