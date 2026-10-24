<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Nonce Content-Security-Policy, unique par requête, partagé entre le middleware
 * SecurityHeaders et les vues.
 */
final class CspNonce
{
    private string $value;

    public function __construct()
    {
        $this->value = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    }

    public function value(): string
    {
        return $this->value;
    }
}
