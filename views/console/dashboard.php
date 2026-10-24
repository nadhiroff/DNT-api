<?php
/**
 * @var \App\Core\View\Template                    $this
 * @var array{name: string, role: string}          $user
 */
$this->layout('layouts/app', ['title' => 'Tableau de bord']);
$firstName = explode(' ', trim($user['name']))[0];
$steps = [
    ['database', 'Créer un projet', 'Une base MySQL dédiée et isolée, prête en quelques secondes.'],
    ['grid', 'Définir vos tables', 'Éditeur visuel : colonnes, types, relations, règles d\'accès.'],
    ['api', 'Appeler l\'API', 'REST instantanée avec clés publiques et secrètes.'],
];
?>
<div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
    <div>
        <p class="text-sm text-muted">Tableau de bord</p>
        <h1 class="mt-1 text-2xl font-semibold tracking-tight">Bonjour <?= e($firstName) ?></h1>
    </div>
    <button type="button" class="btn-primary" disabled title="Disponible très prochainement"><?= $this->icon('plus', 'size-4') ?>Nouveau projet</button>
</div>

<section class="card relative mt-8 overflow-hidden p-8 text-center sm:p-12">
    <div class="bg-grid pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
    <div class="relative">
        <span class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-accent-soft text-accent-text"><?= $this->icon('folder', 'size-7') ?></span>
        <h2 class="mt-5 text-lg font-semibold">Aucun projet pour l'instant</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">La création de projets arrive très prochainement. Votre compte est prêt : voici ce qui vous attend.</p>
    </div>
    <ol class="relative mt-10 grid gap-4 text-left sm:grid-cols-3">
        <?php foreach ($steps as $i => [$icon, $title, $text]): ?>
        <li class="rounded-xl border border-line bg-bg/60 p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-8 items-center justify-center rounded-lg bg-surface-2 text-accent-text"><?= $this->icon($icon, 'size-4') ?></span>
                <span class="font-mono text-xs text-muted">0<?= e($i + 1) ?></span>
            </div>
            <h3 class="mt-4 text-sm font-semibold"><?= e($title) ?></h3>
            <p class="mt-1 text-sm text-muted"><?= e($text) ?></p>
        </li>
        <?php endforeach; ?>
    </ol>
</section>
