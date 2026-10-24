<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\RateLimitRepository;

/**
 * Limitation par fenêtre fixe (formulaires sensibles : inscription, mot de passe oublié…).
 */
final class RateLimiter
{
    public function __construct(private readonly RateLimitRepository $repository)
    {
    }

    /**
     * Compte une tentative ; retourne false si la limite est dépassée.
     */
    public function attempt(string $key, int $max, int $windowSeconds): bool
    {
        $windowStart = intdiv(time(), $windowSeconds) * $windowSeconds;

        return $this->repository->hit(hash('sha256', $key), $windowStart) <= $max;
    }

    public static function retryAfter(int $windowSeconds): int
    {
        return $windowSeconds - (time() % $windowSeconds);
    }
}
