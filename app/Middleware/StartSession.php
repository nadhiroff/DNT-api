<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Core\ErrorHandler;
use App\Core\Session\Session;
use App\Core\Validation\ValidationException;

/**
 * Démarre la session pour les routes web (jamais pour l'API, qui reste sans état).
 */
final class StartSession implements MiddlewareInterface
{
    public function __construct(
        private readonly Session $session,
        private readonly ErrorHandler $errors,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $this->session->start($request);
        try {
            return $next->handle($request);
        } catch (ValidationException $e) {
            // Converti ici, session encore ouverte, pour que les erreurs et la saisie soient conservées en flash.
            return $this->errors->render($request, $e);
        } finally {
            $this->session->save();
        }
    }
}
