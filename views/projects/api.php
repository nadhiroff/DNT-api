<?php
/**
 * Documentation de l'API du projet, générée à partir des tables réelles.
 *
 * @var \App\Core\View\Template $this
 * @var array{ref: string, name: string} $project
 * @var string $apiUrl
 * @var list<array{name: string, access_rules: array{read: string, insert: string, update: string, delete: string}, columns: list<array{name: string, type: string}>}> $tables
 * @var array{public: string|null, secret: string|null} $integration
 */
$this->layout('projects/layout', ['title' => 'API · ' . $project['name']]);
$rules = \App\Repositories\TableRepository::RULES;
$example = $tables[0]['name'] ?? 'produits';
$sampleBody = [];
foreach (($tables[0]['columns'] ?? []) as $c) {
    $sampleBody[$c['name']] = match ($c['type']) {
        'integer', 'bigint', 'foreign' => 1,
        'float', 'decimal' => 9.99,
        'boolean' => true,
        'date' => '2026-10-24',
        'datetime' => '2026-10-24 12:00:00',
        'json' => ['cle' => 'valeur'],
        default => 'texte',
    };
}
if ($sampleBody === []) {
    $sampleBody = ['nom' => 'Café arabica', 'prix' => 1500];
}
$json = (string) json_encode($sampleBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$samples = [
    'curl' => ['curl', "# Lire\ncurl '{$apiUrl}/data/{$example}?limit=20&order=created_at.desc' \\\n  -H 'apikey: \$NOVABASE_KEY'\n\n# Créer\ncurl -X POST '{$apiUrl}/data/{$example}' \\\n  -H 'apikey: \$NOVABASE_SERVICE_KEY' -H 'Content-Type: application/json' \\\n  -d '{$json}'\n\n# Modifier / supprimer\ncurl -X PATCH '{$apiUrl}/data/{$example}/1' -H 'apikey: \$NOVABASE_SERVICE_KEY' -H 'Content-Type: application/json' -d '{...}'\ncurl -X DELETE '{$apiUrl}/data/{$example}/1' -H 'apikey: \$NOVABASE_SERVICE_KEY'"],
    'js' => ['JavaScript', "const api = (path, options = {}) =>\n  fetch(`\${process.env.NOVABASE_URL}\${path}`, {\n    ...options,\n    headers: { apikey: process.env.NOVABASE_KEY, 'Content-Type': 'application/json', ...options.headers },\n  }).then((r) => r.json());\n\nconst lignes = await api('/data/{$example}?limit=20');\nconst creee = await api('/data/{$example}', { method: 'POST', body: JSON.stringify({$json}) });\nawait api(`/data/{$example}/\${creee.id}`, { method: 'PATCH', body: JSON.stringify({ /* colonnes */ }) });\nawait api(`/data/{$example}/\${creee.id}`, { method: 'DELETE' });"],
    'flutter' => ['Flutter', "final base = dotenv.env['NOVABASE_URL']!;\nfinal headers = {'apikey': dotenv.env['NOVABASE_KEY']!, 'Content-Type': 'application/json'};\n\nfinal liste = await http.get(Uri.parse('\$base/data/{$example}?limit=20'), headers: headers);\nfinal lignes = jsonDecode(liste.body) as List;\n\nfinal creee = await http.post(Uri.parse('\$base/data/{$example}'),\n    headers: headers, body: jsonEncode({$json}));"],
    'kotlin' => ['Kotlin', "val client = OkHttpClient()\nval base = BuildConfig.NOVABASE_URL\n\nval lire = Request.Builder().url(\"\$base/data/{$example}?limit=20\")\n    .header(\"apikey\", BuildConfig.NOVABASE_KEY).build()\nval lignes = client.newCall(lire).execute().body?.string()\n\nval corps = \"\"\"{$json}\"\"\".toRequestBody(\"application/json\".toMediaType())\nval creer = Request.Builder().url(\"\$base/data/{$example}\").post(corps)\n    .header(\"apikey\", BuildConfig.NOVABASE_KEY).build()"],
];
?>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6">
        <section class="card p-5 sm:p-6">
            <h2 class="font-semibold">Bases de l'API</h2>
            <div class="mt-4 space-y-4">
                <div><p class="mb-1.5 text-xs font-medium text-muted">URL du projet</p><?= $this->insert('components/copy-field', ['value' => $apiUrl, 'label' => 'l\'URL']) ?></div>
                <div class="grid gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-lg border border-line p-3"><p class="font-medium">En-tête</p><p class="mt-1 font-mono text-xs text-muted">apikey: &lt;clé&gt;</p></div>
                    <div class="rounded-lg border border-line p-3"><p class="font-medium">Format</p><p class="mt-1 text-xs text-muted">JSON, codes HTTP standards</p></div>
                    <div class="rounded-lg border border-line p-3"><p class="font-medium">Erreurs</p><p class="mt-1 font-mono text-xs text-muted">{"error":{"code","message"}}</p></div>
                </div>
            </div>
        </section>

        <section class="kbd-code overflow-hidden shadow-card" data-tabs>
            <div class="flex gap-1 overflow-x-auto border-b border-white/10 px-2 py-2" role="tablist" aria-label="Langage">
                <?php $first = true; foreach ($samples as $key => [$label]): ?>
                <button type="button" role="tab" id="api-tab-<?= e($key) ?>" aria-controls="api-panel-<?= e($key) ?>" aria-selected="<?= e($first ? 'true' : 'false') ?>" data-tab="<?= e($key) ?>" class="min-h-9 shrink-0 cursor-pointer rounded-md px-3 text-xs font-medium text-white/50 hover:text-white aria-selected:bg-white/10 aria-selected:text-white"><?= e($label) ?></button>
                <?php $first = false; endforeach; ?>
                <button type="button" class="ml-auto shrink-0 rounded-md px-3 text-xs text-white/60 hover:text-white" data-copy-panel aria-label="Copier l'exemple">Copier</button>
            </div>
            <?php $first = true; foreach ($samples as $key => [$label, $code]): ?>
            <div id="api-panel-<?= e($key) ?>" role="tabpanel" aria-labelledby="api-tab-<?= e($key) ?>" data-panel="<?= e($key) ?>" <?php if (!$first): ?>hidden<?php endif; ?>>
                <pre class="max-h-96 overflow-auto p-5 text-[12.5px]"><code><?= e($code) ?></code></pre>
            </div>
            <?php $first = false; endforeach; ?>
        </section>

        <section class="card overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-line p-5 sm:px-6">
                <div><h2 class="font-semibold">Points d'accès générés</h2><p class="text-sm text-muted">Un jeu de routes CRUD par table.</p></div>
                <a href="<?= $this->url('/projects/' . $project['ref'] . '/openapi.json') ?>" class="btn-secondary"><?= $this->icon('external', 'size-4') ?>Télécharger OpenAPI</a>
            </div>
            <?php if ($tables === []): ?>
            <p class="px-6 py-10 text-center text-sm text-muted">Créez une table pour générer ses routes.</p>
            <?php endif; ?>
            <?php foreach ($tables as $t): ?>
            <div class="border-b border-line p-5 last:border-b-0 sm:px-6">
                <p class="font-mono font-semibold"><?= e($t['name']) ?></p>
                <ul class="mt-3 space-y-1.5 font-mono text-xs">
                    <?php foreach ([
                        ['GET', '/data/' . $t['name'], 'Lister (filtres, tri, pagination)', $t['access_rules']['read']],
                        ['GET', '/data/' . $t['name'] . '/{id}', 'Lire une ligne', $t['access_rules']['read']],
                        ['POST', '/data/' . $t['name'], 'Créer (objet ou tableau, 500 max)', $t['access_rules']['insert']],
                        ['PATCH', '/data/' . $t['name'] . '/{id}', 'Modifier', $t['access_rules']['update']],
                        ['DELETE', '/data/' . $t['name'] . '/{id}', 'Supprimer', $t['access_rules']['delete']],
                    ] as [$method, $path, $label, $rule]): ?>
                    <li class="flex flex-wrap items-center gap-3">
                        <span class="w-14 rounded px-1.5 py-0.5 text-center font-semibold <?= e(match ($method) { 'GET' => 'bg-accent-soft text-accent-text', 'POST' => 'bg-[#5b8af0]/15 text-[#5b8af0]', 'DELETE' => 'bg-danger-soft text-danger', default => 'bg-warning/15 text-warning' }) ?>"><?= e($method) ?></span>
                        <span class="text-fg"><?= e($path) ?></span>
                        <span class="font-sans text-muted"><?= e($label) ?></span>
                        <span class="badge ml-auto py-0 font-sans">clé publique : <?= e($rules[$rule] ?? $rule) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endforeach; ?>
        </section>
    </div>

    <aside class="space-y-4">
        <div class="card p-5 text-sm">
            <h2 class="font-semibold">Filtres</h2>
            <p class="mt-1 text-muted"><code class="text-fg">?colonne=opérateur.valeur</code></p>
            <ul class="mt-3 space-y-1.5 font-mono text-xs text-muted">
                <li><span class="text-fg">prix=gte.1000</span> ≥ 1000</li>
                <li><span class="text-fg">statut=eq.actif</span> égal</li>
                <li><span class="text-fg">nom=ilike.*café*</span> contient (sans casse)</li>
                <li><span class="text-fg">id=in.(1,2,3)</span> dans la liste</li>
                <li><span class="text-fg">supprime=is.null</span> est NULL</li>
            </ul>
            <p class="mt-3 text-xs text-muted">Aussi : neq, gt, lt, lte, like. <code class="text-fg">select=id,nom</code>, <code class="text-fg">order=prix.desc</code>, <code class="text-fg">limit</code> (≤ 100), <code class="text-fg">offset</code>. Total dans l'en-tête <code class="text-fg">X-Total-Count</code>.</p>
        </div>
        <div class="card p-5 text-sm">
            <h2 class="font-semibold">Envoyer un e-mail</h2>
            <pre class="kbd-code mt-3 overflow-x-auto p-3 text-[11px]"><code>POST /email/send   (clé secrète)
{
  "to": "client@exemple.com",
  "subject": "Bienvenue",
  "body_html": "&lt;p&gt;Bonjour !&lt;/p&gt;"
}</code></pre>
            <a href="<?= $this->url('/projects/' . $project['ref'] . '/emails') ?>" class="link mt-3 inline-block text-xs">Configurer le SMTP →</a>
        </div>
        <div class="card p-5 text-sm text-muted">
            <h2 class="font-semibold text-fg">Codes de réponse</h2>
            <p class="mt-2 font-mono text-xs leading-6">200 OK · 201 créé · 204 supprimé<br>400 requête invalide · 401 clé invalide<br>402 quota dépassé · 403 règle d'accès<br>404 introuvable · 409 conflit · 422 données<br>429 trop de requêtes</p>
        </div>
    </aside>
</div>
