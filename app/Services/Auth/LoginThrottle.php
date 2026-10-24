<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\LoginThrottleRepository;

/**
 * Anti-brute-force : après 5 échecs (par compte ET par IP), blocage progressif
 * de 1 min, doublé à chaque nouvel échec, plafonné à 1 h.
 */
final class LoginThrottle
{
    public const int MAX_FAILURES = 5;

    private const int BASE_LOCK = 60;

    private const int MAX_LOCK = 3600;

    /** Les échecs sont oubliés après 1 h sans nouvelle tentative ratée. */
    private const int RESET_AFTER = 3600;

    public function __construct(private readonly LoginThrottleRepository $repository)
    {
    }

    /**
     * Secondes de blocage restantes pour ce couple identifiant / IP (0 = autorisé).
     */
    public function lockedFor(string $identifier, string $ip): int
    {
        return max($this->repository->lockedFor(self::accountKey($identifier)), $this->repository->lockedFor(self::ipKey($ip)));
    }

    public function failure(string $identifier, string $ip): void
    {
        foreach ([self::accountKey($identifier), self::ipKey($ip)] as $key) {
            $failures = $this->repository->hit($key, self::RESET_AFTER);
            if ($failures >= self::MAX_FAILURES) {
                $this->repository->lock($key, self::lockDuration($failures));
            }
        }
    }

    public function success(string $identifier, string $ip): void
    {
        $this->repository->clear(self::accountKey($identifier));
        $this->repository->clear(self::ipKey($ip));
    }

    public static function lockDuration(int $failures): int
    {
        $exponent = max(0, $failures - self::MAX_FAILURES);

        return min(self::MAX_LOCK, self::BASE_LOCK * (2 ** min($exponent, 10)));
    }

    private static function accountKey(string $identifier): string
    {
        return hash('sha256', 'account:' . mb_strtolower(trim($identifier)));
    }

    private static function ipKey(string $ip): string
    {
        return hash('sha256', 'ip:' . $ip);
    }
}
