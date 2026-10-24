# NovaBase — design system

Référence visuelle commune à toutes les phases. Les jetons sont définis dans
`resources/css/app.css` ; les gabarits n'utilisent **que** les classes sémantiques
(`bg-surface`, `text-muted`, `border-line`, `text-accent-text`…), jamais de couleur brute.

## Direction

- Plateforme pour développeurs, inspirée de supabase.com : **sombre par défaut**, mode clair complet.
- Un seul accent : **émeraude `#3ecf8e`**. Boutons primaires en émeraude avec texte quasi noir
  (`--nb-on-accent`), ce qui garde un contraste > 10:1 dans les deux thèmes.
- Texte d'accent : `accent-text` (plus sombre en clair, plus lumineux en sombre) pour rester ≥ 4.5:1.
- Profondeur par bordures fines et ombres douces, grille de fond masquée, halo émeraude animé.

## Jetons

| Jeton            | Sombre    | Clair     | Usage                                  |
|------------------|-----------|-----------|----------------------------------------|
| `bg`             | `#0c0d0f` | `#fafaf9` | fond de page                           |
| `surface`        | `#131518` | `#ffffff` | cartes, panneaux                       |
| `surface-2`      | `#1a1d21` | `#f2f3f2` | survol, zones secondaires              |
| `line`           | `#25292e` | `#e2e4e3` | bordures                               |
| `line-strong`    | `#353a41` | `#c9cdcb` | bordures de champs, survol de cartes   |
| `fg`             | `#ededed` | `#111315` | texte principal                        |
| `muted`          | `#9aa1a9` | `#5b6168` | texte secondaire (≥ 4.5:1 sur `bg`)    |
| `accent`         | `#3ecf8e` | `#3ecf8e` | boutons primaires, indicateurs         |
| `accent-text`    | `#4fd99a` | `#0b7a4b` | liens et texte accentué                |
| `danger`         | `#f87171` | `#c62828` | erreurs, actions destructrices         |
| `warning`        | `#fbbf24` | `#a15c00` | quotas 70–90 %                         |

## Typographie

- **IBM Plex Sans** (variable) pour l'interface, **JetBrains Mono** pour le code, les clés et les références.
- Polices auto-hébergées (`public/assets/fonts`, copiées par `npm run build`) : aucune requête externe,
  compatible avec la CSP `font-src 'self'`.
- Corps 16 px, interligne 1.6, titres `tracking-tight` + `text-balance`.

## Mouvement

- `animate-fade-up` (600 ms, ease-out doux) pour l'apparition des sections, décalée via
  des classes littérales `[animation-delay:…]` (les attributs `style` sont interdits par la CSP).
- `animate-glow` pour le halo d'arrière-plan. Transitions d'état 150–300 ms.
- `prefers-reduced-motion` neutralise toutes les animations.

## Règles

- Cibles tactiles ≥ 44 px (`min-h-11`, `size-11`), focus visible émeraude sur tout élément interactif.
- Icônes SVG inline (`aria-hidden="true"`), jamais d'emoji.
- Mobile d'abord : gouttières `px-4`, aucune barre de défilement horizontale ; vérifier 375 / 768 / 1024 / 1440 px.
- JavaScript : Alpine.js **build CSP** (`alpine-csp.min.js`), aucun `eval`, aucun gestionnaire `on*` inline.
