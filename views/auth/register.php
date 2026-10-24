<?php
/**
 * @var \App\Core\View\Template      $this
 * @var array<string, list<string>>  $errors
 * @var array<string, mixed>         $old
 */
$this->layout('layouts/auth', ['title' => 'Créer un compte']);
$oldValue = static fn (string $key): string => is_string($old[$key] ?? null) ? $old[$key] : '';
?>
<div class="animate-fade-up">
    <h1 class="text-2xl font-semibold tracking-tight">Créez votre compte</h1>
    <p class="mt-1.5 text-sm text-muted">Gratuit, sans carte bancaire. Votre premier backend est à quelques clics.</p>

    <div class="mt-5 space-y-3 empty:hidden"><?= $this->insert('components/alerts') ?></div>

    <form method="post" action="<?= $this->url('/register') ?>" class="mt-6 space-y-4" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'name', 'label' => 'Nom complet', 'value' => $oldValue('name'), 'autocomplete' => 'name', 'maxlength' => 120, 'autofocus' => true, 'required' => true]) ?>
        <?= $this->insert('components/field', ['name' => 'email', 'label' => 'Adresse e-mail', 'type' => 'email', 'value' => $oldValue('email'), 'autocomplete' => 'email', 'placeholder' => 'vous@exemple.com', 'required' => true]) ?>
        <div class="grid gap-4 sm:grid-cols-2">
            <?= $this->insert('components/field', ['name' => 'password', 'label' => 'Mot de passe', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
            <?= $this->insert('components/field', ['name' => 'password_confirmation', 'label' => 'Confirmation', 'type' => 'password', 'autocomplete' => 'new-password', 'maxlength' => 128, 'required' => true]) ?>
        </div>
        <p class="-mt-2 text-xs text-muted">10 caractères minimum. Une phrase de passe est idéale.</p>

        <div>
            <label class="flex cursor-pointer items-start gap-3 text-sm text-muted">
                <input type="checkbox" name="terms" value="1" class="checkbox" required <?php if (!empty($old['terms'])): ?>checked<?php endif; ?> <?php if (isset($errors['terms'])): ?>aria-invalid="true" aria-describedby="f-terms-error"<?php endif; ?>>
                <span>J'accepte les <a href="<?= $this->url('/conditions') ?>" class="link" target="_blank" rel="noopener">conditions d'utilisation</a> et la <a href="<?= $this->url('/confidentialite') ?>" class="link" target="_blank" rel="noopener">politique de confidentialité</a>.</span>
            </label>
            <?php if (isset($errors['terms'][0])): ?>
            <p id="f-terms-error" class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['terms'][0]) ?></span></p>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn-primary w-full">Créer mon compte</button>
    </form>

    <p class="mt-6 text-center text-sm text-muted">Déjà inscrit ? <a href="<?= $this->url('/login') ?>" class="link">Se connecter</a></p>
</div>
