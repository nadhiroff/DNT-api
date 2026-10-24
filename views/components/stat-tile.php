<?php
/**
 * Indicateur chiffré (stat tile).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $icon
 * @var string                  $label
 * @var string                  $value
 * @var string|null             $hint
 */
$hint ??= null;
?>
<div class="card relative overflow-hidden p-4 sm:p-5">
    <div class="flex items-center justify-between gap-3">
        <p class="text-xs font-medium text-muted sm:text-sm"><?= e($label) ?></p>
        <span class="flex size-9 items-center justify-center rounded-lg border border-line bg-surface-2 text-accent-text"><?= $this->icon($icon, 'size-[18px]') ?></span>
    </div>
    <p class="mt-3 truncate text-2xl font-semibold tracking-tight tabular-nums sm:text-3xl"><?= e($value) ?></p>
    <?php if ($hint !== null): ?><p class="mt-1 text-xs text-muted"><?= e($hint) ?></p><?php endif; ?>
</div>
