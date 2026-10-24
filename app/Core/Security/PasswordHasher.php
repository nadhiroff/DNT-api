<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Hachage Argon2id des mots de passe, avec rehachage transparent si les paramètres évoluent.
 */
final class PasswordHasher
{
    /** @var array{memory_cost: int, time_cost: int, threads: int} */
    private array $options;

    public function __construct(int $memoryCost = 65536, int $timeCost = 4, int $threads = 1)
    {
        $this->options = ['memory_cost' => $memoryCost, 'time_cost' => $timeCost, 'threads' => $threads];
    }

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, $this->options);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, $this->options);
    }

    /**
     * Hash factice pour égaliser le temps de réponse quand le compte n'existe pas
     * (empêche l'énumération d'e-mails par mesure du temps).
     */
    public function dummyVerify(string $password): void
    {
        static $dummy = null;
        $dummy ??= $this->hash(bin2hex(random_bytes(16)));
        password_verify($password, $dummy);
    }
}
