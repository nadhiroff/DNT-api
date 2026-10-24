<?php
/**
 * Coque de la console : barre latérale fixe (desktop) / tiroir avec burger (mobile),
 * barre supérieure, notifications. L'entrée active est mise en surbrillance.
 *
 * @var \App\Core\View\Template   $this
 * @var array<string, mixed>|null $currentUser
 * @var string                    $currentPath
 * @var string                    $appName
 */
$this->layout('layouts/base', ['title' => $title ?? null, 'withCsrf' => true]);
$isSuperadmin = ($currentUser['role'] ?? null) === 'superadmin';
$sections = [
    'Console' => [
        ['/console', 'Tableau de bord', 'grid'],
        ['/projects', 'Projets', 'database'],
    ],
];
if ($isSuperadmin) {
    $sections['Administration'] = [
        ['/admin', 'Vue d\'ensemble', 'monitor'],
        ['/admin/users', 'Utilisateurs', 'user'],
        ['/admin/projects', 'Tous les projets', 'folder'],
        ['/admin/invitations', 'Invitations', 'user'],
        ['/admin/emails', 'E-mails (SMTP)', 'mail'],
        ['/admin/logins', 'Connexions', 'shield'],
        ['/admin/testimonials', 'Témoignages', 'message'],
        ['/admin/community', 'Communauté', 'settings'],
    ];
}
$sections['Compte'] = [['/account', 'Mon compte', 'user']];

// Entrée active : le préfixe le plus long qui correspond au chemin courant.
$active = '';
foreach ($sections as $items) {
    foreach ($items as [$href]) {
        if (($currentPath === $href || str_starts_with($currentPath, rtrim($href, '/') . '/')) && strlen($href) > strlen($active)) {
            $active = $href;
        }
    }
}
$userName = is_string($currentUser['name'] ?? null) ? $currentUser['name'] : '';
$userEmail = is_string($currentUser['email'] ?? null) ? $currentUser['email'] : '';
$initial = mb_strtoupper(mb_substr($userName !== '' ? $userName : $userEmail, 0, 1));
?>
<div class="min-h-dvh lg:pl-64">
    <!-- Fond du tiroir mobile -->
    <div class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden" data-sidebar-backdrop hidden></div>

    <!-- Barre latérale -->
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 flex w-72 -translate-x-full flex-col border-r border-line bg-surface transition-transform duration-300 ease-out data-[open=true]:translate-x-0 lg:w-64 lg:translate-x-0" data-sidebar data-open="false" aria-label="Navigation de la console">
        <div class="flex h-16 shrink-0 items-center justify-between border-b border-line px-5">
            <?= $this->insert('components/logo') ?>
            <button type="button" class="btn-ghost size-10 px-0 lg:hidden" data-sidebar-close aria-label="Fermer le menu"><?= $this->icon('x') ?></button>
        </div>

        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
            <?php foreach ($sections as $label => $items): ?>
            <div>
                <p class="px-3 pb-2 font-mono text-[10px] font-medium tracking-[0.18em] text-muted uppercase"><?= e($label) ?></p>
                <ul class="space-y-0.5">
                    <?php foreach ($items as [$href, $name, $icon]): ?>
                    <?php $isActive = $href === $active; ?>
                    <li>
                        <a href="<?= $this->url($href) ?>"
                           class="group relative flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors <?= e($isActive ? 'bg-accent-soft text-fg' : 'text-muted hover:bg-surface-2 hover:text-fg') ?>"
                           <?php if ($isActive): ?>aria-current="page"<?php endif; ?>>
                            <?php if ($isActive): ?><span class="absolute top-2 bottom-2 left-0 w-[3px] rounded-r-full bg-accent" aria-hidden="true"></span><?php endif; ?>
                            <?= $this->icon($icon, 'size-[18px] ' . ($isActive ? 'text-accent-text' : 'text-muted group-hover:text-fg')) ?>
                            <?= e($name) ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </nav>

        <div class="shrink-0 border-t border-line p-3">
            <div class="flex items-center gap-3 rounded-lg p-2">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-accent-soft text-sm font-semibold text-accent-text" aria-hidden="true"><?= e($initial) ?></span>
                <div class="min-w-0 flex-1 leading-tight">
                    <p class="truncate text-sm font-medium"><?= e($userName) ?></p>
                    <p class="truncate text-xs text-muted"><?= e($userEmail) ?></p>
                </div>
                <form method="post" action="<?= $this->url('/logout') ?>" data-confirm="Êtes-vous sûr de vouloir vous déconnecter ?" data-confirm-title="Déconnexion" data-confirm-ok="Oui, me déconnecter" data-confirm-cancel="Non">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn-ghost size-9 px-0" aria-label="Se déconnecter" title="Se déconnecter"><?= $this->icon('logout', 'size-[18px]') ?></button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Barre supérieure -->
    <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-line bg-bg/85 px-4 backdrop-blur-xl sm:px-6 lg:px-8">
        <button type="button" class="btn-ghost size-10 px-0 lg:hidden" data-sidebar-open aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu"><?= $this->icon('menu') ?></button>
        <div class="lg:hidden"><?= $this->insert('components/logo') ?></div>
        <div class="ml-auto flex items-center gap-2">
            <?php if ($isSuperadmin): ?>
            <span class="badge hidden border-accent/40 text-accent-text sm:inline-flex"><?= $this->icon('shield', 'size-3.5') ?>Superadmin</span>
            <?php endif; ?>
            <?= $this->insert('components/theme-toggle') ?>
        </div>
    </header>

    <main id="contenu" class="mx-auto w-full max-w-[1400px] px-4 py-8 sm:px-6 lg:px-10 lg:py-10">
        <?= $this->section('content') ?>
    </main>
</div>
<?= $this->insert('components/alerts') ?>
