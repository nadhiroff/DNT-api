<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Core\Session\Session;

/**
 * Démarre la session pour les routes web (jamais pour l'API, qui reste sans état).
 */
final class StartSession implements MiddlewareInterface
{
    public function __construct(private readonly Session $session)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $this->session->start($request);
        try {
            return $next->handle($request);
        } finally {
            $this->session->save();
        }
    }
}
