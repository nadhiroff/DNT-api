<?php
/**
 * @var \App\Core\View\Template                                                                    $this
 * @var array{id: int, email: string, name: string, role: string, totp_enabled_at: string|null}   $user
 * @var list<array{id: string, ip: string, user_agent: string, last_activity: int, created_at: int}> $sessions
 * @var string                                                                                     $currentSessionId
 * @var int                                                                                        $recoveryRemaining
 * @var bool                                                                                       $twoFactorRequired
 * @var array<string, list<string>>                                                                $errors
 */
$this->layout('layouts/app', ['title' => 'Mon compte']);
$twoFactorOn = $user['totp_enabled_at'] !== null;
$roles = ['superadmin' => 'Superadmin', 'admin' => 'Administrateur', 'user' => 'Utilisateur'];
?>
<div class="flex flex-col gap-1">
    <h1 class="text-2xl font-semibold tracking-tight">Mon compte</h1>
    <p class="text-sm text-muted">Profil, sécurité et sessions.</p>
</div>

<div class="mt-8 grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)]">
    <nav class="hidden lg:block" aria-label="Sections du compte">
        <ul class="sticky top-24 space-y-1 text-sm">
            <?php foreach (['profil' => 'Profil', 'mot-de-passe' => 'Mot de passe', 'double-authentification' => 'Double authentification', 'sessions' => 'Sessions actives', 'suppression' => 'Supprimer le compte'] as $anchor => $label): ?>
            <li><a href="#<?= e($anchor) ?>" class="block rounded-md px-3 py-2 text-muted hover:bg-surface-2 hover:text-fg"><?= e($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="space-y-6">
        <section id="profil" class="card scroll-mt-24 p-5 sm:p-6">
            <h2 class="font-semibold">Profil</h2>
            <form method="post" action="<?= $this->url('/account/profile') ?>" class="mt-5 grid gap-5 sm:grid-cols-2" novalidate>
                <?= $this->csrfField() ?>
                <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom', 'value' => $user['name'], 'autocomplete' => 'name', 'maxlength' => 120, 'required' => true]) ?>
                <?= $this->insert('components/field', ['name' => 'email_display', 'label' => 'Adresse e-mail', 'value' => $user['email'], 'readonly' => true, 'hint' => 'Rôle : ' . ($roles[$user['role']] ?? $user['role'])]) ?>
                <div class="sm:col-span-2"><button type="submit" class="btn-secondary">Enregistrer</button></div>
            </form>
        </section>

        <section id="mot-de-passe" class="card scroll-mt-24 p-5 sm:p-6">
            <h2 class="font-semibold">Mot de passe</h2>
            <p class="mt-1 text-sm text-muted">Vos autres sessions seront fermées après le changement.</p>
            <form method="post" action="<?= $this->url('/account/password') ?>" class="mt-5 grid gap-5 sm:grid-cols-2" novalidate>
                <?= $this->csrfField() ?>
                <div class="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]">
                    <?= $this->insert('components/field', ['name' => 'current_password', 'label' => 'Mot de passe actuel', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?>
                </div>
                <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => '10 caractères minimum.', 'maxlength' => 128, 'required' => true]) ?>
                <?= $this->insert('components/field', ['name' => 'password_confirmation', 'label' => 'Confirmation', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
                <div class="sm:col-span-2"><button type="submit" class="btn-secondary">Changer le mot de passe</button></div>
            </form>
        </section>

        <section id="double-authentification" class="card scroll-mt-24 p-5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-semibold">Double authentification (TOTP)</h2>
                    <p class="mt-1 text-sm text-muted">Un code de votre téléphone est demandé à chaque connexion.</p>
                </div>
                <?php if ($twoFactorOn): ?>
                <span class="badge border-accent/40 text-accent-text"><span class="size-1.5 rounded-full bg-accent" aria-hidden="true"></span>Activée</span>
                <?php else: ?>
                <span class="badge">Désactivée</span>
                <?php endif; ?>
            </div>

            <?php if (!$twoFactorOn): ?>
            <a href="<?= $this->url('/account/2fa') ?>" class="btn-primary mt-5"><?= $this->icon('shield', 'size-4') ?>Activer la double authentification</a>
            <?php else: ?>
            <p class="mt-4 text-sm text-muted">Codes de secours restants : <strong class="text-fg"><?= e($recoveryRemaining) ?></strong> / 10</p>
            <div class="mt-5 grid gap-6 <?= e($twoFactorRequired ? '' : 'md:grid-cols-2') ?>">
                <form method="post" action="<?= $this->url('/account/2fa/recovery-codes') ?>" class="space-y-3 rounded-lg border border-line p-4" novalidate>
                    <?= $this->csrfField() ?>
                    <p class="text-sm font-medium">Régénérer les codes de secours</p>
                    <?= $this->insert('components/field', ['name' => 'recovery_password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?>
                    <button type="submit" class="btn-secondary">Générer 10 nouveaux codes</button>
                </form>
                <?php if (!$twoFactorRequired): ?>
                <form method="post" action="<?= $this->url('/account/2fa/disable') ?>" class="space-y-3 rounded-lg border border-danger/30 p-4" novalidate>
                    <?= $this->csrfField() ?>
                    <p class="text-sm font-medium">Désactiver la double authentification</p>
                    <?= $this->insert('components/field', ['name' => 'disable_password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?>
                    <button type="submit" class="btn-secondary text-danger">Désactiver</button>
                </form>
                <?php else: ?>
                <p class="text-xs text-muted">Obligatoire pour votre rôle : elle ne peut pas être désactivée.</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </section>

        <section id="sessions" class="card scroll-mt-24 overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 p-5 sm:p-6">
                <div>
                    <h2 class="font-semibold">Sessions actives</h2>
                    <p class="mt-1 text-sm text-muted">Appareils connectés à votre compte au cours des 2 dernières heures.</p>
                </div>
                <?php if (count($sessions) > 1): ?>
                <form method="post" action="<?= $this->url('/account/sessions/revoke-others') ?>">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn-secondary">Fermer les autres sessions</button>
                </form>
                <?php endif; ?>
            </div>
            <ul class="divide-y divide-line border-t border-line">
                <?php foreach ($sessions as $s): ?>
                <li class="flex items-center gap-4 px-5 py-4 sm:px-6">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-surface-2 text-muted"><?= $this->icon('monitor', 'size-5') ?></span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2 text-sm font-medium">
                            <?= e(user_agent_label($s['user_agent'])) ?>
                            <?php if ($s['id'] === $currentSessionId): ?><span class="badge border-accent/40 py-0 text-accent-text">Cette session</span><?php endif; ?>
                        </p>
                        <p class="truncate text-xs text-muted"><?= e($s['ip']) ?> · active <?= e(time_ago($s['last_activity'])) ?></p>
                    </div>
                    <?php if ($s['id'] !== $currentSessionId): ?>
                    <form method="post" action="<?= $this->url('/account/sessions/' . $s['id'] . '/revoke') ?>">
                        <?= $this->csrfField() ?>
                        <button type="submit" class="btn-ghost text-danger">Fermer</button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <section id="suppression" class="card scroll-mt-24 border-danger/30 p-5 sm:p-6">
            <h2 class="font-semibold text-danger">Supprimer le compte</h2>
            <p class="mt-1 text-sm text-muted">Action définitive : votre compte et toutes ses données seront effacés.</p>
            <form method="post" action="<?= $this->url('/account/delete') ?>" class="mt-5 grid gap-5 sm:grid-cols-2" novalidate>
                <?= $this->csrfField() ?>
                <?= $this->insert('components/field', ['name' => 'delete_password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true]) ?>
                <?= $this->insert('components/field', ['name' => 'delete_confirm', 'label' => 'Tapez SUPPRIMER pour confirmer', 'autocomplete' => 'off', 'required' => true]) ?>
                <div class="sm:col-span-2"><button type="submit" class="btn-danger">Supprimer définitivement mon compte</button></div>
            </form>
        </section>
    </div>
</div>
