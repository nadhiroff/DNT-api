<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Core\Env;

$secureCookie = Env::get('SESSION_SECURE_COOKIE');

return [
    'session' => [
        'driver' => Env::string('SESSION_DRIVER', 'database'),
        'cookie' => Env::string('SESSION_COOKIE', 'nb_session'),
        'idle_lifetime' => Env::int('SESSION_IDLE_LIFETIME', 7200),
        // null = automatique selon HTTPS
        'secure' => $secureCookie === null || $secureCookie === '' ? null : Env::bool('SESSION_SECURE_COOKIE'),
    ],
    'hsts_max_age' => Env::int('HSTS_MAX_AGE', 31536000),
    'argon' => [
        'memory_cost' => Env::int('ARGON_MEMORY_COST', 65536),
        'time_cost' => Env::int('ARGON_TIME_COST', 4),
        'threads' => Env::int('ARGON_THREADS', 1),
    ],
    'totp_issuer' => Env::string('TOTP_ISSUER', 'NovaBase'),
    'max_body' => [
        'data' => 1_048_576,      // 1 Mo
        'email' => 10_485_760,    // 10 Mo
    ],
    'cache_driver' => Env::string('CACHE_DRIVER', 'database'),
];
