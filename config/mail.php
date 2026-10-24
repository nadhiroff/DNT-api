<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Core\Env;

return [
    'host' => Env::string('MAIL_HOST'),
    'port' => Env::int('MAIL_PORT', 587),
    'encryption' => Env::string('MAIL_ENCRYPTION', 'tls'),
    'username' => Env::string('MAIL_USERNAME'),
    'password' => Env::string('MAIL_PASSWORD'),
    'from_address' => Env::string('MAIL_FROM_ADDRESS', 'no-reply@localhost'),
    'from_name' => Env::string('MAIL_FROM_NAME', 'NovaBase'),
];
