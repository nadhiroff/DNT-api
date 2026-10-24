<?php
/**
 * Mise en page commune des pages légales.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $pageTitle
 * @var array{updated: string}  $legal
 */
$this->layout('layouts/site', ['title' => $pageTitle]);
?>
<div class="relative isolate overflow-hidden border-b border-line">
    <div class="bg-grid pointer-events-none absolute inset-0 -z-10 opacity-50" aria-hidden="true"></div>
    <div class="mx-auto max-w-7xl px-4 pt-10 pb-12 sm:px-6">
        <a href="<?= $this->url('/') ?>" class="mb-8 inline-flex min-h-9 items-center gap-1.5 text-sm font-medium text-muted hover:text-fg"><span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span>Retour à l'accueil</a>
        <p class="eyebrow">Légal</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"><?= e($pageTitle) ?></h1>
        <p class="mt-3 text-sm text-muted">Dernière mise à jour : <?= e($legal['updated']) ?></p>
    </div>
</div>
<div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16">
    <article class="prose-legal"><?= $this->section('body') ?></article>
</div>
