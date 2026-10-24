<?php
/**
 * Page d'accueil publique (inspirée de la structure de supabase.com, contenu et visuels propres).
 *
 * @var \App\Core\View\Template $this
 * @var string                  $appName
 * @var string                  $appUrl
 * @var list<array{name: string, slug: string, description: string, price: string, currency: string, interval: string, features: list<string>, is_default: bool, is_available: bool, is_featured: bool}> $plans
 * @var array<string, mixed>|null $currentUser
 */
$this->layout('layouts/site', ['title' => 'Construisez en un week-end, évoluez sans limite']);
$loggedIn = !empty($currentUser);
$startPath = $loggedIn ? '/console' : '/register';
$startLabel = $loggedIn ? 'Ouvrir la console' : 'Démarrer un projet';
$apiBase = $appUrl . '/api/v1/<ref>';

$emailsA = ['awa.diop@gmail.com', 'k.mensah@outlook.fr', 'lea.martin@proton.me', 'yanis@startup.io', 'fatou.sow@yahoo.fr', 'tom.leroy@gmail.com'];
$emailsB = ['moussa.k@entreprise.ci', 'ines.b@gmail.com', 'jules@agence.dev', 'aminata.t@orange.sn', 'hugo.p@icloud.com', 'sarah.n@gmail.com'];
$frameworks = ['Flutter', 'React', 'React Native', 'Kotlin', 'Swift', 'Vue', 'Angular', 'Svelte', 'Next.js', 'Nuxt', 'Node.js', 'PHP', 'Laravel', 'Python', 'Go', 'Ionic', 'Expo', 'curl'];

$samples = [
    'flutter' => ['Flutter', "final api = '{$apiBase}';\n\nFuture<List<dynamic>> fetchProducts() async {\n  final res = await http.get(\n    Uri.parse('\$api/data/products?status=eq.active&order=created_at.desc'),\n    headers: {'apikey': 'pk_live_…'},\n  );\n  return jsonDecode(res.body) as List<dynamic>;\n}"],
    'react' => ['React', "const API = '{$apiBase}';\n\nexport default function Products() {\n  const [items, setItems] = useState([]);\n\n  useEffect(() => {\n    fetch(`\${API}/data/products?select=id,name,price&limit=20`, {\n      headers: { apikey: 'pk_live_…' },\n    }).then((r) => r.json()).then(setItems);\n  }, []);\n\n  return <ProductList items={items} />;\n}"],
    'kotlin' => ['Kotlin', "val client = OkHttpClient()\n\nfun fetchProducts(): String? {\n    val request = Request.Builder()\n        .url(\"{$apiBase}/data/products?price=gte.1000\")\n        .header(\"apikey\", \"pk_live_…\")\n        .build()\n    return client.newCall(request).execute().body?.string()\n}"],
    'swift' => ['Swift', "var request = URLRequest(url: URL(string:\n    \"{$apiBase}/data/products?order=price.asc\")!)\nrequest.setValue(\"pk_live_…\", forHTTPHeaderField: \"apikey\")\n\nlet (data, _) = try await URLSession.shared.data(for: request)\nlet products = try JSONDecoder().decode([Product].self, from: data)"],
    'vue' => ['Vue', "<script setup>\nconst products = ref([])\n\nonMounted(async () => {\n  const res = await fetch('{$apiBase}/data/products', {\n    headers: { apikey: 'pk_live_…' },\n  })\n  products.value = await res.json()\n})\n</script>"],
    'curl' => ['curl', "# Insérer une ligne (clé secrète, côté serveur uniquement)\ncurl -X POST '{$apiBase}/data/products' \\\n  -H 'apikey: sk_live_…' \\\n  -H 'Content-Type: application/json' \\\n  -d '{\"name\": \"Café arabica\", \"price\": 1500}'"],
];

$useCases = [
    ['Application mobile e-commerce', 'Catalogue, paniers, commandes et comptes clients pour votre app Flutter ou React Native.', 'database'],
    ['SaaS B2B', 'Données isolées par projet, membres et rôles, clés API pour vos intégrations.', 'grid'],
    ['App de livraison', 'Suivi des commandes, notifications par e-mail et photos de livraison dans le Storage.', 'zap'],
    ['Outil interne', 'Tableaux de données, imports CSV et accès réservé à votre équipe.', 'lock'],
];

$security = [
    ['shield', 'Isolation stricte', 'Chaque projet a sa propre base et son propre utilisateur MySQL.'],
    ['lock', 'Secrets chiffrés', 'Mots de passe SMTP, clés JWT et secrets 2FA chiffrés avec libsodium.'],
    ['key', 'Clés révocables', 'Clés hachées, affichées une seule fois, révoquées instantanément.'],
    ['user', 'Double authentification', 'TOTP et codes de secours, obligatoire pour les administrateurs.'],
    ['alert', 'Anti-brute-force', 'Blocage progressif par compte et par IP, limites de débit par clé.'],
    ['folder', 'Journal d\'audit immuable', 'Chaque action sensible est tracée, sans modification possible.'],
];

$faq = [
    ['Est-ce vraiment gratuit ?', 'Oui. Le plan Gratuit est utilisable sans carte bancaire, dans les limites indiquées. Les offres payantes arrivent bientôt pour les projets qui grandissent.'],
    ['Mes données sont-elles isolées des autres clients ?', 'Chaque projet possède sa propre base MySQL et son propre utilisateur MySQL, avec des droits limités à cette seule base. Une faille dans un projet ne peut pas exposer les données d\'un autre.'],
    ['Puis-je l\'utiliser depuis une application mobile ?', 'Oui : utilisez la clé publique (pk_…) dans votre app Flutter, Kotlin, Swift ou React Native, et définissez des règles d\'accès par table. La clé secrète reste sur vos serveurs.'],
    ['Que se passe-t-il si j\'atteins une limite ?', 'Les nouvelles écritures sont refusées avec un message clair, mais la lecture et la suppression restent possibles pour que vous puissiez libérer de l\'espace. Vous êtes prévenu par e-mail à 80 % et 100 %.'],
    ['Puis-je héberger NovaBase moi-même ?', 'Oui, c\'est son principe : un simple serveur avec PHP 8.3 et MySQL 8 suffit, sans framework ni service payant.'],
];
$currencySymbol = static fn (string $c): string => $c === 'EUR' ? '€' : $c;
?>

<!-- ============================== HERO ============================== -->
<section class="relative isolate overflow-hidden">
    <div class="bg-grid pointer-events-none absolute inset-0 -z-10 opacity-60" aria-hidden="true"></div>
    <div class="pointer-events-none absolute top-[-18rem] left-[-10rem] -z-10 size-[44rem] rounded-full bg-accent/12 blur-3xl animate-glow" aria-hidden="true"></div>

    <div class="mx-auto max-w-6xl px-4 pt-16 pb-12 sm:px-6 sm:pt-24 lg:pb-16">
        <div class="grid items-end gap-8 lg:grid-cols-[1.45fr_0.55fr] lg:gap-12">
            <h1 class="animate-fade-up text-[2.5rem] leading-[1.06] font-semibold tracking-tight text-balance sm:text-5xl lg:text-[3.3rem]">
                Construisez en un week-end<br>
                <span class="bg-gradient-to-r from-accent via-[#5fe0a8] to-accent bg-clip-text text-transparent">Évoluez sans limite</span>
            </h1>
            <p class="animate-fade-up text-lg text-muted [animation-delay:120ms] lg:pb-2">
                Démarrez avec une base de données MySQL dédiée.
                Ajoutez l'authentification, une API REST instantanée, le stockage de fichiers et l'envoi d'e-mails.
            </p>
        </div>
        <div class="mt-9 flex animate-fade-up flex-col gap-3 [animation-delay:200ms] sm:flex-row">
            <a href="<?= $this->url($startPath) ?>" class="btn-primary h-12 px-6 text-[15px]"><?= e($startLabel) ?> <?= $this->icon('arrow-right', 'size-4') ?></a>
            <a href="<?= $this->url('/#demo') ?>" class="btn-secondary h-12 px-6 text-[15px]"><?= $this->icon('monitor', 'size-4') ?>Voir la démo</a>
        </div>
    </div>

    <!-- ===================== GRILLE PRODUITS (BENTO) ===================== -->
    <div id="produits" class="mx-auto max-w-6xl scroll-mt-20 px-4 pb-6 sm:px-6">
        <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">

            <!-- Base de données -->
            <article class="card group relative overflow-hidden p-6 md:col-span-2 lg:h-[23rem]" data-reveal>
                <div class="grid h-full gap-6 sm:grid-cols-2">
                    <div class="flex flex-col">
                        <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('database', 'size-5 text-accent-text') ?>Base de données</h2>
                        <p class="mt-3 text-sm text-muted">Chaque projet est une <strong class="font-medium text-fg">base MySQL complète et isolée</strong>, la base relationnelle la plus utilisée au monde.</p>
                        <ul class="mt-auto space-y-2 pt-6 text-sm">
                            <?php foreach (['Isolée par projet', 'Relations, index et contraintes', 'Import / export CSV'] as $point): ?>
                            <li class="flex items-center gap-2"><?= $this->icon('check', 'size-4 text-accent-text') ?><?= e($point) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="relative h-56 sm:h-full" aria-hidden="true">
                        <svg class="absolute inset-0 size-full" viewBox="0 0 280 220" fill="none">
                            <path d="M96 52 C 140 52, 140 110, 184 110" class="flow-line" stroke="var(--nb-accent)" stroke-width="1.5"/>
                            <path d="M96 168 C 140 168, 140 110, 184 110" class="flow-line" stroke="var(--nb-accent)" stroke-width="1.5"/>
                            <circle r="3" fill="var(--nb-accent)"><animateMotion dur="2.4s" repeatCount="indefinite" path="M96 52 C 140 52, 140 110, 184 110"/></circle>
                            <circle r="3" fill="var(--nb-accent)"><animateMotion dur="2.4s" begin="1.2s" repeatCount="indefinite" path="M96 168 C 140 168, 140 110, 184 110"/></circle>
                        </svg>
                        <?php foreach ([['left-0 top-3', 'customers', ['id', 'email', 'name']], ['left-0 bottom-3', 'products', ['id', 'name', 'price']], ['right-0 top-1/2 -translate-y-1/2', 'orders', ['id', 'customer_id', 'product_id', 'total']]] as [$pos, $table, $cols]): ?>
                        <div class="absolute <?= e($pos) ?> w-[6.75rem] overflow-hidden rounded-lg border border-line bg-bg/90 font-mono text-[11px] shadow-card backdrop-blur float-soft">
                            <p class="flex items-center gap-1.5 border-b border-line bg-surface-2 px-2.5 py-1.5 text-fg"><?= $this->icon('grid', 'size-3 text-accent-text') ?><?= e($table) ?></p>
                            <?php foreach ($cols as $col): ?>
                            <p class="px-2.5 py-1 text-muted"><?= e($col) ?></p>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>

            <!-- Authentification -->
            <article class="card relative flex h-[23rem] flex-col overflow-hidden p-6" data-reveal data-reveal-delay="1">
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('shield', 'size-5 text-accent-text') ?>Authentification</h2>
                <p class="mt-3 text-sm text-muted"><strong class="font-medium text-fg">Inscriptions et connexions</strong> de vos utilisateurs, avec règles d'accès par table.</p>
                <div class="mask-fade-y relative -mx-6 mt-5 grid min-h-0 flex-1 grid-cols-2 items-start gap-2 overflow-hidden px-3" aria-hidden="true">
                    <?php foreach ([[$emailsA, 'marquee-y'], [$emailsB, 'marquee-y-reverse']] as [$list, $anim]): ?>
                    <div class="<?= e($anim) ?> flex flex-col gap-2">
                        <?php foreach ([...$list, ...$list] as $email): ?>
                        <p class="truncate rounded-md border border-line bg-bg/70 px-2.5 py-2 font-mono text-[11px] text-muted"><?= e($email) ?></p>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <!-- Clés API -->
            <article class="card relative flex h-[23rem] flex-col overflow-hidden p-6" data-reveal data-reveal-delay="2">
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('key', 'size-5 text-accent-text') ?>Clés API</h2>
                <p class="mt-3 text-sm text-muted">Clés <strong class="font-medium text-fg">publiques pour vos apps</strong>, secrètes pour vos serveurs, révocables à tout moment.</p>
                <div class="mt-auto space-y-2.5 pt-6 font-mono text-[11.5px]" aria-hidden="true">
                    <div class="rounded-lg border border-line bg-bg/70 p-3">
                        <p class="flex items-center justify-between text-muted"><span>Publique</span><span class="rounded bg-accent-soft px-1.5 text-[10px] text-accent-text">app mobile</span></p>
                        <p class="mt-1.5 text-fg"><span class="type-in">pk_live_7Hq2…f9Ka</span></p>
                    </div>
                    <div class="rounded-lg border border-line bg-bg/70 p-3">
                        <p class="flex items-center justify-between text-muted"><span>Secrète</span><span class="rounded bg-danger-soft px-1.5 text-[10px] text-danger">serveur</span></p>
                        <p class="mt-1.5 tracking-widest text-fg">sk_live_••••••••••</p>
                    </div>
                </div>
            </article>

            <!-- Storage -->
            <article class="card relative flex min-h-72 flex-col overflow-hidden p-6" data-reveal>
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('folder', 'size-5 text-accent-text') ?>Storage</h2>
                <p class="mt-3 text-sm text-muted"><strong class="font-medium text-fg">Stockez et servez</strong> images, documents et vidéos, publics ou privés.</p>
                <div class="mt-auto grid grid-cols-3 gap-2 pt-6" aria-hidden="true">
                    <?php foreach (['monitor', 'folder', 'mail', 'grid', 'monitor', 'folder', 'mail', 'grid', 'monitor'] as $icon): ?>
                    <span class="storage-cell flex aspect-square items-center justify-center rounded-lg border"><?= $this->icon($icon, 'size-5') ?></span>
                    <?php endforeach; ?>
                </div>
            </article>

            <!-- E-mails -->
            <article class="card relative flex min-h-72 flex-col overflow-hidden p-6" data-reveal data-reveal-delay="1">
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('mail', 'size-5 text-accent-text') ?>E-mails</h2>
                <p class="mt-3 text-sm text-muted">Votre propre SMTP, <strong class="font-medium text-fg">file d'attente</strong> et historique de chaque envoi.</p>
                <div class="relative mt-auto h-28" aria-hidden="true">
                    <?php foreach ([['Bienvenue sur MonApp', 'awa.diop@gmail.com'], ['Votre commande #1042', 'k.mensah@outlook.fr'], ['Code de connexion', 'ines.b@gmail.com']] as [$subject, $to]): ?>
                    <div class="mail-toast absolute inset-x-0 bottom-0 rounded-lg border border-line bg-bg/90 p-3 shadow-card">
                        <p class="flex items-center justify-between text-xs"><span class="font-medium text-fg"><?= e($subject) ?></span><span class="flex items-center gap-1 text-accent-text"><?= $this->icon('check', 'size-3.5') ?>Envoyé</span></p>
                        <p class="mt-1 truncate font-mono text-[11px] text-muted">→ <?= e($to) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <!-- Sécurité -->
            <article class="card relative flex min-h-72 flex-col overflow-hidden p-6" data-reveal data-reveal-delay="2">
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('lock', 'size-5 text-accent-text') ?>Sécurité</h2>
                <p class="mt-3 text-sm text-muted"><strong class="font-medium text-fg">Chiffrement, 2FA et audit</strong> activés par défaut, sans configuration.</p>
                <div class="relative mx-auto mt-auto flex size-28 items-center justify-center" aria-hidden="true">
                    <span class="shield-ring absolute inset-0 rounded-full border border-accent/50"></span>
                    <span class="shield-ring absolute inset-0 rounded-full border border-accent/50"></span>
                    <span class="shield-ring absolute inset-0 rounded-full border border-accent/50"></span>
                    <span class="relative flex size-14 items-center justify-center rounded-2xl border border-accent/40 bg-accent-soft text-accent-text"><?= $this->icon('shield', 'size-7') ?></span>
                </div>
            </article>

            <!-- API REST -->
            <article class="card relative flex min-h-72 flex-col overflow-hidden p-6" data-reveal data-reveal-delay="3">
                <h2 class="flex items-center gap-2.5 font-semibold"><?= $this->icon('api', 'size-5 text-accent-text') ?>API REST</h2>
                <p class="mt-3 text-sm text-muted">Une <strong class="font-medium text-fg">API prête à l'emploi</strong> pour chaque table, dès sa création.</p>
                <div class="mt-auto space-y-1.5 pt-5 font-mono text-[10.5px]" aria-hidden="true">
                    <?php foreach (['products', 'orders', 'customers', 'reviews'] as $i => $table): ?>
                    <div class="flex items-center gap-2">
                        <span class="w-[4.6rem] shrink-0 truncate rounded border border-line px-1.5 py-1 text-muted"><?= e($table) ?></span>
                        <svg class="h-2 flex-1" viewBox="0 0 60 8" preserveAspectRatio="none"><line x1="0" y1="4" x2="60" y2="4" class="flow-line" stroke="var(--nb-border-strong)"/><circle r="2" cy="4" fill="var(--nb-accent)"><animate attributeName="cx" from="0" to="60" dur="1.8s" begin="<?= e($i * 0.45) ?>s" repeatCount="indefinite"/></circle></svg>
                        <span class="shrink-0 rounded-full border border-accent/30 bg-accent-soft px-2 py-1 text-accent-text">/<?= e($table) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </div>

        <p class="mt-10 text-xl font-medium tracking-tight sm:text-2xl" data-reveal>Utilisez-en un ou tous. <span class="text-muted">Les meilleurs outils, réunis dans une seule plateforme.</span></p>
    </div>
</section>

<!-- ============================== FRAMEWORKS (DÉFILEMENT) ============================== -->
<section class="border-y border-line bg-surface/40 py-10" aria-label="Technologies compatibles">
    <p class="mx-auto max-w-6xl px-4 text-sm text-muted sm:px-6">Compatible avec toutes vos technologies</p>
    <div class="marquee-pause mask-fade-x mt-6 overflow-hidden">
        <ul class="marquee-x flex w-max gap-3">
            <?php foreach ([...$frameworks, ...$frameworks] as $i => $tech): ?>
            <li class="shrink-0 rounded-full border border-line bg-bg px-5 py-2.5 font-mono text-sm text-muted" <?php if ($i >= count($frameworks)): ?>aria-hidden="true"<?php endif; ?>><?= e($tech) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<!-- ============================== DÉMO CONSOLE (« VIDÉO ») ============================== -->
<section id="demo" class="scroll-mt-20">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <h2 class="section-title max-w-3xl" data-reveal>Restez productif et gérez votre application <span class="text-muted">sans quitter la console</span></h2>

        <div data-tabs class="mt-10" data-reveal>
            <div class="flex flex-wrap gap-2" role="tablist" aria-label="Démonstrations">
                <?php foreach (['editor' => 'Table Editor', 'keys' => 'Clés API', 'rules' => 'Règles d\'accès'] as $key => $label): ?>
                <button type="button" role="tab" id="demo-tab-<?= e($key) ?>" aria-controls="demo-panel-<?= e($key) ?>" aria-selected="<?= e($key === 'editor' ? 'true' : 'false') ?>" data-tab="<?= e($key) ?>"
                    class="min-h-10 cursor-pointer rounded-full border border-line px-5 text-sm font-medium text-muted transition-colors hover:text-fg aria-selected:border-fg aria-selected:text-fg"><?= e($label) ?></button>
                <?php endforeach; ?>
            </div>

            <div class="card mt-6 overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-3" aria-hidden="true">
                    <span class="size-2.5 rounded-full bg-line-strong"></span><span class="size-2.5 rounded-full bg-line-strong"></span><span class="size-2.5 rounded-full bg-line-strong"></span>
                    <span class="ml-3 truncate font-mono text-xs text-muted">console · mon-app</span>
                </div>

                <!-- Table Editor -->
                <div id="demo-panel-editor" role="tabpanel" aria-labelledby="demo-tab-editor" data-panel="editor">
                    <div class="relative grid h-[26rem] grid-cols-1 sm:grid-cols-[12rem_1fr]">
                        <aside class="hidden border-r border-line p-3 text-sm sm:block" aria-hidden="true">
                            <p class="px-2 pb-2 font-mono text-[10px] tracking-widest text-muted uppercase">Tables</p>
                            <?php foreach (['products', 'orders', 'customers', 'reviews', 'categories'] as $i => $table): ?>
                            <p class="flex items-center gap-2 rounded-md px-2 py-1.5 <?= e($i === 0 ? 'bg-accent-soft text-accent-text' : 'text-muted') ?>"><?= $this->icon('grid', 'size-3.5') ?><?= e($table) ?></p>
                            <?php endforeach; ?>
                        </aside>
                        <div class="relative overflow-hidden p-5 sm:p-6" aria-label="Animation : ajout d'une colonne price de type decimal à la table products">
                            <div class="flex items-center justify-between">
                                <p class="font-medium">Colonnes de <span class="font-mono text-accent-text">products</span></p>
                                <span class="hidden rounded-md border border-line px-3 py-1.5 text-xs text-muted sm:inline">Importer un CSV</span>
                            </div>
                            <div class="mt-4 grid grid-cols-[1.4fr_1fr_1fr_auto] gap-2 px-1 text-xs text-muted">
                                <span>Nom</span><span>Type</span><span>Défaut</span><span class="w-12 text-right">Clé</span>
                            </div>
                            <?php foreach ([['id', 'bigint', 'auto', true], ['name', 'string(255)', 'NULL', false], ['created_at', 'datetime', 'now()', false]] as [$col, $type, $default, $pk]): ?>
                            <div class="mt-2 grid grid-cols-[1.4fr_1fr_1fr_auto] items-center gap-2 font-mono text-[13px]">
                                <span class="truncate rounded-md border border-line bg-bg px-3 py-2"><?= e($col) ?></span>
                                <span class="truncate rounded-md border border-line bg-bg px-3 py-2 text-muted"><?= e($type) ?></span>
                                <span class="truncate rounded-md border border-line bg-bg px-3 py-2 text-muted"><?= e($default) ?></span>
                                <span class="flex w-12 justify-end"><span class="flex size-5 items-center justify-center rounded <?= e($pk ? 'bg-accent text-on-accent' : 'border border-line-strong') ?>"><?php if ($pk): ?><?= $this->icon('check', 'size-3.5') ?><?php endif; ?></span></span>
                            </div>
                            <?php endforeach; ?>
                            <div class="demo-row mt-2 grid grid-cols-[1.4fr_1fr_1fr_auto] items-center gap-2 font-mono text-[13px]" aria-hidden="true">
                                <span class="rounded-md border border-accent bg-bg px-3 py-2 ring-4 ring-accent/15"><span class="demo-type">price</span><span class="ml-px inline-block h-4 w-0.5 translate-y-0.5 bg-accent"></span></span>
                                <span class="rounded-md border border-line bg-bg px-3 py-2"><span class="demo-chip inline-block rounded bg-accent-soft px-1.5 text-accent-text">decimal</span></span>
                                <span class="rounded-md border border-line bg-bg px-3 py-2 text-muted">0</span>
                                <span class="flex w-12 justify-end"><span class="size-5 rounded border border-line-strong"></span></span>
                            </div>
                            <div class="mt-4 flex justify-start" aria-hidden="true">
                                <span class="rounded-md border border-dashed border-line-strong px-4 py-2 text-sm text-muted">+ Ajouter une colonne</span>
                            </div>
                            <div class="absolute right-5 bottom-5 flex gap-2 sm:right-6 sm:bottom-6" aria-hidden="true">
                                <span class="rounded-md border border-line px-4 py-2 text-sm text-muted">Annuler</span>
                                <span class="demo-press rounded-md bg-accent px-4 py-2 text-sm font-medium text-on-accent">Enregistrer</span>
                            </div>
                            <div class="demo-toast absolute bottom-20 left-1/2 flex -translate-x-1/2 items-center gap-2 rounded-lg border border-accent/40 bg-surface px-4 py-2.5 text-sm shadow-card sm:left-auto sm:right-6 sm:translate-x-0" aria-hidden="true">
                                <?= $this->icon('check', 'size-4 text-accent-text') ?>Colonne ajoutée · API mise à jour
                            </div>
                            <div class="demo-cursor pointer-events-none absolute z-10 hidden sm:block" aria-hidden="true">
                                <span class="demo-ripple absolute -top-3 -left-3 size-8 rounded-full bg-accent/40"></span>
                                <svg class="demo-cursor-icon relative size-5 drop-shadow" viewBox="0 0 24 24"><path d="M4 3l7 18 2.5-7.5L21 11z" fill="var(--nb-fg)" stroke="var(--nb-bg)" stroke-width="1.5" stroke-linejoin="round"/></svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clés API -->
                <div id="demo-panel-keys" role="tabpanel" aria-labelledby="demo-tab-keys" data-panel="keys" hidden>
                    <div class="h-[26rem] p-5 sm:p-8" aria-label="Animation : régénération d'une clé secrète, l'ancienne est révoquée immédiatement">
                        <p class="font-medium">Clés API du projet</p>
                        <p class="mt-1 text-sm text-muted">Les clés ne sont affichées qu'une seule fois, puis stockées hachées.</p>
                        <div class="mt-6 divide-y divide-line rounded-xl border border-line font-mono text-[13px]" aria-hidden="true">
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <span class="w-24 font-sans text-sm text-muted">Publique</span>
                                <span class="flex-1 text-fg">pk_live_7Hq2Lm…f9Ka</span>
                                <span class="rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-xs text-accent-text">active</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 p-4">
                                <span class="w-24 font-sans text-sm text-muted">Secrète</span>
                                <span class="demo-strike flex-1">sk_live_Qe81…Zt0c</span>
                                <span class="demo-revoke rounded-full bg-danger-soft px-2.5 py-0.5 font-sans text-xs text-danger">révoquée · 401</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 bg-accent-soft/40 p-4">
                                <span class="w-24 font-sans text-sm text-muted">Nouvelle</span>
                                <span class="flex-1 text-accent-text"><span class="demo-newkey">sk_live_N4vX9pRk…2bWq</span></span>
                                <span class="demo-revoke rounded-full bg-accent-soft px-2.5 py-0.5 font-sans text-xs text-accent-text">copiez-la maintenant</span>
                            </div>
                        </div>
                        <div class="mt-6 flex flex-wrap gap-2 font-sans text-xs text-muted" aria-hidden="true">
                            <?php foreach (['read', 'write', 'storage', 'email', 'expire le 31/12', 'IP autorisées'] as $perm): ?>
                            <span class="rounded-full border border-line px-3 py-1"><?= e($perm) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Règles d'accès -->
                <div id="demo-panel-rules" role="tabpanel" aria-labelledby="demo-tab-rules" data-panel="rules" hidden>
                    <div class="h-[26rem] p-5 sm:p-8" aria-label="Animation : modification des règles d'accès de la table orders">
                        <p class="font-medium">Règles d'accès · <span class="font-mono text-accent-text">orders</span></p>
                        <p class="mt-1 text-sm text-muted">Qui peut lire, créer, modifier ou supprimer, avec la clé publique.</p>
                        <div class="mt-6 divide-y divide-line rounded-xl border border-line text-sm" aria-hidden="true">
                            <?php foreach ([['Lire', 'Public', 'Propriétaire uniquement', ''], ['Insérer', 'Public', 'Utilisateurs connectés', 'd2'], ['Modifier', 'Public', 'Propriétaire uniquement', 'd3'], ['Supprimer', 'Clé secrète uniquement', null, '']] as [$op, $from, $to, $delay]): ?>
                            <div class="flex items-center justify-between gap-3 p-4">
                                <span class="font-medium"><?= e($op) ?></span>
                                <span class="relative inline-flex min-w-48 justify-end">
                                    <?php if ($to === null): ?>
                                    <span class="rounded-full border border-line-strong px-3 py-1 text-xs"><?= e($from) ?></span>
                                    <?php else: ?>
                                    <span class="demo-swap-out <?= e($delay) ?> rounded-full border border-warning/40 px-3 py-1 text-xs text-warning"><?= e($from) ?></span>
                                    <span class="demo-swap-in <?= e($delay) ?> absolute right-0 rounded-full border border-accent/40 bg-accent-soft px-3 py-1 text-xs text-accent-text"><?= e($to) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="mt-5 flex items-center gap-2 text-xs text-muted"><?= $this->icon('info', 'size-4') ?>La colonne <code class="text-fg">owner_id</code> est remplie automatiquement avec l'utilisateur connecté.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== FRAMEWORKS + CODE ============================== -->
<section id="api" class="scroll-mt-20 border-y border-line bg-surface/40">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[0.85fr_1.15fr]" data-tabs="framework">
        <div data-reveal>
            <h2 class="section-title"><span class="text-muted">Utilisez <?= e($appName) ?> avec</span><br><span data-tab-label-for="framework">Flutter</span></h2>
            <p class="mt-5 max-w-md text-muted">Une API REST standard : aucun SDK obligatoire. Filtres <code class="rounded bg-surface-2 px-1.5 py-0.5 text-sm text-fg">eq</code>, <code class="rounded bg-surface-2 px-1.5 py-0.5 text-sm text-fg">gte</code>, <code class="rounded bg-surface-2 px-1.5 py-0.5 text-sm text-fg">in</code>, tri, pagination et sélection de colonnes directement dans l'URL.</p>
            <ul class="mt-6 space-y-3 text-sm">
                <?php foreach (['Réponses JSON et codes HTTP standards', 'Limitation de débit par clé (en-têtes X-RateLimit)', 'Documentation OpenAPI générée pour chaque projet'] as $point): ?>
                <li class="flex items-center gap-3"><span class="flex size-5 items-center justify-center rounded-full bg-accent-soft text-accent-text"><?= $this->icon('check', 'size-3.5') ?></span><?= e($point) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="kbd-code overflow-hidden shadow-card" data-reveal data-reveal-delay="1">
            <div class="grid grid-cols-3 border-b border-white/10 sm:grid-cols-6" role="tablist" aria-label="Langage">
                <?php $first = true; foreach ($samples as $key => [$label]): ?>
                <button type="button" role="tab" id="fw-tab-<?= e($key) ?>" aria-controls="fw-panel-<?= e($key) ?>" aria-selected="<?= e($first ? 'true' : 'false') ?>" data-tab="<?= e($key) ?>" data-label="<?= e($label) ?>"
                    class="min-h-12 cursor-pointer border-r border-b border-white/10 px-3 text-xs font-medium text-white/50 transition-colors last:border-r-0 hover:bg-white/5 hover:text-white aria-selected:bg-white/10 aria-selected:text-white sm:border-b-0"><?= e($label) ?></button>
                <?php $first = false; endforeach; ?>
            </div>
            <?php $first = true; foreach ($samples as $key => [$label, $code]): ?>
            <div id="fw-panel-<?= e($key) ?>" role="tabpanel" aria-labelledby="fw-tab-<?= e($key) ?>" data-panel="<?= e($key) ?>" <?php if (!$first): ?>hidden<?php endif; ?>>
                <pre class="h-80 overflow-auto p-5 text-[12.5px] sm:text-[13px]"><code><?= e($code) ?></code></pre>
            </div>
            <?php $first = false; endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== CAS D'USAGE ============================== -->
<section id="fonctionnement" class="scroll-mt-20">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <h2 class="section-title max-w-2xl" data-reveal><span class="text-muted">Lancez votre prochain projet</span><br>en trois étapes</h2>
            <a href="<?= $this->url($startPath) ?>" class="link shrink-0 text-sm" data-reveal><?= e($loggedIn ? 'Ouvrir la console' : 'Créer un compte gratuit') ?> →</a>
        </div>

        <div class="mt-12 grid gap-3 lg:grid-cols-3">
            <?php foreach ([
                ['01', 'Créez un projet', 'Une base dédiée, un utilisateur MySQL isolé et vos clés API sont générés en un clic.', 'database'],
                ['02', 'Dessinez vos tables', 'Colonnes, types, relations et règles d\'accès dans l\'éditeur visuel, sans écrire de SQL.', 'grid'],
                ['03', 'Appelez l\'API', 'Lisez et écrivez vos données depuis votre app web ou mobile, immédiatement.', 'api'],
            ] as $i => [$num, $title, $text, $icon]): ?>
            <article class="card relative overflow-hidden p-6" data-reveal data-reveal-delay="<?= e($i) ?>">
                <span class="pointer-events-none absolute -top-6 -right-2 font-mono text-8xl font-bold text-line/60" aria-hidden="true"><?= e($num) ?></span>
                <span class="relative flex size-10 items-center justify-center rounded-lg border border-line bg-surface-2 text-accent-text"><?= $this->icon($icon) ?></span>
                <h3 class="relative mt-5 font-semibold"><?= e($title) ?></h3>
                <p class="relative mt-2 text-sm text-muted"><?= e($text) ?></p>
            </article>
            <?php endforeach; ?>
        </div>

        <h3 class="mt-20 text-xl font-semibold tracking-tight" data-reveal>Ce que vous pouvez construire</h3>
        <div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($useCases as $i => [$title, $text, $icon]): ?>
            <article class="card group p-5 transition-[border-color,transform] duration-300 hover:-translate-y-0.5 hover:border-line-strong" data-reveal data-reveal-delay="<?= e($i) ?>">
                <span class="text-accent-text"><?= $this->icon($icon) ?></span>
                <h4 class="mt-4 text-sm font-semibold"><?= e($title) ?></h4>
                <p class="mt-1.5 text-sm text-muted"><?= e($text) ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== SÉCURITÉ ============================== -->
<section class="border-y border-line bg-surface/40">
    <div class="mx-auto grid max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-2">
        <div>
            <p class="eyebrow" data-reveal>Sécurité</p>
            <h2 class="section-title mt-3" data-reveal>Une sécurité de niveau production, <span class="text-muted">par défaut</span></h2>
            <div class="mt-10 grid gap-x-8 gap-y-7 sm:grid-cols-2">
                <?php foreach ($security as $i => [$icon, $title, $text]): ?>
                <div data-reveal data-reveal-delay="<?= e($i % 3) ?>">
                    <p class="flex items-center gap-2.5 text-sm font-semibold"><span class="text-accent-text"><?= $this->icon($icon, 'size-[18px]') ?></span><?= e($title) ?></p>
                    <p class="mt-1.5 text-sm text-muted"><?= e($text) ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="kbd-code overflow-hidden shadow-card" data-reveal data-reveal-delay="1" aria-label="Animation : extrait du journal d'audit">
            <div class="flex items-center gap-1.5 border-b border-white/10 px-4 py-3" aria-hidden="true">
                <span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span><span class="size-2.5 rounded-full bg-white/15"></span>
                <span class="ml-3 text-xs text-white/40">journal d'audit</span>
            </div>
            <div class="h-72 space-y-2.5 overflow-hidden p-5 text-[12px] leading-relaxed" aria-hidden="true">
                <?php foreach ([
                    ['12:04:11', 'auth.login', 'awa.diop@gmail.com', '#3ecf8e'],
                    ['12:04:19', 'auth.2fa_verified', 'user:42', '#3ecf8e'],
                    ['12:05:02', 'api_key.regenerated', 'projet mon-app', '#82aaff'],
                    ['12:05:02', 'api_key.revoked', 'sk_live_Qe81…', '#f87171'],
                    ['12:06:47', 'auth.login_failed', 'ip 203.0.113.9', '#fbbf24'],
                    ['12:06:48', 'throttle.locked', 'ip 203.0.113.9 · 60 s', '#fbbf24'],
                    ['12:08:30', 'schema.column_added', 'products.price', '#82aaff'],
                ] as [$time, $action, $target, $color]): ?>
                <p class="log-line"><span class="text-white/35"><?= e($time) ?></span>  <span class="<?= e(match ($color) { '#3ecf8e' => 'text-[#3ecf8e]', '#82aaff' => 'text-[#82aaff]', '#f87171' => 'text-[#f87171]', default => 'text-[#fbbf24]' }) ?>"><?= e($action) ?></span>  <span class="text-white/60"><?= e($target) ?></span></p>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- ============================== TARIFS ============================== -->
<section id="tarifs" class="scroll-mt-20">
    <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 sm:py-28">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="eyebrow">Tarifs</p>
            <h2 class="section-title mt-3">Des offres pour chaque étape</h2>
            <p class="mt-4 text-muted">Commencez gratuitement. Les offres payantes ouvrent très bientôt.</p>
        </div>
        <div class="mt-14 grid items-stretch gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($plans as $i => $plan): ?>
            <article class="card relative flex flex-col p-6 <?= e($plan['is_featured'] ? 'border-accent/60 ring-1 ring-accent/40 lg:-my-2 lg:py-8' : '') ?>" data-reveal data-reveal-delay="<?= e(min($i, 3)) ?>">
                <?php if ($plan['is_featured']): ?>
                <span class="absolute -top-3 left-6 rounded-full bg-accent px-3 py-0.5 text-xs font-semibold text-on-accent">Le plus populaire</span>
                <?php endif; ?>
                <h3 class="text-lg font-semibold"><?= e($plan['name']) ?></h3>
                <p class="mt-1 min-h-10 text-sm text-muted"><?= e($plan['description']) ?></p>
                <p class="mt-6 flex items-baseline gap-1.5">
                    <span class="text-4xl font-semibold tracking-tight"><?= e(rtrim(rtrim(number_format((float) $plan['price'], 2, ',', ' '), '0'), ',')) ?> <?= e($currencySymbol($plan['currency'])) ?></span>
                    <span class="text-sm text-muted">/ <?= e($plan['interval'] === 'year' ? 'an' : 'mois') ?></span>
                </p>
                <?php if ($plan['is_available']): ?>
                <a href="<?= $this->url($startPath) ?>" class="btn-primary mt-6 w-full"><?= e($loggedIn ? 'Ouvrir la console' : 'Commencer gratuitement') ?></a>
                <?php else: ?>
                <button type="button" class="btn-secondary mt-6 w-full" disabled aria-disabled="true">À venir</button>
                <p class="mt-2 text-center text-xs text-muted">Pas encore disponible</p>
                <?php endif; ?>
                <ul class="mt-6 flex-1 space-y-3 border-t border-line pt-6 text-sm">
                    <?php foreach ($plan['features'] as $feature): ?>
                    <li class="flex items-start gap-2.5"><?= $this->icon('check', 'mt-0.5 size-4 shrink-0 text-accent-text') ?><span><?= e($feature) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </article>
            <?php endforeach; ?>
        </div>
        <p class="mt-8 text-center text-xs text-muted" data-reveal>Prix hors taxes. Aucun paiement n'est demandé tant que les offres payantes ne sont pas ouvertes.</p>
    </div>
</section>

<!-- ============================== FAQ ============================== -->
<section id="faq" class="scroll-mt-20 border-t border-line">
    <div class="mx-auto grid max-w-6xl gap-10 px-4 py-20 sm:px-6 sm:py-28 lg:grid-cols-[0.8fr_1.2fr]">
        <div data-reveal>
            <p class="eyebrow">FAQ</p>
            <h2 class="section-title mt-3">Questions fréquentes</h2>
            <p class="mt-4 text-muted">Une autre question ? Consultez nos <a href="<?= $this->url('/conditions') ?>" class="link">conditions d'utilisation</a>.</p>
        </div>
        <div class="divide-y divide-line border-y border-line" data-reveal data-reveal-delay="1">
            <?php foreach ($faq as [$question, $answer]): ?>
            <details class="group py-1">
                <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 font-medium [&::-webkit-details-marker]:hidden">
                    <?= e($question) ?>
                    <span class="text-muted transition-transform duration-200 group-open:rotate-180"><?= $this->icon('chevron-down', 'size-5') ?></span>
                </summary>
                <p class="pb-5 text-sm text-muted"><?= e($answer) ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ============================== APPEL FINAL ============================== -->
<section class="relative isolate overflow-hidden border-t border-line">
    <div class="pointer-events-none absolute inset-y-0 right-0 -z-10 hidden w-1/2 grid-cols-[repeat(24,minmax(0,1fr))] gap-1.5 p-6 md:grid" aria-hidden="true">
        <?php for ($i = 0; $i < 24 * 14; ++$i): ?>
        <span class="pixel aspect-square rounded-[2px] bg-accent"></span>
        <?php endfor; ?>
    </div>
    <div class="pointer-events-none absolute inset-0 -z-10 bg-gradient-to-r from-bg via-bg/95 to-bg/40" aria-hidden="true"></div>
    <div class="mx-auto max-w-6xl px-4 py-24 sm:px-6 sm:py-32">
        <div class="max-w-xl" data-reveal>
            <h2 class="section-title">Construisez en un week-end.<br><span class="text-accent-text">Évoluez sans limite.</span></h2>
            <p class="mt-5 text-muted">Open source et auto-hébergé : vos données restent chez vous. Créez votre compte en moins d'une minute.</p>
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <a href="<?= $this->url($startPath) ?>" class="btn-primary h-12 px-6 text-[15px]"><?= e($startLabel) ?> <?= $this->icon('arrow-right', 'size-4') ?></a>
                <?php if (!$loggedIn): ?>
                <a href="<?= $this->url('/login') ?>" class="btn-secondary h-12 px-6 text-[15px]">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
