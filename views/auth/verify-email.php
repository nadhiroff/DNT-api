<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $email
 */
$this->layout('layouts/auth', ['title' => 'Confirmez votre adresse']);
?>
<div class="animate-fade-up">
    <span class="flex size-12 items-center justify-center rounded-xl bg-accent-soft text-accent-text"><?= $this->icon('mail', 'size-6') ?></span>
    <h1 class="mt-6 text-2xl font-semibold tracking-tight">Confirmez votre adresse e-mail</h1>
    <p class="mt-3 text-sm text-muted">Pour accéder à la console, cliquez sur le lien envoyé à <strong class="font-medium text-fg break-all"><?= e($email) ?></strong>.</p>

    <div class="mt-6 space-y-3 empty:hidden"><?= $this->insert('components/alerts') ?></div>

    <form method="post" action="<?= $this->url('/email/verify/resend') ?>" class="mt-8">
        <?= $this->csrfField() ?>
        <button type="submit" class="btn-primary w-full">Renvoyer le lien</button>
    </form>
    <form method="post" action="<?= $this->url('/logout') ?>" class="mt-3">
        <?= $this->csrfField() ?>
        <button type="submit" class="btn-ghost w-full">Se déconnecter</button>
    </form>
</div>
