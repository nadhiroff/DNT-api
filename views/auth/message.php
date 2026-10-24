<?php
/**
 * Écran de résultat générique (lien expiré, etc.).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $title
 * @var string                  $message
 * @var string                  $tone        success | error
 * @var string                  $actionLabel
 * @var string                  $actionPath
 */
$this->layout('layouts/auth', ['title' => $title]);
?>
<div class="animate-fade-up">
    <span class="flex size-12 items-center justify-center rounded-xl <?= e($tone === 'error' ? 'bg-danger-soft text-danger' : 'bg-accent-soft text-accent-text') ?>"><?= $this->icon($tone === 'error' ? 'alert' : 'check', 'size-6') ?></span>
    <h1 class="mt-6 text-2xl font-semibold tracking-tight"><?= e($title) ?></h1>
    <p class="mt-3 text-muted"><?= e($message) ?></p>
    <a href="<?= $this->url($actionPath) ?>" class="btn-primary mt-8 w-full"><?= e($actionLabel) ?></a>
</div>
