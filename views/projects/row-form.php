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
 * @var array<string, array<string, string>> $relations
 */
$editing = $row !== null;
$this->layout('projects/layout', ['title' => ($editing ? 'Modifier' : 'Ajouter') . ' · ' . $table['name']]);
$base = '/projects/' . $project['ref'] . '/tables/' . $table['name'];
?>
<form method="post" action="<?= $this->url($base . '/rows' . ($editing ? '/' . (is_scalar($row['id'] ?? null) ? (string) $row['id'] : '') : '')) ?>" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]" novalidate>
    <?= $this->csrfField() ?>
    <section class="card p-5 sm:p-6">
        <div class="mb-6 flex items-center gap-3">
            <a href="<?= $this->url($base) ?>" class="btn-ghost size-10 px-0" aria-label="Retour à la table"><span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span></a>
            <h2 class="font-semibold"><?= e($editing ? 'Modifier la ligne ' . (is_scalar($row['id'] ?? null) ? (string) $row['id'] : '') : 'Nouvelle ligne') ?> <span class="font-mono text-muted">· <?= e($table['name']) ?></span></h2>
        </div>
        <?php if (isset($errors['_row'][0])): ?><p class="field-error mb-5 rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['_row'][0]) ?></span></p><?php endif; ?>
        <?= $this->insert('projects/row-fields', ['table' => $table, 'row' => $row, 'old' => $old, 'errors' => $errors, 'relations' => $relations]) ?>
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
