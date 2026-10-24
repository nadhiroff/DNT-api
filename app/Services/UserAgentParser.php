<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Analyse simple d'un User-Agent : navigateur, système, type d'appareil (sans dépendance externe).
 */
final class UserAgentParser
{
    /**
     * @return array{browser: string, os: string, device: string}
     */
    public static function parse(string $ua): array
    {
        $browser = match (true) {
            $ua === '' => 'Inconnu',
            (bool) preg_match('/bot|crawl|spider|slurp|curl|wget|python|httpclient|okhttp/i', $ua) => self::botName($ua),
            str_contains($ua, 'Edg/') => 'Edge ' . self::version($ua, 'Edg/'),
            str_contains($ua, 'OPR/') => 'Opera ' . self::version($ua, 'OPR/'),
            str_contains($ua, 'SamsungBrowser/') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox ' . self::version($ua, 'Firefox/'),
            str_contains($ua, 'Chrome/') => 'Chrome ' . self::version($ua, 'Chrome/'),
            str_contains($ua, 'Safari/') => 'Safari ' . self::version($ua, 'Version/'),
            default => 'Autre',
        };
        $os = match (true) {
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') => 'iOS (iPhone)',
            str_contains($ua, 'iPad') => 'iPadOS',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Inconnu',
        };
        $device = match (true) {
            $ua === '' => 'unknown',
            (bool) preg_match('/bot|crawl|spider|slurp|curl|wget|python|httpclient|okhttp/i', $ua) => 'bot',
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile')) || str_contains($ua, 'Tablet') => 'tablet',
            (bool) preg_match('/Mobile|iPhone|Android/i', $ua) => 'mobile',
            default => 'desktop',
        };

        return ['browser' => mb_substr($browser, 0, 40), 'os' => $os, 'device' => $device];
    }

    private static function version(string $ua, string $token): string
    {
        return preg_match('#' . preg_quote($token, '#') . '(\d+)#', $ua, $m) === 1 ? $m[1] : '';
    }

    private static function botName(string $ua): string
    {
        return preg_match('/(curl|wget|python|okhttp|[A-Za-z]+bot)/i', $ua, $m) === 1 ? ucfirst(strtolower($m[1])) : 'Robot';
    }
}
