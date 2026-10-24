<?php
/**
 * @var \App\Core\View\Template      $this
 * @var list<array{id: int, email: string, inviter_name: string|null, message: string|null, status: string, expires_at: string, accepted_at: string|null, created_at: string}> $invitations
 * @var array<string, mixed>         $old
 */
$this->layout('layouts/app', ['title' => 'Invitations']);
$labels = ['pending' => 'En attente', 'accepted' => 'Acceptée', 'revoked' => 'Révoquée', 'expired' => 'Expirée'];
$classes = ['pending' => 'border-warning/40 text-warning', 'accepted' => 'border-accent/40 text-accent-text', 'revoked' => '', 'expired' => 'border-danger/40 text-danger'];
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'Inviter des proches', 'subtitle' => 'La personne reçoit un e-mail. Son compte reste en attente et inaccessible tant qu\'elle n\'a pas cliqué sur le lien et accepté l\'invitation (valable 7 jours).']) ?>

<div class="grid gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
    <form method="post" action="<?= $this->url('/admin/invitations') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div>
            <h2 class="flex items-center gap-2 font-semibold"><?= $this->icon('mail', 'size-5 text-accent-text') ?>Nouvelle invitation</h2>
            <p class="mt-1 text-sm text-muted">Réservé à vos proches et amis.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'value' => is_string($old['email'] ?? null) ? $old['email'] : '', 'placeholder' => 'ami@exemple.com', 'required' => true]) ?>
        <div>
            <label for="f-message" class="label">Message personnel (facultatif)</label>
            <textarea id="f-message" name="message" rows="4" maxlength="500" class="input resize-y" placeholder="Salut ! Je t'invite à tester NovaBase…"><?= e(is_string($old['message'] ?? null) ? $old['message'] : '') ?></textarea>
        </div>
        <button type="submit" class="btn-primary w-full"><?= $this->icon('mail', 'size-4') ?>Envoyer l'invitation</button>
    </form>

    <section class="card overflow-hidden">
        <div class="border-b border-line p-5 sm:px-6"><h2 class="font-semibold">Invitations envoyées</h2></div>
        <ul class="divide-y divide-line">
            <?php foreach ($invitations as $inv): ?>
            <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:px-6">
                <div class="min-w-0 flex-1">
                    <p class="flex flex-wrap items-center gap-2 text-sm"><span class="font-medium"><?= e($inv['email']) ?></span><span class="badge py-0 <?= e($classes[$inv['status']] ?? '') ?>"><?= e($labels[$inv['status']] ?? $inv['status']) ?></span></p>
                    <p class="mt-1 text-xs text-muted">Envoyée le <?= e(format_datetime($inv['created_at'])) ?><?= e($inv['inviter_name'] !== null ? ' par ' . $inv['inviter_name'] : '') ?> · <?= e($inv['status'] === 'accepted' ? 'acceptée le ' . format_datetime($inv['accepted_at']) : 'expire le ' . format_datetime($inv['expires_at'])) ?></p>
                    <?php if ($inv['message'] !== null): ?><p class="mt-1 line-clamp-1 text-xs text-muted italic">« <?= e($inv['message']) ?> »</p><?php endif; ?>
                </div>
                <?php if (in_array($inv['status'], ['pending', 'expired'], true)): ?>
                <div class="flex shrink-0 gap-2">
                    <form method="post" action="<?= $this->url('/admin/invitations/' . $inv['id'] . '/resend') ?>"><?= $this->csrfField() ?><button type="submit" class="btn-secondary min-h-9 px-3 text-xs">Renvoyer</button></form>
                    <form method="post" action="<?= $this->url('/admin/invitations/' . $inv['id'] . '/revoke') ?>" data-confirm="L'invitation de <?= e($inv['email']) ?> sera annulée et son compte en attente supprimé." data-confirm-title="Révoquer l'invitation" data-confirm-ok="Révoquer"><?= $this->csrfField() ?><button type="submit" class="btn-ghost min-h-9 px-3 text-xs text-danger">Révoquer</button></form>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if ($invitations === []): ?><li class="px-6 py-14 text-center text-sm text-muted">Aucune invitation envoyée pour l'instant.</li><?php endif; ?>
        </ul>
    </section>
</div>
