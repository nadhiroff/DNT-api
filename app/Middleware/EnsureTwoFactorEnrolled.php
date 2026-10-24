<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\Auth\TwoFactorService;

/**
 * À placer après Authenticate : le superadmin doit activer la 2FA avant tout accès.
 */
final class EnsureTwoFactorEnrolled implements MiddlewareInterface
{
    public function __construct(private readonly Responder $responder)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $user = $request->attribute('user');
        if (is_array($user) && is_string($user['role'] ?? null) && TwoFactorService::isRequiredFor($user['role']) && ($user['totp_enabled_at'] ?? null) === null) {
            $this->responder->flash('info', 'La double authentification est obligatoire pour votre rôle : activez-la pour continuer.');

            return $this->responder->redirect('/account/2fa');
        }

        return $next->handle($request);
    }
}
