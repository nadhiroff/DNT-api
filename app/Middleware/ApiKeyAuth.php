<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\HttpException;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\RequestHandlerInterface;
use App\Core\Http\Response;
use App\Repositories\ApiUsageRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\UserRepository;
use App\Services\Projects\ApiKeyService;
use App\Services\QuotaService;
use App\Services\RateLimiter;

/**
 * Authentifie les appels API par l'en-tête « apikey », applique la limite de débit par clé
 * et comptabilise chaque requête (succès ou erreur) dans api_usage_hourly.
 */
final class ApiKeyAuth implements MiddlewareInterface
{
    private const int DEFAULT_RATE = 60;

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ApiKeyService $keys,
        private readonly ApiUsageRepository $usage,
        private readonly RateLimiter $limiter,
        private readonly UserRepository $users,
        private readonly QuotaService $quotas,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $next): Response
    {
        $ref = (string) $request->route('ref');
        $project = preg_match('/^[a-z0-9]{20}$/', $ref) === 1 ? $this->projects->findByRef($ref) : null;
        if ($project === null || $project['status'] === 'failed') {
            throw new HttpException(404, 'project_not_found', 'Projet introuvable.');
        }
        if ($project['status'] !== 'active') {
            throw new HttpException(403, 'project_suspended', 'Ce projet est suspendu.');
        }

        $status = 500;
        try {
            $key = $this->keys->authenticate((string) $request->header('apikey', ''), $project['id']);
            if ($key === null) {
                $status = 401;

                throw new HttpException(401, 'invalid_api_key', 'Clé API absente, invalide ou révoquée.');
            }

            $owner = $this->users->findById($project['owner_id']);
            $limit = $owner !== null ? ($this->quotas->limit($owner, 'rate_limit_per_minute') ?? 100000) : self::DEFAULT_RATE;
            $remaining = $limit;
            if (!$this->limiter->attempt('api:' . $key['id'], $limit, 60)) {
                $status = 429;

                throw new HttpException(429, 'rate_limited', 'Limite de requêtes atteinte, réessayez dans un instant.', [
                    'Retry-After' => (string) RateLimiter::retryAfter(60),
                    'X-RateLimit-Limit' => (string) $limit,
                    'X-RateLimit-Remaining' => '0',
                ]);
            }

            $request->setAttribute('project', $project);
            $request->setAttribute('api_key', $key);
            $response = $next->handle($request);
            $status = $response->status();
            $response->setHeader('X-RateLimit-Limit', (string) $limit);
            $response->setHeader('X-RateLimit-Remaining', (string) max(0, $remaining - 1));

            return $response;
        } catch (HttpException $e) {
            $status = $e->status();

            throw $e;
        } finally {
            $this->usage->hit($project['id'], $status >= 400);
        }
    }
}
