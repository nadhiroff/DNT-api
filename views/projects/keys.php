<?php
/**
 * @var \App\Core\View\Template $this
 * @var array{id: int, ref: string, name: string} $project
 * @var list<array{id: int, name: string, type: string, prefix: string, permissions: list<string>, expires_at: string|null, last_used_at: string|null, revoked_at: string|null, created_at: string}> $apiKeys
 * @var array<int, string|null> $plain
 * @var array<string, mixed>    $old
 */
$this->layout('projects/layout', ['title' => 'Clés API · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
$active = array_values(array_filter($apiKeys, static fn (array $k): bool => $k['revoked_at'] === null));
$history = array_values(array_filter($apiKeys, static fn (array $k): bool => $k['revoked_at'] !== null));
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
    <div class="space-y-6">
        <section class="card overflow-hidden">
            <div class="border-b border-line p-5 sm:px-6">
                <h2 class="font-semibold">Clés actives</h2>
                <p class="mt-1 text-sm text-muted">Envoyez la clé dans l'en-tête <code class="text-fg">apikey</code>. La <strong class="text-fg">publique</strong> va dans vos apps (soumise aux règles d'accès des tables) ; la <strong class="text-fg">secrète</strong> reste sur vos serveurs (accès complet, envoi d'e-mails).</p>
            </div>
            <ul class="divide-y divide-line">
                <?php foreach ($active as $k): ?>
                <li class="p-5 sm:px-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="flex flex-wrap items-center gap-2 text-sm">
                            <span class="font-semibold"><?= e($k['name']) ?></span>
                            <span class="badge py-0 <?= e($k['type'] === 'secret' ? 'border-danger/40 text-danger' : 'border-accent/40 text-accent-text') ?>"><?= e($k['type'] === 'secret' ? 'Secrète' : 'Publique') ?></span>
                        </p>
                        <div class="flex gap-2">
                            <form method="post" action="<?= $this->url($base . '/keys/' . $k['id'] . '/regenerate') ?>" data-confirm="L'ancienne clé « <?= e($k['name']) ?> » sera refusée immédiatement (elle restera 30 jours dans l'historique). Mettez à jour vos applications avec la nouvelle." data-confirm-title="Régénérer la clé" data-confirm-ok="Régénérer">
                                <?= $this->csrfField() ?><button type="submit" class="btn-secondary min-h-9 px-3 text-xs">Régénérer</button>
                            </form>
                            <form method="post" action="<?= $this->url($base . '/keys/' . $k['id'] . '/revoke') ?>" data-confirm="La clé « <?= e($k['name']) ?> » sera refusée immédiatement." data-confirm-title="Révoquer la clé" data-confirm-ok="Révoquer">
                                <?= $this->csrfField() ?><button type="submit" class="btn-ghost min-h-9 px-3 text-xs text-danger">Révoquer</button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php if (($plain[$k['id']] ?? null) !== null): ?>
                        <?= $this->insert('components/copy-field', ['value' => (string) $plain[$k['id']], 'label' => 'la clé ' . $k['name'], 'secret' => $k['type'] === 'secret']) ?>
                        <?php else: ?>
                        <p class="rounded-lg border border-line bg-bg px-3 py-2.5 font-mono text-xs text-muted"><?= e($k['prefix']) ?>•••••••• <span class="ml-2 font-sans">clé ancienne non récupérable : régénérez-la pour pouvoir la copier</span></p>
                        <?php endif; ?>
                    </div>
                    <p class="mt-2 text-xs text-muted">Créée le <?= e(format_datetime($k['created_at'])) ?> · dernière utilisation : <?= e($k['last_used_at'] !== null ? format_datetime($k['last_used_at']) : 'jamais') ?></p>
                </li>
                <?php endforeach; ?>
                <?php if ($active === []): ?><li class="px-6 py-10 text-center text-sm text-muted">Aucune clé active : créez-en une ci-contre.</li><?php endif; ?>
            </ul>
        </section>

        <?php if ($history !== []): ?>
        <section class="card overflow-hidden">
            <div class="border-b border-line p-5 sm:px-6">
                <h2 class="font-semibold">Historique</h2>
                <p class="mt-1 text-sm text-muted">Clés révoquées ou régénérées : refusées par l'API, elles disparaissent automatiquement 30 jours après leur révocation.</p>
            </div>
            <ul class="divide-y divide-line">
                <?php foreach ($history as $k): ?>
                <?php $daysLeft = max(0, 30 - (int) floor((time() - (int) strtotime($k['revoked_at'] . ' UTC')) / 86400)); ?>
                <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 sm:px-6">
                    <div class="min-w-0">
                        <p class="text-sm"><span class="font-medium"><?= e($k['name']) ?></span> <span class="font-mono text-xs text-muted"><?= e($k['prefix']) ?>••••</span></p>
                        <p class="text-xs text-muted">Révoquée le <?= e(format_datetime($k['revoked_at'])) ?></p>
                    </div>
                    <span class="badge py-0">Supprimée dans <?= e($daysLeft) ?> j</span>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= $this->url($base . '/keys') ?>" class="card h-fit space-y-5 p-5 sm:p-6" novalidate>
        <?= $this->csrfField() ?>
        <div>
            <h2 class="font-semibold">Nouvelle clé</h2>
            <p class="mt-1 text-sm text-muted">Les droits sont attribués automatiquement selon le type.</p>
        </div>
        <?= $this->insert('components/field', ['name' => 'key_name', 'label' => 'Nom', 'value' => is_string($old['key_name'] ?? null) ? $old['key_name'] : '', 'placeholder' => 'App iOS, serveur Laravel…', 'maxlength' => 80, 'required' => true]) ?>
        <fieldset class="space-y-2">
            <legend class="label">Type</legend>
            <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-3 has-[:checked]:border-accent has-[:checked]:bg-accent-soft">
                <input type="radio" name="key_type" value="public" class="mt-1 accent-[var(--nb-accent)]" checked>
                <span><span class="block text-sm font-medium">Publique</span><span class="block text-xs text-muted">Pour vos apps mobiles et web. Lecture et écriture selon les règles d'accès de chaque table.</span></span>
            </label>
            <label class="flex cursor-pointer gap-3 rounded-lg border border-line p-3 has-[:checked]:border-danger has-[:checked]:bg-danger-soft">
                <input type="radio" name="key_type" value="secret" class="mt-1 accent-[var(--nb-danger)]">
                <span><span class="block text-sm font-medium">Secrète</span><span class="block text-xs text-muted">Pour vos serveurs. Accès complet (ignore les règles) et envoi d'e-mails.</span></span>
            </label>
        </fieldset>
        <button type="submit" class="btn-primary w-full"><?= $this->icon('plus', 'size-4') ?>Générer la clé</button>
    </form>
</div>
