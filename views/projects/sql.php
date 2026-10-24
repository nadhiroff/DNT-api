<?php
/**
 * Éditeur SQL du projet.
 *
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string, db_name: string} $project
 * @var string $sql
 * @var array{results: list<array{sql: string, status: string, kind: string, columns: list<string>, rows: list<list<string|null>>, truncated: bool, affected: int|null, insert_id: string|null, error: string|null, duration_ms: float}>, sync: array{registered: list<string>, updated: list<string>, removed: list<string>, ignored: array<string, string>}|null, duration_ms: float}|null $outcome
 * @var list<array{name: string, pk_type: string, columns: list<array{name: string}>}> $tables
 * @var list<array{sql_executed: string, status: string, error: string|null, created_at: string}> $history
 * @var int $maxRows
 * @var array<string, string> $snippets
 * @var array<string, string> $selectSnippets
 * @var array<string, list<string>> $errors
 * @var array<string, mixed> $old
 */
$this->layout('projects/layout', ['title' => 'Éditeur SQL · ' . $project['name']]);
$base = '/projects/' . $project['ref'];
$value = is_string($old['sql'] ?? null) ? $old['sql'] : $sql;
$results = $outcome['results'] ?? [];
$sync = $outcome['sync'] ?? null;
$okCount = count(array_filter($results, static fn (array $r): bool => $r['status'] === 'ok'));
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="min-w-0 space-y-6">
        <form method="post" action="<?= $this->url($base . '/sql') ?>" class="card overflow-hidden" data-sql-form>
            <?= $this->csrfField() ?>
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-4 py-3">
                <span class="mr-1 text-xs font-medium text-muted">Modèles :</span>
                <?php foreach ($snippets as $label => $snippet): ?>
                <button type="button" class="badge cursor-pointer transition-colors hover:border-line-strong hover:text-fg" data-sql-snippet="<?= e($snippet) ?>"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>
            <label for="sql" class="sr-only">Requêtes SQL</label>
            <textarea id="sql" name="sql" rows="14" spellcheck="false" autocapitalize="off" autocomplete="off" data-sql-editor
                class="block min-h-72 w-full resize-y border-0 bg-surface-2/40 px-4 py-4 font-mono text-sm leading-relaxed text-fg placeholder:text-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 focus-visible:ring-inset"
                placeholder="-- Écrivez une ou plusieurs requêtes séparées par « ; »&#10;SELECT * FROM produits LIMIT 10;"><?= e($value) ?></textarea>
            <?php if (isset($errors['sql'][0])): ?>
            <p class="field-error m-4 rounded-lg border border-danger/40 bg-danger-soft p-3" role="alert"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($errors['sql'][0]) ?></span></p>
            <?php endif; ?>
            <div class="flex flex-col gap-3 border-t border-line px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-muted">Base <code class="text-fg"><?= e($project['db_name']) ?></code> · <kbd class="kbd-code">Ctrl</kbd> + <kbd class="kbd-code">Entrée</kbd> pour exécuter</p>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost" data-sql-clear><?= $this->icon('x', 'size-4') ?>Effacer</button>
                    <button type="submit" class="btn-primary"><?= $this->icon('play', 'size-4') ?>Exécuter</button>
                </div>
            </div>
        </form>

        <?php if ($outcome !== null): ?>
        <section aria-labelledby="resultats" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="resultats" class="text-lg font-semibold">Résultats</h2>
                <p class="text-sm text-muted"><?= e($okCount) ?> / <?= e(count($results)) ?> instruction<?= e(count($results) > 1 ? 's' : '') ?> réussie<?= e($okCount > 1 ? 's' : '') ?> · <?= e(number_format($outcome['duration_ms'], 1, ',', ' ')) ?> ms</p>
            </div>

            <?php if ($sync !== null && ($sync['registered'] !== [] || $sync['updated'] !== [] || $sync['removed'] !== [] || $sync['ignored'] !== [])): ?>
            <div class="card space-y-2 border-accent/40 p-4 text-sm">
                <p class="flex items-center gap-2 font-medium"><?= $this->icon('zap', 'size-4 text-accent-text') ?>API synchronisée avec la structure de la base</p>
                <?php foreach (['registered' => 'Nouvelles tables disponibles dans l\'API', 'updated' => 'Tables mises à jour', 'removed' => 'Tables retirées de l\'API'] as $key => $label): ?>
                <?php if ($sync[$key] !== []): ?>
                <p class="text-muted"><?= e($label) ?> : <?php foreach ($sync[$key] as $i => $name): ?><?= e($i > 0 ? ', ' : '') ?><code class="text-fg">/data/<?= e($name) ?></code><?php endforeach; ?></p>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php foreach ($sync['ignored'] as $name => $reason): ?>
                <p class="flex gap-2 text-warning"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><code class="font-semibold"><?= e($name) ?></code> non exposée dans l'API : <?= e($reason) ?></span></p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php foreach ($results as $n => $r): ?>
            <article class="card overflow-hidden">
                <header class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-4 py-3">
                    <span class="font-mono text-xs text-muted">#<?= e($n + 1) ?></span>
                    <?php if ($r['status'] === 'ok'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-accent-soft px-2 py-0.5 text-xs font-medium text-accent-text"><?= $this->icon('check', 'size-3.5') ?>Réussie</span>
                    <?php elseif ($r['status'] === 'error'): ?>
                    <span class="inline-flex items-center gap-1 rounded-full bg-danger-soft px-2 py-0.5 text-xs font-medium text-danger"><?= $this->icon('x', 'size-3.5') ?>Erreur</span>
                    <?php else: ?>
                    <span class="badge py-0">Non exécutée</span>
                    <?php endif; ?>
                    <code class="min-w-0 flex-1 truncate font-mono text-xs text-muted" title="<?= e($r['sql']) ?>"><?= e(preg_replace('/\s+/', ' ', $r['sql']) ?? '') ?></code>
                    <?php if ($r['status'] !== 'skipped'): ?><span class="text-xs text-muted tabular-nums"><?= e(number_format($r['duration_ms'], 2, ',', ' ')) ?> ms</span><?php endif; ?>
                </header>
                <?php if ($r['status'] === 'error'): ?>
                <p class="flex gap-2 px-4 py-3 font-mono text-sm text-danger"><?= $this->icon('alert', 'mt-0.5 size-4 shrink-0') ?><span><?= e($r['error'] ?? '') ?></span></p>
                <?php elseif ($r['status'] === 'skipped'): ?>
                <p class="px-4 py-3 text-sm text-muted">Arrêt après l'erreur précédente : cette instruction n'a pas été exécutée.</p>
                <?php elseif ($r['kind'] === 'rows'): ?>
                <?php if ($r['rows'] === []): ?>
                <p class="px-4 py-3 text-sm text-muted">Aucune ligne.</p>
                <?php else: ?>
                <div class="max-h-[32rem] overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 border-b border-line bg-surface-2 text-xs">
                            <tr><?php foreach ($r['columns'] as $col): ?><th class="px-4 py-2.5 font-mono font-medium whitespace-nowrap"><?= e($col) ?></th><?php endforeach; ?></tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            <?php foreach ($r['rows'] as $row): ?>
                            <tr class="hover:bg-surface-2/40">
                                <?php foreach ($row as $cell): ?>
                                <td class="max-w-xs truncate px-4 py-2 font-mono text-xs <?= e($cell === null ? 'text-muted italic' : '') ?>"><?= e($cell ?? 'NULL') ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-line px-4 py-2 text-xs text-muted"><?= e(count($r['rows'])) ?> ligne<?= e(count($r['rows']) > 1 ? 's' : '') ?><?= e($r['truncated'] ? ' affichées (limite de ' . $maxRows . ' : ajoutez LIMIT pour paginer)' : '') ?></p>
                <?php endif; ?>
                <?php else: ?>
                <p class="px-4 py-3 text-sm"><?= e((int) $r['affected']) ?> ligne<?= e((int) $r['affected'] > 1 ? 's' : '') ?> affectée<?= e((int) $r['affected'] > 1 ? 's' : '') ?><?php if ($r['insert_id'] !== null): ?> · <?= e((int) $r['affected'] > 1 ? 'premier' : '') ?> <code>id</code> généré : <code class="text-accent-text"><?= e($r['insert_id']) ?></code><?php endif; ?></p>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>

    <aside class="space-y-6">
        <section class="card p-5">
            <h2 class="flex items-center gap-2 font-semibold"><?= $this->icon('grid', 'size-4 text-accent-text') ?>Tables dans l'API</h2>
            <?php if ($tables === []): ?>
            <p class="mt-3 text-sm text-muted">Aucune table pour l'instant. Créez-en une ici avec <code class="text-fg">CREATE TABLE</code>.</p>
            <?php else: ?>
            <ul class="mt-3 space-y-1">
                <?php foreach ($tables as $t): ?>
                <li class="flex items-center justify-between gap-2">
                    <button type="button" class="truncate font-mono text-sm hover:text-accent-text" data-sql-snippet="<?= e($selectSnippets[$t['name']] ?? '') ?>" title="Insérer un SELECT"><?= e($t['name']) ?></button>
                    <a href="<?= $this->url($base . '/tables/' . $t['name']) ?>" class="shrink-0 text-xs text-muted hover:text-fg"><?= e(count($t['columns']) + 4) ?> col. →</a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>

        <section class="card space-y-2 p-5 text-sm">
            <h2 class="flex items-center gap-2 font-semibold"><?= $this->icon('info', 'size-4 text-accent-text') ?>Pour que l'API voie vos tables</h2>
            <p class="text-muted">Clé primaire <code class="text-fg">id</code> : <code class="text-fg">BIGINT UNSIGNED AUTO_INCREMENT</code> ou <code class="text-fg">CHAR(36)</code> (UUID).</p>
            <p class="text-muted"><code class="text-fg">owner_id</code>, <code class="text-fg">created_at</code> et <code class="text-fg">updated_at</code> sont ajoutées automatiquement si elles manquent.</p>
            <p class="text-muted">Après un <code class="text-fg">CREATE</code>, <code class="text-fg">ALTER</code> ou <code class="text-fg">DROP</code>, l'API est mise à jour aussitôt.</p>
        </section>

        <?php if ($history !== []): ?>
        <section class="card p-5">
            <h2 class="font-semibold">Historique des modifications</h2>
            <ul class="mt-3 space-y-2">
                <?php foreach ($history as $h): ?>
                <li>
                    <button type="button" class="group w-full rounded-lg border border-line px-3 py-2 text-left transition-colors hover:border-line-strong" data-sql-snippet="<?= e($h['sql_executed']) ?>" title="Recharger dans l'éditeur">
                        <span class="flex items-center justify-between gap-2 text-xs text-muted"><span><?= e(format_datetime($h['created_at'])) ?></span><span class="size-1.5 rounded-full <?= e($h['status'] === 'success' ? 'bg-accent' : 'bg-danger') ?>" aria-hidden="true"></span></span>
                        <span class="mt-1 block truncate font-mono text-xs group-hover:text-fg"><?= e(preg_replace('/\s+/', ' ', $h['sql_executed']) ?? '') ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>
    </aside>
</div>
