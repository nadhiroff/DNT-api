<?php

declare(strict_types=1);

namespace App\Services\Auth;

/**
 * Résultat d'une tentative de connexion.
 */
final class LoginResult
{
    public const string OK = 'ok';

    public const string INVALID = 'invalid';

    public const string LOCKED = 'locked';

    public const string TWO_FACTOR = 'two_factor';

    public const string SUSPENDED = 'suspended';

    public const string PENDING = 'pending';

    private function __construct(
        public readonly string $status,
        public readonly ?int $userId = null,
        public readonly int $retryAfter = 0,
    ) {
    }

    public static function ok(int $userId): self
    {
        return new self(self::OK, $userId);
    }

    public static function twoFactor(int $userId): self
    {
        return new self(self::TWO_FACTOR, $userId);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID);
    }

    public static function locked(int $retryAfter): self
    {
        return new self(self::LOCKED, null, $retryAfter);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function suspended(): self
    {
        return new self(self::SUSPENDED);
    }
}
