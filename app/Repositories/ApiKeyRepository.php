<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;

/**
 * Clés API (hachées).
 *
 * @phpstan-type ApiKeyRow array{id: int, project_id: int, name: string, type: string, prefix: string, key_enc: string|null, permissions: list<string>, expires_at: string|null, last_used_at: string|null, revoked_at: string|null, created_at: string}
 */
final class ApiKeyRepository
{
    private const string COLUMNS = 'id, project_id, name, type, prefix, key_enc, permissions, expires_at, last_used_at, revoked_at, created_at';

    /** Les clés révoquées restent visibles dans l'historique pendant 30 jours. */
    public const int HISTORY_DAYS = 30;

    public function __construct(private readonly Database $db)
    {
    }

    /**
     * @param list<string> $permissions
     */
    public function create(int $projectId, string $name, string $type, string $prefix, string $hash, string $keyEnc, array $permissions, ?string $expiresAt): int
    {
        return $this->db->insert(
            'INSERT INTO api_keys (project_id, name, type, prefix, key_hash, key_enc, permissions, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$projectId, $name, $type, $prefix, $hash, $keyEnc, json_encode($permissions, JSON_THROW_ON_ERROR), $expiresAt],
        );
    }

    /** @return list<ApiKeyRow> */
    public function forProject(int $projectId): array
    {
        return array_map(self::hydrate(...), $this->db->select(
            'SELECT ' . self::COLUMNS . ' FROM api_keys WHERE project_id = ? AND (revoked_at IS NULL OR revoked_at > UTC_TIMESTAMP() - INTERVAL ? DAY)
             ORDER BY revoked_at IS NOT NULL, revoked_at DESC, created_at DESC LIMIT 100',
            [$projectId, self::HISTORY_DAYS],
        ));
    }

    /** @return ApiKeyRow|null */
    public function find(int $projectId, int $id): ?array
    {
        $row = $this->db->selectOne('SELECT ' . self::COLUMNS . ' FROM api_keys WHERE id = ? AND project_id = ?', [$id, $projectId]);

        return $row !== null ? self::hydrate($row) : null;
    }

    /**
     * Clé valide (non révoquée, non expirée) pour ce projet.
     *
     * @return ApiKeyRow|null
     */
    public function findUsable(string $hash, int $projectId): ?array
    {
        $row = $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ' FROM api_keys WHERE key_hash = ? AND project_id = ? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())',
            [$hash, $projectId],
        );

        return $row !== null ? self::hydrate($row) : null;
    }

    public function revoke(int $projectId, int $id): bool
    {
        return $this->db->execute('UPDATE api_keys SET revoked_at = UTC_TIMESTAMP() WHERE id = ? AND project_id = ? AND revoked_at IS NULL', [$id, $projectId]) === 1;
    }

    public function touch(int $id): void
    {
        // Mise à jour au plus une fois par minute pour éviter une écriture à chaque requête.
        $this->db->execute('UPDATE api_keys SET last_used_at = UTC_TIMESTAMP() WHERE id = ? AND (last_used_at IS NULL OR last_used_at < UTC_TIMESTAMP() - INTERVAL 1 MINUTE)', [$id]);
    }

    /**
     * Supprime définitivement les clés révoquées depuis plus de 30 jours.
     */
    public function purgeRevoked(): int
    {
        return $this->db->execute('DELETE FROM api_keys WHERE revoked_at IS NOT NULL AND revoked_at < UTC_TIMESTAMP() - INTERVAL ? DAY', [self::HISTORY_DAYS]);
    }

    /**
     * Première clé active d'un type (pour le bloc « Intégration »).
     *
     * @return ApiKeyRow|null
     */
    public function firstActive(int $projectId, string $type): ?array
    {
        $row = $this->db->selectOne(
            'SELECT ' . self::COLUMNS . ' FROM api_keys WHERE project_id = ? AND type = ? AND revoked_at IS NULL AND key_enc IS NOT NULL ORDER BY created_at LIMIT 1',
            [$projectId, $type],
        );

        return $row !== null ? self::hydrate($row) : null;
    }

    public function countActive(int $projectId): int
    {
        return (int) $this->db->scalar('SELECT COUNT(*) FROM api_keys WHERE project_id = ? AND revoked_at IS NULL', [$projectId]);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return ApiKeyRow
     */
    private static function hydrate(array $row): array
    {
        $permissions = is_string($row['permissions'] ?? null) ? json_decode($row['permissions'], true) : [];

        return [
            'id' => (int) $row['id'],
            'project_id' => (int) $row['project_id'],
            'name' => (string) $row['name'],
            'type' => (string) $row['type'],
            'prefix' => (string) $row['prefix'],
            'key_enc' => $row['key_enc'] !== null ? (string) $row['key_enc'] : null,
            'permissions' => is_array($permissions) ? array_values(array_filter($permissions, 'is_string')) : [],
            'expires_at' => $row['expires_at'] !== null ? (string) $row['expires_at'] : null,
            'last_used_at' => $row['last_used_at'] !== null ? (string) $row['last_used_at'] : null,
            'revoked_at' => $row['revoked_at'] !== null ? (string) $row['revoked_at'] : null,
            'created_at' => (string) $row['created_at'],
        ];
    }
}
