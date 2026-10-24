<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\HttpException;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Core\Security\Csrf as CsrfToken;

/**
 * Vérifie le jeton CSRF sur toute requête web modifiante (champ _csrf ou en-tête X-CSRF-Token),
 * plus un contrôle de l'en-tête Origin quand le navigateur l'envoie.
 */
final class Csrf implements MiddlewareInterface
{
    public function __construct(private readonly CsrfToken $csrf)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!$request->isSafeMethod()) {
            $origin = $request->header('origin');
            if ($origin !== null && $origin !== 'null') {
                $originHost = parse_url($origin, PHP_URL_HOST);
                $originPort = parse_url($origin, PHP_URL_PORT);
                $originAuthority = is_string($originHost) ? $originHost . (is_int($originPort) ? ':' . $originPort : '') : null;
                if ($originAuthority !== $request->header('host')) {
                    throw new HttpException(403, 'csrf_origin_mismatch', 'Origine de la requête refusée.');
                }
            }
            $token = $request->header(CsrfToken::HEADER);
            if ($token === null) {
                $field = $request->input(CsrfToken::FIELD);
                $token = is_string($field) ? $field : null;
            }
            if (!$this->csrf->validate($token)) {
                // 403 et non 419 : Apache convertit les statuts non standard en 500.
                throw new HttpException(403, 'csrf_token_mismatch', 'Votre session a expiré. Rechargez la page puis réessayez.');
            }
        }

        return $next->handle($request);
    }
}
