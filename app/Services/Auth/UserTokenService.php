<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\UserTokenRepository;

/**
 * Jetons de vérification d'e-mail et de réinitialisation : 256 bits aléatoires, hachés en base,
 * usage unique, expiration 1 h.
 */
final class UserTokenService
{
    public const string EMAIL_VERIFICATION = 'email_verification';

    public const string PASSWORD_RESET = 'password_reset';

    private const int TTL = 3600;

    public function __construct(private readonly UserTokenRepository $repository)
    {
    }

    public function issue(int $userId, string $type): string
    {
        $token = bin2hex(random_bytes(32));
        $this->repository->replace($userId, $type, hash('sha256', $token), self::TTL);

        return $token;
    }

    public function consume(string $type, string $token): ?int
    {
        return self::isWellFormed($token) ? $this->repository->consume($type, hash('sha256', $token)) : null;
    }

    public function isValid(string $type, string $token): bool
    {
        return $this->peek($type, $token) !== null;
    }

    /**
     * Titulaire d'un jeton encore valide, sans le consommer.
     */
    public function peek(string $type, string $token): ?int
    {
        return self::isWellFormed($token) ? $this->repository->findUserId($type, hash('sha256', $token)) : null;
    }

    private static function isWellFormed(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $token) === 1;
    }
}
