<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<array{project: array{ref: string, name: string, status: string, created_at: string}, bytes: int, requests: int}> $items
 * @var int|null                $limit
 */
$this->layout('layouts/app', ['title' => 'Projets']);
?>
<?= $this->insert('components/page-header', [
    'eyebrow' => 'Console',
    'heading' => 'Projets',
    'subtitle' => 'Chaque projet possède sa propre base MySQL et son propre utilisateur MySQL : aucune donnée n\'est partagée entre projets.' . ($limit !== null ? ' ' . count($items) . ' / ' . $limit . ' projet(s) utilisés.' : ''),
    'action' => ['/projects/new', 'Nouveau projet', 'plus'],
]) ?>

<?php if ($items === []): ?>
<div class="card relative overflow-hidden px-6 py-20 text-center">
    <div class="bg-grid pointer-events-none absolute inset-0 opacity-50" aria-hidden="true"></div>
    <div class="relative">
        <span class="mx-auto flex size-16 items-center justify-center rounded-2xl bg-accent-soft text-accent-text"><?= $this->icon('database', 'size-8') ?></span>
        <h2 class="mt-6 text-xl font-semibold">Aucun projet pour l'instant</h2>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted">Créez votre premier projet : sa base de données, son utilisateur MySQL et ses clés API sont prêts en quelques secondes.</p>
        <a href="<?= $this->url('/projects/new') ?>" class="btn-primary mt-8"><?= $this->icon('plus', 'size-4') ?>Créer un projet</a>
    </div>
</div>
<?php else: ?>
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($items as $item): ?>
    <?= $this->insert('projects/card', ['item' => $item]) ?>
    <?php endforeach; ?>
    <a href="<?= $this->url('/projects/new') ?>" class="flex min-h-44 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-line-strong text-sm font-medium text-muted transition-colors hover:border-accent hover:text-accent-text">
        <span class="flex size-10 items-center justify-center rounded-full border border-current"><?= $this->icon('plus', 'size-5') ?></span>
        Nouveau projet
    </a>
</div>
<?php endif; ?>
