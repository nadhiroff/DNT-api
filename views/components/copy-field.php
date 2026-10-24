<?php
/**
 * Valeur à copier (clé, URL…) avec bouton « Copier » ; option masquée avec bouton « Afficher ».
 *
 * @var \App\Core\View\Template $this
 * @var string                  $value
 * @var string|null             $label
 * @var bool|null               $secret
 */
$label ??= null;
$secret ??= false;
$id = 'cf-' . substr(hash('xxh3', $value), 0, 10);
$masked = substr($value, 0, 12) . str_repeat('•', 20);
?>
<div class="flex items-center gap-2">
    <code id="<?= e($id) ?>" class="min-w-0 flex-1 truncate rounded-lg border border-line bg-bg px-3 py-2.5 font-mono text-xs" <?php if ($secret): ?>data-secret-value="<?= e($value) ?>" data-masked="<?= e($masked) ?>"<?php endif; ?>><?= e($secret ? $masked : $value) ?></code>
    <?php if ($secret): ?>
    <button type="button" class="btn-ghost size-10 shrink-0 px-0" data-reveal-secret="<?= e($id) ?>" aria-label="Afficher la valeur" aria-pressed="false"><?= $this->icon('eye', 'size-4') ?></button>
    <?php endif; ?>
    <button type="button" class="btn-secondary min-h-10 shrink-0 px-3 text-xs" data-copy="<?= e($value) ?>" aria-label="<?= e('Copier ' . ($label ?? 'la valeur')) ?>"><?= $this->icon('copy', 'size-4') ?><span class="hidden sm:inline">Copier</span></button>
</div>
