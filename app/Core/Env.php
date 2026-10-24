<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Accès typé à la configuration d'environnement : variables réelles du processus en priorité,
 * puis le tableau de config/env.php.
 */
final class Env
{
    /** @var array<string, string> */
    private static array $values = [];

    /**
     * Charge config/env.php (tableau clé => valeur scalaire). Fichier absent = aucune valeur.
     */
    public static function load(string $file): void
    {
        if (!is_file($file)) {
            self::$values = [];

            return;
        }
        $values = require $file;
        if (!is_array($values)) {
            throw new \RuntimeException('config/env.php doit retourner un tableau.');
        }
        self::$values = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                self::$values[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? self::$values[$key] ?? null;
        }
        if (!is_scalar($value)) {
            return $default;
        }

        return (string) $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        return self::get($key) ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * Liste séparée par des virgules (« a, b ,c » → ['a', 'b', 'c']).
     *
     * @return list<string>
     */
    public static function list(string $key): array
    {
        $value = self::get($key, '') ?? '';

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $v): bool => $v !== ''));
    }
}
