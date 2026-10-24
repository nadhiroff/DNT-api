# NovaBase

Backend auto-hébergé et gratuit, inspiré de Supabase, écrit en **PHP 8.3+ natif** (sans framework)
avec **MySQL 8** : base de données isolée par projet, API REST instantanée, authentification des
utilisateurs finaux, stockage de fichiers et service d'e-mails.

> **État : phase 1 — socle.** Ce README est complété à chaque phase ; la version finale
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
