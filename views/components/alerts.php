<?php
/**
 * Notifications (toasts) : messages flash affichés en haut à droite, fermeture automatique
 * après 4 s (pause au survol), bouton de fermeture. Sans JavaScript, ils restent visibles.
 *
 * @var \App\Core\View\Template              $this
 * @var array<string, string|null>|null      $flash
 */
$icons = ['success' => 'check', 'error' => 'alert', 'info' => 'info'];
$titles = ['success' => 'Succès', 'error' => 'Erreur', 'info' => 'Information'];
$messages = array_filter($flash ?? [], static fn (mixed $m, string $t): bool => is_string($m) && $m !== '' && isset($icons[$t]), ARRAY_FILTER_USE_BOTH);
?>
<div class="pointer-events-none fixed top-4 right-4 left-4 z-[60] flex flex-col items-end gap-3 sm:left-auto sm:w-96" aria-live="polite" data-toast-region>
    <?php foreach ($messages as $type => $message): ?>
    <div class="pointer-events-auto relative w-full overflow-hidden rounded-xl border bg-surface shadow-card animate-fade-up <?= e($type === 'error' ? 'border-danger/40' : ($type === 'success' ? 'border-accent/40' : 'border-line-strong')) ?>" role="<?= e($type === 'error' ? 'alert' : 'status') ?>" data-toast>
        <div class="flex items-start gap-3 p-4 pr-11">
            <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full <?= e($type === 'error' ? 'bg-danger-soft text-danger' : ($type === 'success' ? 'bg-accent-soft text-accent-text' : 'bg-surface-2 text-muted')) ?>"><?= $this->icon($icons[$type], 'size-3.5') ?></span>
            <div class="min-w-0">
                <p class="text-sm font-semibold"><?= e($titles[$type]) ?></p>
                <p class="mt-0.5 text-sm text-muted"><?= e($message) ?></p>
            </div>
        </div>
        <button type="button" class="absolute top-3 right-3 flex size-7 cursor-pointer items-center justify-center rounded-md text-muted hover:bg-surface-2 hover:text-fg" data-toast-close aria-label="Fermer la notification"><?= $this->icon('x', 'size-4') ?></button>
        <span class="toast-progress absolute bottom-0 left-0 h-0.5 w-full origin-left <?= e($type === 'error' ? 'bg-danger' : ($type === 'success' ? 'bg-accent' : 'bg-muted')) ?>" aria-hidden="true"></span>
    </div>
    <?php endforeach; ?>
</div>
