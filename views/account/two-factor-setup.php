<?php
/**
 * @var \App\Core\View\Template $this
 * @var string                  $secret
 * @var string                  $uri
 * @var bool                    $required
 */
$this->layout('layouts/app', ['title' => 'Activer la double authentification']);
?>
<div class="mx-auto max-w-3xl">
    <?= $this->insert('components/page-header', ['back' => '/account', 'backLabel' => 'Retour à mon compte', 'heading' => 'Activer la double authentification', 'subtitle' => ($required ? 'Obligatoire pour le superadmin. ' : '') . 'Utilisez une application comme Google Authenticator, Microsoft Authenticator, Aegis ou 1Password.']) ?>

    <div class="card divide-y divide-line">
        <div class="grid gap-6 p-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:p-6">
            <div class="mx-auto rounded-xl bg-white p-3 shadow-card sm:mx-0">
                <canvas class="block size-44" width="176" height="176" data-qr="<?= e($uri) ?>" role="img" aria-label="QR code à scanner avec votre application d'authentification"></canvas>
            </div>
            <div>
                <p class="eyebrow">Étape 1</p>
                <h2 class="mt-2 font-semibold">Scannez le QR code</h2>
                <p class="mt-1 text-sm text-muted">Sur mobile, ouvrez directement l'application ou saisissez la clé manuellement.</p>
                <div class="mt-4 flex items-center gap-2">
                    <code class="min-w-0 flex-1 truncate rounded-lg border border-line bg-surface-2 px-3 py-2.5 font-mono text-sm tracking-wider" id="totp-secret"><?= e(trim(chunk_split($secret, 4, ' '))) ?></code>
                    <button type="button" class="btn-secondary px-3" data-copy="<?= e($secret) ?>" aria-label="Copier la clé"><?= $this->icon('copy', 'size-4') ?></button>
                </div>
                <a href="<?= e($uri) ?>" class="btn-ghost mt-3 -ml-3 sm:hidden">Ouvrir dans l'application</a>
            </div>
        </div>
        <form method="post" action="<?= $this->url('/account/2fa') ?>" class="p-5 sm:p-6" novalidate>
            <?= $this->csrfField() ?>
            <p class="eyebrow">Étape 2</p>
            <h2 class="mt-2 font-semibold">Saisissez le code affiché</h2>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="sm:w-56"><?= $this->insert('components/field', ['name' => 'code', 'label' => 'Code à 6 chiffres', 'inputmode' => 'numeric', 'autocomplete' => 'one-time-code', 'placeholder' => '123 456', 'maxlength' => 7, 'autofocus' => true, 'required' => true]) ?></div>
                <button type="submit" class="btn-primary sm:mt-7">Activer</button>
            </div>
        </form>
    </div>
</div>
<script src="<?= $this->asset('js/qrcode.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
