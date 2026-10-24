<?php
/**
 * Page d'un projet, organisée en onglets (vue d'ensemble, base de données, clés API, paramètres).
 *
 * @var \App\Core\View\Template $this
 * @var array{id: int, ref: string, name: string, status: string, db_name: string, db_user: string, created_at: string} $project
 * @var string                  $tab
 * @var string                  $apiUrl
 * @var array<string, list<string>> $errors
 * @var array<string, mixed>    $old
 */
$this->layout('layouts/app', ['title' => $project['name']]);
$base = '/projects/' . $project['ref'];
$tabs = [
    'overview' => [$base, 'Vue d\'ensemble', 'grid'],
    'database' => [$base . '/database', 'Base de données', 'database'],
    'keys' => [$base . '/keys', 'Clés API', 'key'],
    'settings' => [$base . '/settings', 'Paramètres', 'settings'],
];
$statusLabel = ['active' => 'Actif', 'suspended' => 'Suspendu', 'provisioning' => 'Création…'][$project['status']] ?? $project['status'];
?>
<?= $this->insert('components/page-header', [
    'back' => '/projects',
    'backLabel' => 'Tous les projets',
    'heading' => $project['name'],
    'subtitle' => 'Identifiant ' . $project['ref'] . ' · créé le ' . format_datetime($project['created_at']) . ' · ' . $statusLabel,
]) ?>

<nav class="-mt-2 mb-8 grid grid-cols-2 gap-1 rounded-xl border border-line bg-surface p-1 sm:inline-grid sm:grid-cols-4" aria-label="Sections du projet">
    <?php foreach ($tabs as $key => [$href, $label, $icon]): ?>
    <a href="<?= $this->url($href) ?>" class="flex min-h-10 items-center justify-center gap-2 rounded-lg px-4 text-sm font-medium transition-colors <?= e($tab === $key ? 'bg-accent-soft text-fg shadow-card' : 'text-muted hover:bg-surface-2 hover:text-fg') ?>" <?php if ($tab === $key): ?>aria-current="page"<?php endif; ?>>
        <?= $this->icon($icon, 'size-4 ' . ($tab === $key ? 'text-accent-text' : '')) ?><?= e($label) ?>
    </a>
    <?php endforeach; ?>
</nav>

<?php if ($tab === 'overview'): ?>
<?php
/** @var array{bytes: int, tables: list<array{name: string, rows: int, bytes: int, created_at: string|null}>} $database */
/** @var array<string, int> $daily */
/** @var int $requests30 */
/** @var int $keyCount */
?>
<div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
    <?= $this->insert('components/stat-tile', ['icon' => 'api', 'label' => 'Requêtes API (30 j)', 'value' => format_number($requests30)]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'grid', 'label' => 'Tables', 'value' => format_number(count($database['tables']))]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'monitor', 'label' => 'Taille de la base', 'value' => format_bytes($database['bytes'])]) ?>
    <?= $this->insert('components/stat-tile', ['icon' => 'key', 'label' => 'Clés API actives', 'value' => format_number($keyCount)]) ?>
</div>
<div class="mt-6 grid gap-6 xl:grid-cols-3">
    <section class="card p-5 sm:p-6 xl:col-span-2">
        <h2 class="font-semibold">Requêtes API</h2>
        <p class="text-sm text-muted">30 derniers jours</p>
        <div class="mt-5 h-64"><canvas data-chart="<?= e(json_encode(['type' => 'bar', 'label' => 'Requêtes', 'labels' => array_keys($daily), 'values' => array_values($daily)], JSON_THROW_ON_ERROR)) ?>" role="img" aria-label="Requêtes API par jour : <?= e(format_number($requests30)) ?> sur 30 jours"></canvas></div>
    </section>
    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold">Point d'accès de l'API</h2>
        <p class="mt-1 text-sm text-muted">Testez votre clé publique :</p>
        <div class="mt-4 flex items-center gap-2">
            <code class="min-w-0 flex-1 truncate rounded-lg border border-line bg-bg px-3 py-2.5 font-mono text-xs"><?= e($apiUrl) ?></code>
            <button type="button" class="btn-secondary px-3" data-copy="<?= e($apiUrl) ?>" aria-label="Copier l'URL"><?= $this->icon('copy', 'size-4') ?></button>
        </div>
        <pre class="kbd-code mt-4 overflow-x-auto p-4 text-[11.5px]"><code>curl '<?= e($apiUrl) ?>' \
  -H 'apikey: pk_live_…'</code></pre>
        <a href="<?= $this->url($base . '/keys') ?>" class="btn-secondary mt-4 w-full"><?= $this->icon('key', 'size-4') ?>Gérer les clés API</a>
    </section>
</div>

<?php elseif ($tab === 'database'): ?>
<?php
/** @var array{bytes: int, tables: list<array{name: string, rows: int, bytes: int, created_at: string|null}>} $database */
/** @var string $dbHost */
/** @var int $dbPort */
/** @var mixed $revealedPassword */
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
    <section class="card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-5 sm:px-6">
            <div>
                <h2 class="font-semibold">Tables</h2>
                <p class="text-sm text-muted"><?= e(count($database['tables'])) ?> table(s) · <?= e(format_bytes($database['bytes'])) ?></p>
            </div>
            <span class="badge"><?= $this->icon('info', 'size-3.5') ?>Éditeur visuel de tables : prochaine étape</span>
        </div>
        <?php if ($database['tables'] === []): ?>
        <div class="px-6 py-16 text-center">
            <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-surface-2 text-muted"><?= $this->icon('grid', 'size-6') ?></span>
            <p class="mt-4 font-medium">La base est prête et vide</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">Les tables créées dans le Table Editor (ou via les identifiants ci-contre) apparaîtront ici avec leur nombre de lignes et leur taille.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-line text-xs text-muted"><tr><th class="px-6 py-3 font-medium">Table</th><th class="px-6 py-3 font-medium">Lignes (≈)</th><th class="px-6 py-3 font-medium">Taille</th><th class="px-6 py-3 font-medium">Créée le</th></tr></thead>
                <tbody class="divide-y divide-line">
                    <?php foreach ($database['tables'] as $t): ?>
                    <tr class="hover:bg-surface-2/50"><td class="px-6 py-3 font-mono"><?= e($t['name']) ?></td><td class="px-6 py-3 tabular-nums"><?= e(format_number($t['rows'])) ?></td><td class="px-6 py-3 tabular-nums"><?= e(format_bytes($t['bytes'])) ?></td><td class="px-6 py-3 text-muted"><?= e(format_datetime($t['created_at'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

    <section class="card p-5 sm:p-6">
        <h2 class="font-semibold">Connexion à la base</h2>
        <p class="mt-1 text-sm text-muted">Accès direct (outil SQL, serveur) avec l'utilisateur dédié du projet.</p>
        <dl class="mt-5 space-y-3 text-sm">
            <?php foreach ([['Hôte', $dbHost . ':' . $dbPort], ['Base', $project['db_name']], ['Utilisateur', $project['db_user']]] as [$label, $value]): ?>
            <div>
                <dt class="text-xs text-muted"><?= e($label) ?></dt>
                <dd class="mt-1 flex items-center gap-2"><code class="min-w-0 flex-1 truncate rounded-lg border border-line bg-bg px-3 py-2 font-mono text-xs"><?= e($value) ?></code><button type="button" class="btn-ghost size-9 px-0" data-copy="<?= e($value) ?>" aria-label="Copier <?= e($label) ?>"><?= $this->icon('copy', 'size-4') ?></button></dd>
            </div>
            <?php endforeach; ?>
            <div>
                <dt class="text-xs text-muted">Mot de passe</dt>
                <?php if (is_string($revealedPassword)): ?>
                <dd class="mt-1 flex items-center gap-2"><code class="min-w-0 flex-1 truncate rounded-lg border border-accent/40 bg-bg px-3 py-2 font-mono text-xs"><?= e($revealedPassword) ?></code><button type="button" class="btn-ghost size-9 px-0" data-copy="<?= e($revealedPassword) ?>" aria-label="Copier le mot de passe"><?= $this->icon('copy', 'size-4') ?></button></dd>
                <dd class="mt-1 text-xs text-warning">Affiché une seule fois. Rechargez la page pour le masquer.</dd>
                <?php else: ?>
                <dd class="mt-1">
                    <form method="post" action="<?= $this->url($base . '/database/reveal') ?>" data-confirm="Le mot de passe de la base va être affiché en clair. Assurez-vous que personne ne regarde votre écran." data-confirm-title="Afficher le mot de passe" data-confirm-ok="Afficher">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="btn-secondary w-full"><?= $this->icon('eye', 'size-4') ?>Afficher le mot de passe</button>
                    </form>
                </dd>
                <?php endif; ?>
            </div>
        </dl>
    </section>
</div>

<?php elseif ($tab === 'keys'): ?>
<?php
/** @var list<array{id: int, name: string, type: string, prefix: string, permissions: list<string>, expires_at: string|null, last_used_at: string|null, revoked_at: string|null, created_at: string}> $apiKeys */
/** @var array<string, string> $newKeys */
/** @var list<string> $permissions */
$typeLabels = ['public' => 'Publique', 'secret' => 'Secrète', 'custom' => 'Personnalisée'];
?>
<?php if ($newKeys !== []): ?>
<section class="card mb-6 border-accent/50 p-5 ring-1 ring-accent/30 sm:p-6" aria-labelledby="new-keys-title">
    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-accent-soft text-accent-text"><?= $this->icon('key', 'size-5') ?></span>
        <div class="min-w-0 flex-1">
            <h2 id="new-keys-title" class="font-semibold">Copiez vos clés maintenant</h2>
            <p class="mt-1 text-sm text-muted">Pour votre sécurité, elles ne sont stockées que sous forme de hash et <strong class="text-fg">ne seront plus jamais affichées</strong>.</p>
            <div class="mt-4 space-y-3">
                <?php foreach ($newKeys as $label => $key): ?>
                <div>
                    <p class="text-xs text-muted"><?= e($label === 'public' ? 'Clé publique (applications mobiles et web)' : ($label === 'secret' ? 'Clé secrète (serveurs uniquement)' : $label)) ?></p>
                    <div class="mt-1 flex items-center gap-2">
                        <code class="min-w-0 flex-1 overflow-x-auto rounded-lg border border-line bg-bg px-3 py-2.5 font-mono text-xs whitespace-nowrap"><?= e($key) ?></code>
                        <button type="button" class="btn-secondary px-3" data-copy="<?= e($key) ?>" aria-label="Copier la clé"><?= $this->icon('copy', 'size-4') ?></button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <section class="card overflow-hidden">
        <div class="border-b border-line p-5 sm:px-6">
            <h2 class="font-semibold">Clés du projet</h2>
            <p class="text-sm text-muted">Envoyez la clé dans l'en-tête <code class="text-fg">apikey</code>. Une clé régénérée ou révoquée est refusée immédiatement (401).</p>
        </div>
        <ul class="divide-y divide-line">
            <?php foreach ($apiKeys as $k): ?>
            <?php $revoked = $k['revoked_at'] !== null; ?>
            <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:px-6 <?= e($revoked ? 'opacity-60' : '') ?>">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold"><?= e($k['name']) ?></span>
                        <span class="badge py-0 <?= e($k['type'] === 'secret' ? 'border-danger/40 text-danger' : ($k['type'] === 'public' ? 'border-accent/40 text-accent-text' : '')) ?>"><?= e($typeLabels[$k['type']] ?? $k['type']) ?></span>
                        <?php if ($revoked): ?><span class="badge py-0">Révoquée le <?= e(format_datetime($k['revoked_at'])) ?></span><?php endif; ?>
                    </p>
                    <p class="mt-1 font-mono text-xs text-muted"><?= e($k['prefix']) ?>••••••••••••••••</p>
                    <p class="mt-1 text-xs text-muted">Droits : <?= e(implode(', ', $k['permissions'])) ?> · Dernière utilisation : <?= e($k['last_used_at'] !== null ? format_datetime($k['last_used_at']) : 'jamais') ?><?= e($k['expires_at'] !== null ? ' · expire le ' . format_datetime($k['expires_at']) : '') ?></p>
                </div>
                <?php if (!$revoked): ?>
                <div class="flex shrink-0 gap-2">
                    <form method="post" action="<?= $this->url($base . '/keys/' . $k['id'] . '/regenerate') ?>" data-confirm="L'ancienne clé « <?= e($k['name']) ?> » sera refusée immédiatement. Les applications qui l'utilisent devront être mises à jour." data-confirm-title="Régénérer la clé" data-confirm-ok="Régénérer">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="btn-secondary min-h-9 px-3 text-xs">Régénérer</button>
                    </form>
                    <form method="post" action="<?= $this->url($base . '/keys/' . $k['id'] . '/revoke') ?>" data-confirm="La clé « <?= e($k['name']) ?> » sera définitivement révoquée." data-confirm-title="Révoquer la clé" data-confirm-ok="Révoquer">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="btn-ghost min-h-9 px-3 text-xs text-danger">Révoquer</button>
                    </form>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <form method="post" action="<?= $this->url($base . '/keys') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div>
            <h2 class="font-semibold">Nouvelle clé</h2>
            <p class="mt-1 text-sm text-muted">Pour une app, un serveur ou un partenaire.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'key_name', 'label' => 'Nom', 'value' => is_string($old['key_name'] ?? null) ? $old['key_name'] : '', 'placeholder' => 'App iOS', 'maxlength' => 80, 'required' => true]) ?>
        <div>
            <label for="f-key_type" class="label">Type</label>
            <select id="f-key_type" name="key_type" class="input">
                <option value="public">Publique : lecture / écriture selon les règles d'accès</option>
                <option value="secret">Secrète : accès complet (serveur uniquement)</option>
                <option value="custom">Personnalisée : droits choisis</option>
            </select>
        </div>
        <fieldset>
            <legend class="label">Droits (clé personnalisée)</legend>
            <div class="grid grid-cols-2 gap-2">
                <?php foreach ($permissions as $perm): ?>
                <label class="flex min-h-10 cursor-pointer items-center gap-2 rounded-lg border border-line px-3 text-sm"><input type="checkbox" name="permissions[]" value="<?= e($perm) ?>" class="checkbox mt-0"><?= e($perm) ?></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <?= $this->insert('components/field', ['name' => 'expires_at', 'label' => 'Expiration (facultative)', 'type' => 'date', 'value' => '']) ?>
        <button type="submit" class="btn-primary w-full"><?= $this->icon('plus', 'size-4') ?>Générer la clé</button>
    </form>
</div>

<?php else: ?>
<div class="grid gap-6 xl:grid-cols-2">
    <form method="post" action="<?= $this->url($base . '/settings') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div>
            <h2 class="font-semibold">Général</h2>
            <p class="mt-1 text-sm text-muted">L'identifiant <code class="text-fg"><?= e($project['ref']) ?></code> et l'URL de l'API ne changent pas.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom du projet', 'value' => is_string($old['name'] ?? null) ? $old['name'] : $project['name'], 'maxlength' => 80, 'required' => true]) ?>
        <button type="submit" class="btn-primary">Enregistrer</button>
    </form>

    <form method="post" action="<?= $this->url($base . '/delete') ?>" class="card h-fit space-y-5 border-danger/40 p-5 sm:p-6" novalidate data-confirm="Le projet « <?= e($project['name']) ?> », sa base de données, son utilisateur MySQL et toutes ses clés seront supprimés définitivement." data-confirm-title="Supprimer le projet" data-confirm-ok="Oui, supprimer">
        <?= $this->csrfField() ?>
        <div>
            <h2 class="font-semibold text-danger">Supprimer le projet</h2>
            <p class="mt-1 text-sm text-muted">Action irréversible : toutes les données de la base <code class="text-fg"><?= e($project['db_name']) ?></code> seront perdues.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'confirm_name', 'label' => 'Tapez « ' . $project['name'] . ' » pour confirmer', 'autocomplete' => 'off', 'required' => true]) ?>
        <button type="submit" class="btn-danger"><?= $this->icon('trash', 'size-4') ?>Supprimer définitivement</button>
    </form>
</div>
<?php endif; ?>
<?php if ($tab === 'overview'): ?>
<script src="<?= $this->asset('js/chart.umd.min.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
<script src="<?= $this->asset('js/charts.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
<?php endif; ?>
