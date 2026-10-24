<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Core\Env;

$env = Env::string('APP_ENV', 'production');

return [
    'name' => Env::string('APP_NAME', 'NovaBase'),
    'env' => $env,
    // Le mode debug est impossible en production, quelle que soit la valeur de APP_DEBUG.
    'debug' => $env !== 'production' && Env::bool('APP_DEBUG'),
    'url' => rtrim(Env::string('APP_URL', 'http://localhost'), '/'),
    // auto | true | false — voir config/env.example.php
    'url_rewrite' => Env::string('APP_URL_REWRITE', 'auto'),
    'timezone' => Env::string('APP_TIMEZONE', 'UTC'),
    'locale' => Env::string('APP_LOCALE', 'fr'),
    'key' => Env::string('APP_KEY'),
    'log_level' => Env::string('LOG_LEVEL', 'info'),
    'log_retention_days' => Env::int('LOG_RETENTION_DAYS', 14),
    'trusted_proxies' => Env::list('TRUSTED_PROXIES'),
    // Informations affichées dans les pages légales.
    'legal' => [
        'company' => Env::string('LEGAL_COMPANY', Env::string('APP_NAME', 'NovaBase')),
        'address' => Env::string('LEGAL_ADDRESS'),
        'email' => Env::string('LEGAL_EMAIL'),
        'host' => Env::string('LEGAL_HOST'),
    ],
];
