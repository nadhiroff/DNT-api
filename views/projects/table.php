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
 * @var bool $openInsert
 * @var bool $focusInline
 * @var array<string, array<string, string>> $relations
 * @var array<string, mixed> $old
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
$recent = static function (array $row, string $column): bool {
    $time = is_string($row[$column] ?? null) ? strtotime($row[$column] . ' UTC') : false;

    return $time !== false && $time > time() - 600;
};
$lastSaved = null;
foreach ($result['rows'] as $r) {
    $t = is_string($r['updated_at'] ?? null) ? strtotime($r['updated_at'] . ' UTC') : false;
    $lastSaved = $t !== false && ($lastSaved === null || $t > $lastSaved) ? $t : $lastSaved;
}
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
    <div class="flex flex-wrap items-center gap-2 border-b border-line p-4">
        <form method="get" action="<?= $this->url($base) ?>" class="relative min-w-[14rem] flex-1 sm:max-w-md" role="search" data-live-search>
            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><?= $this->icon('search', 'size-4') ?></span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Rechercher dans les colonnes texte…" class="input min-h-10 pr-9 pl-9 text-sm" aria-label="Rechercher" autocomplete="off" spellcheck="false">
            <span class="pointer-events-none absolute top-1/2 right-3 hidden size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-line-strong border-t-accent" data-live-spinner aria-hidden="true"></span>
            <?php if ($order !== ''): ?><input type="hidden" name="order" value="<?= e($order) ?>"><?php endif; ?>
        </form>
        <div class="flex items-center gap-2">
            <details class="relative" data-menu>
                <summary class="btn-primary min-h-10 cursor-pointer list-none [&::-webkit-details-marker]:hidden"><?= $this->icon('plus', 'size-4') ?>Insérer<?= $this->icon('chevron-down', 'size-4 opacity-80') ?></summary>
                <div class="absolute left-0 z-30 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-line bg-surface p-1.5 shadow-card" role="menu">
                    <button type="button" class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-surface-2" role="menuitem" data-focus-inline>
                        <span class="mt-0.5 text-accent-text"><?= $this->icon('pencil', 'size-4') ?></span>
                        <span><span class="block text-sm font-medium">Saisir dans le tableau</span><span class="block text-xs text-muted">Tapez les valeurs dans la première ligne, puis Entrée</span></span>
                    </button>
                    <button type="button" class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-surface-2" role="menuitem" data-open-dialog="insert-row">
                        <span class="mt-0.5 text-accent-text"><?= $this->icon('plus', 'size-4') ?></span>
                        <span><span class="block text-sm font-medium">Insérer une ligne (formulaire)</span><span class="block text-xs text-muted">Fenêtre avec toutes les colonnes, champs larges</span></span>
                    </button>
                    <label class="flex w-full cursor-pointer items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-surface-2" role="menuitem">
                        <span class="mt-0.5 text-accent-text"><?= $this->icon('upload', 'size-4') ?></span>
                        <span><span class="block text-sm font-medium">Insérer des lignes depuis un CSV</span><span class="block text-xs text-muted">1re ligne = noms des colonnes, « ; » ou « , »</span></span>
                        <input type="file" name="csv" accept=".csv,text/csv" class="sr-only" form="csv-import" data-autosubmit-file>
                    </label>
                    <a href="<?= $this->url($base . '?view=structure#ajouter-colonne') ?>" class="flex w-full items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-surface-2" role="menuitem">
                        <span class="mt-0.5 text-accent-text"><?= $this->icon('columns', 'size-4') ?></span>
                        <span><span class="block text-sm font-medium">Insérer une colonne</span><span class="block text-xs text-muted">Ajouter un champ à la structure de la table</span></span>
                    </a>
                </div>
            </details>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            <a href="<?= $this->url($base . ($order !== '' || $search !== '' ? '?' . http_build_query(['order' => $order, 'q' => $search]) : '')) ?>" class="btn-ghost min-h-10" title="Relire les données depuis la base"><?= $this->icon('refresh', 'size-4') ?>Actualiser</a>
            <a href="<?= $this->url($base . '/export.csv') ?>" class="btn-secondary min-h-10"><?= $this->icon('external', 'size-4') ?>Exporter CSV</a>
        </div>
        <form method="post" action="<?= $this->url($base . '/import') ?>" enctype="multipart/form-data" id="csv-import" hidden><?= $this->csrfField() ?></form>
        <form method="post" action="<?= $this->url($base . '/rows') ?>" id="inline-insert" hidden novalidate><?= $this->csrfField() ?><input type="hidden" name="mode" value="inline"></form>
    </div>
    <div class="flex flex-col gap-2 border-b border-line bg-surface-2/30 px-4 py-2.5 text-sm sm:flex-row sm:items-center sm:justify-between" data-live-region="status">
        <p class="flex items-center gap-2 text-muted">
            <span class="size-2 rounded-full <?= e($lastSaved !== null ? 'bg-accent' : 'bg-line-strong') ?>" aria-hidden="true"></span>
            <?php if ($lastSaved !== null): ?>
            <span><strong class="font-medium text-fg"><?= e(format_number($result['total'])) ?></strong> ligne(s) enregistrée(s) dans MySQL · dernier enregistrement <strong class="font-medium text-fg"><?= e(time_ago($lastSaved)) ?></strong></span>
            <?php else: ?>
            <span>Aucune ligne enregistrée dans cette table.</span>
            <?php endif; ?>
        </p>
        <div class="flex flex-wrap gap-2">
            <form method="post" action="<?= $this->url($base . '/rows/delete') ?>" id="bulk-delete" data-confirm="Les lignes cochées seront supprimées définitivement." data-confirm-title="Supprimer la sélection" data-confirm-ok="Supprimer">
                <?= $this->csrfField() ?>
                <button type="submit" class="btn-ghost min-h-9 text-danger disabled:pointer-events-none disabled:opacity-40" data-bulk-button disabled><?= $this->icon('trash', 'size-4') ?><span>Supprimer la sélection (<span data-selected-count>0</span>)</span></button>
            </form>
            <?php if ($result['total'] > 0): ?>
            <form method="post" action="<?= $this->url($base . '/truncate') ?>" data-confirm="Les <?= e(format_number($result['total'])) ?> ligne(s) de « <?= e($table['name']) ?> » seront supprimées. La structure de la table est conservée." data-confirm-title="Vider la table" data-confirm-ok="Tout supprimer">
                <?= $this->csrfField() ?>
                <button type="submit" class="btn-ghost min-h-9 text-danger"><?= $this->icon('x', 'size-4') ?>Vider la table</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div data-live-region="table">
    <?php if ($gridError !== null): ?><p class="border-b border-line bg-danger-soft px-4 py-3 text-sm text-danger"><?= e($gridError) ?></p><?php endif; ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-line bg-surface-2/50 text-xs">
                <tr>
                    <th class="w-10 py-2.5 pr-1 pl-4"><input type="checkbox" class="checkbox" data-select-all aria-label="Tout sélectionner"></th>
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
                <?php $defs = array_column($table['columns'], null, 'name'); $firstInline = true; ?>
                <tr class="bg-accent-soft/20" data-inline-row>
                    <td class="py-2 pr-1 pl-4 text-accent-text" title="Nouvelle ligne"><?= $this->icon('plus', 'size-4') ?></td>
                    <?php foreach ($columns as $name => $col): ?>
                    <?php if (!$col['writable']): ?>
                    <?php
                        $isDate = $col['type'] === 'datetime';
                        $sysHint = match ($name) { 'id' => 'auto', 'owner_id' => 'NULL', default => '' };
                    ?>
                    <td class="px-2 py-1.5">
                        <input name="f_<?= e($name) ?>" form="inline-insert" class="input min-h-9 w-full <?= e($isDate ? 'min-w-[12.5rem]' : 'min-w-[6rem]') ?> px-2.5 py-1 font-mono text-[13px]" aria-label="<?= e($name . ($isDate ? ' (vide = maintenant)' : ' (vide = automatique)')) ?>" title="<?= e($isDate ? 'Vide = date et heure actuelles' : 'Vide = automatique') ?>" autocomplete="off"
                            type="<?= e($isDate ? 'datetime-local' : ($col['type'] === 'uuid' ? 'text' : 'number')) ?>" <?php if ($isDate): ?>step="1"<?php else: ?>placeholder="<?= e($sysHint) ?>"<?php endif; ?> <?php if ($name !== 'id' && !$isDate): ?>min="1"<?php endif; ?>>
                    </td>
                    <?php else: ?>
                    <?php
                        $def = $defs[$name] ?? null;
                        $hint = $def !== null && $def['default_value'] !== null ? 'défaut : ' . $def['default_value'] : ($col['nullable'] ? 'NULL' : $name . ' *');
                        $autofocus = $firstInline && $focusInline;
                        $firstInline = false;
                        $inputClass = 'input min-h-9 w-full min-w-[9rem] px-2.5 py-1 font-mono text-[13px]';
                        $required = !$col['nullable'] && $col['type'] !== 'boolean';
                    ?>
                    <td class="px-2 py-1.5">
                        <?php if ($col['type'] === 'boolean'): ?>
                        <label class="flex min-h-9 items-center gap-2 px-2 font-sans text-xs text-muted"><input type="checkbox" name="f_<?= e($name) ?>" value="1" form="inline-insert" class="checkbox mt-0" <?php if ($def !== null && $def['default_value'] === '1'): ?>checked<?php endif; ?> <?php if ($autofocus): ?>data-inline-autofocus<?php endif; ?> aria-label="<?= e($name) ?>">vrai</label>
                        <?php elseif ($def !== null && $def['fk_table'] !== null && isset($relations[$def['fk_table']])): ?>
                        <select name="f_<?= e($name) ?>" form="inline-insert" class="<?= e($inputClass) ?>" aria-label="<?= e($name) ?>" <?php if ($required): ?>data-required<?php endif; ?> <?php if ($autofocus): ?>data-inline-autofocus<?php endif; ?>>
                            <option value=""><?= e($col['nullable'] ? 'NULL' : '— choisir —') ?></option>
                            <?php foreach ($relations[$def['fk_table']] as $optionId => $optionLabel): ?><option value="<?= e($optionId) ?>"><?= e($optionLabel) ?></option><?php endforeach; ?>
                        </select>
                        <?php else: ?>
                        <input name="f_<?= e($name) ?>" form="inline-insert" class="<?= e($inputClass) ?>" placeholder="<?= e($hint) ?>" aria-label="<?= e($name) ?>" autocomplete="off" <?php if ($required): ?>data-required<?php endif; ?> <?php if ($autofocus): ?>data-inline-autofocus<?php endif; ?>
                            type="<?= e(match ($col['type']) { 'integer', 'bigint', 'float', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', default => 'text' }) ?>"
                            <?php if (in_array($col['type'], ['float', 'decimal'], true)): ?>step="any"<?php endif; ?>
                            <?php if ($col['type'] === 'string'): ?>maxlength="<?= e($col['length'] ?? 255) ?>"<?php endif; ?>>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="sticky right-0 bg-surface px-2 py-1.5"><button type="submit" form="inline-insert" class="btn-primary min-h-9 px-3 text-xs"><?= $this->icon('plus', 'size-3.5') ?>Insérer</button></td>
                </tr>
                <tr data-inline-error hidden><td colspan="<?= e(count($columns) + 2) ?>" class="bg-danger-soft px-4 py-2 font-sans text-sm text-danger"><span class="inline-flex items-center gap-2"><?= $this->icon('alert', 'size-4 shrink-0') ?><span data-inline-error-text></span></span></td></tr>
                <?php foreach ($result['rows'] as $row): ?>
                <?php $isNew = $recent($row, 'created_at'); $isChanged = !$isNew && $recent($row, 'updated_at'); ?>
                <tr class="group hover:bg-surface-2/40 <?= e($isNew || $isChanged ? 'bg-accent-soft/40' : '') ?>">
                    <td class="py-2.5 pr-1 pl-4"><input type="checkbox" class="checkbox" name="ids[]" value="<?= e($cell($row['id'] ?? '')) ?>" form="bulk-delete" data-row-check aria-label="Sélectionner la ligne <?= e($cell($row['id'] ?? '')) ?>"></td>
                    <?php foreach ($columns as $name => $col): ?>
                    <?php $text = $cell($row[$name] ?? null); ?>
                    <td class="max-w-[16rem] truncate px-4 py-2.5 <?= e(($row[$name] ?? null) === null ? 'text-muted/60 italic' : '') ?>" title="<?= e(mb_substr($text, 0, 500)) ?>"><?= e(mb_substr($text, 0, 120)) ?><?php if ($name === 'id' && ($isNew || $isChanged)): ?> <span class="ml-1 rounded-full bg-accent-soft px-1.5 py-0.5 font-sans text-[10px] font-semibold text-accent-text"><?= e($isNew ? 'nouvelle' : 'modifiée') ?></span><?php endif; ?></td>
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
                <tr><td colspan="<?= e(count($columns) + 2) ?>" class="px-4 py-14 text-center font-sans text-muted"><?= e($search !== '' ? 'Aucune ligne ne correspond à la recherche.' : 'La table est vide : tapez les valeurs dans la ligne verte ci-dessus, puis « Insérer ».') ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    </div>
    <div class="flex items-center justify-between border-t border-line px-4 py-3 text-sm" data-live-region="footer">
        <span class="text-muted"><?= e(format_number($result['total'])) ?> ligne(s) · page <?= e($page) ?> / <?= e($pages) ?></span>
        <span class="flex gap-2">
            <?php if ($page > 1): ?><a class="btn-secondary min-h-9" href="<?= $this->url($base . '?' . http_build_query(['page' => $page - 1, 'order' => $order, 'q' => $search])) ?>">Précédente</a><?php endif; ?>
            <?php if ($page < $pages): ?><a class="btn-secondary min-h-9" href="<?= $this->url($base . '?' . http_build_query(['page' => $page + 1, 'order' => $order, 'q' => $search])) ?>">Suivante</a><?php endif; ?>
        </span>
    </div>
</div>
<p class="mt-3 text-xs text-muted">Import CSV : première ligne = noms des colonnes (séparateur « ; » ou « , »). Les colonnes système sont ignorées.</p>

<?php $rowErrors = array_filter($errors, static fn (string $k): bool => $k === '_row' || str_starts_with($k, 'f_'), ARRAY_FILTER_USE_KEY); ?>
<dialog id="insert-row" class="m-auto w-[min(46rem,calc(100vw-2rem))] rounded-2xl border border-line bg-surface p-0 text-fg shadow-card backdrop:bg-black/60 backdrop:backdrop-blur-sm" aria-labelledby="insert-row-title" <?php if ($openInsert || $rowErrors !== []): ?>data-autoshow<?php endif; ?>>
    <form method="post" action="<?= $this->url($base . '/rows') ?>" class="flex max-h-[calc(100dvh-2rem)] flex-col" novalidate>
        <?= $this->csrfField() ?>
        <div class="flex items-start justify-between gap-4 border-b border-line px-6 py-4">
            <div>
                <h2 id="insert-row-title" class="text-base font-semibold">Insérer une ligne</h2>
                <p class="mt-0.5 text-sm text-muted">dans <span class="font-mono text-fg"><?= e($table['name']) ?></span> · <span class="font-mono">id</span>, <span class="font-mono">created_at</span> et <span class="font-mono">updated_at</span> sont remplis automatiquement</p>
            </div>
            <button type="button" class="btn-ghost size-9 shrink-0 px-0" data-close-dialog aria-label="Fermer"><?= $this->icon('x', 'size-4') ?></button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5">
            <?php if (isset($errors['_row'][0])): ?><p class="field-error mb-5 rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['_row'][0]) ?></span></p><?php endif; ?>
            <?= $this->insert('projects/row-fields', ['table' => $table, 'row' => null, 'old' => $rowErrors !== [] ? $old : [], 'errors' => $rowErrors, 'relations' => $relations]) ?>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-line bg-bg/40 px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
            <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="again" value="1" class="checkbox mt-0" <?php if ($openInsert): ?>checked<?php endif; ?>>Insérer une autre ligne ensuite</label>
            <div class="flex gap-2">
                <button type="button" class="btn-secondary" data-close-dialog>Annuler</button>
                <button type="submit" class="btn-primary"><?= $this->icon('check', 'size-4') ?>Enregistrer la ligne</button>
            </div>
        </div>
    </form>
</dialog>

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
        <form method="post" action="<?= $this->url($base . '/columns') ?>" class="card scroll-mt-24 space-y-4 p-5" id="ajouter-colonne" novalidate data-column-builder>
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
