<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<string>            $codes
 */
$this->layout('layouts/app', ['title' => 'Codes de secours']);
?>
<div class="mx-auto max-w-xl">
    <span class="flex size-12 items-center justify-center rounded-xl bg-accent-soft text-accent-text"><?= $this->icon('key', 'size-6') ?></span>
    <h1 class="mt-6 text-2xl font-semibold tracking-tight">Vos codes de secours</h1>
    <p class="mt-2 text-sm text-muted">Chaque code permet une connexion si vous n'avez pas votre téléphone. <strong class="text-fg">Ils ne seront plus jamais affichés</strong> : conservez-les dans un gestionnaire de mots de passe.</p>

    <div class="card mt-6 p-5">
        <ol class="grid grid-cols-2 gap-x-6 gap-y-3 font-mono text-[15px] tracking-wider">
            <?php foreach ($codes as $code): ?>
            <li><?= e($code) ?></li>
            <?php endforeach; ?>
        </ol>
        <button type="button" class="btn-secondary mt-5 w-full" data-copy="<?= e(implode("\n", $codes)) ?>"><?= $this->icon('copy', 'size-4') ?>Copier les codes</button>
    </div>
    <a href="<?= $this->url('/console') ?>" class="btn-primary mt-6 w-full">J'ai sauvegardé mes codes</a>
</div>
