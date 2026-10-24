<?php
/**
 * Carte d'un projet.
 *
 * @var \App\Core\View\Template $this
 * @var array{project: array{ref: string, name: string, status: string, created_at: string}, bytes: int, requests: int} $item
 */
$p = $item['project'];
$statusLabel = ['active' => 'Actif', 'suspended' => 'Suspendu', 'provisioning' => 'Création…'][$p['status']] ?? $p['status'];
?>
<a href="<?= $this->url('/projects/' . $p['ref']) ?>" class="card group flex flex-col p-5 transition-[border-color,transform] duration-200 hover:-translate-y-0.5 hover:border-line-strong">
    <div class="flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface-2 text-accent-text"><?= $this->icon('database', 'size-5') ?></span>
            <div class="min-w-0">
                <p class="truncate font-semibold"><?= e($p['name']) ?></p>
                <p class="truncate font-mono text-xs text-muted"><?= e($p['ref']) ?></p>
            </div>
        </div>
        <span class="badge shrink-0 py-0 <?= e($p['status'] === 'active' ? 'border-accent/40 text-accent-text' : 'border-warning/40 text-warning') ?>"><span class="size-1.5 rounded-full <?= e($p['status'] === 'active' ? 'bg-accent' : 'bg-warning') ?>" aria-hidden="true"></span><?= e($statusLabel) ?></span>
    </div>
    <dl class="mt-6 grid grid-cols-3 gap-3 border-t border-line pt-4 text-xs">
        <div><dt class="text-muted">Stockage</dt><dd class="mt-1 font-medium tabular-nums"><?= e(format_bytes($item['bytes'])) ?></dd></div>
        <div><dt class="text-muted">Requêtes 30 j</dt><dd class="mt-1 font-medium tabular-nums"><?= e(format_number($item['requests'])) ?></dd></div>
        <div><dt class="text-muted">Créé le</dt><dd class="mt-1 font-medium"><?= e(substr(format_datetime($p['created_at']), 0, 10)) ?></dd></div>
    </dl>
</a>
