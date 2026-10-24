<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Container;
use App\Core\Http\Request;
use App\Core\Log\Logger;
use App\Repositories\LoginEventRepository;
use Throwable;

/**
 * Trace chaque connexion, échec, blocage et déconnexion avec l'appareil utilisé.
 * Un échec d'écriture du journal ne bloque jamais la connexion.
 */
final class LoginEventService
{
    public function __construct(
        private readonly LoginEventRepository $events,
        private readonly Container $container,
        private readonly Logger $logger,
    ) {
    }

    public function record(string $event, ?int $userId, ?string $email, string $ip): void
    {
        $request = $this->container->hasInstance(Request::class) ? $this->container->resolve(Request::class) : null;
        $ua = $request instanceof Request ? $request->userAgent() : '';
        $parsed = UserAgentParser::parse($ua);
        try {
            $this->events->insert($userId, $email, $event, $ip, $ua, $parsed['browser'], $parsed['os'], $parsed['device']);
        } catch (Throwable $e) {
            $this->logger->error('Journal des connexions indisponible', ['exception' => $e]);
        }
    }
}
