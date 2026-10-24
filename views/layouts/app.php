<?php
/**
 * Gabarit de la console (utilisateur connecté).
 *
 * @var \App\Core\View\Template   $this
 * @var array<string, mixed>|null $currentUser
 * @var string                    $currentPath
 */
$this->layout('layouts/base', ['title' => $title ?? null, 'withCsrf' => true]);
$nav = [
    ['/console', 'Tableau de bord', 'grid'],
    ['/account', 'Mon compte', 'user'],
];
$userName = is_string($currentUser['name'] ?? null) ? $currentUser['name'] : '';
$userEmail = is_string($currentUser['email'] ?? null) ? $currentUser['email'] : '';
$initial = mb_strtoupper(mb_substr($userName !== '' ? $userName : $userEmail, 0, 1));
?>
<div class="min-h-dvh">
    <header class="sticky top-0 z-40 border-b border-line bg-bg/85 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4 sm:px-6">
            <?= $this->insert('components/logo') ?>
            <span class="hidden h-6 w-px bg-line sm:block" aria-hidden="true"></span>
            <nav class="hidden items-center gap-1 sm:flex" aria-label="Console">
                <?php foreach ($nav as [$href, $label, $icon]): ?>
                <a href="<?= $this->url($href) ?>" class="nav-link inline-flex items-center gap-2 <?= e($currentPath === $href ? 'bg-surface-2 text-fg' : '') ?>" <?php if ($currentPath === $href): ?>aria-current="page"<?php endif; ?>><?= $this->icon($icon, 'size-4') ?><?= e($label) ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <?= $this->insert('components/theme-toggle') ?>
                <div class="hidden text-right leading-tight md:block">
                    <p class="text-sm font-medium"><?= e($userName) ?></p>
                    <p class="text-xs text-muted"><?= e($userEmail) ?></p>
                </div>
                <span class="flex size-9 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-text" aria-hidden="true"><?= e($initial) ?></span>
                <form method="post" action="<?= $this->url('/logout') ?>">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn-ghost size-11 px-0" aria-label="Se déconnecter" title="Se déconnecter"><?= $this->icon('logout') ?></button>
                </form>
            </div>
        </div>
        <nav class="flex gap-1 overflow-x-auto border-t border-line px-4 py-2 sm:hidden" aria-label="Console (mobile)">
            <?php foreach ($nav as [$href, $label, $icon]): ?>
            <a href="<?= $this->url($href) ?>" class="nav-link inline-flex shrink-0 items-center gap-2 <?= e($currentPath === $href ? 'bg-surface-2 text-fg' : '') ?>"><?= $this->icon($icon, 'size-4') ?><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10">
        <div class="mb-6 space-y-3 empty:hidden"><?= $this->insert('components/alerts') ?></div>
        <?= $this->section('content') ?>
    </main>
</div>
