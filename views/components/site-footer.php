<?php
/**
 * Pied de page du site public.
 *
 * @var \App\Core\View\Template   $this
 * @var string                    $appName
 * @var array<string, mixed>|null $currentUser
 */
$columns = [
    'Produit' => [
        ['/#produits', 'Base de données'],
        ['/#produits', 'API REST instantanée'],
        ['/#produits', 'Authentification'],
        ['/#produits', 'Storage & e-mails'],
        ['/#tarifs', 'Tarifs'],
    ],
    'Ressources' => [
        ['/#fonctionnement', 'Comment ça marche'],
        ['/#api', 'Exemples de code'],
        ['/#faq', 'Questions fréquentes'],
    ],
    'Compte' => empty($currentUser) ? [
        ['/register', 'Créer un compte'],
        ['/login', 'Se connecter'],
        ['/forgot-password', 'Mot de passe oublié'],
    ] : [
        ['/console', 'Console'],
        ['/account', 'Mon compte'],
    ],
    'Légal' => [
        ['/mentions-legales', 'Mentions légales'],
        ['/confidentialite', 'Confidentialité'],
        ['/conditions', 'Conditions d\'utilisation'],
    ],
];
?>
<footer class="relative border-t border-line bg-surface/40">
    <div class="mx-auto max-w-7xl px-4 pt-14 pb-10 sm:px-6">
        <div class="grid gap-10 lg:grid-cols-[1.4fr_repeat(4,1fr)]">
            <div class="max-w-xs">
                <?= $this->insert('components/logo') ?>
                <p class="mt-4 text-sm text-muted">Le backend open source et auto-hébergé pour vos applications web et mobiles : base de données, API, authentification, fichiers et e-mails.</p>
            </div>
            <div class="grid grid-cols-2 gap-8 sm:grid-cols-4 lg:col-span-4">
                <?php foreach ($columns as $title => $items): ?>
                <div>
                    <h2 class="text-sm font-semibold text-fg"><?= e($title) ?></h2>
                    <ul class="mt-4 space-y-3">
                        <?php foreach ($items as [$href, $label]): ?>
                        <li><a href="<?= $this->url($href) ?>" class="text-sm text-muted transition-colors hover:text-fg"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mt-12 flex flex-col-reverse items-start justify-between gap-4 border-t border-line pt-6 sm:flex-row sm:items-center">
            <p class="text-xs text-muted">© <?= e(date('Y')) ?> <?= e($appName) ?>. Auto-hébergé, propulsé par PHP &amp; MySQL.</p>
            <div class="flex items-center gap-2 text-xs text-muted">
                <span>Thème</span>
                <?= $this->insert('components/theme-toggle') ?>
            </div>
        </div>
    </div>
</footer>
