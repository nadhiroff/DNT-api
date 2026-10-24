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
