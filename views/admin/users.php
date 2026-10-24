<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<array{id: int, email: string, name: string, role: string, status: string, plan_name: string|null, plan_id: int|null, projects: int, email_verified: bool, last_login_at: string|null, created_at: string}> $users
 * @var list<array{id: int, name: string}> $plans
 * @var string $search
 * @var string $status
 * @var int    $page
 * @var int    $pages
 * @var int    $total
 */
$this->layout('layouts/app', ['title' => 'Utilisateurs']);
$statusLabels = ['active' => 'Actif', 'pending' => 'Invitation en attente', 'suspended' => 'Suspendu'];
$statusClasses = ['active' => 'border-accent/40 text-accent-text', 'pending' => 'border-warning/40 text-warning', 'suspended' => 'border-danger/40 text-danger'];
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'Utilisateurs', 'subtitle' => format_number($total) . ' compte(s). Modifiez l\'offre d\'un utilisateur ou suspendez son accès (déconnexion immédiate).', 'action' => ['/admin/invitations', 'Inviter un proche', 'mail']]) ?>

<form method="get" action="<?= $this->url('/admin/users') ?>" class="card mb-4 flex flex-col gap-3 p-4 sm:flex-row sm:items-end">
    <div class="flex-1"><label for="q" class="label">Rechercher</label><input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Nom ou e-mail" class="input"></div>
    <div class="sm:w-56"><label for="status" class="label">Statut</label>
        <select id="status" name="status" class="input">
            <option value="">Tous</option>
            <?php foreach ($statusLabels as $key => $label): ?><option value="<?= e($key) ?>" <?php if ($status === $key): ?>selected<?php endif; ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-secondary">Filtrer</button>
</form>

<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-line bg-surface-2/40 text-xs text-muted">
                <tr><th class="px-5 py-3 font-medium">Utilisateur</th><th class="px-5 py-3 font-medium">Statut</th><th class="px-5 py-3 font-medium">Offre</th><th class="px-5 py-3 font-medium">Projets</th><th class="px-5 py-3 font-medium">Dernière connexion</th><th class="px-5 py-3 font-medium">Inscrit le</th><th class="px-5 py-3 text-right font-medium">Actions</th></tr>
            </thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($users as $u): ?>
                <tr class="hover:bg-surface-2/40">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-surface-2 text-sm font-semibold text-muted"><?= e(mb_strtoupper(mb_substr($u['name'] !== '' ? $u['name'] : $u['email'], 0, 1))) ?></span>
                            <div class="min-w-0"><p class="truncate font-medium"><?= e($u['name'] !== '' ? $u['name'] : '—') ?><?php if ($u['role'] === 'superadmin'): ?> <span class="badge ml-1 py-0 border-accent/40 text-accent-text">Superadmin</span><?php endif; ?></p><p class="truncate text-xs text-muted"><?= e($u['email']) ?><?= e($u['email_verified'] ? '' : ' · non confirmée') ?></p></div>
                        </div>
                    </td>
                    <td class="px-5 py-3"><span class="badge py-0 <?= e($statusClasses[$u['status']] ?? '') ?>"><?= e($statusLabels[$u['status']] ?? $u['status']) ?></span></td>
                    <td class="px-5 py-3">
                        <?php if ($u['role'] === 'superadmin'): ?>
                        <span class="text-muted">Illimité</span>
                        <?php else: ?>
                        <form method="post" action="<?= $this->url('/admin/users/' . $u['id'] . '/plan') ?>" class="flex items-center gap-2" data-autosubmit>
                            <?= $this->csrfField() ?>
                            <select name="plan_id" class="input min-h-9 w-36 py-1 text-sm" aria-label="Offre de <?= e($u['email']) ?>">
                                <?php foreach ($plans as $plan): ?><option value="<?= e($plan['id']) ?>" <?php if ($u['plan_id'] === $plan['id']): ?>selected<?php endif; ?>><?= e($plan['name']) ?></option><?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-ghost min-h-9 px-2 text-xs">OK</button>
                        </form>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 tabular-nums"><?= e($u['projects']) ?></td>
                    <td class="px-5 py-3 text-muted"><?= e(format_datetime($u['last_login_at'])) ?></td>
                    <td class="px-5 py-3 text-muted"><?= e(format_datetime($u['created_at'])) ?></td>
                    <td class="px-5 py-3 text-right">
                        <?php if ($u['role'] !== 'superadmin' && $u['status'] !== 'pending'): ?>
                        <?php $suspend = $u['status'] === 'active'; ?>
                        <form method="post" action="<?= $this->url('/admin/users/' . $u['id'] . '/status') ?>" data-confirm="<?= e($suspend ? 'Le compte ' . $u['email'] . ' sera suspendu et déconnecté de tous ses appareils.' : 'Le compte ' . $u['email'] . ' pourra de nouveau se connecter.') ?>" data-confirm-title="<?= e($suspend ? 'Suspendre le compte' : 'Réactiver le compte') ?>" data-confirm-ok="<?= e($suspend ? 'Suspendre' : 'Réactiver') ?>">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="status" value="<?= e($suspend ? 'suspended' : 'active') ?>">
                            <button type="submit" class="<?= e($suspend ? 'btn-ghost text-danger' : 'btn-secondary') ?> min-h-9 px-3 text-xs"><?= e($suspend ? 'Suspendre' : 'Réactiver') ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if ($users === []): ?><tr><td colspan="7" class="px-5 py-12 text-center text-muted">Aucun utilisateur ne correspond.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($pages > 1): ?>
<nav class="mt-4 flex items-center justify-between text-sm" aria-label="Pagination">
    <span class="text-muted">Page <?= e($page) ?> sur <?= e($pages) ?></span>
    <span class="flex gap-2">
        <?php if ($page > 1): ?><a class="btn-secondary min-h-9" href="<?= $this->url('/admin/users?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $page - 1])) ?>">Précédente</a><?php endif; ?>
        <?php if ($page < $pages): ?><a class="btn-secondary min-h-9" href="<?= $this->url('/admin/users?' . http_build_query(['q' => $search, 'status' => $status, 'page' => $page + 1])) ?>">Suivante</a><?php endif; ?>
    </span>
</nav>
<?php endif; ?>
