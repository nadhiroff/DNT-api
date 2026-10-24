<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Core\Env;

return [
    // smtp | log (développement : messages enregistrés dans storage/mail, consultables sur /_dev/mail)
    'driver' => Env::string('MAIL_DRIVER', 'smtp'),
    // database (production : worker obligatoire) | sync (développement uniquement)
    'queue_driver' => Env::string('QUEUE_DRIVER', 'database'),
    'host' => Env::string('MAIL_HOST'),
    'port' => Env::int('MAIL_PORT', 587),
    'encryption' => Env::string('MAIL_ENCRYPTION', 'tls'),
    'username' => Env::string('MAIL_USERNAME'),
    'password' => Env::string('MAIL_PASSWORD'),
    // Vide = adresse de MAIL_USERNAME.
    'from_address' => Env::string('MAIL_FROM_ADDRESS'),
    'from_name' => Env::string('MAIL_FROM_NAME', 'NovaBase'),
];
