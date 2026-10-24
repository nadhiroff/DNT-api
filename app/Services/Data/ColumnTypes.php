<?php

declare(strict_types=1);

namespace App\Services\Data;

/**
 * Types logiques proposés dans le Table Editor et leur traduction SQL (liste blanche).
 */
final class ColumnTypes
{
    public const array LABELS = [
        'string' => 'Texte court',
        'text' => 'Texte long',
        'integer' => 'Entier',
        'bigint' => 'Grand entier',
        'float' => 'Nombre décimal (approché)',
        'decimal' => 'Montant (décimal exact)',
        'boolean' => 'Booléen (vrai / faux)',
        'date' => 'Date',
        'datetime' => 'Date et heure',
        'json' => 'JSON',
        'uuid' => 'UUID',
        'foreign' => 'Relation (clé étrangère)',
    ];

    public const array ON_DELETE = ['CASCADE' => 'Supprimer en cascade', 'SET NULL' => 'Mettre à NULL', 'RESTRICT' => 'Interdire la suppression'];

    /** Colonnes gérées par la plateforme : jamais modifiables par le client. */
    public const array SYSTEM_COLUMNS = ['id', 'created_at', 'updated_at', 'owner_id'];

    /**
     * Définition SQL d'une colonne (sans le nom). $refPkType : type de clé de la table référencée.
     */
    public static function sql(string $type, ?int $length, ?string $refPkType = null): string
    {
        return match ($type) {
            'string' => 'VARCHAR(' . max(1, min(255, $length ?? 255)) . ')',
            'text' => 'TEXT',
            'integer' => 'INT',
            'bigint' => 'BIGINT',
            'float' => 'DOUBLE',
            'decimal' => 'DECIMAL(14,2)',
            'boolean' => 'TINYINT(1)',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'json' => 'JSON',
            'uuid' => 'CHAR(36)',
            'foreign' => $refPkType === 'uuid' ? 'CHAR(36)' : 'BIGINT UNSIGNED',
            default => throw new \InvalidArgumentException('Type de colonne inconnu.'),
        };
    }

    /**
     * Valide et normalise une valeur pour une colonne. Retourne [valeur, erreur].
     *
     * @param array{type: string, length: int|null, nullable: bool} $column
     *
     * @return array{0: mixed, 1: string|null}
     */
    public static function cast(array $column, mixed $value): array
    {
        if ($value === null || $value === '') {
            if ($value === '' && in_array($column['type'], ['string', 'text'], true)) {
                return ['', null];
            }

            return $column['nullable'] ? [null, null] : [null, 'valeur obligatoire'];
        }
        if (is_array($value) && $column['type'] !== 'json') {
            return [null, 'valeur scalaire attendue'];
        }

        return match ($column['type']) {
            'string' => is_scalar($value) && mb_strlen((string) $value) <= ($column['length'] ?? 255) ? [(string) $value, null] : [null, 'texte trop long (' . ($column['length'] ?? 255) . ' caractères max)'],
            'text' => is_scalar($value) && strlen((string) $value) <= 65535 ? [(string) $value, null] : [null, 'texte trop long'],
            'integer' => self::int($value, -2147483648, 2147483647),
            'bigint', 'foreign' => $column['type'] === 'foreign' && is_string($value) && preg_match('/^[0-9a-f-]{36}$/i', $value) === 1 ? [$value, null] : self::int($value, PHP_INT_MIN, PHP_INT_MAX),
            'float' => is_numeric($value) ? [(float) $value, null] : [null, 'nombre attendu'],
            'decimal' => is_numeric($value) && abs((float) $value) < 1e12 ? [number_format((float) $value, 2, '.', ''), null] : [null, 'montant invalide'],
            'boolean' => match (true) {
                in_array($value, [true, 1, '1', 'true', 'on', 'oui'], true) => [1, null],
                in_array($value, [false, 0, '0', 'false', 'off', 'non'], true) => [0, null],
                default => [null, 'booléen attendu'],
            },
            'date' => is_string($value) && self::isDate($value, 'Y-m-d') ? [$value, null] : [null, 'date AAAA-MM-JJ attendue'],
            'datetime' => is_string($value) && ($normalized = self::datetime($value)) !== null ? [$normalized, null] : [null, 'date et heure AAAA-MM-JJ HH:MM:SS attendue'],
            'json' => self::json($value),
            'uuid' => is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1 ? [strtolower($value), null] : [null, 'UUID attendu'],
            default => [null, 'type inconnu'],
        };
    }

    /**
     * Valeur par défaut d'une colonne (DDL), validée selon le type. Retourne [valeur, erreur].
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function defaultValue(string $type, ?int $length, string $raw): array
    {
        if ($raw === '') {
            return [null, null];
        }
        if (in_array($type, ['text', 'json', 'foreign'], true)) {
            return [null, 'pas de valeur par défaut pour ce type'];
        }
        [$value, $error] = self::cast(['type' => $type, 'length' => $length, 'nullable' => false], $raw);

        return $error !== null ? [null, $error] : [(string) $value, null];
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private static function int(mixed $value, int $min, int $max): array
    {
        if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,19}$/', $value) === 1)) {
            $int = (int) $value;
            if ((string) $int === ltrim((string) $value, '+') && $int >= $min && $int <= $max) {
                return [$int, null];
            }
        }
        if (is_float($value) && floor($value) === $value && $value >= $min && $value <= $max) {
            return [(int) $value, null];
        }

        return [null, 'nombre entier attendu'];
    }

    private static function isDate(string $value, string $format): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!' . $format, $value);

        return $d !== false && $d->format($format) === $value;
    }

    private static function datetime(string $value): ?string
    {
        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.vP', 'Y-m-d\TH:i:s\Z'] as $format) {
            $d = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
            if ($d !== false) {
                return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private static function json(mixed $value): array
    {
        if (is_string($value)) {
            try {
                json_decode($value, false, 32, JSON_THROW_ON_ERROR);

                return [$value, null];
            } catch (\JsonException) {
                return [null, 'JSON invalide'];
            }
        }

        return [json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null, null];
    }
}
