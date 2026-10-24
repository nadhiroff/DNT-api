# NovaBase

Backend auto-hébergé et gratuit, inspiré de Supabase, écrit en **PHP 8.3+ natif** (sans framework)
avec **MySQL 8** : base de données isolée par projet, API REST instantanée, authentification des
utilisateurs finaux, stockage de fichiers et service d'e-mails.

> **État : phases 1 à 6 livrées + e-mails** : comptes, site public, projets à base MySQL isolée, Table Editor, éditeur SQL, API REST CRUD, clés API, e-mails (SMTP configurable, modèles, API d'envoi), console et administration. Ce README est complété à chaque phase ; la version finale
> (worker, cron, Supervisor/systemd, sauvegardes, mises à jour) arrive en phase 11.

## Prérequis

| Composant  | Version / remarque                                                          |
|------------|-----------------------------------------------------------------------------|
| PHP        | 8.3 ou plus, extensions `pdo_mysql sodium mbstring intl fileinfo openssl` (`apcu` optionnelle) |
| MySQL      | 8.0 ou plus (InnoDB, utf8mb4)                                               |
| Composer   | 2.x                                                                         |
| Node.js    | 20+ — uniquement pour recompiler les assets (déjà compilés dans le dépôt)   |
| Serveur    | Nginx + PHP-FPM (recommandé) ou Apache 2.4 avec `mod_rewrite`               |

## Installation

```bash
git clone <dépôt> /var/www/novabase && cd /var/www/novabase
composer install --no-dev --optimize-autoloader
php install.php                 # vérifie les prérequis, crée config/env.php, génère APP_KEY
```

La configuration vit dans **`config/env.php`** (un tableau PHP, pas de fichier `.env`) : même si un
hébergement l'exposait par erreur, il serait exécuté et renverrait une 404 vide au lieu d'être affiché.
Une vraie variable d'environnement du même nom est prioritaire. Le modèle commenté est
`config/env.example.php`.

Sauvegardez `APP_KEY` hors du serveur : elle chiffre tous les secrets stockés en base
(mots de passe SMTP et MySQL des projets, clés JWT, secrets 2FA). Sans elle, ils sont perdus.

### Comptes MySQL

NovaBase utilise **deux comptes distincts** :

- `novabase_app` — compte applicatif utilisé à chaque requête, **DML uniquement** sur la base plateforme ;
- `novabase_admin` — utilisé seulement par `bin/console migrate` et par le provisioning des projets
  (création des bases `proj_<ref>` et des utilisateurs `u_<ref>`).

```sql
CREATE USER 'novabase_admin'@'localhost' IDENTIFIED BY '<mot de passe fort>';
CREATE USER 'novabase_app'@'localhost'   IDENTIFIED BY '<autre mot de passe fort>';

GRANT ALL PRIVILEGES ON `novabase`.* TO 'novabase_admin'@'localhost';
GRANT ALL PRIVILEGES ON `proj\_%`.*  TO 'novabase_admin'@'localhost' WITH GRANT OPTION;
GRANT CREATE USER ON *.*             TO 'novabase_admin'@'localhost';

GRANT SELECT, INSERT, UPDATE, DELETE ON `novabase`.* TO 'novabase_app'@'localhost';
```

Renseignez ensuite `DB_USERNAME`/`DB_PASSWORD` et `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` dans `config/env.php`.

Le journal d'audit est rendu immuable par des triggers MySQL. Si le binlog est actif (défaut de MySQL 8),
leur création exige que le compte d'administration ait le privilège `SUPER`, ou que le serveur ait
`log_bin_trust_function_creators = 1`.

### Base de données et superadmin

```bash
php bin/console migrate              # crée la base si besoin et applique les migrations
php bin/console migrate --status     # état des migrations
php bin/console create-superadmin    # interactif ; 2FA TOTP obligatoire, codes de secours affichés une fois
```

Le superadmin ne peut être créé **qu'en ligne de commande**. Le mot de passe n'est jamais passé en
argument (historique du shell) ; pour l'automatisation : `--email=… --name=… --password-stdin`.

### Serveur web

**Recommandé : la racine web pointe sur `public/`.** Si votre hébergeur impose le dossier du projet
comme racine (ou si l'application est dans un sous-dossier, ex. `http://localhost/DNT-api/`) :

- `index.php` à la racine du projet sert l'application dès qu'on ouvre le dossier ;
- `.htaccess` à la racine renvoie tout vers `public/` (si `mod_rewrite` et `AllowOverride` sont actifs) :
  les dossiers `config/`, `storage/`, `vendor/`… deviennent alors inaccessibles et les URL sont propres ;
- sans `.htaccess` pris en compte, l'application passe automatiquement en liens `index.php/chemin`
  (`APP_URL_REWRITE=auto`). `config/env.php`, `config/*.php` et les journaux (`*.log.php`) restent
  protégés, mais d'autres fichiers du projet (ex. `composer.json`) sont lisibles : activez
  `AllowOverride All` ou pointez la racine sur `public/` en production.

- **Nginx** : `deploy/nginx.conf` (HTTPS, en-têtes, seul `index.php` exécutable) et
  `deploy/php-fpm-pool.conf` (directives PHP de production).
- **Apache** : `DocumentRoot` sur `public/` avec `AllowOverride All` ; `public/.htaccess` gère la réécriture.
- **HTTPS** : `sudo certbot --nginx -d novabase.example.com` (Let's Encrypt), puis gardez
  `SESSION_SECURE_COOKIE=true` et `HSTS_MAX_AGE=31536000`.
- Derrière un proxy/répartiteur : renseignez `TRUSTED_PROXIES` (sinon `X-Forwarded-*` est ignoré).

### Comptes et e-mails

Les visiteurs créent leur compte depuis **« Créer un compte »** (en-tête de la page d'accueil) :
inscription → e-mail de confirmation (lien valable 1 h, usage unique) → connexion → console.
Mot de passe oublié, double authentification (TOTP + 10 codes de secours), sessions actives
révocables et suppression du compte sont dans **Mon compte**. Après 5 échecs de connexion, le compte
et l'adresse IP sont bloqués progressivement (1 min, doublé à chaque échec, 1 h maximum).

Les e-mails partent par une file d'attente MySQL. En production : `MAIL_DRIVER=smtp`,
`QUEUE_DRIVER=database` et un worker permanent :

```bash
php bin/console worker            # à superviser par systemd ou Supervisor
php bin/console worker --once     # variante cron : * * * * * php /chemin/bin/console worker --once
```

**Gmail** : `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_ENCRYPTION=tls`, votre adresse dans
`MAIL_USERNAME` et un **mot de passe d'application** Google dans `MAIL_PASSWORD` (les espaces affichés
par Google sont ignorés). `MAIL_FROM_ADDRESS` vide = votre adresse Gmail. Vérification :

```bash
php bin/console mail:test vous@exemple.com
```

En développement, `QUEUE_DRIVER=sync` envoie l'e-mail tout de suite et affiche l'erreur SMTP à l'écran
en cas d'échec ; `MAIL_DRIVER=log` enregistre les messages au lieu de les envoyer (lisibles sur
`/_dev/mail`, page refusée hors `APP_ENV=local`).

Le QR code de la 2FA est dessiné dans le navigateur par `qrcode-generator` (MIT, ~50 Ko, compilé
dans `public/assets/js`) : aucun service externe ne voit le secret.

### Tâches planifiées (cron)

```cron
* * * * *    php /var/www/novabase/bin/console worker --once      # e-mails (si pas de worker permanent)
* * * * *    php /var/www/novabase/bin/console metrics:collect    # RAM / uptime pour le tableau de bord
*/10 * * * * php /var/www/novabase/bin/console maintenance        # quotas, purge clés révoquées > 30 j…
```

Le worker permanent (`php bin/console worker`) fait aussi `metrics:collect` chaque minute et
`maintenance` toutes les 10 minutes.

### Utiliser un projet depuis une application

Une application n'a besoin que de l'URL du projet et d'une clé (onglet « Vue d'ensemble » → bloc
**Intégration**, `.env` prêt à copier) :

```
NOVABASE_URL=https://votre-domaine/api/v1/<ref>
NOVABASE_KEY=pk_live_…           # applications mobiles / web (règles d'accès des tables)
NOVABASE_SERVICE_KEY=sk_live_…   # serveurs uniquement (accès complet, e-mails)
```

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/data/<table>?col=op.valeur&order=col.desc&limit=20&offset=0&select=a,b` | Lister |
| GET | `/data/<table>/<id>` | Lire |
| POST | `/data/<table>` | Créer (objet ou tableau, 500 max) |
| PATCH / PUT | `/data/<table>/<id>` | Modifier / remplacer |
| DELETE | `/data/<table>/<id>` | Supprimer |
| POST | `/email/send` | Envoyer un e-mail (clé secrète) |
| GET | `/email/<id>` | Statut d'un e-mail |
| GET | `/openapi.json` | Documentation OpenAPI 3.1 du projet |

Opérateurs : `eq, neq, gt, gte, lt, lte, like, ilike, in, is`. Les clés restent copiables dans la
console (stockées chiffrées) ; une clé régénérée ou révoquée est refusée immédiatement et reste
affichée 30 jours dans l'historique.

Le tableau de bord superadmin lit CPU, RAM, disque et uptime. Si le serveur web n'a pas accès à
`/proc` (Apache/PHP-FPM durcis par systemd `ProtectProc`/`ProcSubset`, ou `open_basedir`), la RAM et
l'uptime proviennent de `metrics:collect` (le worker l'exécute aussi chaque minute).

### Projets et isolation

Chaque projet crée une base `proj_<ref>` et un utilisateur MySQL `u_<ref>` n'ayant que
`SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES` sur cette base ; en cas
d'échec, les étapes déjà faites sont annulées. L'API d'un projet (`/api/v1/<ref>`, en-tête `apikey`)
se connecte toujours avec cet utilisateur. Le journal des connexions peut localiser les IP via
`GEOIP_PROVIDER=ip-api` (envoie les IP publiques à ip-api.com : à mentionner dans votre politique
de confidentialité) ; `none` par défaut.

**Éditeur SQL** (onglet du projet) : exécute des requêtes directement sur la base du projet, avec ce
même utilisateur MySQL (impossible d'atteindre une autre base). Limites : 100 Ko et 50 instructions
par exécution, 15 s par lecture, 500 lignes affichées ; `USE`, `LOCK`, transactions manuelles et
`SLEEP` refusés. Après un `CREATE`, `ALTER`, `DROP` ou `RENAME`, l'API est resynchronisée : une table
est exposée si sa clé primaire est une colonne `id` (`BIGINT UNSIGNED AUTO_INCREMENT` ou `CHAR(36)`),
et les colonnes `owner_id`, `created_at`, `updated_at` sont ajoutées si elles manquent.

Journaux : `storage/logs/app-AAAA-MM-JJ.log.php`, une ligne JSON par événement après une ligne de
garde PHP (`tail -n +2 storage/logs/app-*.log.php`).

Supervision : `GET /health` renvoie `200` (`ok`/`degraded`) ou `503` (`fail`) avec l'état de la base,
de la file d'attente et du disque.

## Développement

```bash
composer install
npm ci && npm run build          # Tailwind CSS compilé + polices + Alpine.js (build CSP)
php -S localhost:8080 -t public public/index.php   # ou via Apache : http://localhost/DNT-api/
composer check                   # PHPStan niveau 8 + PHPUnit
```

Pour les tests d'intégration MySQL, fournissez un compte autorisé sur `novabase\_test\_%` :

```bash
NB_TEST_DB_USER=admin NB_TEST_DB_PASSWORD=… vendor/bin/phpunit
```

Sans ces variables, les tests d'intégration sont ignorés (les tests unitaires tournent toujours).

## Architecture

```
public/index.php        point d'entrée unique ; index.php racine = relais pour les hébergements sans public/
app/Core                Application, Container, Config, Http (Router, Request, Response, Pipeline),
                        Database (PDO, Migrator), Session, Security (Csrf, Totp, mots de passe), View
app/Middleware          SecurityHeaders, HandleErrors, StartSession, Csrf
app/Controllers         contrôleurs fins (Web/ et Api/)
app/Services            logique métier (Crypto, AuditService, HealthService…)
app/Repositories        tout le SQL, une classe par table
app/Console/Commands    commandes de bin/console
views/                  gabarits PHP, sorties échappées par e()
config/                 configuration (env.php, ignoré par git) et routes
database/migrations     migrations SQL versionnées et rejouables
storage/                journaux, cache, fichiers — hors du web
docs/design-system.md   jetons, typographie et règles d'interface
```

Garde-fous automatisés (tests) : aucune concaténation de variable dans une requête SQL, aucun SQL dans
les contrôleurs ou les vues, toute sortie de gabarit échappée, aucun style ou script inline sans nonce.
