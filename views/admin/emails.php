<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{host: string, port: int, encryption: string, username: string, from_email: string, from_name: string, has_password: bool, updated_at: string}|null $settings
 * @var array{host: string, from_email: string, source: string}|null $effective
 * @var array<string, array{label: string, host: string, port: int, encryption: string}> $presets
 * @var array<string, array{label: string, description: string}> $templates
 * @var list<string> $customized
 */
$this->layout('layouts/app', ['title' => 'E-mails']);
$source = ['platform' => 'configuré ici', 'env' => 'lu dans config/env.php', 'project' => 'projet'];
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'E-mails de la plateforme', 'subtitle' => 'Serveur d\'envoi (vérification d\'adresse, mot de passe oublié, invitations…) et textes des messages. Il sert aussi aux projets qui n\'ont pas leur propre SMTP.']) ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
    <section class="card p-5 sm:p-6">
        <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
            <h2 class="font-semibold">Serveur SMTP</h2>
            <span class="badge <?= e($effective === null ? 'border-danger/40 text-danger' : 'border-accent/40 text-accent-text') ?>"><?= e($effective === null ? 'Non configuré : aucun e-mail ne part' : 'Actif · ' . ($source[$effective['source']] ?? '')) ?></span>
        </div>
        <?= $this->insert('components/smtp-form', ['action' => '/admin/emails/smtp', 'settings' => $settings, 'presets' => $presets]) ?>
    </section>
    <div class="space-y-6">
        <form method="post" action="<?= $this->url('/admin/emails/test') ?>" class="card space-y-4 p-5 sm:p-6" novalidate data-submit-loading="Envoi en cours…">
            <?= $this->csrfField() ?>
            <div><h2 class="font-semibold">E-mail de test</h2><p class="mt-1 text-sm text-muted"><?= e($effective !== null ? 'Via ' . $effective['host'] . ' en tant que ' . $effective['from_email'] . '. Le résultat (ou l\'erreur exacte) s\'affiche immédiatement.' : 'Enregistrez d\'abord un serveur SMTP.') ?></p></div>
            <?= $this->insert('components/field', ['name' => 'test_to', 'label' => 'Destinataire', 'type' => 'email', 'placeholder' => 'vous@exemple.com', 'required' => true]) ?>
            <button type="submit" class="btn-secondary w-full" <?php if ($effective === null): ?>disabled<?php endif; ?>><?= $this->icon('mail', 'size-4') ?>Envoyer le test</button>
        </form>
        <div class="card p-5 text-sm text-muted">
            <p class="flex items-center gap-2 font-medium text-fg"><?= $this->icon('info', 'size-4 text-accent-text') ?>Gmail avec un mot de passe d'application</p>
            <ol class="mt-3 list-decimal space-y-1.5 pl-5">
                <li>Activez la validation en deux étapes de votre compte Google.</li>
                <li>Compte Google → Sécurité → <strong class="text-fg">Mots de passe des applications</strong> → créez-en un (« NovaBase »).</li>
                <li>Cliquez « Gmail » ci-contre, saisissez votre adresse et collez le mot de passe de 16 caractères.</li>
            </ol>
        </div>
    </div>
</div>

<section class="card mt-6 overflow-hidden">
    <div class="border-b border-line p-5 sm:px-6"><h2 class="font-semibold">Modèles des messages</h2><p class="mt-1 text-sm text-muted">Personnalisez le sujet, le titre, le texte et le bouton de chaque e-mail. Variables : <code class="text-fg">{{nom}}</code>, <code class="text-fg">{{lien}}</code>…</p></div>
    <ul class="divide-y divide-line">
        <?php foreach ($templates as $key => $t): ?>
        <li>
            <a href="<?= $this->url('/admin/emails/templates/' . $key) ?>" class="flex items-center gap-4 px-5 py-4 hover:bg-surface-2/50 sm:px-6">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-surface-2 text-accent-text"><?= $this->icon('mail', 'size-5') ?></span>
                <span class="min-w-0 flex-1"><span class="block font-medium"><?= e($t['label']) ?></span><span class="block truncate text-sm text-muted"><?= e($t['description']) ?></span></span>
                <span class="badge py-0 <?= e(in_array($key, $customized, true) ? 'border-accent/40 text-accent-text' : '') ?>"><?= e(in_array($key, $customized, true) ? 'Personnalisé' : 'Texte d\'origine') ?></span>
                <?= $this->icon('arrow-right', 'size-4 text-muted') ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
