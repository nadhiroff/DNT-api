<?php

declare(strict_types=1);

/*
 * =============================================================================
 *  NovaBase — configuration d'environnement
 *  Copiez ce fichier en config/env.php (php install.php le fait pour vous) et ne
 *  le versionnez JAMAIS. C'est un fichier PHP : même si le serveur l'expose par
 *  erreur, il est exécuté (et renvoie 404) au lieu d'être affiché.
 *  Une vraie variable d'environnement du même nom a toujours priorité.
 * =============================================================================
 */

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

return [
    // --- Application -------------------------------------------------------------
    'APP_NAME' => 'NovaBase',
    // production | staging | local  (en production, APP_DEBUG est forcé à false)
    'APP_ENV' => 'production',
    'APP_DEBUG' => 'false',
    // Réécriture d'URL : auto (détectée via .htaccess / Nginx) | true | false.
    // false = liens de la forme /index.php/chemin (hébergement sans mod_rewrite ni .htaccess).
    'APP_URL_REWRITE' => 'auto',
    // URL publique, sans barre oblique finale. Utilisée dans les e-mails et la doc API.
    'APP_URL' => 'https://novabase.example.com',
    'APP_TIMEZONE' => 'UTC',
    'APP_LOCALE' => 'fr',

    // Clé maître (libsodium secretbox, 32 octets en base64). Elle chiffre tous les
    // secrets stockés en base (*_enc). La perdre = perdre ces secrets.
    // Générée par : php install.php   ou   php bin/console key:generate
    'APP_KEY' => '',

    // --- Journalisation ----------------------------------------------------------
    // debug | info | warning | error
    'LOG_LEVEL' => 'info',
    // Nombre de jours de journaux conservés par la tâche de nettoyage.
    'LOG_RETENTION_DAYS' => '14',

    // --- Proxy inverse -----------------------------------------------------------
    // IP ou CIDR des proxys de confiance (séparés par des virgules) dont on accepte
    // X-Forwarded-For / X-Forwarded-Proto. Vide = aucun proxy (accès direct).
    'TRUSTED_PROXIES' => '',

    // --- Base plateforme : compte APPLICATIF (requêtes courantes) -----------------
    // Droits requis : SELECT, INSERT, UPDATE, DELETE sur novabase.* uniquement.
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '3306',
    'DB_SOCKET' => '',
    'DB_DATABASE' => 'novabase',
    'DB_USERNAME' => 'novabase_app',
    'DB_PASSWORD' => '',

    // --- Compte ADMINISTRATEUR (migrations + provisioning des projets) -----------
    // Distinct du compte applicatif. Utilisé uniquement par bin/console migrate et
    // par le ProjectProvisioner (création des bases proj_* et des utilisateurs u_*).
    // Voir README, section « Comptes MySQL », pour les GRANT exacts.
    'DB_ADMIN_USERNAME' => 'novabase_admin',
    'DB_ADMIN_PASSWORD' => '',

    // Hôte depuis lequel les utilisateurs MySQL des projets se connectent
    // (valeur de l'hôte dans CREATE USER 'u_xxx'@'<hôte>').
    'DB_PROJECT_USER_HOST' => 'localhost',

    // --- Sessions ----------------------------------------------------------------
    // database (recommandé, multi-serveurs) | files (mono-serveur uniquement)
    'SESSION_DRIVER' => 'database',
    'SESSION_COOKIE' => 'nb_session',
    // Expiration d'inactivité en secondes (2 h).
    'SESSION_IDLE_LIFETIME' => '7200',
    // Force l'attribut Secure du cookie (true en production derrière HTTPS).
    // Vide = automatique selon la requête.
    'SESSION_SECURE_COOKIE' => 'true',

    // --- Sécurité ----------------------------------------------------------------
    // Durée HSTS en secondes (0 pour désactiver, ex. en développement).
    'HSTS_MAX_AGE' => '31536000',
    // Paramètres Argon2id (mémoire en Kio).
    'ARGON_MEMORY_COST' => '65536',
    'ARGON_TIME_COST' => '4',
    'ARGON_THREADS' => '1',
    // Nom affiché dans les applications d'authentification (TOTP).
    'TOTP_ISSUER' => 'NovaBase',

    // --- Cache / rate limit ------------------------------------------------------
    // apcu | redis | database
    'CACHE_DRIVER' => 'database',
    'REDIS_HOST' => '127.0.0.1',
    'REDIS_PORT' => '6379',
    'REDIS_PASSWORD' => '',

    // --- SMTP global (repli si un projet n'a pas son propre SMTP) ----------------
    'MAIL_HOST' => '',
    'MAIL_PORT' => '587',
    // tls | ssl | none
    'MAIL_ENCRYPTION' => 'tls',
    'MAIL_USERNAME' => '',
    'MAIL_PASSWORD' => '',
    'MAIL_FROM_ADDRESS' => 'no-reply@novabase.example.com',
    'MAIL_FROM_NAME' => 'NovaBase',
];
