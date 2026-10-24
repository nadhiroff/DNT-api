<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;

/**
 * Pages réservées aux visiteurs (connexion, inscription…) : un utilisateur connecté va à la console.
 */
final class RedirectIfAuthenticated implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly Responder $responder,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if ($this->session->userId() !== null) {
            return $this->responder->redirect('/console');
        }

        return $next->handle($request);
    }
}
