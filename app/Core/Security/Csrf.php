<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Session\Session;

/**
 * Jeton anti-CSRF lié à la session (synchronizer token), comparé en temps constant.
 */
final class Csrf
{
    public const string FIELD = '_csrf';

    public const string HEADER = 'x-csrf-token';

    private const string SESSION_KEY = '_csrf_token';

    public function __construct(private readonly Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::SESSION_KEY);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function validate(?string $candidate): bool
    {
        $token = $this->session->get(self::SESSION_KEY);

        return is_string($token) && is_string($candidate) && $candidate !== '' && hash_equals($token, $candidate);
    }

    /**
     * À appeler après connexion/déconnexion pour éviter la fixation du jeton.
     */
    public function rotate(): string
    {
        $this->session->remove(self::SESSION_KEY);

        return $this->token();
    }
}
