<?php

declare(strict_types=1);

if (!defined('NOVABASE_ROOT')) {
    http_response_code(404);
    exit;
}

use App\Controllers\Api\HealthController;
use App\Controllers\Web\HomeController;
use App\Core\Http\Router;
use App\Middleware\Csrf;
use App\Middleware\StartSession;

return static function (Router $router): void {
    // Supervision (sans session, sans état).
    $router->get('/health', [HealthController::class, 'show'], name: 'health');

    // Espace web : sessions + CSRF.
    $router->group('/', [StartSession::class, Csrf::class], static function (Router $web): void {
        $web->get('/', [HomeController::class, 'index'], name: 'home');
    });
};
