<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{id: int, ref: string, name: string, db_name: string, db_user: string} $project
 * @var list<array{id: int, name: string, pk_type: string, access_rules: array{read: string, insert: string, update: string, delete: string}, created_at: string, columns: list<array{name: string}>}> $tables
 * @var array<string, array{name: string, rows: int, bytes: int, created_at: string|null}> $stats
 * @var list<array{action: string, sql_executed: string, status: string, error: string|null, created_at: string}> $changes
 * @var string $dbHost
 * @var int    $dbPort
 * @var mixed  $revealedPassword
 * @var array{bytes: int, tables: list<array{name: string}>} $database
 */
$this->layout('projects/layout', ['title' => 'Tables · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
$rules = \App\Repositories\TableRepository::RULES;
?>
<div class="mb-6 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <p class="text-sm text-muted">Chaque table devient immédiatement une API : <code class="text-fg">/data/&lt;table&gt;</code>. Colonnes <code class="text-fg">id</code>, <code class="text-fg">owner_id</code>, <code class="text-fg">created_at</code> et <code class="text-fg">updated_at</code> ajoutées automatiquement.</p>
    <div class="flex shrink-0 gap-2">
        <a href="<?= $this->url($base . '/sql') ?>" class="btn-secondary"><?= $this->icon('terminal', 'size-4') ?>Éditeur SQL</a>
        <a href="<?= $this->url($base . '/tables/new') ?>" class="btn-primary"><?= $this->icon('plus', 'size-4') ?>Nouvelle table</a>
    </div>
</div>

<?php if ($tables === []): ?>
<div class="card relative overflow-hidden px-6 py-16 text-center">
    <div class="bg-grid pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
    <div class="relative">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent-text"><?= $this->icon('grid', 'size-7') ?></span>
        <h2 class="mt-5 text-lg font-semibold">Créez votre première table</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">Par exemple <code class="text-fg">produits</code> avec un nom, un prix et un stock. Vous pourrez ensuite la remplir ici ou via l'API.</p>
        <a href="<?= $this->url($base . '/tables/new') ?>" class="btn-primary mt-6"><?= $this->icon('plus', 'size-4') ?>Créer une table</a>
    </div>
</div>
<?php else: ?>
<div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
    <?php foreach ($tables as $t): ?>
    <?php $s = $stats[$t['name']] ?? null; ?>
    <a href="<?= $this->url($base . '/tables/' . $t['name']) ?>" class="card group p-5 transition-[border-color,transform] duration-200 hover:-translate-y-0.5 hover:border-line-strong">
        <div class="flex items-center gap-3">
            <span class="flex size-10 items-center justify-center rounded-lg border border-line bg-surface-2 text-accent-text"><?= $this->icon('grid', 'size-5') ?></span>
            <div class="min-w-0"><p class="truncate font-mono font-semibold group-hover:text-accent-text"><?= e($t['name']) ?></p><p class="text-xs text-muted"><?= e(count($t['columns']) + 4) ?> colonnes · clé <?= e($t['pk_type'] === 'uuid' ? 'UUID' : 'auto-incrément') ?></p></div>
        </div>
        <dl class="mt-5 grid grid-cols-3 gap-3 border-t border-line pt-4 text-xs">
            <div><dt class="text-muted">Lignes (≈)</dt><dd class="mt-1 font-medium tabular-nums"><?= e(format_number($s['rows'] ?? 0)) ?></dd></div>
            <div><dt class="text-muted">Taille</dt><dd class="mt-1 font-medium tabular-nums"><?= e(format_bytes($s['bytes'] ?? 0)) ?></dd></div>
            <div><dt class="text-muted">Lecture</dt><dd class="mt-1 truncate font-medium"><?= e($rules[$t['access_rules']['read']] ?? '') ?></dd></div>
        </dl>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="mt-8 grid gap-6 xl:grid-cols-2">
    <details id="acces-direct" class="card group p-5 sm:p-6" <?php if (is_string($revealedPassword)): ?>open<?php endif; ?>>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden">
            <span><span class="block font-semibold">Accès direct MySQL <span class="badge ml-1 py-0">Avancé</span></span><span class="mt-1 block text-sm text-muted">Inutile pour vos applications (elles passent par l'API). Utile pour un outil SQL ou un import massif.</span></span>
            <span class="text-muted transition-transform group-open:rotate-180"><?= $this->icon('chevron-down') ?></span>
        </summary>
        <dl class="mt-5 space-y-3 text-sm">
            <?php foreach ([['Hôte', $dbHost . ':' . $dbPort], ['Base', $project['db_name']], ['Utilisateur', $project['db_user']]] as [$label, $value]): ?>
            <div><dt class="mb-1 text-xs text-muted"><?= e($label) ?></dt><dd><?= $this->insert('components/copy-field', ['value' => $value, 'label' => $label]) ?></dd></div>
            <?php endforeach; ?>
            <div>
                <dt class="mb-1 text-xs text-muted">Mot de passe</dt>
                <?php if (is_string($revealedPassword)): ?>
                <dd><?= $this->insert('components/copy-field', ['value' => $revealedPassword, 'label' => 'le mot de passe']) ?><p class="mt-1 text-xs text-warning">Affiché une seule fois : rechargez la page pour le masquer.</p></dd>
                <?php else: ?>
                <dd><form method="post" action="<?= $this->url($base . '/database/reveal') ?>" data-confirm="Le mot de passe MySQL du projet va être affiché en clair." data-confirm-title="Afficher le mot de passe" data-confirm-ok="Afficher"><?= $this->csrfField() ?><button type="submit" class="btn-secondary"><?= $this->icon('eye', 'size-4') ?>Afficher le mot de passe</button></form></dd>
                <?php endif; ?>
            </div>
        </dl>
    </details>

    <section class="card overflow-hidden">
        <div class="border-b border-line p-5 sm:px-6"><h2 class="font-semibold">Historique du schéma</h2><p class="text-sm text-muted">Dernières modifications de structure.</p></div>
        <ul class="max-h-80 divide-y divide-line overflow-y-auto">
            <?php foreach ($changes as $c): ?>
            <li class="px-5 py-3 sm:px-6">
                <p class="flex items-center justify-between gap-3 text-sm"><span class="font-medium"><?= e(str_replace('_', ' ', $c['action'])) ?></span><span class="badge py-0 <?= e($c['status'] === 'success' ? 'border-accent/40 text-accent-text' : 'border-danger/40 text-danger') ?>"><?= e($c['status'] === 'success' ? 'Réussi' : 'Échec') ?></span></p>
                <p class="mt-1 truncate font-mono text-xs text-muted" title="<?= e($c['sql_executed']) ?>"><?= e(preg_replace('/\s+/', ' ', $c['sql_executed']) ?? '') ?></p>
                <p class="mt-0.5 text-xs text-muted"><?= e(format_datetime($c['created_at'])) ?></p>
            </li>
            <?php endforeach; ?>
            <?php if ($changes === []): ?><li class="px-6 py-10 text-center text-sm text-muted">Aucune modification pour l'instant.</li><?php endif; ?>
        </ul>
    </section>
</div>
