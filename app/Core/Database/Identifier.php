<?php

declare(strict_types=1);

namespace App\Core\Database;

use InvalidArgumentException;

/**
 * Identifiants SQL (bases, tables, colonnes) : ils ne peuvent pas être liés comme des valeurs,
 * ils sont donc validés par liste blanche de caractères puis encadrés de backticks.
 */
final class Identifier
{
    /**
     * Valide un nom de base ou d'objet interne à la plateforme et le retourne entre backticks.
     */
    public static function quote(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,63}$/', $name) !== 1) {
            throw new InvalidArgumentException('Identifiant SQL invalide.');
        }

        return '`' . $name . '`';
    }
}
