<?php
/**
 * Messages flash (succès, erreur, information).
 *
 * @var \App\Core\View\Template              $this
 * @var array<string, string|null>|null      $flash
 */
$icons = ['success' => 'check', 'error' => 'alert', 'info' => 'info'];
?>
<?php foreach ($flash ?? [] as $type => $message): ?>
<?php if (is_string($message) && $message !== '' && isset($icons[$type])): ?>
<div class="alert-<?= e($type) ?> animate-fade-up" role="<?= e($type === 'error' ? 'alert' : 'status') ?>">
    <?= $this->icon($icons[$type], 'mt-0.5 size-[18px] shrink-0 ' . ($type === 'error' ? 'text-danger' : ($type === 'success' ? 'text-accent-text' : 'text-muted'))) ?>
    <p><?= e($message) ?></p>
</div>
<?php endif; ?>
<?php endforeach; ?>
