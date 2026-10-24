<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string} $project
 * @var array{host: string, port: int, encryption: string, username: string, from_email: string, from_name: string, has_password: bool, updated_at: string}|null $settings
 * @var array{host: string, from_email: string, source: string}|null $effective
 * @var list<array{id: int, recipients: string, subject: string, status: string, error: string|null, attempts: int, sent_at: string|null, created_at: string}> $logs
 * @var string $status
 * @var array<string, array{label: string, host: string, port: int, encryption: string}> $presets
 */
$this->layout('projects/layout', ['title' => 'E-mails · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
$statusLabels = ['pending' => 'En attente', 'sent' => 'Envoyé', 'failed' => 'Échec'];
$statusClasses = ['pending' => 'border-warning/40 text-warning', 'sent' => 'border-accent/40 text-accent-text', 'failed' => 'border-danger/40 text-danger'];
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
    <section class="card p-5 sm:p-6">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-semibold">Serveur SMTP du projet</h2>
                <p class="mt-1 text-sm text-muted">Utilisé par <code class="text-fg">POST /email/send</code>. Facultatif : sans configuration, le SMTP de la plateforme est utilisé.</p>
            </div>
            <span class="badge <?= e($effective === null ? 'border-danger/40 text-danger' : 'border-accent/40 text-accent-text') ?>"><?= e($effective === null ? 'Aucun SMTP disponible' : ($effective['source'] === 'project' ? 'SMTP du projet actif' : 'SMTP de la plateforme utilisé')) ?></span>
        </div>
        <?= $this->insert('components/smtp-form', ['action' => $base . '/emails/smtp', 'settings' => $settings, 'presets' => $presets]) ?>
        <?php if ($settings !== null): ?>
        <form method="post" action="<?= $this->url($base . '/emails/smtp/delete') ?>" class="mt-4 border-t border-line pt-4" data-confirm="Le projet utilisera de nouveau le SMTP de la plateforme." data-confirm-title="Retirer le SMTP du projet" data-confirm-ok="Retirer">
            <?= $this->csrfField() ?><button type="submit" class="btn-ghost text-danger">Retirer ce serveur et utiliser celui de la plateforme</button>
        </form>
        <?php endif; ?>
    </section>
    <div class="space-y-6">
        <form method="post" action="<?= $this->url($base . '/emails/test') ?>" class="card space-y-4 p-5 sm:p-6" novalidate data-submit-loading="Envoi en cours…">
            <?= $this->csrfField() ?>
            <div><h2 class="font-semibold">Envoyer un e-mail de test</h2><p class="mt-1 text-sm text-muted"><?= e($effective !== null ? 'Via ' . $effective['host'] . ' en tant que ' . $effective['from_email'] . '.' : 'Configurez d\'abord un serveur SMTP.') ?></p></div>
            <?= $this->insert('components/field', ['name' => 'test_to', 'label' => 'Destinataire', 'type' => 'email', 'placeholder' => 'vous@exemple.com', 'required' => true]) ?>
            <button type="submit" class="btn-secondary w-full" <?php if ($effective === null): ?>disabled<?php endif; ?>><?= $this->icon('mail', 'size-4') ?>Envoyer le test</button>
        </form>
        <div class="card p-5 text-sm">
            <h2 class="font-semibold">Depuis votre serveur</h2>
            <pre class="kbd-code mt-3 overflow-x-auto p-3 text-[11px]"><code>curl -X POST "$NOVABASE_URL/email/send" \
  -H 'apikey: $NOVABASE_SERVICE_KEY' \
  -H 'Content-Type: application/json' \
  -d '{"to":"client@exemple.com","subject":"Bienvenue",
       "body_html":"&lt;p&gt;Bonjour !&lt;/p&gt;"}'</code></pre>
            <p class="mt-3 text-xs text-muted">Destinataires (to, cc, bcc) : 50 max · pièces jointes en base64 : 10 Mo max · statut : <code class="text-fg">GET /email/{id}</code>.</p>
        </div>
    </div>
</div>

<section class="card mt-6 overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-5 sm:px-6">
        <div><h2 class="font-semibold">Historique des envois</h2><p class="text-sm text-muted">E-mails envoyés par l'API de ce projet (3 tentatives avec délai croissant en cas d'échec).</p></div>
        <div class="inline-flex rounded-lg border border-line p-1 text-sm">
            <?php foreach (['' => 'Tous', 'sent' => 'Envoyés', 'pending' => 'En attente', 'failed' => 'Échecs'] as $key => $label): ?>
            <a href="<?= $this->url($base . '/emails' . ($key !== '' ? '?status=' . $key : '')) ?>" class="rounded-md px-3 py-1.5 <?= e($status === $key ? 'bg-accent-soft text-fg' : 'text-muted hover:text-fg') ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-line bg-surface-2/40 text-xs text-muted"><tr><th class="px-5 py-3 font-medium">Date</th><th class="px-5 py-3 font-medium">Destinataire(s)</th><th class="px-5 py-3 font-medium">Sujet</th><th class="px-5 py-3 font-medium">Statut</th></tr></thead>
            <tbody class="divide-y divide-line">
                <?php foreach ($logs as $log): ?>
                <tr class="align-top hover:bg-surface-2/40">
                    <td class="px-5 py-3 whitespace-nowrap text-muted"><?= e(format_datetime($log['created_at'])) ?></td>
                    <td class="max-w-64 truncate px-5 py-3" title="<?= e($log['recipients']) ?>"><?= e($log['recipients']) ?></td>
                    <td class="max-w-72 truncate px-5 py-3"><?= e($log['subject']) ?></td>
                    <td class="px-5 py-3"><span class="badge py-0 <?= e($statusClasses[$log['status']] ?? '') ?>"><?= e($statusLabels[$log['status']] ?? $log['status']) ?></span><?php if ($log['error'] !== null): ?><p class="mt-1 max-w-80 text-xs text-danger"><?= e(mb_substr($log['error'], 0, 160)) ?></p><?php endif; ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($logs === []): ?><tr><td colspan="4" class="px-5 py-12 text-center text-muted">Aucun e-mail envoyé pour l'instant.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
