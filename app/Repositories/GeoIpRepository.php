<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

final class GeoIpRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<string> $ips
     *
     * @return array<string, array{country: string|null, country_code: string|null, city: string|null}>
     */
    public function many(array $ips): array
    {
        if ($ips === []) {
            return [];
        }
        $result = [];
        foreach ($this->db->select('SELECT ip, country, country_code, city FROM geoip_cache WHERE ip IN (' . Database::placeholders($ips) . ') AND resolved_at > UTC_TIMESTAMP() - INTERVAL 30 DAY', $ips) as $row) {
            $result[(string) $row['ip']] = [
                'country' => $row['country'] !== null ? (string) $row['country'] : null,
                'country_code' => $row['country_code'] !== null ? (string) $row['country_code'] : null,
                'city' => $row['city'] !== null ? (string) $row['city'] : null,
            ];
        }

        return $result;
    }

    public function put(string $ip, ?string $country, ?string $countryCode, ?string $city): void
    {
        $this->db->execute(
            'INSERT INTO geoip_cache (ip, country, country_code, city, resolved_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE country = VALUES(country), country_code = VALUES(country_code), city = VALUES(city), resolved_at = VALUES(resolved_at)',
            [$ip, $country, $countryCode, $city],
        );
    }
}
