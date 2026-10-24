<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Responder;
use App\Core\Http\Response;

/**
 * À placer après Authenticate : la console exige une adresse e-mail confirmée.
 */
final class EnsureEmailVerified implements MiddlewareInterface
{
    public function __construct(private readonly Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $request->attribute('user');
        if (is_array($user) && ($user['email_verified_at'] ?? null) === null) {
            return $this->responder->redirect('/email/verify');
        }

        return $next->handle($request);
    }
}
