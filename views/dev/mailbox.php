<?php
/**
 * Boîte de réception de développement.
 *
 * @var \App\Core\View\Template                                                  $this
 * @var list<array{id: string, to: string, subject: string, sent_at: string}>   $mails
 */
$this->layout('layouts/site', ['title' => 'Boîte mail de développement']);
?>
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    <span class="badge border-warning/40 text-warning">Développement uniquement</span>
    <h1 class="mt-4 text-2xl font-semibold tracking-tight">E-mails envoyés</h1>
    <p class="mt-2 text-sm text-muted">MAIL_DRIVER=log : les messages sont enregistrés dans storage/mail au lieu d'être envoyés.</p>
    <ul class="card mt-8 divide-y divide-line">
        <?php foreach ($mails as $mail): ?>
        <li>
            <a href="<?= $this->url('/_dev/mail/' . $mail['id']) ?>" class="flex flex-col gap-1 px-5 py-4 hover:bg-surface-2 sm:flex-row sm:items-center sm:justify-between" target="_blank" rel="noopener">
                <span class="min-w-0">
                    <span class="block truncate font-medium"><?= e($mail['subject']) ?></span>
                    <span class="block truncate text-sm text-muted">À : <?= e($mail['to']) ?></span>
                </span>
                <span class="shrink-0 font-mono text-xs text-muted"><?= e($mail['sent_at']) ?></span>
            </a>
        </li>
        <?php endforeach; ?>
        <?php if ($mails === []): ?>
        <li class="px-5 py-10 text-center text-sm text-muted">Aucun e-mail pour le moment.</li>
        <?php endif; ?>
    </ul>
</div>
