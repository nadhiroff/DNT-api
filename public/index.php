<?php

declare(strict_types=1);

/*
 * Point d'entrée unique de NovaBase. Seul le dossier public/ est exposé au serveur web.
 */

$basePath = dirname(__DIR__);

// Serveur de développement intégré (php -S) : les fichiers statiques existants sont servis directement.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($file !== __DIR__ . '/' && is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

if (!is_file($basePath . '/vendor/autoload.php')) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Installation incomplète : exécutez « composer install --no-dev ».';
    exit;
}

require $basePath . '/vendor/autoload.php';

App\Core\Application::create($basePath)->run();
