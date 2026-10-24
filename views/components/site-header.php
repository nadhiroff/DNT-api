<?php
/**
 * En-tête du site public : navigation, thème, connexion / inscription (ou accès console si connecté).
 *
 * @var \App\Core\View\Template   $this
 * @var array<string, mixed>|null $currentUser
 */
$links = [
    ['/#produits', 'Produit'],
    ['/#fonctionnement', 'Comment ça marche'],
    ['/#tarifs', 'Tarifs'],
    ['/#faq', 'FAQ'],
];
$loggedIn = !empty($currentUser);
?>
<header class="sticky top-0 z-40 border-b border-line/70 bg-bg/80 backdrop-blur-xl supports-[backdrop-filter]:bg-bg/65" data-site-header>
    <div class="mx-auto flex h-16 max-w-7xl items-center justify-between gap-4 px-4 sm:px-6">
        <?= $this->insert('components/logo') ?>

        <nav class="hidden items-center gap-1 md:flex" aria-label="Navigation principale">
            <?php foreach ($links as [$href, $label]): ?>
            <a href="<?= $this->url($href) ?>" class="nav-link"><?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-2">
            <?= $this->insert('components/theme-toggle') ?>
            <div class="hidden items-center gap-2 sm:flex">
                <?php if ($loggedIn): ?>
                <a href="<?= $this->url('/console') ?>" class="btn-primary">Ouvrir la console <?= $this->icon('arrow-right', 'size-4') ?></a>
                <?php else: ?>
                <a href="<?= $this->url('/login') ?>" class="btn-ghost">Se connecter</a>
                <a href="<?= $this->url('/register') ?>" class="btn-primary">Créer un compte</a>
                <?php endif; ?>
            </div>
            <button type="button" class="btn-ghost size-11 px-0 md:hidden" data-nav-toggle aria-controls="menu-mobile" aria-expanded="false" aria-label="Ouvrir le menu">
                <span data-nav-icon-open><?= $this->icon('menu') ?></span>
                <span data-nav-icon-close hidden><?= $this->icon('x') ?></span>
            </button>
        </div>
    </div>

    <div id="menu-mobile" class="border-t border-line md:hidden" hidden>
        <nav class="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4" aria-label="Navigation mobile">
            <?php foreach ($links as [$href, $label]): ?>
            <a href="<?= $this->url($href) ?>" class="rounded-lg px-3 py-3 text-base font-medium text-fg hover:bg-surface-2" data-nav-close><?= e($label) ?></a>
            <?php endforeach; ?>
            <div class="mt-3 grid gap-2 border-t border-line pt-4">
                <?php if ($loggedIn): ?>
                <a href="<?= $this->url('/console') ?>" class="btn-primary w-full">Ouvrir la console</a>
                <?php else: ?>
                <a href="<?= $this->url('/register') ?>" class="btn-primary w-full">Créer un compte</a>
                <a href="<?= $this->url('/login') ?>" class="btn-secondary w-full">Se connecter</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
