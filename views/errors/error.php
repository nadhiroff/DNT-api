<?php
/**
 * Page d'erreur générique (aucun détail technique hors mode debug).
 *
 * @var \App\Core\View\Template $this
 * @var int                     $status
 * @var string                  $message
 * @var string                  $requestId
 * @var \Throwable|null         $debug
 */
$this->layout('layouts/base', ['title' => 'Erreur ' . $status]);
$headline = match (true) {
    $status === 404 => 'Cette page n\'existe pas',
    $status === 403 => 'Accès refusé',
    $status === 419 => 'Session expirée',
    $status === 429 => 'Doucement !',
    $status >= 500 => 'Un incident est survenu',
    default => 'Requête impossible',
};
?>
<main id="contenu" class="relative isolate grid min-h-dvh place-items-center overflow-hidden px-4 py-16 sm:px-6">
    <div class="bg-grid pointer-events-none absolute inset-0 -z-10 opacity-60" aria-hidden="true"></div>
    <div class="pointer-events-none absolute top-[-12rem] left-1/2 -z-10 size-[36rem] -translate-x-1/2 rounded-full bg-accent/15 blur-3xl animate-glow" aria-hidden="true"></div>

    <div class="w-full max-w-lg text-center animate-fade-up">
        <p class="font-mono text-sm font-medium text-accent-text"><?= e($status) ?></p>
        <h1 class="mt-3 text-3xl font-semibold tracking-tight text-balance sm:text-4xl"><?= e($headline) ?></h1>
        <p class="mt-4 text-base text-muted text-pretty"><?= e($message) ?></p>

        <div class="mt-8 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:items-center">
            <a href="<?= $this->url('/') ?>" class="btn-primary">Retour à l'accueil</a>
        </div>

        <p class="mt-10 font-mono text-xs text-muted">Référence : <?= e($requestId) ?></p>

        <?php if ($debug !== null): ?>
        <details class="card mt-8 p-4 text-left">
            <summary class="cursor-pointer text-sm font-medium text-danger">Détails (mode debug uniquement)</summary>
            <p class="mt-3 font-mono text-xs break-all"><?= e($debug::class) ?> : <?= e($debug->getMessage()) ?></p>
            <p class="mt-1 font-mono text-xs text-muted break-all"><?= e($debug->getFile()) ?>:<?= e($debug->getLine()) ?></p>
            <pre class="mt-3 max-h-80 overflow-auto font-mono text-[11px] leading-relaxed text-muted"><?= e($debug->getTraceAsString()) ?></pre>
        </details>
        <?php endif; ?>
    </div>
</main>
