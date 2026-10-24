# NovaBase — notes pour les agents

- Cahier des charges : clone Supabase en PHP 8.3 natif + MySQL 8, livré **phase par phase** ; attendre la validation de l'utilisateur avant la phase suivante.
- Avant toute livraison : `composer check` (PHPStan niveau 8 sans erreur + PHPUnit vert). Intégration MySQL : `NB_TEST_DB_USER` / `NB_TEST_DB_PASSWORD`.
- SQL uniquement dans `app/Repositories` (ou Migrator/commandes d'infrastructure), toujours paramétré ; identifiants via `Identifier::quote()`. `tests/Unit/SqlSafetyTest.php` refuse toute concaténation de variable.
- Gabarits : toute sortie via `e()` ou un helper `$this->…` de `Template` ; pas d'attribut `style`, pas de script sans nonce (CSP stricte). Couleurs via jetons sémantiques (`docs/design-system.md`).
- Assets : `npm run build` après toute modification de `resources/` ou de classes Tailwind dans `views/` ; les fichiers compilés sont versionnés.
- Configuration : `config/env.php` (tableau PHP, pas de `.env` : l'hébergeur ne les accepte pas). Les fichiers de `config/` commencent par la garde `NOVABASE_ROOT`.
- Installation possible en sous-dossier sans réécriture (Apache local `AllowOverride None`, `http://localhost/DNT-api/`) : générer toutes les URL via `UrlGenerator` / `$this->url()` / `$this->asset()`, jamais en dur.
- MySQL local : compte `admin` (migrations/provisioning/tests), `novabase_app` (applicatif). Tests d'intégration : `NB_TEST_DB_USER=admin NB_TEST_DB_PASSWORD=Admin`.
- Messages, commentaires et textes d'interface en français.
