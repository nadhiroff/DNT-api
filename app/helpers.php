<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Échappe une valeur pour un contexte HTML (contenu ou attribut entre guillemets).
     */
    function e(mixed $value): string
    {
        if ($value === null || is_bool($value)) {
            return '';
        }
        if (is_array($value) || (is_object($value) && !$value instanceof Stringable)) {
            throw new InvalidArgumentException('e() attend une valeur scalaire.');
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('time_ago')) {
    /**
     * Durée relative en français (« il y a 5 min »).
     */
    function time_ago(int $timestamp, ?int $now = null): string
    {
        $diff = max(0, ($now ?? time()) - $timestamp);

        return match (true) {
            $diff < 60 => 'à l\'instant',
            $diff < 3600 => 'il y a ' . intdiv($diff, 60) . ' min',
            $diff < 86400 => 'il y a ' . intdiv($diff, 3600) . ' h',
            default => 'il y a ' . intdiv($diff, 86400) . ' j',
        };
    }
}

if (!function_exists('user_agent_label')) {
    /**
     * Libellé lisible d'un User-Agent (« Chrome sur Windows »), sans dépendance externe.
     */
    function user_agent_label(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            str_contains($userAgent, 'curl/') => 'curl',
            default => 'Navigateur inconnu',
        };
        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS X') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        return $os !== null ? $browser . ' sur ' . $os : $browser;
    }
}
