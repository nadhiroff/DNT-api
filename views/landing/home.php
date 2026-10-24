<?php
/**
 * Accueil provisoire (la landing page complète arrive en phase 3).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 */
$this->layout('layouts/base', ['title' => 'Votre backend en quelques minutes']);
// Classes littérales (et non un attribut style) : la CSP interdit les styles en ligne.
$delays = ['[animation-delay:80ms]', '[animation-delay:140ms]', '[animation-delay:200ms]', '[animation-delay:260ms]', '[animation-delay:320ms]', '[animation-delay:380ms]'];
$products = [
    ['Base de données', 'MySQL isolée par projet, éditeur visuel de tables.'],
    ['API REST instantanée', 'Filtres, tri, pagination et règles d\'accès par table.'],
    ['Authentification', 'Inscription, connexion, JWT et refresh tokens rotatifs.'],
    ['Storage', 'Buckets publics ou privés, URL signées à durée limitée.'],
    ['E-mails', 'SMTP par projet, file d\'attente et historique d\'envoi.'],
    ['Clés API', 'Clés publiques et secrètes, permissions fines, révocation immédiate.'],
];
?>
<div class="relative isolate min-h-dvh overflow-hidden">
    <div class="bg-grid pointer-events-none absolute inset-0 -z-10 opacity-70" aria-hidden="true"></div>
    <div class="pointer-events-none absolute top-[-14rem] left-1/2 -z-10 size-[42rem] -translate-x-1/2 rounded-full bg-accent/15 blur-3xl animate-glow" aria-hidden="true"></div>

    <header class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
        <?= $this->insert('components/logo') ?>
        <?= $this->insert('components/theme-toggle') ?>
    </header>

    <main id="contenu" class="mx-auto max-w-6xl px-4 pt-16 pb-24 sm:px-6 sm:pt-24">
        <section class="mx-auto max-w-3xl text-center animate-fade-up">
            <span class="badge"><span class="size-1.5 rounded-full bg-accent" aria-hidden="true"></span>Auto-hébergé · open source · gratuit</span>
            <h1 class="mt-6 text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                Votre backend <span class="text-accent-text">en quelques minutes</span>
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-muted text-pretty">
                Créez un projet, définissez vos tables, et obtenez immédiatement une API REST sécurisée,
                l'authentification de vos utilisateurs, le stockage de fichiers et l'envoi d'e-mails.
            </p>
            <p class="mx-auto mt-8 inline-flex items-center gap-2 rounded-lg border border-line bg-surface/70 px-3 py-2 font-mono text-xs text-muted backdrop-blur sm:text-sm">
                <span class="text-accent-text" aria-hidden="true">$</span> curl <?= e($appUrl) ?>/api/v1/&lt;ref&gt;/data/products
            </p>
        </section>

        <section class="mt-20 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" aria-label="Produits">
            <?php foreach ($products as $i => [$name, $text]): ?>
            <article class="card group p-6 transition-colors duration-200 hover:border-line-strong animate-fade-up <?= e($delays[$i] ?? '') ?>">
                <h2 class="font-medium"><?= e($name) ?></h2>
                <p class="mt-2 text-sm text-muted"><?= e($text) ?></p>
            </article>
            <?php endforeach; ?>
        </section>
    </main>
</div>
