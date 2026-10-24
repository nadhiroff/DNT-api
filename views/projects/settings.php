<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string, db_name: string} $project
 * @var list<string> $cors
 * @var array<string, mixed> $old
 */
$this->layout('projects/layout', ['title' => 'Paramètres · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
?>
<div class="grid gap-6 xl:grid-cols-2">
    <form method="post" action="<?= $this->url($base . '/settings') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div><h2 class="font-semibold">Général</h2><p class="mt-1 text-sm text-muted">L'identifiant <code class="text-fg"><?= e($project['ref']) ?></code> et l'URL de l'API ne changent jamais.</p></div>
        <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom du projet', 'value' => is_string($old['name'] ?? null) ? $old['name'] : $project['name'], 'maxlength' => 80, 'required' => true]) ?>
        <button type="submit" class="btn-primary">Enregistrer</button>
    </form>

    <form method="post" action="<?= $this->url($base . '/settings/cors') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div><h2 class="font-semibold">CORS (applications web)</h2><p class="mt-1 text-sm text-muted">Sites autorisés à appeler l'API depuis un navigateur. Laissez vide pour autoriser tous les sites (les applications mobiles ne sont pas concernées).</p></div>
        <div>
            <label for="f-cors" class="label">Origines autorisées (une par ligne)</label>
            <textarea id="f-cors" name="cors_origins" rows="4" class="input font-mono text-sm" placeholder="https://monapp.com&#10;http://localhost:3000"><?= e(implode("\n", $cors)) ?></textarea>
        </div>
        <button type="submit" class="btn-secondary">Enregistrer les origines</button>
    </form>

    <form id="suppression" method="post" action="<?= $this->url($base . '/delete') ?>" class="card space-y-5 border-danger/40 p-5 sm:p-6 xl:col-span-2 scroll-mt-24" novalidate data-confirm="Le projet « <?= e($project['name']) ?> », sa base de données, son utilisateur MySQL, ses clés et son historique seront supprimés définitivement." data-confirm-title="Supprimer le projet" data-confirm-ok="Oui, supprimer">
        <?= $this->csrfField() ?>
        <div><h2 class="font-semibold text-danger">Supprimer le projet</h2><p class="mt-1 text-sm text-muted">Action immédiate et irréversible : toutes les données de la base <code class="text-fg"><?= e($project['db_name']) ?></code> seront perdues et les clés API cesseront de fonctionner.</p></div>
        <div class="grid items-end gap-4 sm:grid-cols-[minmax(0,1fr)_auto]">
            <?= $this->insert('components/field', ['name' => 'confirm_name', 'label' => 'Tapez « ' . $project['name'] . ' » pour confirmer', 'autocomplete' => 'off', 'required' => true]) ?>
            <button type="submit" class="btn-danger"><?= $this->icon('trash', 'size-4') ?>Supprimer définitivement</button>
        </div>
    </form>
</div>
