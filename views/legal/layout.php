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
    <div class="mx-auto max-w-6xl px-4 pt-16 pb-12 sm:px-6">
        <p class="eyebrow">Légal</p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl"><?= e($pageTitle) ?></h1>
        <p class="mt-3 text-sm text-muted">Dernière mise à jour : <?= e($legal['updated']) ?></p>
    </div>
</div>
<div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 sm:py-16">
    <article class="prose-legal"><?= $this->section('body') ?></article>
</div>
