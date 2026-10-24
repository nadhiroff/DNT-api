<?php
/**
 * @var \App\Core\View\Template $this
 * @var list<array{id: int, author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool, sort: int}> $testimonials
 */
$this->layout('layouts/app', ['title' => 'Témoignages']);
$platforms = \App\Services\CommunityService::PLATFORMS;
?>
<?= $this->insert('components/page-header', ['eyebrow' => 'Administration', 'heading' => 'Témoignages', 'subtitle' => 'Seuls les témoignages publiés apparaissent sur la page d\'accueil, triés par ordre croissant. Chaque carte ouvre le profil de la personne dans un nouvel onglet.', 'action' => ['/admin/testimonials/new', 'Ajouter un témoignage', 'plus']]) ?>

<div class="card overflow-hidden">
    <ul class="divide-y divide-line">
        <?php foreach ($testimonials as $t): ?>
        <li class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start">
            <div class="flex min-w-0 flex-1 gap-4">
                <?= $this->insert('components/avatar', ['name' => $t['author_name'], 'file' => $t['avatar_file']]) ?>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold"><?= e($t['author_name']) ?></span>
                        <span class="text-muted"><?= e($t['handle']) ?></span>
                        <span class="badge py-0"><?= e($platforms[$t['platform']] ?? $t['platform']) ?></span>
                        <?php if ($t['is_published']): ?>
                        <span class="badge border-accent/40 py-0 text-accent-text">Publié</span>
                        <?php else: ?>
                        <span class="badge border-warning/40 py-0 text-warning">Brouillon</span>
                        <?php endif; ?>
                        <span class="font-mono text-xs text-muted">ordre <?= e($t['sort']) ?></span>
                    </p>
                    <p class="mt-1.5 line-clamp-2 text-sm text-muted"><?= e($t['content']) ?></p>
                    <?php if ($t['profile_url'] !== null): ?>
                    <a href="<?= e($t['profile_url']) ?>" class="mt-1.5 inline-flex items-center gap-1 text-xs link" target="_blank" rel="noopener nofollow"><?= $this->icon('external', 'size-3.5') ?><?= e(parse_url($t['profile_url'], PHP_URL_HOST) ?: $t['profile_url']) ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex shrink-0 flex-wrap gap-2">
                <form method="post" action="<?= $this->url('/admin/testimonials/' . $t['id'] . '/publish') ?>">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="<?= e($t['is_published'] ? 'btn-secondary' : 'btn-primary') ?> min-h-9 px-3 text-xs"><?= e($t['is_published'] ? 'Dépublier' : 'Publier') ?></button>
                </form>
                <a href="<?= $this->url('/admin/testimonials/' . $t['id'] . '/edit') ?>" class="btn-secondary min-h-9 px-3 text-xs"><?= $this->icon('pencil', 'size-3.5') ?>Modifier</a>
                <form method="post" action="<?= $this->url('/admin/testimonials/' . $t['id'] . '/delete') ?>" data-confirm="Supprimer définitivement ce témoignage ?">
                    <?= $this->csrfField() ?>
                    <button type="submit" class="btn-ghost min-h-9 px-3 text-xs text-danger" aria-label="Supprimer"><?= $this->icon('trash', 'size-3.5') ?></button>
                </form>
            </div>
        </li>
        <?php endforeach; ?>
        <?php if ($testimonials === []): ?>
        <li class="p-10 text-center text-sm text-muted">Aucun témoignage pour l'instant.</li>
        <?php endif; ?>
    </ul>
</div>
