<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{users: int, verified: int, new_week: int, superadmins: int, testimonials: int, published: int, pending_jobs: int, failed_jobs: int} $stats
 * @var int                     $projects
 * @var int                     $requests30
 * @var array<string, int>      $requestsDaily
 * @var array<string, int>      $signupsDaily
 * @var int                     $pendingInvitations
 * @var array{logins: int, failed: int, unique_users: int} $logins
 * @var array{cpu_percent: float|null, load: list<float>|null, cores: int, ram_total: int|null, ram_used: int|null, ram_percent: float|null, disk_total: int|null, disk_used: int|null, disk_percent: float|null, uptime: int|null, php_version: string, os: string, hostname: string, time: string, source: string, cache_age: int|null} $server
 * @var array{mysql_version: string, platform_bytes: int, projects_bytes: int} $databases
 */
$this->layout('layouts/app', ['title' => 'Administration']);
$uptime = $server['uptime'] !== null ? sprintf('%d j %d h %02d min', intdiv($server['uptime'], 86400), intdiv($server['uptime'] % 86400, 3600), intdiv($server['uptime'] % 3600, 60)) : '—';
$gauges = [
    ['cpu', 'Charge CPU', $server['cpu_percent'], ($server['cores']) . ' cœur(s) · charge ' . ($server['load'] !== null ? implode(' · ', $server['load']) : '—'), 'load'],
    ['ram', 'Mémoire vive', $server['ram_percent'], format_bytes($server['ram_used']) . ' / ' . format_bytes($server['ram_total']), 'ram-detail'],
    ['disk', 'Espace disque', $server['disk_percent'], format_bytes($server['disk_used']) . ' / ' . format_bytes($server['disk_total']), 'disk-detail'],
];
$levelOf = static fn (?float $p): string => $p === null ? 'ok' : ($p >= 90 ? 'critical' : ($p >= 70 ? 'warning' : 'ok'));
$levelLabels = ['ok' => 'Normal', 'warning' => 'Élevé', 'critical' => 'Critique'];
$levelColors = ['ok' => 'bg-accent', 'warning' => 'bg-warning', 'critical' => 'bg-danger'];
?>
<?= $this->insert('components/page-header', [
    'eyebrow' => 'Superadmin',
    'heading' => 'Vue d\'ensemble de la plateforme',
    'subtitle' => 'Utilisateurs, projets, trafic de l\'API et état du serveur qui héberge NovaBase.',
]) ?>

<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <?= $this->insert('components/stat-tile', ['icon' => 'user', 'label' => 'Utilisateurs', 'value' => format_number($stats['users']), 'hint' => $stats['verified'] . ' confirmés · +' . $stats['new_week'] . ' cette semaine']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'database', 'label' => 'Bases de données', 'value' => format_number($projects), 'hint' => format_bytes($databases['projects_bytes']) . ' de données clients']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'api', 'label' => 'Requêtes API (30 j)', 'value' => format_number($requests30), 'hint' => 'tous projets confondus']) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'shield', 'label' => 'Connexions (24 h)', 'value' => format_number($logins['logins']), 'hint' => $logins['failed'] . ' échec(s) · ' . $logins['unique_users'] . ' utilisateur(s)']) ?>
</div>

<!-- Serveur en temps réel -->
<section class="card mt-6 p-5 sm:p-6" data-metrics-url="<?= $this->url('/admin/metrics') ?>" aria-labelledby="server-title">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="server-title" class="flex items-center gap-2 font-semibold">
                <span class="relative flex size-2.5"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60"></span><span class="relative inline-flex size-2.5 rounded-full bg-accent"></span></span>
                Serveur en temps réel
            </h2>
            <p class="text-sm text-muted"><?= e($server['hostname']) ?> · <?= e($server['os']) ?> · PHP <?= e($server['php_version']) ?> · MySQL <?= e($databases['mysql_version']) ?></p>
        </div>
        <p class="text-xs text-muted">Actualisé toutes les 5 s · dernière mesure <span class="font-mono" data-metric="updated"><?= e(date('H:i:s')) ?></span> · en ligne depuis <?= e($uptime) ?></p>
    </div>
    <?php if ($server['ram_percent'] === null): ?>
    <p class="mt-4 flex items-start gap-2 rounded-lg border border-warning/40 bg-surface-2 p-3 text-xs text-muted"><?= $this->icon('info', 'mt-0.5 size-4 shrink-0 text-warning') ?><span>Le serveur web n'a pas accès à <code class="text-fg">/proc</code> (sécurité systemd). Pour afficher la mémoire, planifiez <code class="text-fg">php bin/console metrics:collect</code> chaque minute (cron) ou lancez le worker.</span></p>
    <?php elseif ($server['source'] === 'cache'): ?>
    <p class="mt-4 text-xs text-muted">Mémoire et uptime mesurés par la tâche planifiée il y a <?= e(format_number(intdiv((int) $server['cache_age'], 60))) ?> min (le serveur web n'a pas accès à /proc).</p>
    <?php endif; ?>
    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
        <div class="space-y-6">
            <?php foreach ($gauges as [$key, $label, $percent, $detail, $detailKey]): ?>
            <?php $level = $levelOf($percent); ?>
            <div data-gauge="<?= e($key) ?>">
                <div class="flex items-baseline justify-between gap-3">
                    <p class="text-sm font-medium"><?= e($label) ?></p>
                    <p class="text-sm"><span class="font-semibold tabular-nums" data-gauge-value><?= e($percent !== null ? format_number($percent) . ' %' : 'indisponible') ?></span> <span class="text-xs text-muted" data-gauge-level><?= e($levelLabels[$level]) ?></span></p>
                </div>
                <div class="meter mt-2"><span class="<?= e($levelColors[$level]) ?>" data-gauge-bar data-percent="<?= e($percent ?? 0) ?>"></span></div>
                <p class="mt-1.5 text-xs text-muted" data-metric="<?= e($detailKey) ?>"><?= e($detail) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        <div>
            <p class="text-sm font-medium">CPU et mémoire (%)</p>
            <div class="mt-3 h-60"><canvas data-live-chart role="img" aria-label="Évolution en direct de la charge CPU et de la mémoire, en pourcentage"></canvas></div>
        </div>
    </div>
</section>

<div class="mt-6 grid gap-6 xl:grid-cols-2">
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold">Requêtes API</h2>
        <p class="text-sm text-muted">30 derniers jours, tous projets</p>
        <div class="mt-5 h-64"><canvas data-chart="<?= e(json_encode(['type' => 'line', 'label' => 'Requêtes', 'labels' => array_keys($requestsDaily), 'values' => array_values($requestsDaily)], JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Requêtes API par jour, <?= e(format_number($requests30)) ?> au total"></canvas></div>
    </section>
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold">Inscriptions</h2>
        <p class="text-sm text-muted">Nouveaux comptes par jour (30 j)</p>
        <div class="mt-5 h-64"><canvas data-chart="<?= e(json_encode(['type' => 'bar', 'label' => 'Inscriptions', 'labels' => array_keys($signupsDaily), 'values' => array_values($signupsDaily)], JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Inscriptions par jour, <?= e(array_sum($signupsDaily)) ?> sur 30 jours"></canvas></div>
    </section>
</div>

<div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <?php foreach ([
        ['/admin/invitations', 'mail', 'Invitations en attente', format_number($pendingInvitations)],
        ['/admin/testimonials', 'message', 'Témoignages publiés', $stats['published'] . ' / ' . $stats['testimonials']],
        ['/admin/logins', 'shield', 'Échecs de connexion (24 h)', format_number($logins['failed'])],
        ['/admin', 'zap', 'File d\'attente', $stats['pending_jobs'] . ' en cours · ' . $stats['failed_jobs'] . ' échec(s)'],
    ] as [$href, $icon, $label, $value]): ?>
    <a href="<?= $this->url($href) ?>" class="card flex items-center gap-4 p-4 transition-colors hover:border-line-strong">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent-text"><?= $this->icon($icon, 'size-5') ?></span>
        <span class="min-w-0"><span class="block text-xs text-muted"><?= e($label) ?></span><span class="block truncate font-semibold tabular-nums"><?= e($value) ?></span></span>
    </a>
    <?php endforeach; ?>
</div>
<p class="mt-4 text-xs text-muted">Base plateforme : <?= e(format_bytes($databases['platform_bytes'])) ?> · Bases des projets : <?= e(format_bytes($databases['projects_bytes'])) ?></p>
<script src="<?= $this->asset('js/chart.umd.min.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
<script src="<?= $this->asset('js/charts.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
