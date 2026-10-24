<?php
/**
 * Champs d'une ligne (ajout / modification), partagés par la page de ligne et la fenêtre « Insérer une ligne ».
 *
 * @var \App\Core\View\Template $this
 * @var array{name: string, pk_type?: string, columns: list<array{name: string, type: string, length: int|null, nullable: bool, default_value: string|null, fk_table: string|null}>} $table
 * @var array<string, mixed>|null $row
 * @var array<string, mixed> $old
 * @var array<string, list<string>> $errors
 * @var array<string, array<string, string>> $relations lignes proposées pour les colonnes « relation »
 */
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
        <div class="grid gap-5 lg:grid-cols-2">
            <?php foreach ($table['columns'] as $c): ?>
            <?php $field = 'f_' . $c['name']; $error = $errors[$field][0] ?? null; $v = $value($c['name'], $c['type']); ?>
            <div class="<?= e(in_array($c['type'], ['text', 'json'], true) ? 'lg:col-span-2' : '') ?>">
                <label for="<?= e($field) ?>" class="label"><span class="font-mono"><?= e($c['name']) ?></span> <span class="font-normal text-muted">· <?= e($c['type']) ?><?= e($c['nullable'] || $c['default_value'] !== null ? '' : ' · obligatoire') ?></span></label>
                <?php if ($c['type'] === 'boolean'): ?>
                <label class="flex min-h-11 items-center gap-3 rounded-lg border border-line-strong px-3"><input type="checkbox" id="<?= e($field) ?>" name="<?= e($field) ?>" value="1" class="checkbox mt-0" <?php if ($v === '1' || ($v === '' && $c['default_value'] === '1')): ?>checked<?php endif; ?>>Vrai</label>
                <?php elseif ($c['fk_table'] !== null && isset($relations[$c['fk_table']])): ?>
                <select id="<?= e($field) ?>" name="<?= e($field) ?>" class="input" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>>
                    <option value=""><?= e($c['nullable'] ? '— Aucune (NULL) —' : '— Choisir —') ?></option>
                    <?php foreach ($relations[$c['fk_table']] as $optionId => $optionLabel): ?>
                    <option value="<?= e($optionId) ?>" <?php if ((string) $optionId === $v): ?>selected<?php endif; ?>><?= e($optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php elseif (in_array($c['type'], ['text', 'json'], true)): ?>
                <textarea id="<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= e($c['type'] === 'json' ? 6 : 4) ?>" class="input resize-y <?= e($c['type'] === 'json' ? 'font-mono text-sm' : '') ?>" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>><?= e($v) ?></textarea>
                <?php else: ?>
                <input id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($v) ?>" class="input" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>
                    type="<?= e(match ($c['type']) { 'integer', 'bigint', 'foreign' => 'number', 'float', 'decimal' => 'number', 'date' => 'date', 'datetime' => 'datetime-local', default => 'text' }) ?>"
                    <?php if (in_array($c['type'], ['float', 'decimal'], true)): ?>step="any"<?php endif; ?>
                    <?php if ($c['type'] === 'string'): ?>maxlength="<?= e($c['length'] ?? 255) ?>"<?php endif; ?>
                    placeholder="<?= e($c['default_value'] !== null ? 'défaut : ' . $c['default_value'] : ($c['nullable'] ? 'vide = NULL' : '')) ?>">
                <?php endif; ?>
                <?php if ($c['fk_table'] !== null): ?><p class="field-hint">Ligne liée de la table <span class="font-mono"><?= e($c['fk_table']) ?></span>.</p><?php endif; ?>
                <?php if ($error !== null): ?><p class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($error) ?></span></p><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ($table['columns'] === []): ?><p class="text-sm text-muted lg:col-span-2">Cette table n'a que des colonnes système : ajoutez des colonnes dans l'onglet « Structure ».</p><?php endif; ?>
        </div>
<?php if ($row === null): ?>
<?php $systemOpen = array_filter(['f_id', 'f_owner_id', 'f_created_at', 'f_updated_at'], static fn (string $k): bool => ($old[$k] ?? '') !== '' || isset($errors[$k])) !== []; ?>
<details class="mt-5 rounded-lg border border-line" <?php if ($systemOpen): ?>open<?php endif; ?>>
    <summary class="cursor-pointer px-4 py-3 text-sm font-medium">Colonnes automatiques <span class="font-normal text-muted">· facultatif, vide = automatique</span></summary>
    <div class="grid gap-4 border-t border-line p-4 lg:grid-cols-2">
        <?php foreach (['id' => 'auto', 'owner_id' => 'NULL', 'created_at' => '', 'updated_at' => ''] as $sys => $sysHint): ?>
        <?php $field = 'f_' . $sys; $error = $errors[$field][0] ?? null; $isDate = $sys === 'created_at' || $sys === 'updated_at'; ?>
        <div>
            <label for="<?= e($field) ?>" class="label"><span class="font-mono"><?= e($sys) ?></span> <span class="font-normal text-muted">· <?= e($isDate ? 'vide = maintenant' : 'vide = ' . $sysHint) ?></span></label>
            <input id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e(is_scalar($old[$field] ?? null) ? (string) $old[$field] : '') ?>" class="input font-mono text-sm" autocomplete="off" <?php if ($error !== null): ?>aria-invalid="true"<?php endif; ?>
                type="<?= e($isDate ? 'datetime-local' : ($sys === 'id' && ($table['pk_type'] ?? 'bigint') === 'uuid' ? 'text' : 'number')) ?>" <?php if ($isDate): ?>step="1"<?php else: ?>placeholder="<?= e($sysHint) ?>"<?php endif; ?>>
            <?php if ($error !== null): ?><p class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($error) ?></span></p><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>
