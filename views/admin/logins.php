<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<array{id: int, user_id: int|null, email: string|null, user_name: string|null, event: string, ip: string, user_agent: string, browser: string, os: string, device: string, created_at: string}> $rows
 * @var array<string, string>   $locations
 * @var array{logins: int, failed: int, unique_users: int} $summary
 * @var string $event
 * @var string $search
 * @var int    $page
 * @var bool   $hasMore
 */
$this->layout('layouts/app', ['title' => 'Connexions']);
$events = \App\Controllers\Web\Admin\LoginLogController::EVENTS;
$badge = ['login' => 'border-accent/40 text-accent-text', 'invitation_accepted' => 'border-accent/40 text-accent-text', 'logout' => ''];
$deviceIcon = ['mobile' => 'monitor', 'tablet' => 'monitor', 'desktop' => 'monitor', 'bot' => 'zap', 'unknown' => 'info'];
$deviceLabel = ['mobile' => 'Mobile', 'tablet' => 'Tablette', 'desktop' => 'Ordinateur', 'bot' => 'Robot / script', 'unknown' => 'Inconnu'];
$maxId = $rows !== [] ? max(array_column($rows, 'id')) : 0;
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Sécurité', 'heading' => 'Journal des connexions', 'subtitle' => 'Qui se connecte, quand, d\'où et avec quel appareil. Heures affichées dans le fuseau ' . date_default_timezone_get() . '.']) ?>

<div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
    <?= $this->insert('components/stat-tile', ['icon' => 'check', 'label' => 'Connexions réussies (24 h)', 'value' => format_number($summary['logins'])]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'alert', 'label' => 'Échecs et blocages (24 h)', 'value' => format_number($summary['failed'])]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'user', 'label' => 'Utilisateurs connectés (24 h)', 'value' => format_number($summary['unique_users'])]) ?>
</div>

<form method="get" action="<?= $this->url('/admin/logins') ?>" class="card mt-6 flex flex-col gap-3 p-4 lg:flex-row lg:items-end">
    <div class="flex-1"><label for="q" class="label">Rechercher</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Nom, e-mail ou début d'adresse IP" class="input"></div>
    <div class="lg:w-56"><label for="event" class="label">Événement</label>
        <select id="event" name="event" class="input"><option value="">Tous</option><?php foreach ($events as $key => $label): ?><option value="<?= e($key) ?>" <?php if ($event === $key): ?>selected<?php endif; ?>><?= e($label) ?></option><?php endforeach; ?></select>
    </div>
    <button type="submit" class="btn-secondary">Filtrer</button>
    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 text-sm"><input type="checkbox" class="checkbox mt-0" data-live-toggle <?php if ($page === 1): ?>checked<?php endif; ?>><span class="relative flex size-2"><span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-60"></span><span class="relative inline-flex size-2 rounded-full bg-accent"></span></span>En direct</label>
</form>

<div class="card mt-4 overflow-hidden" data-login-feed data-url="<?= $this->url('/admin/logins/feed?' . http_build_query(['event' => $event, 'q' => $search])) ?>" data-after="<?= e($maxId) ?>">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[960px] text-left text-sm">
            <thead class="border-b border-line bg-surface-2/40 text-xs text-muted"><tr><th class="px-4 py-3 font-medium">Utilisateur</th><th class="px-4 py-3 font-medium">Événement</th><th class="px-4 py-3 font-medium">Date et heure</th><th class="px-4 py-3 font-medium">Localisation</th><th class="px-4 py-3 font-medium">Appareil / navigateur</th><th class="px-4 py-3 font-medium">Adresse IP</th></tr></thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($rows as $r): ?>
                <tr class="hover:bg-surface-2/40">
                    <td class="px-4 py-3 align-top"><p class="font-medium"><?= e($r['user_name'] !== null && $r['user_name'] !== '' ? $r['user_name'] : ($r['email'] ?? '—')) ?></p><?php if ($r['user_name'] !== null && $r['email'] !== null): ?><p class="text-xs text-muted"><?= e($r['email']) ?></p><?php endif; ?></td>
                    <td class="px-4 py-3 align-top"><span class="badge py-0 <?= e($badge[$r['event']] ?? 'border-danger/40 text-danger') ?>"><?= e($events[$r['event']] ?? $r['event']) ?></span></td>
                    <td class="px-4 py-3 align-top font-mono text-xs whitespace-nowrap"><?= e(format_datetime($r['created_at'], true)) ?></td>
                    <td class="px-4 py-3 align-top"><?= e($locations[$r['ip']] ?? 'Inconnue') ?></td>
                    <td class="px-4 py-3 align-top"><p class="flex items-center gap-1.5"><?= $this->icon($deviceIcon[$r['device']] ?? 'info', 'size-4 text-muted') ?><?= e($deviceLabel[$r['device']] ?? $r['device']) ?></p><p class="text-xs text-muted"><?= e($r['browser'] . ' · ' . $r['os']) ?></p></td>
                    <td class="px-4 py-3 align-top font-mono text-xs"><?= e($r['ip']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr data-feed-empty><td colspan="6" class="px-4 py-12 text-center text-muted">Aucune connexion enregistrée pour ces critères.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<nav class="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
    <span class="text-muted">Page <?= e($page) ?></span>
    <span class="flex gap-2">
        <?php if ($page > 1): ?><a class="btn-secondary min-h-9" href="<?= $this->url('/admin/logins?' . http_build_query(['q' => $search, 'event' => $event, 'page' => $page - 1])) ?>">Plus récentes</a><?php endif; ?>
        <?php if ($hasMore): ?><a class="btn-secondary min-h-9" href="<?= $this->url('/admin/logins?' . http_build_query(['q' => $search, 'event' => $event, 'page' => $page + 1])) ?>">Plus anciennes</a><?php endif; ?>
    </span>
</nav>
<script src="<?= $this->asset('js/charts.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
