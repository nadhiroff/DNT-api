<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Génération et hachage des codes de secours 2FA (format « XXXXX-XXXXX », ~50 bits d'entropie).
 * Ils sont stockés sous forme de HMAC (clé dérivée de la clé maître) pour résister au
 * cassage hors ligne en cas de fuite de la base.
 */
final class RecoveryCodeService
{
    private const string ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    public function __construct(private readonly Crypto $crypto)
    {
    }

    /** @return list<string> */
    public function generate(int $count = 10): array
    {
        $codes = [];
        while (count($codes) < $count) {
            $raw = '';
            for ($i = 0; $i < 10; ++$i) {
                $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $codes[substr($raw, 0, 5) . '-' . substr($raw, 5)] = true;
        }

        return array_keys($codes);
    }

    public function hash(string $code): string
    {
        return $this->crypto->hmac(self::normalize($code), 'recovery_code');
    }

    public static function normalize(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}
