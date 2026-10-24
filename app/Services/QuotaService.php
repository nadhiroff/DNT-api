<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PlanRepository;
use App\Repositories\UserLimitRepository;

/**
 * Limites effectives d'un utilisateur : superadmin illimité, sinon surcharge individuelle,
 * sinon limite du plan. null = illimité.
 */
final class QuotaService
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly UserLimitRepository $overrides,
    ) {
    }

    /**
     * @param array{id: int, role: string, plan_id: int|null} $user
     */
    public function limit(array $user, string $key): ?int
    {
        if ($user['role'] === 'superadmin') {
            return null;
        }
        $override = $this->overrides->get($user['id'], $key);
        if ($override['found']) {
            return $override['value'];
        }
        if ($user['plan_id'] === null) {
            $default = $this->plans->defaultPlanId();
            if ($default === null) {
                return 0;
            }

            return $this->plans->limits($default)[$key] ?? null;
        }

        return $this->plans->limits($user['plan_id'])[$key] ?? null;
    }

    /**
     * Pourcentage d'utilisation (null si illimité).
     */
    public static function percent(int $used, ?int $limit): ?float
    {
        if ($limit === null) {
            return null;
        }

        return $limit <= 0 ? 100.0 : min(100.0, round($used / $limit * 100, 1));
    }
}
