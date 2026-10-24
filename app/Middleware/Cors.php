<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Repositories\ProjectRepository;

/**
 * CORS de l'API des projets : origines autorisées par projet (toutes si la liste est vide,
 * l'authentification se faisant par en-tête et non par cookie). Répond aux pré-vérifications OPTIONS.
 */
final class Cors implements MiddlewareInterface
{
    public function __construct(private readonly ProjectRepository $projects)
    {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        if (!str_starts_with($request->path(), '/api/v1/')) {
            return $next->handle($request);
        }
        $origin = $request->header('origin');
        $allowed = $origin !== null ? $this->allowedOrigin($request->path(), $origin) : null;

        if ($request->isMethod('OPTIONS')) {
            $response = new Response('', 204);
        } else {
            $response = $next->handle($request);
        }
        if ($allowed !== null) {
            $response->setHeader('Access-Control-Allow-Origin', $allowed);
            $response->setHeader('Vary', 'Origin');
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'apikey, authorization, content-type, x-client-info');
            $response->setHeader('Access-Control-Expose-Headers', 'X-Total-Count, X-RateLimit-Limit, X-RateLimit-Remaining, Retry-After, Content-Range');
            $response->setHeader('Access-Control-Max-Age', '600');
        }

        return $response;
    }

    private function allowedOrigin(string $path, string $origin): ?string
    {
        if (preg_match('#^/api/v1/([a-z0-9]{20})(/|$)#', $path, $m) !== 1 || preg_match('#^https?://[^/\s]+$#i', $origin) !== 1) {
            return null;
        }
        $origins = $this->projects->corsOrigins($m[1]);
        if ($origins === null) {
            return null;
        }

        return $origins === [] || in_array(rtrim($origin, '/'), $origins, true) ? $origin : null;
    }
}
