<?php
/**
 * Squelette HTML commun.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 * @var string|null             $title
 * @var string|null             $description
 * @var bool|null               $withCsrf
 */
$pageTitle = isset($title) && $title !== '' ? $title . ' · ' . $appName : $appName;
?>
<!doctype html>
<html lang="fr" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($description ?? 'Base de données, API REST instantanée, authentification, stockage et e-mails pour vos applications web et mobiles.') ?>">
    <meta name="color-scheme" content="dark light">
    <meta name="theme-color" content="#0c0d0f">
    <?php if (!empty($withCsrf)): ?>
    <meta name="csrf-token" content="<?= $this->csrfToken() ?>">
    <?php endif; ?>
    <script nonce="<?= $this->nonce() ?>">try{var t=localStorage.getItem('nb-theme');if(t==='light'||t==='dark'){document.documentElement.dataset.theme=t;}}catch(e){}</script>
    <link rel="preload" href="<?= $this->asset('fonts/ibm-plex-sans-latin.woff2', false) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= $this->asset('css/app.css') ?>">
    <link rel="icon" href="<?= $this->asset('img/favicon.svg') ?>" type="image/svg+xml">
    <script src="<?= $this->asset('js/app.js') ?>" defer nonce="<?= $this->nonce() ?>"></script>
</head>
<body class="min-h-dvh">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-50 focus:rounded-lg focus:bg-surface focus:px-4 focus:py-2">Aller au contenu</a>
    <?= $this->section('content') ?>
</body>
</html>
