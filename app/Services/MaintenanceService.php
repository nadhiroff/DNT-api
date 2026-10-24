<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ApiKeyRepository;
use App\Repositories\LoginThrottleRepository;
use App\Repositories\RateLimitRepository;
use App\Repositories\UserTokenRepository;

/**
 * Maintenance périodique (worker ou cron).
 */
final class MaintenanceService
{
    public function __construct(
        private readonly UsageService $usage,
        private readonly ApiKeyRepository $keys,
        private readonly UserTokenRepository $tokens,
        private readonly LoginThrottleRepository $throttles,
        private readonly RateLimitRepository $rateLimits,
        private readonly ServerMetrics $metrics,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function run(): array
    {
        $this->metrics->collect();

        return [
            'Projets mesurés (quotas)' => $this->usage->recalculate(),
            'Clés révoquées purgées (> 30 j)' => $this->keys->purgeRevoked(),
            'Jetons expirés purgés' => $this->tokens->purgeExpired(),
            'Compteurs anti-brute-force purgés' => $this->throttles->purge(86400),
            'Compteurs de débit purgés' => $this->rateLimits->purgeBefore(time() - 7200),
        ];
    }
}
