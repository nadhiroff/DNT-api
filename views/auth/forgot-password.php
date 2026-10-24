<?php
/**
 * @var \App\Core\View\Template $this
 * @var array<string, mixed>    $old
 */
$this->layout('layouts/auth', ['title' => 'Mot de passe oublié']);
?>
<div class="animate-fade-up">
    <h1 class="text-2xl font-semibold tracking-tight">Mot de passe oublié</h1>
    <p class="mt-2 text-sm text-muted">Indiquez votre adresse : nous vous enverrons un lien pour choisir un nouveau mot de passe.</p>

    <div class="mt-6 space-y-3 empty:hidden"><?= $this->insert('components/alerts') ?></div>

    <form method="post" action="<?= $this->url('/forgot-password') ?>" class="mt-8 space-y-5" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'value' => is_string($old['email'] ?? null) ? $old['email'] : '', 'autocomplete' => 'email', 'placeholder' => 'vous@exemple.com', 'autofocus' => true, 'required' => true]) ?>
        <button type="submit" class="btn-primary w-full">Envoyer le lien</button>
    </form>
    <p class="mt-8 text-center text-sm text-muted"><a href="<?= $this->url('/login') ?>" class="link">Revenir à la connexion</a></p>
</div>
