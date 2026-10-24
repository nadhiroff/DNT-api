<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $token
 */
$this->layout('layouts/auth', ['title' => 'Nouveau mot de passe']);
?>
<div class="animate-fade-up">
    <h1 class="text-2xl font-semibold tracking-tight">Choisissez un nouveau mot de passe</h1>
    <p class="mt-2 text-sm text-muted">Toutes vos sessions ouvertes seront fermées après le changement.</p>

    <form method="post" action="<?= $this->url('/reset-password/' . $token) ?>" class="mt-8 space-y-5" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Nouveau mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => '10 caractères minimum, différent des mots de passe courants.', 'maxlength' => 128, 'autofocus' => true, 'required' => true]) ?>
        <?= $this->insert('components/field', ['name' => 'password_confirmation', 'label' => 'Confirmation', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
        <button type="submit" class="btn-primary w-full">Enregistrer le mot de passe</button>
    </form>
</div>
