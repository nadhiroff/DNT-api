<?php
/**
 * @var \App\Core\View\Template      $this
 * @var array{id: int, author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int}|null $testimonial
 * @var array<string, list<string>>  $errors
 * @var array<string, mixed>         $old
 */
$editing = $testimonial !== null;
$this->layout('layouts/app', ['title' => $editing ? 'Modifier un témoignage' : 'Ajouter un témoignage']);
$value = static function (string $key) use ($old, $testimonial): string {
    if (array_key_exists($key, $old) && is_scalar($old[$key])) {
        return (string) $old[$key];
    }
    $current = $testimonial[$key] ?? '';

    return is_scalar($current) ? (string) $current : '';
};
$platform = $value('platform') !== '' ? $value('platform') : 'x';
$published = $old !== [] ? !empty($old['is_published']) : ($testimonial['is_published'] ?? false);
$action = $editing ? '/admin/testimonials/' . $testimonial['id'] : '/admin/testimonials';
?>
<?= $this->insert('components/page-header', ['back' => '/admin/testimonials', 'backLabel' => 'Retour aux témoignages', 'heading' => $editing ? 'Modifier le témoignage' : 'Ajouter un témoignage', 'subtitle' => 'Nom, réseau, lien vers le profil, photo et texte.']) ?>

<form method="post" action="<?= $this->url($action) ?>" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]" novalidate>
    <?= $this->csrfField() ?>
    <div class="card space-y-5 p-5 sm:p-6">
        <h2 class="font-semibold"><?= e($editing ? 'Modifier le témoignage' : 'Nouveau témoignage') ?></h2>
        <div class="grid gap-5 sm:grid-cols-2">
            <?= $this->insert('components/field', ['name' => 'author_name', 'label' => 'Nom de la personne', 'value' => $value('author_name'), 'maxlength' => 120, 'required' => true]) ?>
            <?= $this->insert('components/field', ['name' => 'handle', 'label' => 'Identifiant affiché', 'value' => $value('handle'), 'placeholder' => '@pseudo', 'maxlength' => 60, 'required' => true]) ?>
        </div>
        <div class="grid gap-5 sm:grid-cols-[200px_minmax(0,1fr)]">
            <div>
                <label for="f-platform" class="label">Réseau</label>
                <select id="f-platform" name="platform" class="input">
                    <?php foreach (\App\Services\CommunityService::PLATFORMS as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?php if ($platform === $key): ?>selected<?php endif; ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?= $this->insert('components/field', ['name' => 'profile_url', 'label' => 'Lien vers son profil', 'type' => 'url', 'value' => $value('profile_url'), 'placeholder' => 'https://x.com/pseudo', 'hint' => 'La carte renverra vers ce lien (nouvel onglet).']) ?>
        </div>
        <div>
            <label for="f-content" class="label">Témoignage</label>
            <textarea id="f-content" name="content" rows="6" maxlength="600" class="input min-h-36 resize-y" required <?php if (isset($errors['content'])): ?>aria-invalid="true" aria-describedby="f-content-error"<?php endif; ?>><?= e($value('content')) ?></textarea>
            <?php if (isset($errors['content'][0])): ?>
            <p id="f-content-error" class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['content'][0]) ?></span></p>
            <?php else: ?>
            <p class="field-hint">600 caractères maximum. Les retours à la ligne sont conservés.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-6">
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold">Photo</h2>
            <div class="flex items-center gap-4">
                <?= $this->insert('components/avatar', ['name' => $value('author_name') !== '' ? $value('author_name') : '?', 'file' => $testimonial['avatar_file'] ?? null, 'size' => 'size-16']) ?>
                <p class="text-xs text-muted">JPEG, PNG, WebP ou GIF, 2 Mo maximum. Recadrée en carré automatiquement.</p>
            </div>
            <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif" class="block w-full cursor-pointer text-sm text-muted file:mr-3 file:cursor-pointer file:rounded-md file:border-0 file:bg-surface-2 file:px-3 file:py-2 file:text-sm file:font-medium file:text-fg" <?php if (isset($errors['avatar'])): ?>aria-invalid="true"<?php endif; ?>>
            <?php if (isset($errors['avatar'][0])): ?>
            <p class="field-error" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['avatar'][0]) ?></span></p>
            <?php endif; ?>
            <?php if (!empty($testimonial['avatar_file'])): ?>
            <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" name="remove_avatar" value="1" class="checkbox">Retirer la photo actuelle</label>
            <?php endif; ?>
        </div>
        <div class="card space-y-4 p-5">
            <h2 class="font-semibold">Publication</h2>
            <?= $this->insert('components/field', ['name' => 'sort', 'label' => 'Ordre d\'affichage', 'type' => 'number', 'value' => $value('sort') !== '' ? $value('sort') : '0', 'hint' => 'Les plus petits nombres apparaissent en premier.']) ?>
            <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="is_published" value="1" class="checkbox" <?php if ($published): ?>checked<?php endif; ?>><span>Publier sur la page d'accueil</span></label>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="btn-primary flex-1"><?= e($editing ? 'Enregistrer' : 'Ajouter') ?></button>
            <a href="<?= $this->url('/admin/testimonials') ?>" class="btn-secondary">Annuler</a>
        </div>
    </div>
</form>
