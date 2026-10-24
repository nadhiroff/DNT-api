<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\HttpException;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Repositories\UserRepository;

/**
 * Exige une session authentifiée sur un compte actif ; place l'utilisateur dans l'attribut « user ».
 */
final class Authenticate implements MiddlewareInterface
{
    public const string INTENDED = '_url_intended';

    public function __construct(
        private readonly Session $session,
        private readonly UserRepository $users,
        private readonly Responder $responder,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $userId = $this->session->userId();
        $user = $userId !== null ? $this->users->findById($userId) : null;

        if ($user === null || $user['status'] !== 'active') {
            if ($user !== null) {
                // Compte suspendu pendant la session : déconnexion immédiate.
                $this->session->invalidate();
                $this->responder->flash('error', 'Votre compte est suspendu.');
            }
            if ($request->wantsJson()) {
                throw new HttpException(401, 'unauthenticated');
            }
            if ($request->isMethod('GET')) {
                $query = $request->server('QUERY_STRING');
                $this->session->set(self::INTENDED, $request->path() . (is_string($query) && $query !== '' ? '?' . $query : ''));
            }

            return $this->responder->redirect('/login');
        }

        $request->setAttribute('user', $user);

        return $next->handle($request);
    }
}
