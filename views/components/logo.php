<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 */
?>
<a href="<?= $this->url('/') ?>" class="inline-flex items-center gap-2.5 rounded-lg font-semibold tracking-tight text-fg" aria-label="<?= e($appName) ?> — accueil">
    <svg class="size-7" viewBox="0 0 32 32" fill="none" aria-hidden="true">
        <rect width="32" height="32" rx="8" class="fill-accent-soft"/>
        <path d="M10 22V10l12 12V10" stroke="currentColor" class="text-accent" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    <span><?= e($appName) ?></span>
</a>
