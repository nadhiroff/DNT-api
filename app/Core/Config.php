<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Configuration en lecture seule, accessible en notation pointée (« app.env »).
 */
final class Config
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private readonly array $items)
    {
    }

    public static function load(string $directory): self
    {
        $items = [];
        foreach (['app', 'database', 'security', 'mail'] as $name) {
            $file = $directory . '/' . $name . '.php';
            if (is_file($file)) {
                $items[$name] = require $file;
            }
        }

        return new self($items);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /** @return array<mixed> */
    public function array(string $key): array
    {
        $value = $this->get($key, []);

        return is_array($value) ? $value : [];
    }
}
