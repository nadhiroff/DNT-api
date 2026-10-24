<?php
/**
 * Gabarit commun des pages d'un projet : en-tête, bouton retour, onglets.
 *
 * @var \App\Core\View\Template $this
 * @var array{id: int, ref: string, name: string, status: string, created_at: string} $project
 * @var string                  $tab
 */
$this->layout('layouts/app', ['title' => $title ?? $project['name']]);
$base = '/projects/' . $project['ref'];
$tabs = [
    'overview' => [$base, 'Vue d\'ensemble', 'grid'],
    'tables' => [$base . '/tables', 'Tables', 'database'],
    'sql' => [$base . '/sql', 'Éditeur SQL', 'terminal'],
    'api' => [$base . '/api', 'API', 'api'],
    'emails' => [$base . '/emails', 'E-mails', 'mail'],
    'keys' => [$base . '/keys', 'Clés API', 'key'],
    'settings' => [$base . '/settings', 'Paramètres', 'settings'],
];
$statusLabel = ['active' => 'Actif', 'suspended' => 'Suspendu', 'provisioning' => 'Création…'][$project['status']] ?? $project['status'];
?>
<a href="<?= $this->url('/projects') ?>" class="mb-4 inline-flex min-h-9 items-center gap-1.5 rounded-lg pr-2 text-sm font-medium text-muted transition-colors hover:text-fg">
    <span class="rotate-180"><?= $this->icon('arrow-right', 'size-4') ?></span>Tous les projets
</a>
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="flex min-w-0 items-center gap-4">
        <span class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-line bg-surface text-accent-text"><?= $this->icon('database', 'size-6') ?></span>
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-semibold tracking-tight"><?= e($project['name']) ?></h1>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted">
                <span class="font-mono"><?= e($project['ref']) ?></span>
                <span class="inline-flex items-center gap-1.5"><span class="size-1.5 rounded-full <?= e($project['status'] === 'active' ? 'bg-accent' : 'bg-warning') ?>" aria-hidden="true"></span><?= e($statusLabel) ?></span>
                <span>créé le <?= e(format_datetime($project['created_at'])) ?></span>
            </p>
        </div>
    </div>
    <a href="<?= $this->url($base . '/settings#suppression') ?>" class="btn-ghost shrink-0 text-danger"><?= $this->icon('trash', 'size-4') ?>Supprimer le projet</a>
</div>

<nav class="mt-6 mb-8 flex gap-1 overflow-x-auto border-b border-line [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" aria-label="Sections du projet">
    <?php foreach ($tabs as $key => [$href, $label, $icon]): ?>
    <a href="<?= $this->url($href) ?>" class="-mb-px inline-flex min-h-11 shrink-0 items-center gap-2 border-b-2 px-4 text-sm font-medium transition-colors <?= e($tab === $key ? 'border-accent text-fg' : 'border-transparent text-muted hover:text-fg') ?>" <?php if ($tab === $key): ?>aria-current="page"<?php endif; ?>>
        <?= $this->icon($icon, 'size-4 ' . ($tab === $key ? 'text-accent-text' : '')) ?><?= e($label) ?>
    </a>
    <?php endforeach; ?>
</nav>

<?= $this->section('content') ?>
