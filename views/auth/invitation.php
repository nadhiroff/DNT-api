<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{email: string, inviter_name: string|null, message: string|null, expires_at: string} $invitation
 * @var string                  $token
 * @var array<string, list<string>> $errors
 * @var array<string, mixed>    $old
 */
$this->layout('layouts/auth', ['title' => 'Accepter l\'invitation']);
?>
<div class="animate-fade-up">
    <span class="badge border-accent/40 text-accent-text"><?= $this->icon('mail', 'size-3.5') ?>Invitation personnelle</span>
    <h1 class="mt-4 text-2xl font-semibold tracking-tight"><?= e(($invitation['inviter_name'] ?? 'Quelqu\'un') . ' vous invite sur NovaBase') ?></h1>
    <p class="mt-1.5 text-sm text-muted">Choisissez votre nom et votre mot de passe pour activer le compte <strong class="text-fg"><?= e($invitation['email']) ?></strong>.</p>
    <?php if ($invitation['message'] !== null): ?>
    <blockquote class="mt-4 rounded-lg border-l-2 border-accent bg-surface px-4 py-3 text-sm text-muted italic">« <?= e($invitation['message']) ?> »</blockquote>
    <?php endif; ?>
    <?= $this->insert('components/alerts') ?>

    <form method="post" action="<?= $this->url('/invitation/' . $token) ?>" class="mt-6 space-y-4" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom complet', 'value' => is_string($old['name'] ?? null) ? $old['name'] : '', 'autocomplete' => 'name', 'maxlength' => 120, 'autofocus' => true, 'required' => true]) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
            <?= $this->insert('components/field', ['name' => 'password_confirmation', 'label' => 'Confirmation', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
        </div>
        <p class="-mt-2 text-xs text-muted">10 caractères minimum.</p>
        <div>
            <label class="flex cursor-pointer items-start gap-3 text-sm text-muted">
                <input type="checkbox" name="terms" value="1" class="checkbox" required>
                <span>J'accepte les <a href="<?= $this->url('/conditions') ?>" class="link" target="_blank" rel="noopener">conditions d'utilisation</a> et la <a href="<?= $this->url('/confidentialite') ?>" class="link" target="_blank" rel="noopener">politique de confidentialité</a>.</span>
            </label>
            <?php if (isset($errors['terms'][0])): ?><p class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['terms'][0]) ?></span></p><?php endif; ?>
        </div>
        <button type="submit" class="btn-primary w-full">Accepter l'invitation et activer mon compte</button>
    </form>
    <p class="mt-6 text-center text-xs text-muted">Invitation valable jusqu'au <?= e(format_datetime($invitation['expires_at'])) ?>.</p>
</div>
