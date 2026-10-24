<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http\IpUtils;
use App\Core\Log\Logger;
use App\Repositories\GeoIpRepository;
use Throwable;

/**
 * Localisation approximative des adresses IP (pays, ville), mise en cache 30 jours.
 * Fournisseur configurable (GEOIP_PROVIDER) : « none » par défaut, « ip-api » (gratuit, sans clé,
 * requêtes groupées). Les IP privées ne quittent jamais le serveur.
 */
final class GeoIpService
{
    public function __construct(
        private readonly GeoIpRepository $cache,
        private readonly Config $config,
        private readonly Logger $logger,
    ) {
    }

    /**
     * @param list<string> $ips
     *
     * @return array<string, string> ip => libellé (« Dakar, Sénégal », « Réseau local »…)
     */
    public function locate(array $ips): array
    {
        $ips = array_values(array_unique(array_filter($ips, static fn (string $ip): bool => filter_var($ip, FILTER_VALIDATE_IP) !== false)));
        $labels = [];
        $public = [];
        foreach ($ips as $ip) {
            if (IpUtils::isPrivateOrReserved($ip)) {
                $labels[$ip] = 'Réseau local';
            } else {
                $public[] = $ip;
            }
        }
        $cached = $this->cache->many($public);
        $missing = array_values(array_diff($public, array_keys($cached)));
        if ($missing !== [] && $this->config->string('app.geoip_provider', 'none') === 'ip-api') {
            $cached += $this->fetchIpApi(array_slice($missing, 0, 100));
        }
        foreach ($public as $ip) {
            $entry = $cached[$ip] ?? null;
            $labels[$ip] = $entry === null || ($entry['country'] ?? null) === null
                ? 'Inconnue'
                : (($entry['city'] ?? '') !== '' ? $entry['city'] . ', ' : '') . $entry['country'];
        }

        return $labels;
    }

    /**
     * @param list<string> $ips
     *
     * @return array<string, array{country: string|null, country_code: string|null, city: string|null}>
     */
    private function fetchIpApi(array $ips): array
    {
        $payload = json_encode(array_map(static fn (string $ip): array => ['query' => $ip, 'fields' => 'status,country,countryCode,city,query', 'lang' => 'fr'], $ips), JSON_THROW_ON_ERROR);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 2.5,
            'ignore_errors' => true,
        ]]);
        try {
            $body = @file_get_contents('http://ip-api.com/batch', false, $context);
            $data = is_string($body) ? json_decode($body, true) : null;
        } catch (Throwable $e) {
            $this->logger->warning('Géolocalisation indisponible', ['exception' => $e]);

            return [];
        }
        $result = [];
        foreach (is_array($data) ? $data : [] as $item) {
            if (!is_array($item) || !is_string($item['query'] ?? null) || !in_array($item['query'], $ips, true)) {
                continue;
            }
            $ok = ($item['status'] ?? '') === 'success';
            $entry = [
                'country' => $ok && is_string($item['country'] ?? null) ? mb_substr($item['country'], 0, 80) : null,
                'country_code' => $ok && is_string($item['countryCode'] ?? null) ? substr($item['countryCode'], 0, 2) : null,
                'city' => $ok && is_string($item['city'] ?? null) ? mb_substr($item['city'], 0, 80) : null,
            ];
            $this->cache->put($item['query'], $entry['country'], $entry['country_code'], $entry['city']);
            $result[$item['query']] = $entry;
        }

        return $result;
    }
}
