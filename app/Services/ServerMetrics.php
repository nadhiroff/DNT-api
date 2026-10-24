<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Métriques du serveur qui héberge le backend.
 *
 * Linux : /proc et /sys. Quand le serveur web n'y a pas accès (systemd ProtectProc/ProcSubset,
 * open_basedir…), les valeurs manquantes sont lues dans un instantané écrit par la ligne de
 * commande (« php bin/console metrics:collect » en cron, ou le worker), avec son âge.
 *
 * @phpstan-type Snapshot array{cpu_percent: float|null, load: list<float>|null, cores: int, ram_total: int|null, ram_used: int|null, ram_percent: float|null, disk_total: int|null, disk_used: int|null, disk_percent: float|null, uptime: int|null, php_version: string, os: string, hostname: string, time: string, source: string, cache_age: int|null}
 */
final class ServerMetrics
{
    /** Au-delà de 15 minutes, l'instantané n'est plus utilisé. */
    private const int MAX_CACHE_AGE = 900;

    public function __construct(
        private readonly string $diskPath,
        private readonly string $cacheFile,
    ) {
    }

    /**
     * @return Snapshot
     */
    public function snapshot(): array
    {
        $cache = $this->readCache();
        $cores = $this->cores() ?? ($cache['cores'] ?? 1);
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;
        $load = is_array($load) ? array_map(static fn (float $l): float => round($l, 2), $load) : null;
        $cpu = $load !== null ? min(100.0, round($load[0] / max(1, $cores) * 100, 1)) : null;

        [$ramTotal, $ramUsed] = $this->memory();
        $source = 'live';
        $uptime = $this->uptime();
        if (($ramTotal === null || $uptime === null) && $cache !== null) {
            $ramTotal ??= $cache['ram_total'];
            $ramUsed ??= $cache['ram_used'];
            $uptime ??= $cache['uptime'] !== null ? $cache['uptime'] + $cache['age'] : null;
            $source = 'cache';
        }

        $diskTotal = @disk_total_space($this->diskPath);
        $diskFree = @disk_free_space($this->diskPath);
        $diskTotal = is_float($diskTotal) ? (int) $diskTotal : null;
        $diskUsed = $diskTotal !== null && is_float($diskFree) ? $diskTotal - (int) $diskFree : null;

        return [
            'cpu_percent' => $cpu,
            'load' => $load,
            'cores' => $cores,
            'ram_total' => $ramTotal,
            'ram_used' => $ramUsed,
            'ram_percent' => $ramTotal !== null && $ramUsed !== null && $ramTotal > 0 ? round($ramUsed / $ramTotal * 100, 1) : null,
            'disk_total' => $diskTotal,
            'disk_used' => $diskUsed,
            'disk_percent' => $diskTotal !== null && $diskUsed !== null && $diskTotal > 0 ? round($diskUsed / $diskTotal * 100, 1) : null,
            'uptime' => $uptime,
            'php_version' => PHP_VERSION,
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'hostname' => (string) gethostname(),
            'time' => gmdate('c'),
            'source' => $source,
            'cache_age' => $source === 'cache' && $cache !== null ? $cache['age'] : null,
        ];
    }

    /**
     * Écrit un instantané complet (à lancer en ligne de commande, qui a accès à /proc).
     */
    public function collect(): bool
    {
        [$ramTotal, $ramUsed] = $this->memory();
        $data = ['cores' => $this->cores() ?? 1, 'ram_total' => $ramTotal, 'ram_used' => $ramUsed, 'uptime' => $this->uptime(), 'collected_at' => time()];
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $tmp = $this->cacheFile . '.' . bin2hex(random_bytes(4));

        return file_put_contents($tmp, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) !== false && rename($tmp, $this->cacheFile);
    }

    /**
     * @return array{cores: int, ram_total: int|null, ram_used: int|null, uptime: int|null, age: int}|null
     */
    private function readCache(): ?array
    {
        $raw = is_file($this->cacheFile) ? @file_get_contents($this->cacheFile) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !is_int($data['collected_at'] ?? null)) {
            return null;
        }
        $age = max(0, time() - $data['collected_at']);
        if ($age > self::MAX_CACHE_AGE) {
            return null;
        }

        return [
            'cores' => is_int($data['cores'] ?? null) ? $data['cores'] : 1,
            'ram_total' => is_int($data['ram_total'] ?? null) ? $data['ram_total'] : null,
            'ram_used' => is_int($data['ram_used'] ?? null) ? $data['ram_used'] : null,
            'uptime' => is_int($data['uptime'] ?? null) ? $data['uptime'] : null,
            'age' => $age,
        ];
    }

    private function cores(): ?int
    {
        $info = @file_get_contents('/proc/cpuinfo');
        if (is_string($info) && $info !== '') {
            return max(1, preg_match_all('/^processor\s*:/m', $info));
        }
        // /sys reste lisible quand /proc est restreint : « 0-3 » ou « 0,2-5 ».
        $online = @file_get_contents('/sys/devices/system/cpu/online');
        if (is_string($online) && preg_match('/^[\d,\-\s]+$/', $online) === 1) {
            $count = 0;
            foreach (explode(',', trim($online)) as $range) {
                $bounds = array_map('intval', explode('-', $range));
                $count += count($bounds) === 2 ? $bounds[1] - $bounds[0] + 1 : 1;
            }

            return max(1, $count);
        }

        return null;
    }

    private function uptime(): ?int
    {
        $raw = @file_get_contents('/proc/uptime');

        return is_string($raw) && $raw !== '' ? (int) (float) $raw : null;
    }

    /**
     * @return array{0: int|null, 1: int|null} total, utilisé (octets)
     */
    private function memory(): array
    {
        $info = @file_get_contents('/proc/meminfo');
        if (!is_string($info) || preg_match('/MemTotal:\s+(\d+)/', $info, $total) !== 1 || preg_match('/MemAvailable:\s+(\d+)/', $info, $available) !== 1) {
            return [null, null];
        }
        $totalBytes = (int) $total[1] * 1024;

        return [$totalBytes, $totalBytes - (int) $available[1] * 1024];
    }
}
