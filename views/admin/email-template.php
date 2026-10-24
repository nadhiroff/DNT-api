<?php
/**
 * @var \App\Core\View\Template $this
 * @var string $key
 * @var array{label: string, description: string, variables: array<string, string>} $definition
 * @var array{subject: string, heading: string, body: string, button_label: string} $template
 * @var bool $customized
 * @var array<string, list<string>> $errors
 * @var array<string, mixed> $old
 */
$this->layout('layouts/app', ['title' => $definition['label']]);
$v = static fn (string $field): string => is_string($old[$field] ?? null) ? $old[$field] : $template[$field];
?>
<?= $this->insert('components/page-header', ['back' => '/admin/emails', 'backLabel' => 'Retour aux e-mails', 'heading' => $definition['label'], 'subtitle' => $definition['description']]) ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <form method="post" action="<?= $this->url('/admin/emails/templates/' . $key) ?>" class="card space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <?= $this->insert('components/field', ['name' => 'subject', 'label' => 'Sujet', 'value' => $v('subject'), 'maxlength' => 200, 'required' => true]) ?>
        <?= $this->insert('components/field', ['name' => 'heading', 'label' => 'Titre dans le message', 'value' => $v('heading'), 'maxlength' => 200, 'required' => true]) ?>
        <div>
            <label for="f-body" class="label">Message</label>
            <textarea id="f-body" name="body" rows="10" maxlength="5000" class="input resize-y" <?php if (isset($errors['body'])): ?>aria-invalid="true"<?php endif; ?>><?= e($v('body')) ?></textarea>
            <p class="field-hint">Texte simple : une ligne vide sépare les paragraphes. Le HTML n'est pas interprété (sécurité).</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'button_label', 'label' => 'Texte du bouton', 'value' => $v('button_label'), 'maxlength' => 80, 'hint' => 'Le bouton mène au lien {{lien}}. Vide = pas de bouton.']) ?>
        <div class="flex flex-wrap gap-3 border-t border-line pt-5">
            <button type="submit" class="btn-primary">Enregistrer</button>
            <a href="<?= $this->url('/admin/emails/templates/' . $key . '/preview') ?>" class="btn-secondary" target="_blank" rel="noopener"><?= $this->icon('eye', 'size-4') ?>Aperçu</a>
        </div>
    </form>
    <aside class="space-y-4">
        <div class="card p-5 text-sm">
            <p class="font-medium">Variables disponibles</p>
            <ul class="mt-3 space-y-2">
                <?php foreach ($definition['variables'] as $var => $label): ?>
                <li class="flex items-center justify-between gap-3"><code class="rounded bg-surface-2 px-1.5 py-0.5 text-xs text-fg">{{<?= e($var) ?>}}</code><span class="text-right text-xs text-muted"><?= e($label) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php if ($customized): ?>
        <form method="post" action="<?= $this->url('/admin/emails/templates/' . $key . '/reset') ?>" class="card p-5" data-confirm="Votre texte personnalisé sera remplacé par le texte d'origine." data-confirm-title="Restaurer le texte d'origine" data-confirm-ok="Restaurer">
            <?= $this->csrfField() ?>
            <p class="text-sm text-muted">Ce modèle est personnalisé.</p>
            <button type="submit" class="btn-ghost mt-2 -ml-3 text-danger">Restaurer le texte d'origine</button>
        </form>
        <?php endif; ?>
    </aside>
</div>
