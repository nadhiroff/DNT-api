<?php
/**
 * Champs de définition d'une colonne (nouvelle table ou ajout de colonne).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $prefix nom de base des champs (columns[0] ou column)
 * @var list<string>            $tableNames tables disponibles pour une relation
 */
$types = \App\Services\Data\ColumnTypes::LABELS;
$onDelete = \App\Services\Data\ColumnTypes::ON_DELETE;
?>
<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[1.2fr_1fr_0.6fr_1fr_auto]" data-column-row>
    <div><label class="label text-xs">Nom</label><input name="<?= e($prefix) ?>[name]" class="input min-h-10 font-mono text-sm" placeholder="prix" pattern="[a-z][a-z0-9_]{0,62}" maxlength="63"></div>
    <div><label class="label text-xs">Type</label>
        <select name="<?= e($prefix) ?>[type]" class="input min-h-10 text-sm" data-column-type>
            <?php foreach ($types as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div><label class="label text-xs">Longueur</label><input name="<?= e($prefix) ?>[length]" type="number" min="1" max="255" value="255" class="input min-h-10 text-sm"></div>
    <div><label class="label text-xs">Valeur par défaut</label><input name="<?= e($prefix) ?>[default_value]" class="input min-h-10 text-sm" placeholder="(aucune)"></div>
    <div class="flex items-end"><button type="button" class="btn-ghost size-10 px-0 text-danger" data-column-remove aria-label="Retirer la colonne"><?= $this->icon('trash', 'size-4') ?></button></div>
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm sm:col-span-2 xl:col-span-5">
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="<?= e($prefix) ?>[nullable]" value="1" class="checkbox mt-0" checked>Peut être vide</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="<?= e($prefix) ?>[is_unique]" value="1" class="checkbox mt-0">Unique</label>
        <label class="inline-flex items-center gap-2"><input type="checkbox" name="<?= e($prefix) ?>[is_indexed]" value="1" class="checkbox mt-0">Indexée (recherche rapide)</label>
        <span class="inline-flex flex-wrap items-center gap-2" data-fk-fields hidden>
            <span class="text-muted">→ table liée</span>
            <select name="<?= e($prefix) ?>[fk_table]" class="input min-h-9 w-40 py-1 text-sm"><?php foreach ($tableNames as $name): ?><option value="<?= e($name) ?>"><?= e($name) ?></option><?php endforeach; ?></select>
            <select name="<?= e($prefix) ?>[fk_on_delete]" class="input min-h-9 w-52 py-1 text-sm"><?php foreach ($onDelete as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
        </span>
    </div>
</div>
