<?php
/**
 * Carte de témoignage (page d'accueil).
 *
 * @var \App\Core\View\Template                                                                                                             $this
 * @var array{author_name: string, handle: string, platform: string, profile_url: string|null, avatar_file: string|null, content: string, is_published: bool} $t
 * @var bool                                                                                                                                $preview
 */
$url = \App\Services\CommunityService::profileUrl($t['platform'], $t['handle'], $t['profile_url']);
$tag = $url !== null ? 'a' : 'div';
?>
<<?= e($tag) ?> <?php if ($url !== null): ?>href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer nofollow ugc" aria-label="<?= e('Voir le profil de ' . $t['author_name'] . ' (' . $t['handle'] . '), nouvel onglet') ?>"<?php endif; ?>
    class="card group mb-4 block break-inside-avoid p-5 transition-[border-color,transform] duration-300 <?= e($url !== null ? 'cursor-pointer hover:-translate-y-0.5 hover:border-accent/50' : '') ?>">
    <div class="flex items-center gap-3">
        <span class="relative">
            <?= $this->insert('components/avatar', ['name' => $t['author_name'], 'file' => $t['avatar_file']]) ?>
            <span class="absolute -top-1 -left-1 flex size-[18px] items-center justify-center rounded-full bg-fg text-bg ring-2 ring-surface"><?= $this->icon('brand-' . $t['platform'], 'size-2.5') ?></span>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold group-hover:text-accent-text"><?= e($t['handle']) ?></span>
            <?php if ($preview && !$t['is_published']): ?><span class="text-[11px] font-medium text-warning">Brouillon · visible par vous seul</span><?php endif; ?>
        </span>
        <?php if ($url !== null): ?><span class="shrink-0 text-muted opacity-0 transition-opacity group-hover:opacity-100" aria-hidden="true"><?= $this->icon('external', 'size-4') ?></span><?php endif; ?>
    </div>
    <p class="mt-4 text-[15px] leading-relaxed whitespace-pre-line text-muted"><?= e($t['content']) ?></p>
</<?= e($tag) ?>>
