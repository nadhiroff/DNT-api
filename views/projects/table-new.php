<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string} $project
 * @var list<array{name: string}> $tables
 * @var array<string, list<string>> $errors
 * @var array<string, mixed> $old
 */
$this->layout('projects/layout', ['title' => 'Nouvelle table · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
$tableNames = array_column($tables, 'name');
$generalError = $errors['columns'][0] ?? null;
?>
<form method="post" action="<?= $this->url($base . '/tables') ?>" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]" novalidate data-column-builder>
    <?= $this->csrfField() ?>
    <div class="space-y-6">
        <section class="card space-y-5 p-5 sm:p-6">
            <div class="flex items-center justify-between"><h2 class="font-semibold">Nouvelle table</h2><a href="<?= $this->url($base . '/tables') ?>" class="text-sm text-muted hover:text-fg">Annuler</a></div>
            <div class="grid gap-5 sm:grid-cols-2">
                <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom de la table', 'value' => is_string($old['name'] ?? null) ? $old['name'] : '', 'placeholder' => 'produits', 'maxlength' => 63, 'autofocus' => true, 'required' => true, 'hint' => 'Minuscules, chiffres et « _ ». Ce nom apparaît dans l\'URL de l\'API.']) ?>
                <div>
                    <label for="f-pk_type" class="label">Identifiant (id)</label>
                    <select id="f-pk_type" name="pk_type" class="input"><option value="bigint">Nombre auto-incrémenté (1, 2, 3…)</option><option value="uuid">UUID (difficile à deviner)</option></select>
                    <p class="field-hint">Choisissez UUID si les identifiants sont visibles par vos utilisateurs.</p>
                </div>
            </div>
        </section>

        <section class="card p-5 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div><h2 class="font-semibold">Colonnes</h2><p class="text-sm text-muted">En plus de id, owner_id, created_at et updated_at.</p></div>
                <button type="button" class="btn-secondary" data-column-add><?= $this->icon('plus', 'size-4') ?>Ajouter une colonne</button>
            </div>
            <?php if ($generalError !== null): ?><p class="field-error mt-4 rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($generalError) ?></span></p><?php endif; ?>
            <div class="mt-5 space-y-4" data-column-list>
                <div class="rounded-xl border border-line p-4"><?= $this->insert('projects/column-fields', ['prefix' => 'columns[0]', 'tableNames' => $tableNames]) ?></div>
            </div>
            <template data-column-template>
                <div class="rounded-xl border border-line p-4"><?= $this->insert('projects/column-fields', ['prefix' => 'columns[__i__]', 'tableNames' => $tableNames]) ?></div>
            </template>
        </section>
    </div>
    <aside class="space-y-4">
        <div class="card p-5 text-sm">
            <p class="font-medium">Colonnes automatiques</p>
            <ul class="mt-3 space-y-2 font-mono text-xs text-muted">
                <li><span class="text-fg">id</span> · clé primaire</li>
                <li><span class="text-fg">owner_id</span> · utilisateur propriétaire (règle « propriétaire »)</li>
                <li><span class="text-fg">created_at</span> · date de création</li>
                <li><span class="text-fg">updated_at</span> · date de modification</li>
            </ul>
        </div>
        <div class="card p-5 text-sm text-muted">
            <p class="font-medium text-fg">Règles d'accès par défaut</p>
            <p class="mt-2">Lecture publique, écriture réservée à la clé secrète. Modifiables ensuite dans l'onglet « Règles d'accès » de la table.</p>
        </div>
        <button type="submit" class="btn-primary w-full"><?= $this->icon('plus', 'size-4') ?>Créer la table</button>
    </aside>
</form>
