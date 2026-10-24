<?php
/**
 * @var \App\Core\View\Template $this
 */
$this->layout('layouts/auth', ['title' => 'Double authentification']);
?>
<div class="animate-fade-up">
    <span class="flex size-12 items-center justify-center rounded-xl bg-accent-soft text-accent-text"><?= $this->icon('shield', 'size-6') ?></span>
    <h1 class="mt-6 text-2xl font-semibold tracking-tight">Double authentification</h1>
    <p class="mt-2 text-sm text-muted">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>
    <?= $this->insert('components/alerts') ?>

    <form method="post" action="<?= $this->url('/login/2fa') ?>" class="mt-8 space-y-5" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'code', 'label' => 'Code de vérification', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'placeholder' => '123 456', 'maxlength' => 11, 'autofocus' => true, 'required' => true, 'hint' => 'Téléphone indisponible ? Saisissez un de vos codes de secours (XXXXX-XXXXX).']) ?>
        <button type="submit" class="btn-primary w-full">Vérifier</button>
    </form>
    <p class="mt-8 text-center text-sm text-muted"><a href="<?= $this->url('/login') ?>" class="link">Revenir à la connexion</a></p>
</div>
