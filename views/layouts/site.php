<?php
/**
 * Gabarit des pages publiques : en-tête, contenu, pied de page.
 *
 * @var \App\Core\View\Template $this
 */
$this->layout('layouts/base', ['title' => $title ?? null, 'description' => $description ?? null, 'withCsrf' => true]);
?>
<div class="flex min-h-dvh flex-col">
    <?= $this->insert('components/site-header') ?>
    <main id="contenu" class="flex-1">
        <?= $this->section('content') ?>
    </main>
    <?= $this->insert('components/site-footer') ?>
</div>
