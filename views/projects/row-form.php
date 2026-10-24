<?php
/**
 * Ajout / modification d'une ligne.
 *
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string} $project
 * @var array{name: string, columns: list<array{name: string, type: string, length: int|null, nullable: bool, default_value: string|null, fk_table: string|null}>} $table
 * @var array<string, mixed>|null $row
 * @var array<string, list<string>> $errors
 * @var array<string, mixed> $old
 */
$editing = $row !== null;
$this->layout('projects/layout', ['title' => ($editing ? 'Modifier' : 'Ajouter') . ' · ' . $table['name']]);
$base = '/projects/' . $project['ref'] . '/tables/' . $table['name'];
$value = static function (string $name, string $type) use ($old, $row): string {
    if (array_key_exists('f_' . $name, $old)) {
        return is_scalar($old['f_' . $name]) ? (string) $old['f_' . $name] : '';
    }
    $v = $row[$name] ?? null;
    if ($v === null) {
        return '';
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    if (is_array($v)) {
        return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    $s = is_scalar($v) ? (string) $v : '';

    return $type === 'datetime' ? str_replace(' ', 'T', substr($s, 0, 16)) : $s;
};
?>
<form method="post" action="<?= $this->url($base . '/rows' . ($editing ? '/' . (is_scalar($row['id'] ?? null) ? (string) $row['id'] : '') : '')) ?>" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" novalidate>
    <?= $this->csrfField() ?>
    <section class="card p-5 sm:p-6">
        <div class="mb-6 flex items-center gap-3">
            <a href="<?= $this->url($base) ?>" class="btn-ghost size-10 px-0" aria-label="Retour à la table"><span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span></a>
            <h2 class="font-semibold"><?= e($editing ? 'Modifier la ligne ' . (is_scalar($row['id'] ?? null) ? (string) $row['id'] : '') : 'Nouvelle ligne') ?> <span class="font-mono text-muted">· <?= e($table['name']) ?></span></h2>
        </div>
        <?php if (isset($errors['_row'][0])): ?><p class="field-error mb-5 rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['_row'][0]) ?></span></p><?php endif; ?>
        <div class="grid gap-5 lg:grid-cols-2">
            <?php foreach ($table['columns'] as $c): ?>
            <?php $field = 'f_' . $c['name']; $error = $errors[$field][0] ?? null; $v = $value($c['name'], $c['type']); ?>
            <div class="<?= e(in_array($c['type'], ['text', 'json'], true) ? 'lg:col-span-2' : '') ?>">
                <label for="<?= e($field) ?>" class="label"><span class="font-mono"><?= e($c['name']) ?></span> <span class="font-normal text-muted">· <?= e($c['type']) ?><?= e($c['nullable'] || $c['default_value'] !== null ? '' : ' · obligatoire') ?></span></label>
                <?php if ($c['type'] === 'boolean'): ?>
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-line-strong px-3"><input type="checkbox" id="<?= e($field) ?>" name="<?= e($field) ?>" value="1" class="checkbox mt-0" <?php if ($v === '1' || ($v === '' && $c['default_value'] === '1')): ?>checked<?php endif; ?>>Vrai</label>
                <?php elseif (in_array($c['type'], ['text', 'json'], true)): ?>
                <textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= e($c['type'] === 'json' ? 6 : 4) ?>" class="input resize-y <?= e($c['type'] === 'json' ? 'font-mono text-sm' : '') ?>" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>><?= e($v) ?></textarea>
                <?php else: ?>
                <input id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($v) ?>" class="input" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>
                    type="<?= e(match ($c['type']) { 'integer', 'bigint', 'foreign' => 'number', 'float', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', default => 'text' }) ?>"
                    <?php if (in_array($c['type'], ['float', 'decimal'], true)): ?>step="any"<?php endif; ?>
                    <?php if ($c['type'] === 'string'): ?>maxlength="<?= e($c['length'] ?? 255) ?>"<?php endif; ?>
                    placeholder="<?= e($c['default_value'] !== null ? 'défaut : ' . $c['default_value'] : ($c['nullable'] ? 'vide = NULL' : '')) ?>">
                <?php endif; ?>
                <?php if ($c['fk_table'] !== null): ?><p class="field-hint">Identifiant d'une ligne de <span class="font-mono"><?= e($c['fk_table']) ?></span>.</p><?php endif; ?>
                <?php if ($error !== null): ?><p class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($error) ?></span></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($table['columns'] === []): ?><p class="text-sm text-muted lg:col-span-2">Cette table n'a que des colonnes système : ajoutez des colonnes dans l'onglet « Structure ».</p><?php endif; ?>
        </div>
    </section>
    <aside class="space-y-4">
        <?php if ($editing): ?>
        <div class="card space-y-2 p-5 font-mono text-xs text-muted">
            <?php foreach (['id', 'owner_id', 'created_at', 'updated_at'] as $sys): ?><p><span class="text-fg"><?= e($sys) ?></span> : <?= e(is_scalar($row[$sys] ?? null) ? (string) $row[$sys] : 'NULL') ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn-primary w-full"><?= e($editing ? 'Enregistrer' : 'Ajouter la ligne') ?></button>
        <a href="<?= $this->url($base) ?>" class="btn-secondary w-full">Annuler</a>
    </aside>
</form>
