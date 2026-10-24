<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\HttpException;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;

/**
 * À placer après Authenticate : réserve la route au superadmin (404 pour les autres, pour ne pas
 * révéler l'existence de l'espace d'administration).
 */
final class RequireSuperadmin implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $request->attribute('user');
        if (!is_array($user) || ($user['role'] ?? null) !== 'superadmin') {
            throw HttpException::notFound();
        }

        return $next->handle($request);
    }
}
