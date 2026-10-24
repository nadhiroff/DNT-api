<?php
/**
 * Fenêtre de confirmation unique, utilisée par tout formulaire portant data-confirm
 * (remplace window.confirm()).
 *
 * @var \App\Core\View\Template $this
 */
?>
<dialog id="confirm-dialog" class="m-auto w-[min(28rem,calc(100vw-2rem))] rounded-2xl border border-line bg-surface p-0 text-fg shadow-card backdrop:bg-black/60 backdrop:backdrop-blur-sm" aria-labelledby="confirm-title" aria-describedby="confirm-message">
    <div class="p-6">
        <div class="flex items-start gap-4">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-danger-soft text-danger" data-confirm-icon><?= $this->icon('alert', 'size-5') ?></span>
            <div class="min-w-0 pt-0.5">
                <h2 id="confirm-title" class="text-base font-semibold">Confirmation</h2>
                <p id="confirm-message" class="mt-1.5 text-sm text-muted">Êtes-vous sûr ?</p>
            </div>
        </div>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t border-line bg-bg/40 px-6 py-4 sm:flex-row sm:justify-end">
        <button type="button" class="btn-secondary" data-confirm-cancel-btn>Annuler</button>
        <button type="button" class="btn-danger" data-confirm-ok-btn>Confirmer</button>
    </div>
</dialog>
