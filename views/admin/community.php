<?php
/**
 * @var \App\Core\View\Template      $this
 * @var array<string, string>        $settings
 * @var array<string, list<string>>  $errors
 * @var array<string, mixed>         $old
 */
$this->layout('layouts/app', ['title' => 'Section communauté']);
$value = static fn (string $key): string => is_string($old[$key] ?? null) ? $old[$key] : ($settings[$key] ?? '');
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'Section communauté', 'subtitle' => 'Titre, texte et bouton de la section « Rejoignez la communauté » de la page d\'accueil.']) ?>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
<form method="post" action="<?= $this->url('/admin/community') ?>" class="card space-y-5 p-5 sm:p-8" novalidate>
    <?= $this->csrfField() ?>
    <div>
        <h2 class="font-semibold">Section « communauté » de la page d'accueil</h2>
        <p class="mt-1 text-sm text-muted">Le bouton n'est affiché que si un lien est renseigné.</p>
    </div>
    <?= $this->insert('components/field', ['name' => 'community_title', 'label' => 'Titre', 'value' => $value('community_title'), 'maxlength' => 120, 'required' => true]) ?>
    <?= $this->insert('components/field', ['name' => 'community_subtitle', 'label' => 'Texte d\'introduction', 'value' => $value('community_subtitle'), 'maxlength' => 300]) ?>
    <div class="grid gap-5 sm:grid-cols-2">
        <?= $this->insert('components/field', ['name' => 'community_button_label', 'label' => 'Texte du bouton', 'value' => $value('community_button_label'), 'maxlength' => 60, 'placeholder' => 'Rejoignez-nous sur Discord']) ?>
        <?= $this->insert('components/field', ['name' => 'community_button_url', 'label' => 'Lien du bouton', 'type' => 'url', 'value' => $value('community_button_url'), 'placeholder' => 'https://discord.gg/…']) ?>
    </div>
    <button type="submit" class="btn-primary">Enregistrer</button>
</form>
<aside class="card h-fit p-5 text-sm text-muted">
    <p class="flex items-center gap-2 font-medium text-fg"><?= $this->icon('info', 'size-4 text-accent-text') ?>Conseils</p>
    <ul class="mt-3 list-disc space-y-2 pl-5">
        <li>Le bouton s'ouvre dans un nouvel onglet (Discord, WhatsApp, Telegram, Slack…).</li>
        <li>La section n'apparaît au public que lorsqu'au moins un témoignage est publié.</li>
        <li><a href="<?= $this->url('/admin/testimonials') ?>" class="link">Gérer les témoignages →</a></li>
    </ul>
</aside>
</div>
