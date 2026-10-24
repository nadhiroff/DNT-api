<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<array{id: int, ref: string, name: string, status: string, db_name: string, created_at: string, owner_email: string, owner_name: string}> $projects
 * @var array<string, int>      $sizes
 * @var string                  $search
 */
$this->layout('layouts/app', ['title' => 'Tous les projets']);
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'Tous les projets', 'subtitle' => 'Bases de données de tous les utilisateurs. Un projet suspendu voit son API répondre 403.']) ?>

<form method="get" action="<?= $this->url('/admin/projects') ?>" class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
    <div class="flex-1"><label for="q" class="label">Rechercher</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Nom, identifiant ou e-mail du propriétaire" class="input"></div>
    <button type="submit" class="btn-secondary">Rechercher</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-left text-sm">
            <thead class="border-b border-line bg-surface-2/40 text-xs text-muted"><tr><th class="px-5 py-3 font-medium">Projet</th><th class="px-5 py-3 font-medium">Propriétaire</th><th class="px-5 py-3 font-medium">Base</th><th class="px-5 py-3 font-medium">Taille</th><th class="px-5 py-3 font-medium">Statut</th><th class="px-5 py-3 font-medium">Créé le</th><th class="px-5 py-3 text-right font-medium">Actions</th></tr></thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($projects as $p): ?>
                <tr class="hover:bg-surface-2/40">
                    <td class="px-5 py-3"><a href="<?= $this->url('/projects/' . $p['ref']) ?>" class="font-medium hover:text-accent-text"><?= e($p['name']) ?></a><p class="font-mono text-xs text-muted"><?= e($p['ref']) ?></p></td>
                    <td class="px-5 py-3"><p><?= e($p['owner_name']) ?></p><p class="text-xs text-muted"><?= e($p['owner_email']) ?></p></td>
                    <td class="px-5 py-3 font-mono text-xs"><?= e($p['db_name']) ?></td>
                    <td class="px-5 py-3 tabular-nums"><?= e(format_bytes($sizes[$p['db_name']] ?? 0)) ?></td>
                    <td class="px-5 py-3"><span class="badge py-0 <?= e($p['status'] === 'active' ? 'border-accent/40 text-accent-text' : 'border-warning/40 text-warning') ?>"><?= e($p['status'] === 'active' ? 'Actif' : 'Suspendu') ?></span></td>
                    <td class="px-5 py-3 text-muted"><?= e(format_datetime($p['created_at'])) ?></td>
                    <td class="px-5 py-3 text-right">
                        <form method="post" action="<?= $this->url('/admin/projects/' . $p['ref'] . '/suspend') ?>" data-confirm="<?= e($p['status'] === 'active' ? 'L\'API du projet « ' . $p['name'] . ' » sera bloquée (403) jusqu\'à sa réactivation.' : 'Le projet « ' . $p['name'] . ' » sera de nouveau accessible.') ?>" data-confirm-title="<?= e($p['status'] === 'active' ? 'Suspendre le projet' : 'Réactiver le projet') ?>" data-confirm-ok="<?= e($p['status'] === 'active' ? 'Suspendre' : 'Réactiver') ?>">
                            <?= $this->csrfField() ?>
                            <button type="submit" class="<?= e($p['status'] === 'active' ? 'btn-ghost text-danger' : 'btn-secondary') ?> min-h-9 px-3 text-xs"><?= e($p['status'] === 'active' ? 'Suspendre' : 'Réactiver') ?></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($projects === []): ?><tr><td colspan="7" class="px-5 py-12 text-center text-muted">Aucun projet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
