<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{name: string, role: string}  $user
 * @var list<array{project: array{ref: string, name: string, status: string, created_at: string, db_name: string}, bytes: int, requests: int}> $items
 * @var int                     $projectCount
 * @var array<string, int>      $daily
 * @var int                     $requests30
 * @var int                     $bytes
 * @var array<string, array{used: int, limit: int|null}> $quotas
 */
$this->layout('layouts/app', ['title' => 'Tableau de bord']);
$firstName = explode(' ', trim($user['name']))[0];
$hour = (int) date('G');
$greeting = $hour < 18 && $hour >= 5 ? 'Bonjour' : 'Bonsoir';
$q = $quotas;
?>
<?= $this->insert('components/page-header', [
    'eyebrow' => 'Tableau de bord',
    'heading' => $greeting . ' ' . $firstName,
    'subtitle' => 'Vue d\'ensemble de vos projets, de leurs bases de données et de l\'utilisation de l\'API sur les 30 derniers jours.',
    'action' => ['/projects/new', 'Nouveau projet', 'plus'],
]) ?>

<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <?= $this->insert('components/stat-tile', ['icon' => 'folder', 'label' => 'Projets', 'value' => format_number($projectCount), 'hint' => $q['projects']['limit'] === null ? 'Illimités' : 'sur ' . $q['projects']['limit'] . ' inclus dans votre offre']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'database', 'label' => 'Bases de données', 'value' => format_number($projectCount), 'hint' => 'une base MySQL isolée par projet']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'api', 'label' => 'Requêtes API (30 j)', 'value' => format_number($requests30), 'hint' => 'toutes clés confondues']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'monitor', 'label' => 'Stockage utilisé', 'value' => format_bytes($bytes), 'hint' => 'données + index']) ?>
</div>

<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="card p-5 sm:p-6 xl:col-span-2" aria-labelledby="chart-requests">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 id="chart-requests" class="font-semibold">Requêtes API</h2>
                <p class="text-sm text-muted">30 derniers jours, par jour</p>
            </div>
            <span class="font-mono text-sm text-muted"><?= e(format_number($requests30)) ?> au total</span>
        </div>
        <div class="mt-5 h-64">
            <canvas data-chart="<?= e(json_encode(['type' => 'line', 'label' => 'Requêtes', 'labels' => array_keys($daily), 'values' => array_values($daily)], JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Requêtes API par jour sur 30 jours : <?= e(format_number($requests30)) ?> au total"></canvas>
        </div>
    </section>

    <section class="card p-5 sm:p-6" aria-labelledby="quotas-title">
        <h2 id="quotas-title" class="font-semibold">Utilisation de votre offre</h2>
        <p class="text-sm text-muted">Les écritures sont bloquées à 100 %, la lecture reste possible.</p>
        <div class="mt-6 space-y-6">
            <?php foreach ([
                ['Projets', $q['projects']['used'], $q['projects']['limit'], false],
                ['Stockage base de données', $q['db_storage_bytes']['used'], $q['db_storage_bytes']['limit'], true],
                ['Requêtes API / mois', $q['api_requests_month']['used'], $q['api_requests_month']['limit'], false],
            ] as [$label, $used, $limit, $isBytes]): ?>
            <?= $this->insert('components/quota-bar', [
                'label' => $label,
                'usedLabel' => $isBytes ? format_bytes($used) : format_number($used),
                'limitLabel' => $limit === null ? null : ($isBytes ? format_bytes($limit) : format_number($limit)),
                'percent' => \App\Services\QuotaService::percent($used, $limit),
            ]) ?>
            <?php endforeach; ?>
        </div>
    </section>
</div>

<section class="mt-6" aria-labelledby="recent-projects">
    <div class="mb-4 flex items-center justify-between">
        <h2 id="recent-projects" class="font-semibold">Projets récents</h2>
        <a href="<?= $this->url('/projects') ?>" class="text-sm link">Voir tous les projets →</a>
    </div>
    <?php if ($items === []): ?>
    <div class="card relative overflow-hidden px-6 py-14 text-center">
        <div class="bg-grid pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
        <div class="relative">
            <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent-text"><?= $this->icon('database', 'size-7') ?></span>
            <h3 class="mt-5 text-lg font-semibold">Créez votre premier projet</h3>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">Une base MySQL dédiée, un utilisateur isolé et vos clés API sont générés en quelques secondes.</p>
            <a href="<?= $this->url('/projects/new') ?>" class="btn-primary mt-6"><?= $this->icon('plus', 'size-4') ?>Nouveau projet</a>
        </div>
    </div>
    <?php else: ?>
    <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
        <?php foreach ($items as $item): ?>
        <?= $this->insert('projects/card', ['item' => $item]) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<script src="<?= $this->asset('js/chart.umd.min.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
<script src="<?= $this->asset('js/charts.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
