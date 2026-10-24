<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string} $project
 * @var array{id: int, name: string, pk_type: string, access_rules: array{read: string, insert: string, update: string, delete: string}, columns: list<array{name: string, type: string, length: int|null, nullable: bool, is_unique: bool, is_indexed: bool, default_value: string|null, fk_table: string|null, fk_on_delete: string|null}>} $table
 * @var list<array{name: string}> $tables
 * @var string $view
 * @var array<string, array{type: string, length: int|null, nullable: bool, writable: bool}> $columns
 * @var array{rows: list<array<string, mixed>>, total: int, limit: int, offset: int} $result
 * @var int $page
 * @var int $pages
 * @var string $order
 * @var string $search
 * @var string|null $gridError
 * @var array<string, list<string>> $errors
 */
$this->layout('projects/layout', ['title' => $table['name'] . ' · ' . $project['name']]);
$base = '/projects/' . $project['ref'] . '/tables/' . $table['name'];
$types = \App\Services\Data\ColumnTypes::LABELS;
$rules = \App\Repositories\TableRepository::RULES;
$cell = static function (mixed $value): string {
    return match (true) {
        $value === null => 'NULL',
        is_bool($value) => $value ? 'true' : 'false',
        is_array($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
        is_scalar($value) => (string) $value,
        default => '',
    };
};
$sortLink = static function (string $column) use ($order, $search): string {
    $next = $order === $column . '.asc' ? $column . '.desc' : $column . '.asc';

    return '?' . http_build_query(['order' => $next, 'q' => $search]);
};
?>
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div class="flex min-w-0 items-center gap-3">
        <a href="<?= $this->url('/projects/' . $project['ref'] . '/tables') ?>" class="btn-ghost size-10 shrink-0 px-0" aria-label="Retour aux tables"><span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span></a>
        <div class="min-w-0"><h2 class="truncate font-mono text-xl font-semibold"><?= e($table['name']) ?></h2><p class="text-sm text-muted">API : <code class="text-fg">/data/<?= e($table['name']) ?></code></p></div>
    </div>
    <div class="inline-flex rounded-xl border border-line bg-surface p-1">
        <?php foreach (['data' => 'Données', 'structure' => 'Structure', 'rules' => 'Règles d\'accès'] as $key => $label): ?>
        <a href="<?= $this->url($base . ($key === 'data' ? '' : '?view=' . $key)) ?>" class="flex min-h-9 items-center rounded-lg px-4 text-sm font-medium <?= e($view === $key ? 'bg-accent-soft text-fg' : 'text-muted hover:text-fg') ?>" <?php if ($view === $key): ?>aria-current="page"<?php endif; ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($view === 'data'): ?>
<div class="card overflow-hidden">
    <div class="flex flex-col gap-3 border-b border-line p-4 lg:flex-row lg:items-center lg:justify-between">
        <form method="get" action="<?= $this->url($base) ?>" class="flex flex-1 gap-2">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Rechercher dans les colonnes texte…" class="input min-h-10 max-w-md text-sm" aria-label="Rechercher">
            <?php if ($order !== ''): ?><input type="hidden" name="order" value="<?= e($order) ?>"><?php endif; ?>
            <button type="submit" class="btn-secondary min-h-10">Rechercher</button>
        </form>
        <div class="flex flex-wrap gap-2">
            <a href="<?= $this->url($base . '/rows/new') ?>" class="btn-primary min-h-10"><?= $this->icon('plus', 'size-4') ?>Ajouter une ligne</a>
            <a href="<?= $this->url($base . '/export.csv') ?>" class="btn-secondary min-h-10"><?= $this->icon('external', 'size-4') ?>Exporter CSV</a>
            <form method="post" action="<?= $this->url($base . '/import') ?>" enctype="multipart/form-data" class="flex items-center gap-2">
                <?= $this->csrfField() ?>
                <label class="btn-secondary min-h-10 cursor-pointer"><?= $this->icon('plus', 'size-4') ?>Importer CSV<input type="file" name="csv" accept=".csv,text/csv" class="sr-only" data-autosubmit-file></label>
            </form>
        </div>
    </div>
    <?php if ($gridError !== null): ?><p class="border-b border-line bg-danger-soft px-4 py-3 text-sm text-danger"><?= e($gridError) ?></p><?php endif; ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line bg-surface-2/50 text-xs">
                <tr>
                    <?php foreach ($columns as $name => $col): ?>
                    <th class="px-4 py-2.5 font-medium whitespace-nowrap">
                        <a href="<?= $this->url($base . $sortLink($name)) ?>" class="inline-flex items-center gap-1 hover:text-accent-text">
                            <span class="font-mono"><?= e($name) ?></span>
                            <span class="font-normal text-muted"><?= e($col['type']) ?></span>
                            <?php if ($order === $name . '.asc'): ?>↑<?php elseif ($order === $name . '.desc'): ?>↓<?php endif; ?>
                        </a>
                    </th>
                    <?php endforeach; ?>
                    <th class="sticky right-0 bg-surface-2 px-4 py-2.5"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line font-mono text-[13px]">
                <?php foreach ($result['rows'] as $row): ?>
                <tr class="group hover:bg-surface-2/40">
                    <?php foreach ($columns as $name => $col): ?>
                    <?php $text = $cell($row[$name] ?? null); ?>
                    <td class="max-w-[16rem] truncate px-4 py-2.5 <?= e(($row[$name] ?? null) === null ? 'text-muted/60 italic' : '') ?>" title="<?= e(mb_substr($text, 0, 500)) ?>"><?= e(mb_substr($text, 0, 120)) ?></td>
                    <?php endforeach; ?>
                    <td class="sticky right-0 bg-surface px-2 py-1.5 group-hover:bg-surface-2">
                        <div class="flex justify-end gap-1">
                            <a href="<?= $this->url($base . '/rows/' . $cell($row['id'] ?? '')) ?>" class="btn-ghost size-8 px-0" aria-label="Modifier la ligne"><?= $this->icon('pencil', 'size-3.5') ?></a>
                            <form method="post" action="<?= $this->url($base . '/rows/' . $cell($row['id'] ?? '') . '/delete') ?>" data-confirm="La ligne <?= e($cell($row['id'] ?? '')) ?> sera supprimée définitivement." data-confirm-title="Supprimer la ligne" data-confirm-ok="Supprimer">
                                <?= $this->csrfField() ?><button type="submit" class="btn-ghost size-8 px-0 text-danger" aria-label="Supprimer la ligne"><?= $this->icon('trash', 'size-3.5') ?></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($result['rows'] === []): ?>
                <tr><td colspan="<?= e(count($columns) + 1) ?>" class="px-4 py-14 text-center font-sans text-muted"><?= e($search !== '' ? 'Aucune ligne ne correspond à la recherche.' : 'La table est vide : ajoutez une ligne, importez un CSV ou utilisez l\'API.') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm">
        <span class="text-muted"><?= e(format_number($result['total'])) ?> ligne(s) · page <?= e($page) ?> / <?= e($pages) ?></span>
        <span class="flex gap-2">
            <?php if ($page > 1): ?><a class="btn-secondary min-h-9" href="<?= $this->url($base . '?' . http_build_query(['page' => $page - 1, 'order' => $order, 'q' => $search])) ?>">Précédente</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn-secondary min-h-9" href="<?= $this->url($base . '?' . http_build_query(['page' => $page + 1, 'order' => $order, 'q' => $search])) ?>">Suivante</a><?php endif; ?>
        </span>
    </div>
</div>
<p class="mt-3 text-xs text-muted">Import CSV : première ligne = noms des colonnes (séparateur « ; » ou « , »). Les colonnes système sont ignorées.</p>

<?php elseif ($view === 'structure'): ?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
    <section class="card overflow-hidden">
        <div class="border-b border-line p-5 sm:px-6"><h3 class="font-semibold">Colonnes</h3></div>
        <ul class="divide-y divide-line">
            <?php foreach ([['id', $table['pk_type'] === 'uuid' ? 'uuid' : 'bigint', 'clé primaire'], ['owner_id', 'bigint', 'propriétaire (auto)'], ['created_at', 'datetime', 'auto'], ['updated_at', 'datetime', 'auto']] as [$name, $type, $note]): ?>
            <li class="flex items-center gap-4 px-5 py-3 sm:px-6"><span class="w-40 truncate font-mono text-sm"><?= e($name) ?></span><span class="text-sm text-muted"><?= e($type) ?></span><span class="badge ml-auto py-0">Système · <?= e($note) ?></span></li>
            <?php endforeach; ?>
            <?php foreach ($table['columns'] as $c): ?>
            <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 sm:px-6">
                <span class="w-40 truncate font-mono text-sm font-medium"><?= e($c['name']) ?></span>
                <span class="text-sm text-muted"><?= e(($types[$c['type']] ?? $c['type']) . ($c['type'] === 'string' ? ' (' . $c['length'] . ')' : '') . ($c['fk_table'] !== null ? ' → ' . $c['fk_table'] . ' (' . $c['fk_on_delete'] . ')' : '')) ?></span>
                <span class="flex flex-wrap gap-1.5">
                    <?php if (!$c['nullable']): ?><span class="badge py-0">obligatoire</span><?php endif; ?>
                    <?php if ($c['is_unique']): ?><span class="badge py-0">unique</span><?php endif; ?>
                    <?php if ($c['is_indexed']): ?><span class="badge py-0">indexée</span><?php endif; ?>
                    <?php if ($c['default_value'] !== null): ?><span class="badge py-0">défaut : <?= e($c['default_value']) ?></span><?php endif; ?>
                </span>
                <form method="post" action="<?= $this->url($base . '/columns/delete') ?>" class="ml-auto" data-confirm="La colonne « <?= e($c['name']) ?> » et toutes ses données seront supprimées." data-confirm-title="Supprimer la colonne" data-confirm-ok="Supprimer">
                    <?= $this->csrfField() ?><input type="hidden" name="column_name" value="<?= e($c['name']) ?>"><button type="submit" class="btn-ghost size-9 px-0 text-danger" aria-label="Supprimer la colonne <?= e($c['name']) ?>"><?= $this->icon('trash', 'size-4') ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <div class="space-y-6">
        <form method="post" action="<?= $this->url($base . '/columns') ?>" class="card space-y-4 p-5" novalidate data-column-builder>
            <?= $this->csrfField() ?>
            <h3 class="font-semibold">Ajouter une colonne</h3>
            <?php if (isset($errors['column'][0])): ?><p class="field-error rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['column'][0]) ?></span></p><?php endif; ?>
            <?php if (isset($errors['columns'][0])): ?><p class="field-error rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['columns'][0]) ?></span></p><?php endif; ?>
            <div data-column-list><?= $this->insert('projects/column-fields', ['prefix' => 'column', 'tableNames' => array_column($tables, 'name')]) ?></div>
            <button type="submit" class="btn-primary w-full"><?= $this->icon('plus', 'size-4') ?>Ajouter</button>
        </form>
        <form method="post" action="<?= $this->url($base . '/rename') ?>" class="card space-y-4 p-5" novalidate>
            <?= $this->csrfField() ?>
            <h3 class="font-semibold">Renommer la table</h3>
            <?= $this->insert('components/field', ['name' => 'new_name', 'label' => 'Nouveau nom', 'value' => $table['name'], 'maxlength' => 63, 'hint' => 'L\'URL de l\'API changera : mettez à jour vos applications.']) ?>
            <button type="submit" class="btn-secondary">Renommer</button>
        </form>
        <form method="post" action="<?= $this->url($base . '/delete') ?>" class="card space-y-4 border-danger/40 p-5" novalidate data-confirm="La table « <?= e($table['name']) ?> » et toutes ses lignes seront supprimées définitivement." data-confirm-title="Supprimer la table" data-confirm-ok="Supprimer">
            <?= $this->csrfField() ?>
            <h3 class="font-semibold text-danger">Supprimer la table</h3>
            <?= $this->insert('components/field', ['name' => 'confirm_table', 'label' => 'Tapez « ' . $table['name'] . ' » pour confirmer', 'autocomplete' => 'off']) ?>
            <button type="submit" class="btn-danger"><?= $this->icon('trash', 'size-4') ?>Supprimer la table</button>
        </form>
    </div>
</div>

<?php else: ?>
<form method="post" action="<?= $this->url($base . '/rules') ?>" class="card max-w-4xl overflow-hidden">
    <?= $this->csrfField() ?>
    <div class="border-b border-line p-5 sm:px-6">
        <h3 class="font-semibold">Règles d'accès avec la clé publique</h3>
        <p class="mt-1 text-sm text-muted">La clé secrète (serveur) ignore toujours ces règles. « Propriétaire uniquement » limite chaque utilisateur à ses lignes (colonne <code class="text-fg">owner_id</code>, remplie automatiquement à l'insertion).</p>
    </div>
    <div class="divide-y divide-line">
        <?php foreach (['read' => ['Lire', 'GET /data/' . $table['name']], 'insert' => ['Insérer', 'POST /data/' . $table['name']], 'update' => ['Modifier', 'PATCH /data/' . $table['name'] . '/{id}'], 'delete' => ['Supprimer', 'DELETE /data/' . $table['name'] . '/{id}']] as $op => [$label, $endpoint]): ?>
        <div class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div><p class="font-medium"><?= e($label) ?></p><p class="font-mono text-xs text-muted"><?= e($endpoint) ?></p></div>
            <select name="rule_<?= e($op) ?>" class="input sm:w-72" aria-label="Règle : <?= e($label) ?>">
                <?php foreach ($rules as $key => $ruleLabel): ?><option value="<?= e($key) ?>" <?php if ($table['access_rules'][$op] === $key): ?>selected<?php endif; ?>><?= e($ruleLabel) ?></option><?php endforeach; ?>
            </select>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center justify-between gap-4 border-t border-line bg-bg/40 p-5 sm:px-6">
        <p class="text-xs text-muted">« Utilisateurs connectés » et « Propriétaire » s'appuient sur l'authentification des utilisateurs de vos applications (prochaine étape).</p>
        <button type="submit" class="btn-primary shrink-0">Enregistrer</button>
    </div>
</form>
<?php endif; ?>
