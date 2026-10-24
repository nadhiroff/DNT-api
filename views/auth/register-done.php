<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $email
 */
$this->layout('layouts/auth', ['title' => 'Vérifiez votre boîte mail']);
?>
<div class="animate-fade-up text-center sm:text-left">
    <span class="mx-auto flex size-12 items-center justify-center rounded-xl bg-accent-soft text-accent-text sm:mx-0"><?= $this->icon('mail', 'size-6') ?></span>
    <h1 class="mt-6 text-2xl font-semibold tracking-tight">Vérifiez votre boîte mail</h1>
    <div class="mt-4 space-y-3 text-left empty:hidden"><?= $this->insert('components/alerts') ?></div>
    <p class="mt-3 text-muted">Nous avons envoyé un lien de confirmation à <strong class="font-medium text-fg break-all"><?= e($email) ?></strong>. Il est valable 1 heure.</p>
    <ul class="mt-6 space-y-2 text-left text-sm text-muted">
        <li class="flex gap-2"><?= $this->icon('check', 'mt-0.5 size-4 shrink-0 text-accent-text') ?>Cliquez sur le lien pour activer votre compte.</li>
        <li class="flex gap-2"><?= $this->icon('check', 'mt-0.5 size-4 shrink-0 text-accent-text') ?>Rien reçu ? Regardez dans les indésirables, ou connectez-vous pour renvoyer le lien.</li>
    </ul>
    <a href="<?= $this->url('/login') ?>" class="btn-primary mt-8 w-full">Aller à la connexion</a>
</div>
