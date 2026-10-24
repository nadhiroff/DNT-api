<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Jetons à usage unique (stockés hachés).
 */
final class UserTokenRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /**
     * Invalide les jetons encore actifs du même type puis enregistre le nouveau.
     */
    public function replace(int $userId, string $type, string $hash, int $ttlSeconds): void
    {
        $this->db->transaction(function (Database $db) use ($userId, $type, $hash, $ttlSeconds): void {
            $db->execute('UPDATE user_tokens SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND type = ? AND used_at IS NULL', [$userId, $type]);
            $db->execute(
                'INSERT INTO user_tokens (user_id, type, token_hash, expires_at) VALUES (?, ?, ?, UTC_TIMESTAMP() + INTERVAL ? SECOND)',
                [$userId, $type, $hash, $ttlSeconds],
            );
        });
    }

    /**
     * Consomme atomiquement un jeton valide (non utilisé, non expiré) et retourne son user_id.
     */
    public function consume(string $type, string $hash): ?int
    {
        return $this->db->transaction(function (Database $db) use ($type, $hash): ?int {
            $row = $db->selectOne(
                'SELECT id, user_id FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() FOR UPDATE',
                [$hash, $type],
            );
            if ($row === null) {
                return null;
            }
            $db->execute('UPDATE user_tokens SET used_at = UTC_TIMESTAMP() WHERE id = ?', [(int) $row['id']]);

            return (int) $row['user_id'];
        });
    }

    /**
     * Titulaire d'un jeton valide, sans le consommer (affichage et pré-validation du formulaire).
     */
    public function findUserId(string $type, string $hash): ?int
    {
        $userId = $this->db->scalar(
            'SELECT user_id FROM user_tokens WHERE token_hash = ? AND type = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [$hash, $type],
        );

        return $userId === null ? null : (int) $userId;
    }

    public function purgeExpired(): int
    {
        return $this->db->execute('DELETE FROM user_tokens WHERE expires_at < UTC_TIMESTAMP() - INTERVAL 7 DAY');
    }
}
