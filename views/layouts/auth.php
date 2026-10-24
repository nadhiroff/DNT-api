<?php
/**
 * Gabarit des écrans d'authentification : formulaire à gauche, panneau de marque à droite (desktop).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 */
$this->layout('layouts/base', ['title' => $title ?? null, 'withCsrf' => true]);
?>
<div class="grid h-dvh overflow-hidden lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]">
    <!-- La page tient dans l'écran ; seul ce panneau défile si la fenêtre est très petite. -->
    <div class="flex h-dvh flex-col overflow-y-auto px-4 py-5 sm:px-10">
        <div class="flex items-center justify-between">
            <?= $this->insert('components/logo') ?>
            <?= $this->insert('components/theme-toggle') ?>
        </div>
        <main id="contenu" class="mx-auto flex w-full max-w-[440px] flex-1 flex-col justify-center py-6">
            <?= $this->section('content') ?>
        </main>
        <p class="text-center text-xs text-muted">
            <a href="<?= $this->url('/conditions') ?>" class="hover:text-fg">Conditions</a>
            <span class="mx-2" aria-hidden="true">·</span>
            <a href="<?= $this->url('/confidentialite') ?>" class="hover:text-fg">Confidentialité</a>
            <span class="mx-2" aria-hidden="true">·</span>
            <a href="<?= $this->url('/') ?>" class="hover:text-fg">Accueil</a>
        </p>
    </div>

    <aside class="relative hidden overflow-hidden border-l border-line bg-surface lg:flex lg:flex-col lg:justify-center" aria-hidden="true">
        <div class="bg-grid pointer-events-none absolute inset-0 opacity-80"></div>
        <div class="pointer-events-none absolute -top-40 -right-40 size-[34rem] rounded-full bg-accent/15 blur-3xl animate-glow"></div>
        <div class="relative mx-auto w-full max-w-lg px-12">
            <p class="eyebrow">Backend as a service</p>
            <p class="mt-4 text-3xl leading-tight font-semibold tracking-tight text-balance">Base de données, API, auth et fichiers. <span class="text-accent-text">Prêts en quelques minutes.</span></p>
            <div class="kbd-code mt-10 overflow-hidden shadow-card">
                <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-3">
                    <span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span>
                    <span class="ml-3 text-xs text-white/40">app.js</span>
                </div>
                <pre class="overflow-x-auto p-5"><code><span class="text-[#7c8a99]">// Lire les produits actifs</span>
<span class="text-[#c792ea]">const</span> res = <span class="text-[#c792ea]">await</span> <span class="text-[#82aaff]">fetch</span>(<span class="text-[#3ecf8e]">`${API}/data/products?status=eq.active`</span>, {
  headers: { apikey: <span class="text-[#3ecf8e]">'pk_live_…'</span> },
});
<span class="text-[#c792ea]">const</span> products = <span class="text-[#c792ea]">await</span> res.<span class="text-[#82aaff]">json</span>();</code></pre>
            </div>
            <ul class="mt-10 space-y-3 text-sm text-muted">
                <?php foreach (['Une base MySQL isolée par projet', 'Clés API publiques et secrètes, révocables', 'Données chiffrées, 2FA, journal d\'audit'] as $point): ?>
                <li class="flex items-center gap-3"><span class="flex size-5 items-center justify-center rounded-full bg-accent-soft text-accent-text"><?= $this->icon('check', 'size-3.5') ?></span><?= e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    </aside>
</div>
