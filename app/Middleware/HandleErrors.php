<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\ErrorHandler;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use Throwable;

/**
 * Convertit toute exception levée plus bas dans la chaîne en réponse HTTP propre.
 */
final class HandleErrors implements MiddlewareInterface
{
    public function __construct(private readonly ErrorHandler $handler)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        try {
            return $next->handle($request);
        } catch (Throwable $e) {
            return $this->handler->render($request, $e);
        }
    }
}
