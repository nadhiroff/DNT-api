<?php
/**
 * @var \App\Core\View\Template      $this
 * @var int|null                     $limit
 * @var int                          $count
 * @var array<string, list<string>>  $errors
 * @var array<string, mixed>         $old
 */
$this->layout('layouts/app', ['title' => 'Nouveau projet']);
$reached = $limit !== null && $count >= $limit;
?>
<?= $this->insert('components/page-header', [
    'back' => '/projects',
    'backLabel' => 'Retour aux projets',
    'heading' => 'Créer un projet',
    'subtitle' => 'NovaBase crée une base MySQL dédiée, un utilisateur MySQL qui n\'a accès qu\'à cette base, et une paire de clés API.',
]) ?>

<div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
    <form method="post" action="<?= $this->url('/projects') ?>" class="card space-y-6 p-5 sm:p-8" novalidate data-submit-loading="Création de la base…">
        <?= $this->csrfField() ?>
        <div>
            <h2 class="font-semibold">Informations du projet</h2>
            <p class="mt-1 text-sm text-muted">Le nom est visible uniquement par vous. Il peut être modifié plus tard.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom du projet', 'value' => is_string($old['name'] ?? null) ? $old['name'] : '', 'placeholder' => 'Mon application', 'maxlength' => 80, 'autofocus' => true, 'required' => true, 'hint' => 'Lettres, chiffres, espaces, tirets et points.']) ?>
        <div class="rounded-lg border border-line bg-bg/50 p-4 text-sm">
            <p class="font-medium">Ce qui va être créé</p>
            <ul class="mt-3 space-y-2 text-muted">
                <?php foreach (['Base de données MySQL proj_xxxxxxxx (utf8mb4)', 'Utilisateur MySQL dédié, avec un mot de passe aléatoire chiffré', 'Clé publique (pk_live_…) et clé secrète (sk_live_…)', 'URL d\'API : /api/v1/<identifiant du projet>'] as $point): ?>
                <li class="flex gap-2"><?= $this->icon('check', 'mt-0.5 size-4 shrink-0 text-accent-text') ?><?= e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:justify-end">
            <a href="<?= $this->url('/projects') ?>" class="btn-secondary">Annuler</a>
            <button type="submit" class="btn-primary" <?php if ($reached): ?>disabled<?php endif; ?>><?= $this->icon('plus', 'size-4') ?>Créer le projet</button>
        </div>
    </form>

    <aside class="space-y-4">
        <div class="card p-5">
            <p class="text-sm font-medium">Votre offre</p>
            <p class="mt-3 text-3xl font-semibold tabular-nums"><?= e($count) ?> <span class="text-base font-normal text-muted">/ <?= e($limit === null ? 'illimité' : $limit) ?></span></p>
            <p class="mt-1 text-sm text-muted">projets utilisés</p>
            <?php if ($reached): ?>
            <p class="mt-4 rounded-lg border border-warning/40 bg-surface-2 p-3 text-sm text-warning">Limite atteinte : supprimez un projet ou passez à une offre supérieure (bientôt disponible).</p>
            <?php endif; ?>
        </div>
        <div class="card p-5 text-sm text-muted">
            <p class="flex items-center gap-2 font-medium text-fg"><?= $this->icon('shield', 'size-4 text-accent-text') ?>Isolation garantie</p>
            <p class="mt-2">L'API de votre projet se connecte toujours avec l'utilisateur MySQL du projet, jamais avec un compte d'administration. Une faille dans un projet ne peut pas exposer un autre projet.</p>
        </div>
    </aside>
</div>
