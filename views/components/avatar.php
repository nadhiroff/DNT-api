<?php
/**
 * Photo de profil d'un témoignage, ou initiales sur fond coloré à défaut.
 *
 * @var \App\Core\View\Template $this
 * @var string                  $name
 * @var string|null             $file
 * @var string|null             $size classes de taille
 */
$size ??= 'size-10';
$initials = mb_strtoupper(implode('', array_map(static fn (string $p): string => mb_substr($p, 0, 1), array_slice(preg_split('/[\s@._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'], 0, 2))));
$palette = ['bg-[#1f3b2f] text-[#5fe0a8]', 'bg-[#1e2a44] text-[#82aaff]', 'bg-[#3a2340] text-[#d59cf5]', 'bg-[#40301d] text-[#fbbf24]', 'bg-[#3d1f24] text-[#f87171]'];
$color = $palette[abs(crc32($name)) % count($palette)];
?>
<?php if (!empty($file)): ?>
<img src="<?= $this->url('/media/avatars/' . $file) ?>" alt="" class="<?= e($size) ?> shrink-0 rounded-full object-cover" loading="lazy" width="40" height="40">
<?php else: ?>
<span class="<?= e($size . ' ' . $color) ?> flex shrink-0 items-center justify-center rounded-full text-sm font-semibold" aria-hidden="true"><?= e($initials) ?></span>
<?php endif; ?>
