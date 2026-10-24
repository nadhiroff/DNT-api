<?php

declare(strict_types=1);

namespace App\Services\Data;

use App\Core\Http\HttpException;

/**
 * Règles d'accès par table (équivalent simplifié du RLS) pour la clé publique.
 * La clé secrète contourne les règles (usage serveur uniquement).
 */
final class AccessPolicy
{
    /**
     * Retourne l'identifiant de propriétaire à imposer (règle « owner »), ou null si aucun filtre.
     *
     * @param array{read: string, insert: string, update: string, delete: string} $rules
     * @param 'read'|'insert'|'update'|'delete'                                   $operation
     *
     * @throws HttpException 401 / 403
     */
    public static function check(array $rules, string $operation, string $keyType, ?int $endUserId): ?int
    {
        if ($keyType === 'secret') {
            return null;
        }

        return match ($rules[$operation]) {
            'public' => null,
            'authenticated' => $endUserId !== null ? null : throw new HttpException(401, 'auth_required', 'Cette opération nécessite un utilisateur connecté (en-tête Authorization: Bearer <jwt>).'),
            'owner' => $endUserId ?? throw new HttpException(401, 'auth_required', 'Cette opération est réservée au propriétaire des lignes : connectez un utilisateur.'),
            default => throw new HttpException(403, 'secret_key_required', 'Cette opération n\'est permise qu\'avec la clé secrète (serveur).'),
        };
    }
}
