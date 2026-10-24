<?php
/**
 * Barre de quota : vert < 70 %, orange 70–90 %, rouge > 90 %, avec libellé (jamais la couleur seule).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $label
 * @var string                  $usedLabel
 * @var string|null             $limitLabel null = illimité
 * @var float|null              $percent
 */
$level = $percent === null ? 'ok' : ($percent > 90 ? 'critical' : ($percent >= 70 ? 'warning' : 'ok'));
$colors = ['ok' => 'bg-accent', 'warning' => 'bg-warning', 'critical' => 'bg-danger'];
?>
<div>
    <div class="flex items-baseline justify-between gap-3 text-sm">
        <span class="font-medium"><?= e($label) ?></span>
        <span class="text-muted tabular-nums"><?= e($usedLabel) ?> / <?= e($limitLabel ?? 'Illimité') ?></span>
    </div>
    <div class="meter mt-2" role="meter" aria-label="<?= e($label) ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e($percent ?? 0) ?>">
        <span class="<?= e($colors[$level]) ?>" data-percent="<?= e($percent ?? ($limitLabel === null ? 3 : 0)) ?>"></span>
    </div>
    <?php if ($level !== 'ok'): ?>
    <p class="mt-1 text-xs <?= e($level === 'critical' ? 'text-danger' : 'text-warning') ?>"><?= e($level === 'critical' ? 'Limite presque atteinte' : 'Utilisation élevée') ?></p>
    <?php endif; ?>
</div>
