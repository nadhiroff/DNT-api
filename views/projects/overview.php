<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{id: int, ref: string, name: string, status: string, created_at: string, db_name: string} $project
 * @var string                  $apiUrl
 * @var array{bytes: int, tables: list<array{name: string, rows: int, bytes: int, created_at: string|null}>} $database
 * @var int                     $tableCount
 * @var array<string, int>      $daily
 * @var int                     $requests30
 * @var int                     $keyCount
 * @var array{public: string|null, secret: string|null} $integration
 */
$this->layout('projects/layout');
$public = $integration['public'] ?? 'pk_live_…';
$env = "NOVABASE_URL=" . $apiUrl . "\nNOVABASE_KEY=" . $public . "\n# Serveur uniquement, jamais dans une app mobile ou web :\nNOVABASE_SERVICE_KEY=" . ($integration['secret'] ?? 'sk_live_…');
?>
<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <?= $this->insert('components/stat-tile', ['icon' => 'api', 'label' => 'Requêtes API (30 j)', 'value' => format_number($requests30)]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'grid', 'label' => 'Tables', 'value' => format_number($tableCount)]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'monitor', 'label' => 'Taille de la base', 'value' => format_bytes($database['bytes'])]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'key', 'label' => 'Clés API actives', 'value' => format_number($keyCount)]) ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
    <section class="card p-5 sm:p-6" aria-labelledby="integration-title">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 id="integration-title" class="flex items-center gap-2 font-semibold"><?= $this->icon('zap', 'size-5 text-accent-text') ?>Intégration</h2>
                <p class="mt-1 text-sm text-muted">Tout ce dont votre application a besoin : l'URL du projet et une clé. Pas d'identifiants MySQL : l'API fait le CRUD et l'envoi d'e-mails pour vous.</p>
            </div>
        </div>
        <div class="mt-5 space-y-4">
            <div><p class="mb-1.5 text-xs font-medium text-muted">URL du projet</p><?= $this->insert('components/copy-field', ['value' => $apiUrl, 'label' => 'l\'URL']) ?></div>
            <?php if ($integration['public'] !== null): ?>
            <div><p class="mb-1.5 text-xs font-medium text-muted">Clé publique <span class="text-accent-text">· app mobile / web</span></p><?= $this->insert('components/copy-field', ['value' => $integration['public'], 'label' => 'la clé publique']) ?></div>
            <?php endif; ?>
            <?php if ($integration['secret'] !== null): ?>
            <div><p class="mb-1.5 text-xs font-medium text-muted">Clé secrète <span class="text-danger">· serveur uniquement</span></p><?= $this->insert('components/copy-field', ['value' => $integration['secret'], 'label' => 'la clé secrète', 'secret' => true]) ?></div>
            <?php endif; ?>
            <div>
                <div class="mb-1.5 flex items-center justify-between"><p class="text-xs font-medium text-muted">Fichier .env</p><button type="button" class="btn-ghost min-h-8 px-2 text-xs" data-copy="<?= e($env) ?>"><?= $this->icon('copy', 'size-3.5') ?>Copier le .env</button></div>
                <pre class="kbd-code overflow-x-auto p-4 text-[12px]"><code><?= e(preg_replace('/(SERVICE_KEY=sk_live_.{6}).+/', '$1••••••••••••', $env) ?? '') ?></code></pre>
            </div>
        </div>
    </section>

    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold">Démarrage rapide</h2>
        <ol class="mt-4 space-y-4 text-sm">
            <?php foreach ([
                ['/tables/new', 'Créez une table', 'Colonnes, types et relations dans l\'éditeur visuel.'],
                ['/api', 'Appelez l\'API', 'GET, POST, PATCH, DELETE sur /data/<table> avec votre clé.'],
                ['/emails', 'Configurez les e-mails', 'Votre SMTP (Gmail, Outlook…) pour POST /email/send.'],
            ] as $i => [$path, $title, $text]): ?>
            <li>
                <a href="<?= $this->url('/projects/' . $project['ref'] . $path) ?>" class="group flex gap-4 rounded-lg p-2 -m-2 hover:bg-surface-2">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-full border border-line-strong font-mono text-xs text-accent-text"><?= e($i + 1) ?></span>
                    <span><span class="block font-medium group-hover:text-accent-text"><?= e($title) ?></span><span class="block text-muted"><?= e($text) ?></span></span>
                </a>
            </li>
            <?php endforeach; ?>
        </ol>
        <pre class="kbd-code mt-6 overflow-x-auto p-4 text-[12px]"><code>const res = await fetch(`${process.env.NOVABASE_URL}/data/produits?limit=20`, {
  headers: { apikey: process.env.NOVABASE_KEY },
});
const produits = await res.json();</code></pre>
    </section>
</div>

<section class="card mt-6 p-5 sm:p-6">
    <div class="flex items-center justify-between"><div><h2 class="font-semibold">Requêtes API</h2><p class="text-sm text-muted">30 derniers jours</p></div><span class="font-mono text-sm text-muted"><?= e(format_number($requests30)) ?> au total</span></div>
    <div class="mt-5 h-64"><canvas data-chart="<?= e(json_encode(['type' => 'bar', 'label' => 'Requêtes', 'labels' => array_keys($daily), 'values' => array_values($daily)], JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Requêtes API par jour : <?= e(format_number($requests30)) ?> sur 30 jours"></canvas></div>
</section>
<script src="<?= $this->asset('js/chart.umd.min.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
<script src="<?= $this->asset('js/charts.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
