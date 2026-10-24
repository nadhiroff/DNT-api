<?php
/**
 * @var \App\Core\View\Template      $this
 * @var array<string, list<string>>  $errors
 * @var array<string, mixed>         $old
 */
$this->layout('layouts/auth', ['title' => 'Connexion']);
?>
<div class="animate-fade-up">
    <h1 class="text-2xl font-semibold tracking-tight">Bon retour parmi nous</h1>
    <p class="mt-2 text-sm text-muted">Connectez-vous pour accéder à vos projets.</p>

    <div class="mt-6 space-y-3 empty:hidden"><?= $this->insert('components/alerts') ?></div>

    <form method="post" action="<?= $this->url('/login') ?>" class="mt-8 space-y-5" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'value' => is_string($old['email'] ?? null) ? $old['email'] : '', 'autocomplete' => 'email', 'placeholder' => 'vous@exemple.com', 'autofocus' => true, 'required' => true]) ?>
        <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'current-password', 'required' => true, 'asideHref' => '/forgot-password', 'asideLabel' => 'Mot de passe oublié ?']) ?>
        <button type="submit" class="btn-primary w-full">Se connecter</button>
    </form>

    <p class="mt-8 text-center text-sm text-muted">Pas encore de compte ? <a href="<?= $this->url('/register') ?>" class="link">Créer un compte gratuit</a></p>
</div>
