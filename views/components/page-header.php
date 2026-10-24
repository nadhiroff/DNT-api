<?php
/**
 * En-tête de page : bouton retour, titre, description et action principale facultative.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $heading
 * @var string|null             $subtitle
 * @var string|null             $back       chemin du bouton retour
 * @var string|null             $backLabel
 * @var string|null             $eyebrow
 * @var array{0: string, 1: string, 2?: string}|null $action [chemin, libellé, icône]
 */
$subtitle ??= null;
$back ??= null;
$backLabel ??= 'Retour';
$eyebrow ??= null;
$action ??= null;
?>
<div class="mb-8">
    <?php if ($back !== null): ?>
    <a href="<?= $this->url($back) ?>" class="mb-4 inline-flex min-h-9 items-center gap-1.5 rounded-lg pr-2 text-sm font-medium text-muted transition-colors hover:text-fg">
        <span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span><?= e($backLabel) ?>
    </a>
    <?php endif; ?>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <?php if ($eyebrow !== null): ?><p class="eyebrow mb-2"><?= e($eyebrow) ?></p><?php endif; ?>
            <h1 class="truncate text-2xl font-semibold tracking-tight sm:text-[1.75rem]"><?= e($heading) ?></h1>
            <?php if ($subtitle !== null): ?><p class="mt-1.5 max-w-3xl text-sm text-muted"><?= e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($action !== null): ?>
        <a href="<?= $this->url($action[0]) ?>" class="btn-primary shrink-0"><?php if (isset($action[2])): ?><?= $this->icon($action[2], 'size-4') ?><?php endif; ?><?= e($action[1]) ?></a>
        <?php endif; ?>
    </div>
</div>
