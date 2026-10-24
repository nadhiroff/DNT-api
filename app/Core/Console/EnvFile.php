<?php

declare(strict_types=1);

namespace App\Core\Console;

use RuntimeException;

/**
 * Lecture/écriture ciblée d'une valeur dans config/env.php (install, key:generate), sans exécuter
 * le fichier et en préservant ses commentaires.
 */
final class EnvFile
{
    private const string VALUE = "'(?:[^'\\\\]|\\\\.)*'";

    public function __construct(private readonly string $path)
    {
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    public function get(string $key): ?string
    {
        if (!$this->exists()) {
            return null;
        }
        if (preg_match($this->pattern($key), (string) file_get_contents($this->path), $m) !== 1) {
            return null;
        }

        // Seules \' et \\ sont des séquences d'échappement dans une chaîne PHP entre apostrophes.
        return (string) preg_replace('/\\\\([\\\\\'])/', '$1', substr($m[2], 1, -1));
    }

    public function set(string $key, string $value): void
    {
        if (!$this->exists()) {
            throw new RuntimeException('Fichier introuvable : ' . $this->path);
        }
        $content = (string) file_get_contents($this->path);
        $replacement = var_export($value, true);
        if (preg_match($this->pattern($key), $content) === 1) {
            $content = (string) preg_replace_callback(
                $this->pattern($key),
                static fn (array $m): string => $m[1] . "'" . $key . "' => " . $replacement . ',',
                $content,
                1,
            );
        } else {
            $content = (string) preg_replace('/\n\];\s*$/', "\n    '" . $key . "' => " . $replacement . ",\n];\n", $content, 1, $count);
            if ($count !== 1) {
                throw new RuntimeException('Format de ' . basename($this->path) . ' non reconnu.');
            }
        }
        if (file_put_contents($this->path, $content, LOCK_EX) === false) {
            throw new RuntimeException('Impossible d\'écrire ' . $this->path);
        }
        @chmod($this->path, 0640);
    }

    private function pattern(string $key): string
    {
        if (preg_match('/^[A-Z][A-Z0-9_]*$/', $key) !== 1) {
            throw new RuntimeException('Nom de variable invalide.');
        }

        return "/^([ \\t]*)'" . $key . "'\\s*=>\\s*(" . self::VALUE . '),/m';
    }
}
