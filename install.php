<?php

declare(strict_types=1);

/*
 * NovaBase — assistant d'installation (CLI uniquement).
 * Vérifie les prérequis, crée config/env.php à partir de config/env.example.php et génère la clé maître.
 * Usage : php install.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$base = __DIR__;
$failures = 0;
$check = static function (bool $pass, string $label, string $hint = '', bool $required = true) use (&$failures): void {
    $mark = $pass ? "\033[32m✔\033[0m" : ($required ? "\033[31m✘\033[0m" : "\033[33m–\033[0m");
    echo '  ' . $mark . ' ' . $label . ($pass || $hint === '' ? '' : "\n      → " . $hint) . "\n";
    if (!$pass && $required) {
        ++$failures;
    }
};

echo "\nNovaBase — vérification des prérequis\n\n";

$check(version_compare(PHP_VERSION, '8.3.0', '>='), 'PHP ' . PHP_VERSION . ' (8.3 minimum)', 'Installez PHP 8.3 ou supérieur.');
foreach (['pdo_mysql', 'sodium', 'mbstring', 'intl', 'fileinfo', 'openssl'] as $ext) {
    $check(extension_loaded($ext), 'Extension ' . $ext, 'apt install php8.3-' . str_replace('pdo_', '', $ext) . ' (ou équivalent)');
}
$check(extension_loaded('apcu'), 'Extension apcu (optionnelle : cache et compteurs en mémoire)', 'apt install php8.3-apcu', false);
$check(defined('PASSWORD_ARGON2ID'), 'Argon2id disponible', 'PHP doit être compilé avec libargon2 ou libsodium.');
foreach (['storage/logs', 'storage/cache', 'storage/uploads'] as $dir) {
    if (!is_dir($base . '/' . $dir)) {
        @mkdir($base . '/' . $dir, 0750, true);
    }
    $check(is_writable($base . '/' . $dir), $dir . ' accessible en écriture', 'chown -R www-data: ' . $dir);
}
$check(is_file($base . '/vendor/autoload.php'), 'Dépendances Composer installées', 'composer install --no-dev --optimize-autoloader');
$check(is_file($base . '/public/assets/css/app.css'), 'Assets compilés', 'npm ci && npm run build', false);

if ($failures > 0) {
    echo "\nCorrigez les points ci-dessus puis relancez « php install.php ».\n\n";
    exit(1);
}

require $base . '/vendor/autoload.php';

echo "\nConfiguration\n\n";
$envPath = $base . '/config/env.php';
if (!is_file($envPath)) {
    copy($base . '/config/env.example.php', $envPath);
    @chmod($envPath, 0640);
    $check(true, 'config/env.php créé à partir de config/env.example.php');
} else {
    $check(true, 'config/env.php déjà présent (conservé)');
}

$env = new App\Core\Console\EnvFile($envPath);
$key = $env->get('APP_KEY');
if ($key === null || $key === '') {
    $env->set('APP_KEY', App\Services\Crypto::generateKey());
    $check(true, 'Clé maître APP_KEY générée — sauvegardez-la hors du serveur');
} else {
    $check(true, 'APP_KEY déjà définie (conservée)');
}

echo <<<TXT

Étapes suivantes :
  1. Renseignez les comptes MySQL dans config/env.php (DB_USERNAME / DB_ADMIN_USERNAME, voir README).
  2. php bin/console migrate
  3. php bin/console create-superadmin
  4. Racine web : public/ (recommandé) ; sinon le dossier du projet (index.php + .htaccess fournis).


TXT;
