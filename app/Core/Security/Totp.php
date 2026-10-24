<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * TOTP (RFC 6238) : HMAC-SHA1, 6 chiffres, pas de 30 s — compatible Google Authenticator,
 * Authy, 1Password, Aegis…
 */
class Totp
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function __construct(
        private readonly int $digits = 6,
        private readonly int $period = 30,
    ) {
    }

    /**
     * @param int<10, 64> $bytes 20 octets (160 bits) recommandés par la RFC 4226
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * Nouveau secret (méthode d'instance pour permettre la substitution dans les tests).
     */
    public function newSecret(): string
    {
        return self::generateSecret();
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($account)
            . '?' . http_build_query([
                'secret' => $secret,
                'issuer' => $issuer,
                'algorithm' => 'SHA1',
                'digits' => $this->digits,
                'period' => $this->period,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function code(string $secret, ?int $timestamp = null): string
    {
        return $this->codeAt($secret, $this->step($timestamp ?? time()));
    }

    /**
     * Vérifie un code dans une fenêtre de ±$window pas. Retourne le pas validé (à mémoriser pour
     * empêcher le rejeu : tout pas ≤ $lastUsedStep est refusé), ou null.
     */
    public function verify(string $secret, string $code, ?int $lastUsedStep = null, int $window = 1, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{' . $this->digits . '}$/', $code) !== 1) {
            return null;
        }
        $current = $this->step($timestamp ?? time());
        $matched = null;
        for ($offset = -$window; $offset <= $window; ++$offset) {
            $step = $current + $offset;
            // Pas de sortie anticipée : temps de calcul constant quelle que soit la position du code.
            if (hash_equals($this->codeAt($secret, $step), $code) && ($lastUsedStep === null || $step > $lastUsedStep)) {
                $matched = $step;
            }
        }

        return $matched;
    }

    private function step(int $timestamp): int
    {
        return intdiv($timestamp, $this->period);
    }

    private function codeAt(string $secret, int $step): string
    {
        $key = self::base32Decode($secret);
        $binaryStep = pack('N2', ($step >> 32) & 0xFFFFFFFF, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $binaryStep, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** $this->digits)), $this->digits, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $output = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($data) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= self::ALPHABET[($buffer >> $bits) & 0x1F];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bits)) & 0x1F];
        }

        return $output;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(rtrim(str_replace(' ', '', $secret), '='));
        $output = '';
        $buffer = 0;
        $bits = 0;
        foreach (str_split($secret) as $char) {
            $position = strpos(self::ALPHABET, $char);
            if ($position === false) {
                throw new \InvalidArgumentException('Secret TOTP invalide.');
            }
            $buffer = ($buffer << 5) | $position;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
            $buffer &= (1 << $bits) - 1;
        }

        return $output;
    }
}
