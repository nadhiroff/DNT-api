<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Core\Env;

$common = [
    'host' => Env::string('DB_HOST', '127.0.0.1'),
    'port' => Env::int('DB_PORT', 3306),
    'socket' => Env::get('DB_SOCKET'),
];

return [
    'platform_database' => Env::string('DB_DATABASE', 'novabase'),

    // Compte applicatif : DML uniquement sur la base plateforme.
    'platform' => $common + [
        'database' => Env::string('DB_DATABASE', 'novabase'),
        'username' => Env::string('DB_USERNAME', 'novabase_app'),
        'password' => Env::string('DB_PASSWORD'),
    ],

    // Compte d'administration : migrations et provisioning uniquement.
    'admin' => $common + [
        'database' => Env::string('DB_DATABASE', 'novabase'),
        'username' => Env::string('DB_ADMIN_USERNAME', 'novabase_admin'),
        'password' => Env::string('DB_ADMIN_PASSWORD'),
    ],

    'project_user_host' => Env::string('DB_PROJECT_USER_HOST', 'localhost'),
];
