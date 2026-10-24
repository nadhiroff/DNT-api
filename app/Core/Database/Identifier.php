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
    /** Mots réservés MySQL les plus courants (les noms de l'utilisateur ne peuvent pas les reprendre). */
    private const array RESERVED = [
        'add', 'all', 'alter', 'analyze', 'and', 'as', 'asc', 'before', 'between', 'by', 'call', 'cascade', 'case', 'change', 'check',
        'column', 'condition', 'constraint', 'continue', 'convert', 'create', 'cross', 'current_date', 'current_time', 'current_timestamp',
        'current_user', 'cursor', 'database', 'databases', 'default', 'delete', 'desc', 'describe', 'distinct', 'div', 'drop', 'dual',
        'each', 'else', 'elseif', 'exists', 'exit', 'explain', 'false', 'fetch', 'for', 'force', 'foreign', 'from', 'fulltext', 'function',
        'grant', 'group', 'groups', 'having', 'if', 'ignore', 'in', 'index', 'inner', 'insert', 'interval', 'into', 'is', 'join', 'key',
        'keys', 'kill', 'lead', 'leave', 'left', 'like', 'limit', 'lines', 'load', 'lock', 'loop', 'match', 'mod', 'natural', 'not', 'null',
        'of', 'on', 'option', 'or', 'order', 'out', 'outer', 'over', 'partition', 'primary', 'procedure', 'range', 'rank', 'read', 'references',
        'regexp', 'rename', 'repeat', 'replace', 'require', 'restrict', 'return', 'revoke', 'right', 'rlike', 'row', 'rows', 'schema',
        'schemas', 'select', 'set', 'show', 'signal', 'system', 'table', 'then', 'to', 'trigger', 'true', 'union', 'unique', 'unlock',
        'unsigned', 'update', 'usage', 'use', 'using', 'values', 'when', 'where', 'while', 'window', 'with', 'write', 'xor',
    ];

    /** Préfixes réservés à la plateforme. */
    private const array RESERVED_PREFIXES = ['nb_', 'auth_', 'mysql_', 'sys_'];

    /**
     * Valide un nom de table ou de colonne saisi par un utilisateur (liste blanche stricte).
     * Retourne un message d'erreur, ou null si le nom est acceptable.
     */
    public static function problem(string $name): ?string
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $name) !== 1) {
            return 'Utilisez des lettres minuscules, chiffres et « _ », en commençant par une lettre (63 caractères max).';
        }
        if (in_array($name, self::RESERVED, true)) {
            return '« ' . $name . ' » est un mot réservé de MySQL.';
        }
        foreach (self::RESERVED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return 'Le préfixe « ' . $prefix . ' » est réservé.';
            }
        }

        return null;
    }

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

    /**
     * Littéral SQL pour les rares instructions qui n'acceptent pas de paramètre lié
     * (CREATE USER … IDENTIFIED BY, hôte d'un compte MySQL). Échappé par le pilote.
     */
    public static function literal(\PDO $pdo, string $value): string
    {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Littéral SQL invalide.');
        }

        return $pdo->quote($value);
    }
}
