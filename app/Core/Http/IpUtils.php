<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Utilitaires IPv4/IPv6 : appartenance à un CIDR et détection des plages privées / réservées (SSRF).
 */
final class IpUtils
{
    /** @param list<string> $cidrs */
    public static function inAny(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::inCidr($ip, $cidr)) {
                return true;
            }
        }

        return false;
    }

    public static function inCidr(string $ip, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        $subnet = $parts[0];
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $maxBits = strlen($ipBin) * 8;
        $bits = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : $maxBits;
        if ($bits > $maxBits) {
            return false;
        }
        $fullBytes = intdiv($bits, 8);
        if (substr($ipBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }
        $remaining = $bits % 8;
        if ($remaining === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $remaining)) & 0xFF;

        return (ord($ipBin[$fullBytes]) & $mask) === (ord($subnetBin[$fullBytes]) & $mask);
    }

    /**
     * Vrai si l'IP est privée, de bouclage, lien-local, réservée ou invalide.
     */
    public static function isPrivateOrReserved(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return true;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Plages non couvertes par les filtres natifs selon les versions de PHP.
        return self::inAny($ip, [
            '0.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '192.0.0.0/24',
            // Plages de documentation (RFC 5737), jamais routées sur Internet.
            '192.0.2.0/24', '198.51.100.0/24', '203.0.113.0/24', '2001:db8::/32',
            '198.18.0.0/15', '224.0.0.0/4', '240.0.0.0/4',
            '::/128', '::1/128', '::ffff:0:0/96', 'fc00::/7', 'fe80::/10', 'ff00::/8', '64:ff9b::/96',
        ]);
    }
}
